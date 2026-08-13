<?php

declare(strict_types=1);

namespace OCA\Empleados\Service;

use DateTime;
use OCA\Empleados\Db\historialvacacionesMapper;

/**
 * Corrige el desfase que queda en historial_vacaciones cuando se edita
 * el campo Ingreso de un empleado DESPUÉS de que sus periodos ya se
 * habían calculado con la fecha vieja.
 */
class AniversarioSyncService {

    private historialvacacionesMapper $historialvacacionesMapper;

    public function __construct(historialvacacionesMapper $historialvacacionesMapper) {
        $this->historialvacacionesMapper = $historialvacacionesMapper;
    }

    /**
     * @return array Lista de cambios aplicados (o que se aplicarían, si $dryRun=true).
     *               Vacío si no había nada desfasado.
     */
    public function sincronizarPeriodos(int $idEmpleado, string $ingresoActualStr, bool $dryRun = false): array {
        if (empty($ingresoActualStr)) {
            return [];
        }

        $ingresoActual = new DateTime($ingresoActualStr);
        $filas = $this->historialvacacionesMapper->getByEmpleado($idEmpleado);

        $cambios = [];

        foreach ($filas as $fila) {
            $n = (int) $fila['numero_aniversario'];

            $periodoInicioNuevo = (clone $ingresoActual)->modify('+' . $n . ' years');
            $periodoFinNuevo    = (clone $ingresoActual)->modify('+' . ($n + 1) . ' years');
            $periodoInicioNuevoStr = $periodoInicioNuevo->format('Y-m-d');
            $periodoFinNuevoStr    = $periodoFinNuevo->format('Y-m-d');

            $fechaExpiracionNueva = !empty($fila['fecha_expiracion_acumulados'])
                ? (clone $periodoInicioNuevo)->modify('+6 months')->format('Y-m-d')
                : null;

            $huboCambio = $fila['periodo_inicio'] !== $periodoInicioNuevoStr
                || $fila['periodo_fin'] !== $periodoFinNuevoStr
                || ($fila['fecha_expiracion_acumulados'] ?? null) !== $fechaExpiracionNueva;

            if (!$huboCambio) {
                continue;
            }

            $cambios[] = [
                'numero_aniversario' => $n,
                'periodo_inicio' => [$fila['periodo_inicio'], $periodoInicioNuevoStr],
                'periodo_fin' => [$fila['periodo_fin'], $periodoFinNuevoStr],
                'fecha_expiracion_acumulados' => [$fila['fecha_expiracion_acumulados'] ?? null, $fechaExpiracionNueva],
                'dias_derecho' => $fila['dias_derecho'],
                'asignado_manualmente' => (int) ($fila['asignado_manualmente'] ?? 0),
            ];

            if (!$dryRun) {
                $this->historialvacacionesMapper->actualizarFechasYExpiracion(
                    $idEmpleado,
                    $n,
                    $periodoInicioNuevoStr,
                    $periodoFinNuevoStr,
                    $fechaExpiracionNueva
                );
            }
        }

        return $cambios;
    }
}