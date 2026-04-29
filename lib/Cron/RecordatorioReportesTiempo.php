<?php

declare(strict_types=1);

namespace OCA\Empleados\Cron;

use OCA\Empleados\AppInfo\Application;
use OCA\Empleados\Db\empleadosMapper;
use OCA\Empleados\Db\reportetiempoMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Mail\IMailer;
use Psr\Log\LoggerInterface;
use OCP\Notification\IManager as INotificationManager;

class RecordatorioReportesTiempo extends TimedJob {

	private const DEFAULT_TIMEZONE = 'America/Mexico_City';
	private const DEFAULT_GROUP = 'empleados';
	private const DEFAULT_REMINDER_HOUR = 17;

	private const CONFIG_ENABLED = 'reportes_recordatorios_enabled';
	private const CONFIG_GROUP = 'reportes_recordatorios_grupo';
	private const CONFIG_HOUR = 'reportes_recordatorios_hora';
	private const CONFIG_TIMEZONE = 'reportes_recordatorios_zona_horaria';
	private const CONFIG_EMAIL = 'reportes_recordatorios_email';
	private const CONFIG_MIN_HOURS = 'reportes_horas_minimas';

	private const USER_CONFIG_LAST_REMINDER = 'ultimo_recordatorio_reporte_tiempo';

	public function __construct(
		ITimeFactory $time,
		private IGroupManager $groupManager,
		private empleadosMapper $empleadosMapper,
		private reportetiempoMapper $reportetiempoMapper,
		private IConfig $config,
		private IMailer $mailer,
		private IURLGenerator $urlGenerator,
		private LoggerInterface $logger,
		private INotificationManager $notificationManager,
	) {
		parent::__construct($time);

		$this->setInterval(3600);
		$this->setAllowParallelRuns(false);
	}

	protected function run($argument): void {
		if (!$this->getBoolConfig(self::CONFIG_ENABLED, true)) {
			return;
		}

		// if (!$this->getBoolConfig(self::CONFIG_EMAIL, true)) {
		//	return;
		// }

		$now = new \DateTimeImmutable('now', $this->getConfiguredTimezone());

		$fecha = $now->format('Y-m-d');
		$horaActual = (int)$now->format('H');
		$diaSemana = (int)$now->format('N'); // 1 lunes, 7 domingo

		if ($diaSemana > 5) {
			return;
		}

		$horaRecordatorio = $this->getReminderHour();

		if ($horaActual !== $horaRecordatorio) {
			return;
		}

		$grupoRecordatorio = $this->getStringConfig(self::CONFIG_GROUP, self::DEFAULT_GROUP);
		$grupo = $this->groupManager->get($grupoRecordatorio);

		if ($grupo === null) {
			$this->logger->warning('No existe el grupo configurado para recordatorios de reportes.', [
				'app' => Application::APP_ID,
				'grupo' => $grupoRecordatorio,
			]);
			return;
		}

		$horasMinimas = $this->getMinimumHours();
		$minutosMinimos = $horasMinimas > 0 ? $horasMinimas * 60 : 0;
		$quickReportUrl = $this->getQuickReportUrl();

		foreach ($grupo->getUsers() as $user) {
			$this->processUserReminder(
				$user,
				$fecha,
				$quickReportUrl,
				$minutosMinimos,
				$horasMinimas
			);
		}
	}

	private function processUserReminder(
		IUser $user,
		string $fecha,
		string $quickReportUrl,
		float $minutosMinimos,
		float $horasMinimas
	): void {
		$uid = $user->getUID();

		if ($this->alreadyRemindedToday($uid, $fecha)) {
			return;
		}

		$idEmpleado = $this->getEmployeeIdByUid($uid);

		if ($idEmpleado <= 0) {
			return;
		}

		$resumen = $this->reportetiempoMapper->getResumenDiaByEmpleado($idEmpleado, $fecha);

		$registros = (int)($resumen['registros'] ?? 0);
		$minutosReportados = (float)($resumen['minutos_reportados'] ?? 0);

		if ($this->hasComplied($registros, $minutosReportados, $minutosMinimos)) {
			return;
		}

		$sent = false;

		try {
			$this->sendNextcloudNotification(
				$user,
				$fecha,
				$minutosReportados,
				$horasMinimas
			);

			$sent = true;
		} catch (\Throwable $e) {
			$this->logger->error('Error enviando notificación interna de reporte de tiempo: ' . $e->getMessage(), [
				'app' => Application::APP_ID,
				'uid' => $uid,
				'exception' => $e,
			]);
		}

		if ($this->getBoolConfig(self::CONFIG_EMAIL, true)) {
			$email = $user->getEMailAddress();

			if (empty($email)) {
				$this->logger->warning('No se envió correo de recordatorio: usuario sin correo.', [
					'app' => Application::APP_ID,
					'uid' => $uid,
				]);
			} else {
				try {
					$this->sendReminderEmail(
						$user,
						$email,
						$quickReportUrl,
						$minutosReportados,
						$horasMinimas,
						$minutosMinimos
					);

					$sent = true;
				} catch (\Throwable $e) {
					$this->logger->error('Error enviando correo de recordatorio de reporte de tiempo: ' . $e->getMessage(), [
						'app' => Application::APP_ID,
						'uid' => $uid,
						'email' => $email,
						'exception' => $e,
					]);
				}
			}
		}

		if ($sent) {
			$this->markUserAsReminded($uid, $fecha);

			$this->logger->info('Recordatorio de reporte de tiempo enviado.', [
				'app' => Application::APP_ID,
				'uid' => $uid,
				'fecha' => $fecha,
				'registros' => $registros,
				'minutos_reportados' => $minutosReportados,
				'horas_minimas' => $horasMinimas,
			]);
		}
	}

