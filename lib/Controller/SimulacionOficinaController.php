<?php

declare(strict_types=1);

namespace OCA\Empleados\Controller;

use OCA\Empleados\AppInfo\Application;
use OCA\Empleados\Db\configuracionesMapper;
use OCA\Empleados\Db\empleadosMapper;
use OCA\Empleados\Db\reportetiempoMapper;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\DataResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\UserStatus\IManager as IUserStatusManager;
use OCP\UserStatus\IUserStatus;
use Psr\Log\LoggerInterface;

/**
 * Datos públicos laborales para la simulación visual de oficina.
 * Accesible a cualquier empleado autenticado del módulo.
 */
class SimulacionOficinaController extends BaseController {

	private reportetiempoMapper $reportetiempoMapper;
	private IURLGenerator $urlGenerator;
	private IUserStatusManager $userStatusManager;
	private LoggerInterface $logger;

	public function __construct(
		IRequest $request,
		IUserSession $userSession,
		IGroupManager $groupManager,
		empleadosMapper $empleadosMapper,
		configuracionesMapper $configuracionesMapper,
		reportetiempoMapper $reportetiempoMapper,
		IURLGenerator $urlGenerator,
		IUserStatusManager $userStatusManager,
		LoggerInterface $logger
	) {
		parent::__construct(
			Application::APP_ID,
			$request,
			$userSession,
			$groupManager,
			$empleadosMapper,
			$configuracionesMapper
		);

		$this->reportetiempoMapper = $reportetiempoMapper;
		$this->urlGenerator = $urlGenerator;
		$this->userStatusManager = $userStatusManager;
		$this->logger = $logger;
	}

