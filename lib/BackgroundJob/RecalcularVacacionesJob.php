<?php

declare(strict_types=1);
namespace OCA\Empleados\BackgroundJob;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCA\Empleados\Db\ausenciasMapper;
use OCA\Empleados\Service\VacacionesCalculoService;
use Psr\Log\LoggerInterface;
use DateTime;

/**
 * Recalcula diariamente el periodo/aniversario y el colchón acumulado de
 * TODOS los empleados, sin importar si entraron o no a la aplicación.
 */
class RecalcularVacacionesJob extends TimedJob {

    private ausenciasMapper $ausenciasMapper;
    private VacacionesCalculoService $vacacionesCalculoService;
    private LoggerInterface $logger;

    public function __construct(
        ITimeFactory $time,
        ausenciasMapper $ausenciasMapper,
        VacacionesCalculoService $vacacionesCalculoService,
        LoggerInterface $logger,
    ) {
        parent::__construct($time);

        // Corre 1 vez cada 24 horas. Nextcloud decide la hora exacta según
        // cuándo se ejecute el cron.php del servidor, pero no se repetirá
        // dos veces dentro de esta ventana.
        $this->setInterval(24 * 60 * 60);

        $this->ausenciasMapper = $ausenciasMapper;
        $this->vacacionesCalculoService = $vacacionesCalculoService;
        $this->logger = $logger;
    }

    protected function run($argument): void {
        $this->logger->info('Iniciando recalculo automático de vacaciones', [
            'app' => 'empleados',
        ]);

        $todasLasAusencias = $this->ausenciasMapper->Getausencias();

        foreach ($todasLasAusencias as $registro) {
            $idEmpleado = (int) ($registro['id_empleado'] ?? 0);
            $idAusencias = (int) ($registro['id_ausencias'] ?? 0);

            if ($idEmpleado <= 0 || $idAusencias <= 0) {
                continue;
            }

            try {
                $resultado = $this->vacacionesCalculoService->getPeriodoActualEmpleado(
                    $idEmpleado,
                    $idAusencias,
                );

            } catch (\Throwable $e) {
                $this->logger->error(
                    'Error recalculando vacaciones del empleado ' . $idEmpleado . ': ' . $e->getMessage(),
                    [
                        'app' => 'empleados',
                        'exception' => $e,
                    ]
                );
            }
        }

        $this->logger->info('Finalizó el recalculo automático de vacaciones', [
            'app' => 'empleados',
        ]);
    }
}