	private function sendReminderEmail(
		IUser $user,
		string $email,
		string $quickReportUrl,
		float $minutosReportados,
		float $horasMinimas,
		float $minutosMinimos
	): void {
		$uid = $user->getUID();
		$displayName = $user->getDisplayName() ?: $uid;

		$message = $this->mailer->createMessage();

		$message->setTo([
			$email => $displayName,
		]);

		$message->setSubject('Recordatorio: registra tu tiempo de hoy');

		$body = implode("\n", [
			'Hola ' . $displayName . ',',
			'',
			$this->getReminderStatusText($minutosReportados, $horasMinimas, $minutosMinimos),
			'',
			'Puedes registrar tu tiempo aquí:',
			$quickReportUrl,
			'',
			'Este es un recordatorio automático.',
		]);

		$message->setPlainBody($body);

		$this->mailer->send($message);
	}

	private function getReminderStatusText(
		float $minutosReportados,
		float $horasMinimas,
		float $minutosMinimos
	): string {
		if ($minutosMinimos <= 0) {
			return 'Aún no tienes reportes de tiempo registrados el día de hoy.';
		}

		$horasReportadas = $minutosReportados / 60;

		return 'Actualmente llevas ' . round($horasReportadas, 2) . ' horas reportadas. '
			. 'La meta mínima configurada es de ' . round($horasMinimas, 2) . ' horas.';
	}

	private function getEmployeeIdByUid(string $uid): int {
		$empleado = $this->empleadosMapper->GetMyEmployeeInfo($uid);

		if (empty($empleado)) {
			return 0;
		}

		$empleadoRow = isset($empleado[0]) && is_array($empleado[0])
			? $empleado[0]
			: $empleado;

		return (int)($empleadoRow['Id_empleados'] ?? $empleadoRow['id_empleados'] ?? 0);
	}

	private function hasComplied(int $registros, float $minutosReportados, float $minutosMinimos): bool {
		if ($minutosMinimos > 0) {
			return $minutosReportados >= $minutosMinimos;
		}

		return $registros > 0;
	}

	private function alreadyRemindedToday(string $uid, string $fecha): bool {
		$ultimoRecordatorio = $this->config->getUserValue(
			$uid,
			Application::APP_ID,
			self::USER_CONFIG_LAST_REMINDER,
			''
		);

		return $ultimoRecordatorio === $fecha;
	}

	private function markUserAsReminded(string $uid, string $fecha): void {
		$this->config->setUserValue(
			$uid,
			Application::APP_ID,
			self::USER_CONFIG_LAST_REMINDER,
			$fecha
		);
	}

	private function getConfiguredTimezone(): \DateTimeZone {
		$timezone = $this->getStringConfig(self::CONFIG_TIMEZONE, self::DEFAULT_TIMEZONE);

		try {
			return new \DateTimeZone($timezone);
		} catch (\Throwable $e) {
			$this->logger->warning('Zona horaria inválida en configuración de reportes. Se usará la zona horaria por defecto.', [
				'app' => Application::APP_ID,
				'timezone' => $timezone,
				'default' => self::DEFAULT_TIMEZONE,
			]);

			return new \DateTimeZone(self::DEFAULT_TIMEZONE);
		}
	}

	private function getReminderHour(): int {
		$hour = (int)$this->config->getAppValue(
			Application::APP_ID,
			self::CONFIG_HOUR,
			(string)self::DEFAULT_REMINDER_HOUR
		);

		return max(0, min(23, $hour));
	}

	private function getMinimumHours(): float {
		$hours = (float)$this->config->getAppValue(
			Application::APP_ID,
			self::CONFIG_MIN_HOURS,
			'0'
		);

		return max(0, $hours);
	}

	private function getQuickReportUrl(): string {
		return $this->urlGenerator->linkToRouteAbsolute('empleados.page.index') . '#/quick-report';
	}

	private function getBoolConfig(string $key, bool $default): bool {
		$value = $this->config->getAppValue(
			Application::APP_ID,
			$key,
			$default ? 'true' : 'false'
		);

		return $value === 'true';
	}

	private function getStringConfig(string $key, string $default): string {
		$value = trim($this->config->getAppValue(
			Application::APP_ID,
			$key,
			$default
		));

		return $value !== '' ? $value : $default;
	}
	
	private function sendNextcloudNotification(
		IUser $user,
		string $fecha,
		float $minutosReportados,
		float $horasMinimas
	): void {
		$uid = $user->getUID();

		$oldNotification = $this->notificationManager->createNotification();
		$oldNotification
			->setApp(Application::APP_ID)
			->setUser($uid)
			->setObject('reporte_tiempo', $fecha);

		$this->notificationManager->markProcessed($oldNotification);

		$notification = $this->notificationManager->createNotification();

		$notification
			->setApp(Application::APP_ID)
			->setUser($uid)
			->setDateTime(new \DateTime())
			->setObject('reporte_tiempo', $fecha)
			->setSubject('tiempo_pendiente', [
				'fecha' => $fecha,
				'horas_reportadas' => round($minutosReportados / 60, 2),
				'horas_minimas' => round($horasMinimas, 2),
			]);

		$this->notificationManager->notify($notification);
	}
}