<?php

declare(strict_types=1);

namespace OCA\Empleados\Service;

use OCA\Empleados\Db\empleadosMapper;
use OCA\Empleados\Db\movimientosMapper;
use OCP\IUserManager;

/**
 * Servicio centralizado para registrar movimientos en la bitácora general.
 */
class BitacoraService {

    private movimientosMapper $movimientosMapper;
    private empleadosMapper $empleadosMapper;
    private IUserManager $userManager;

    public function __construct(
        movimientosMapper $movimientosMapper,
        empleadosMapper $empleadosMapper,
        IUserManager $userManager
    ) {
        $this->movimientosMapper = $movimientosMapper;
        $this->empleadosMapper = $empleadosMapper;
        $this->userManager = $userManager;
    }

    /**
     * Registra un movimiento hecho por un usuario
     */
    public function registrar(
        string $modulo,
        ?string $uidActor,
        ?int $idEmpleadoAfectado,
        ?string $nombreAfectado,
        string $tipo,
        string $mensaje,
        ?int $idReferencia = null
    ): void {
        try {
            $idEmpleadoActor = null;
            $nombreActor = null;

            if ($uidActor !== null) {
                $userActor = $this->userManager->get($uidActor);
                $nombreActor = $userActor ? $userActor->getDisplayName() : $uidActor;
                $infoActor = $this->empleadosMapper->GetMyEmployeeInfo($uidActor);
                $idEmpleadoActor = !empty($infoActor) ? (int) $infoActor[0]['Id_empleados'] : null;
            } else {
                $nombreActor = 'Sistema';
            }

            $this->movimientosMapper->registrar(
                $modulo, $idEmpleadoActor, $nombreActor,
                $idEmpleadoAfectado, $nombreAfectado,
                $tipo, $mensaje, $idReferencia
            );
        } catch (\Throwable $e) {
            error_log('No se pudo registrar movimiento (' . $modulo . '.' . $tipo . '): ' . $e->getMessage());
        }
    }

    public function registrarSistema(
        string $modulo,
        ?int $idEmpleadoAfectado,
        ?string $nombreAfectado,
        string $tipo,
        string $mensaje,
        ?int $idReferencia = null
    ): void {
        if (stripos($mensaje, 'Sistema') !== 0) {
            $mensaje = 'Sistema - ' . $mensaje;
        }
        $this->registrar($modulo, null, $idEmpleadoAfectado, $nombreAfectado, $tipo, $mensaje, $idReferencia);
    }

    public function formatearDiasTexto(float $dias): string {
        if ($dias == 0.5) {
            return 'medio día';
        }
        $n = $dias == floor($dias) ? (string) (int) $dias : rtrim(rtrim(number_format($dias, 2), '0'), '.');
        return $n . ' ' . ($dias == 1 ? 'día' : 'días');
    }
}