	/**
	 * Lista empleados activos con área, puesto y señales de actividad visual.
	 *
	 * Query params:
	 * - periodo: last_30_days | current_month | current_fortnight | custom
	 * - fecha_inicio / fecha_fin: solo si periodo=custom (Y-m-d)
	 */
	#[UseSession]
	#[NoAdminRequired]
	public function getEmpleados(): DataResponse {
		$this->checkAccess(['admin', 'recursos_humanos', 'empleados']);

		try {
			$periodo = (string)$this->request->getParam('periodo', 'last_30_days');
			$rango = $this->resolverPeriodo(
				$periodo,
				$this->request->getParam('fecha_inicio'),
				$this->request->getParam('fecha_fin')
			);

			$hoy = new \DateTimeImmutable('today');
			$dow = (int)$hoy->format('N'); // 1 = lunes … 7 = domingo
			$inicioSemana = $hoy->modify('-' . ($dow - 1) . ' days')->format('Y-m-d');
			$fechaHoy = $hoy->format('Y-m-d');

			$empleados = $this->empleadosMapper->getSimulacionOficinaLookup();
			$uids = array_values(array_map(
				static fn(array $emp): string => (string)$emp['uid'],
				$empleados
			));

			$activityMap = $this->reportetiempoMapper->getSimulacionActivityByEmployee(
				$fechaHoy,
				$inicioSemana,
				$rango['inicio'],
				$rango['fin']
			);
			$lastLoginMap = $this->empleadosMapper->getLastLoginByUids($uids);
			$statusMap = $this->getStatusMap($uids);

			$payload = array_map(function (array $empleado) use ($activityMap, $lastLoginMap, $statusMap, $hoy): array {
				$id = (int)$empleado['id'];
				$uid = (string)$empleado['uid'];
				$activity = $activityMap[$id] ?? [
					'reportes_periodo' => 0,
					'reportes_hoy' => 0,
					'reportes_semana' => 0,
					'minutos_hoy' => 0.0,
					'minutos_semana' => 0.0,
					'ultimo_reporte' => null,
					'ultimo_created_at' => null,
				];
				$lastLoginAt = (int)($lastLoginMap[$uid] ?? 0);
				$status = $statusMap[$uid] ?? [
					'status' => IUserStatus::OFFLINE,
					'icon' => null,
					'message' => null,
					'clearAt' => null,
				];
				$userStatus = (string)$status['status'];
				$dailyEvents = $this->buildDailyEventsForEmployee($empleado, $hoy);

				return [
					'id' => $id,
					'uid' => $uid,
					'displayName' => (string)$empleado['displayName'],
					'avatarUrl' => $this->urlGenerator->linkToRoute('core.avatar.getAvatar', [
						'userId' => $uid,
						'size' => 64,
					]),
					'area' => $empleado['area'],
					'puesto' => $empleado['puesto'],
					'reportesPeriodo' => $activity['reportes_periodo'],
					'reportesHoy' => $activity['reportes_hoy'],
					'reportesSemana' => $activity['reportes_semana'],
					'minutosHoy' => (int)round($activity['minutos_hoy']),
					'minutosSemana' => (int)round($activity['minutos_semana']),
					'ultimoReporte' => $activity['ultimo_reporte'],
					'lastLoginAt' => $lastLoginAt > 0 ? $lastLoginAt : null,
					'userStatus' => $userStatus,
					'statusIcon' => $status['icon'],
					'statusMessage' => $status['message'],
					'statusClearAt' => $status['clearAt'],
					'activityScore' => $this->computeActivityScore($activity, $lastLoginAt, $userStatus),
					'dailyEvents' => $dailyEvents,
				];
			}, $empleados);

			return new DataResponse([
				'periodo' => [
					'tipo' => $rango['tipo'],
					'inicio' => $rango['inicio'],
					'fin' => $rango['fin'],
				],
				'fecha' => $hoy->format('Y-m-d'),
				'eventosHoy' => $this->buildEventosHoySummary($payload),
				'empleados' => $payload,
			], Http::STATUS_OK);
		} catch (\Throwable $e) {
			$this->logger->error('Simulacion oficina: ' . $e->getMessage(), ['exception' => $e]);
			return new DataResponse([
				'error' => $e->getMessage(),
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Snapshot ligero de estados Nextcloud solo para empleados activos de la simulación.
	 */
	#[UseSession]
	#[NoAdminRequired]
	public function getStatuses(): DataResponse {
		$this->checkAccess(['admin', 'recursos_humanos', 'empleados']);

		try {
			$uids = $this->empleadosMapper->getActiveEmployeeUids();
			$statusMap = $this->getStatusMap($uids);

			$statuses = [];
			foreach ($uids as $uid) {
				$row = $statusMap[$uid] ?? [
					'status' => IUserStatus::OFFLINE,
					'icon' => null,
					'message' => null,
					'clearAt' => null,
				];
				$statuses[] = [
					'uid' => $uid,
					'status' => $row['status'],
					'icon' => $row['icon'],
					'message' => $row['message'],
					'clearAt' => $row['clearAt'],
				];
			}

			return new DataResponse([
				'statuses' => $statuses,
			], Http::STATUS_OK);
		} catch (\Throwable $e) {
			$this->logger->error('Simulacion oficina statuses: ' . $e->getMessage(), ['exception' => $e]);
			return new DataResponse([
				'error' => $e->getMessage(),
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * @param list<string> $uids
	 * @return array<string, array{status: string, icon: ?string, message: ?string, clearAt: ?int}>
	 */
	private function getStatusMap(array $uids): array {
		if ($uids === []) {
			return [];
		}

		try {
			$statuses = $this->userStatusManager->getUserStatuses($uids);
		} catch (\Throwable $e) {
			$this->logger->debug('Simulacion oficina: user status unavailable', ['exception' => $e]);
			return [];
		}

		$map = [];
		foreach ($statuses as $uid => $status) {
			$value = strtolower((string)$status->getStatus());
			if ($value === IUserStatus::INVISIBLE) {
				$value = IUserStatus::OFFLINE;
			}

			$icon = $status->getIcon();
			$message = $status->getMessage();
			$clearAt = $status->getClearAt();

			$map[(string)$uid] = [
				'status' => $value,
				'icon' => is_string($icon) && $icon !== '' ? $icon : null,
				'message' => is_string($message) && trim($message) !== '' ? trim($message) : null,
				'clearAt' => $clearAt instanceof \DateTimeInterface ? $clearAt->getTimestamp() : null,
			];
		}

		return $map;
	}

	/**
	 * Eventos del día sin exponer fecha de nacimiento cruda.
	 *
	 * @param array{ingreso?: ?string, fechaNacimiento?: ?string} $empleado
	 * @return list<array{type: string, icon: string, years?: int}>
	 */
	private function buildDailyEventsForEmployee(array $empleado, \DateTimeImmutable $hoy): array {
		$events = [];
		$mdHoy = $hoy->format('m-d');

		$ingreso = $this->parseDate($empleado['ingreso'] ?? null);
		if ($ingreso instanceof \DateTimeImmutable && $ingreso->format('m-d') === $mdHoy) {
			$years = (int)$hoy->format('Y') - (int)$ingreso->format('Y');
			if ($years > 0) {
				$events[] = [
					'type' => 'work_anniversary',
					'icon' => '🎉',
					'years' => $years,
				];
			}
		}

		$nacimiento = $this->parseDate($empleado['fechaNacimiento'] ?? null);
		if ($nacimiento instanceof \DateTimeImmutable && $nacimiento->format('m-d') === $mdHoy) {
			$events[] = [
				'type' => 'birthday',
				'icon' => '🎂',
			];
		}

		return $events;
	}

	/**
	 * @param list<array{uid: string, displayName: string, dailyEvents: list<array{type: string, icon: string, years?: int}>}> $payload
	 * @return array{
	 *   work_anniversary: list<array{uid: string, displayName: string, years: int}>,
	 *   birthday: list<array{uid: string, displayName: string}>
	 * }
	 */
	private function buildEventosHoySummary(array $payload): array {
		$summary = [
			'work_anniversary' => [],
			'birthday' => [],
		];

		foreach ($payload as $emp) {
			foreach ($emp['dailyEvents'] ?? [] as $event) {
				$type = (string)($event['type'] ?? '');
				if ($type === 'work_anniversary') {
					$summary['work_anniversary'][] = [
						'uid' => (string)$emp['uid'],
						'displayName' => (string)$emp['displayName'],
						'years' => (int)($event['years'] ?? 0),
					];
				} elseif ($type === 'birthday') {
					$summary['birthday'][] = [
						'uid' => (string)$emp['uid'],
						'displayName' => (string)$emp['displayName'],
					];
				}
			}
		}

		return $summary;
	}

	private function parseDate($value): ?\DateTimeImmutable {
		if (!is_string($value)) {
			return null;
		}
		$value = trim($value);
		if ($value === '' || $value === '0000-00-00') {
			return null;
		}
		// Acepta Y-m-d o datetime.
		$datePart = substr($value, 0, 10);
		if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $datePart) !== 1) {
			return null;
		}
		try {
			return new \DateTimeImmutable($datePart);
		} catch (\Throwable $e) {
			return null;
		}
	}

	/**
	 * @param array{
	 *   reportes_periodo: int,
	 *   reportes_hoy: int,
	 *   reportes_semana: int,
	 *   minutos_hoy: float,
	 *   minutos_semana: float
	 * } $activity
	 */
	private function computeActivityScore(array $activity, int $lastLoginAt, string $userStatus): float {
		$periodPart = min(1.0, ((int)$activity['reportes_periodo']) / 40.0);
		$todayPart = min(
			1.0,
			(((int)$activity['reportes_hoy']) * 0.28)
			+ (((float)$activity['minutos_hoy']) / 480.0)
		);
		$weekPart = min(1.0, ((int)$activity['reportes_semana']) / 15.0);

		$loginBoost = 0.0;
		if ($lastLoginAt > 0) {
			$hoursAgo = (time() - $lastLoginAt) / 3600;
			if ($hoursAgo <= 2) {
				$loginBoost = 0.22;
			} elseif ($hoursAgo <= 24) {
				$loginBoost = 0.12;
			} elseif ($hoursAgo <= 72) {
				$loginBoost = 0.05;
			}
		}

		$statusBoost = match ($userStatus) {
			IUserStatus::ONLINE => 0.20,
			IUserStatus::AWAY, IUserStatus::BUSY => 0.10,
			IUserStatus::DND => 0.04,
			default => 0.0,
		};

		$raw = (0.22 * sqrt($periodPart))
			+ (0.34 * $todayPart)
			+ (0.14 * sqrt($weekPart))
			+ $loginBoost
			+ $statusBoost;

		return round(max(0.12, min(0.92, $raw)), 3);
	}

	/**
	 * @param mixed $fechaInicio
	 * @param mixed $fechaFin
	 * @return array{tipo: string, inicio: string, fin: string}
	 */
	private function resolverPeriodo(string $periodo, $fechaInicio, $fechaFin): array {
		$hoy = new \DateTimeImmutable('today');

		switch ($periodo) {
			case 'current_month':
				return [
					'tipo' => 'current_month',
					'inicio' => $hoy->modify('first day of this month')->format('Y-m-d'),
					'fin' => $hoy->format('Y-m-d'),
				];

			case 'current_fortnight':
				$dia = (int)$hoy->format('j');
				if ($dia <= 15) {
					$inicio = $hoy->modify('first day of this month');
					$fin = $hoy->modify('first day of this month')->modify('+14 days');
					if ($fin > $hoy) {
						$fin = $hoy;
					}
				} else {
					$inicio = $hoy->modify('first day of this month')->modify('+15 days');
					$fin = $hoy;
				}

				return [
					'tipo' => 'current_fortnight',
					'inicio' => $inicio->format('Y-m-d'),
					'fin' => $fin->format('Y-m-d'),
				];

			case 'custom':
				$inicio = is_string($fechaInicio) ? trim($fechaInicio) : '';
				$fin = is_string($fechaFin) ? trim($fechaFin) : '';
				if (
					preg_match('/^\d{4}-\d{2}-\d{2}$/', $inicio) !== 1
					|| preg_match('/^\d{4}-\d{2}-\d{2}$/', $fin) !== 1
				) {
					throw new \InvalidArgumentException('Custom period requires fecha_inicio and fecha_fin (Y-m-d).');
				}
				if ($inicio > $fin) {
					[$inicio, $fin] = [$fin, $inicio];
				}

				return [
					'tipo' => 'custom',
					'inicio' => $inicio,
					'fin' => $fin,
				];

			case 'last_30_days':
			default:
				return [
					'tipo' => 'last_30_days',
					'inicio' => $hoy->modify('-29 days')->format('Y-m-d'),
					'fin' => $hoy->format('Y-m-d'),
				];
		}
	}
}
