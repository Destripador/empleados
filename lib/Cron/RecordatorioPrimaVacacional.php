<?php

declare(strict_types=1);

namespace OCA\Empleados\Cron;

use OCA\Empleados\Db\empleadosMapper;
use OCA\Empleados\Db\historialausenciasMapper;
use OCA\Empleados\Helper\MailHelper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\ILogger;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

/**
 * Envía un recordatorio a los empleados que aún no han solicitado su
 * prima vacacional del año en curso. Solo actúa el 1 de diciembre.
 */
class RecordatorioPrimaVacacional extends TimedJob {

	private empleadosMapper $empleadosMapper;
	private historialausenciasMapper $historialausenciasMapper;
	private IUserManager $userManager;
	private MailHelper $mailHelper;
    private LoggerInterface $logger;

	public function __construct(
		ITimeFactory $time,
		empleadosMapper $empleadosMapper,
		historialausenciasMapper $historialausenciasMapper,
		IUserManager $userManager,
		MailHelper $mailHelper,
        LoggerInterface $logger
	) {
		parent::__construct($time);

		$this->setInterval(24 * 60 * 60);

		$this->empleadosMapper = $empleadosMapper;
		$this->historialausenciasMapper = $historialausenciasMapper;
		$this->userManager = $userManager;
		$this->mailHelper = $mailHelper;
        $this->logger = $logger;
	}

	protected function run($argument): void {
		$hoy = new \DateTime();

		$this->logger->warning('RecordatorioPrimaVacacional iniciado.', [
			'app' => 'empleados',
			'fecha' => $hoy->format('Y-m-d'),
		]);

		// Solo enviar el recordatorio el 1 de diciembre.
		if ((int) $hoy->format('n') !== 12 || (int) $hoy->format('j') !== 1) {
			$this->logger->warning('No es 1 de diciembre. Finalizando job.', [
				'app' => 'empleados',
			]);
			return;
		}

		$anio = (int) $hoy->format('Y');
		$empleados = $this->empleadosMapper->GetUserLists();

		$this->logger->warning('Empleados obtenidos.', [
			'app' => 'empleados',
			'total' => count($empleados),
		]);

		foreach ($empleados as $empleado) {
            $idAusencias = $empleado['id_ausencias'] ?? null;
            $uid = $empleado['Id_user'] ?? null;
            $fechaIngreso = $empleado['Ingreso'] ?? null;

            if (!$fechaIngreso) {
                $this->logger->warning('Empleado omitido: sin fecha de ingreso.', [
                    'app' => 'empleados',
                    'uid' => $uid,
                ]);
                continue;
            }

            $numeroAniversario = $hoy->diff(new \DateTime($fechaIngreso))->y;

            $this->logger->warning('Procesando empleado.', [
                'app' => 'empleados',
                'uid' => $uid,
                'idAusencias' => $idAusencias,
                'ingreso' => $fechaIngreso,
                'aniversario' => $numeroAniversario,
            ]);

            if ($numeroAniversario <= 0) {
                $this->logger->warning('Empleado omitido: aún no cumple un aniversario.', [
                    'app' => 'empleados',
                    'uid' => $uid,
                    'ingreso' => $fechaIngreso,
                ]);
                continue;
            }

            if (!$idAusencias || !$uid) {
                $this->logger->warning('Empleado omitido: datos incompletos.', [
                    'app' => 'empleados',
                    'uid' => $uid,
                    'idAusencias' => $idAusencias,
                ]);
                continue;
            }

            $yaSolicito = $this->historialausenciasMapper->PrimaVacacionalUsadaEsteAnio(
                (int) $idAusencias,
                $anio
            );

            if ($yaSolicito) {
                $this->logger->warning('Empleado ya solicitó su prima vacacional.', [
                    'app' => 'empleados',
                    'uid' => $uid,
                ]);
                continue;
            }

            $user = $this->userManager->get($uid);

            if (!$user) {
                $this->logger->warning('Usuario no encontrado.', [
                    'app' => 'empleados',
                    'uid' => $uid,
                ]);
                continue;
            }

            $mail = $user->getEMailAddress();

            if (!$mail) {
                $this->logger->warning('Usuario sin correo electrónico.', [
                    'app' => 'empleados',
                    'uid' => $uid,
                ]);
                continue;
            }

            $this->logger->warning('Enviando recordatorio.', [
                'app' => 'empleados',
                'uid' => $uid,
                'correo' => $mail,
            ]);

            $this->mailHelper->enviarCorreo(
                $mail,
                'Recordatorio: Prima vacacional',
                [
                    'Hola ' . $user->getDisplayName(),
                    'Aún no has solicitado tu prima vacacional correspondiente a este año.',
                    'Te recomendamos solicitarla lo antes posible.',
                    '',
                ]
            );

            $this->logger->warning('Recordatorio enviado correctamente.', [
                'app' => 'empleados',
                'uid' => $uid,
            ]);
        }

        $this->logger->warning('RecordatorioPrimaVacacional finalizado.', [
            'app' => 'empleados',
        ]);
	}
}