<?php

declare(strict_types=1);
namespace OCA\Empleados\Service;

use DateTime;
use OCA\Empleados\Db\empleadosMapper;
use OCA\Empleados\Db\historialausenciasMapper;
use OCA\Empleados\Db\historialvacacionesMapper;
use OCA\Empleados\Db\aniversarioMapper;
use Psr\Log\LoggerInterface;

class VacacionesCalculoService {

    protected historialvacacionesMapper $historialvacacionesMapper;
    protected historialausenciasMapper $historialausenciasMapper;
    protected empleadosMapper $empleadosMapper;
    protected aniversarioMapper $aniversarioMapper;
    protected LoggerInterface $logger;

    public function __construct(
        empleadosMapper $empleadosMapper,
        historialvacacionesMapper $historialvacacionesMapper,
        historialausenciasMapper $historialausenciasMapper,
        aniversarioMapper $aniversarioMapper,
        LoggerInterface $logger,
    ) {
        $this->empleadosMapper = $empleadosMapper;
        $this->historialvacacionesMapper = $historialvacacionesMapper;
        $this->historialausenciasMapper = $historialausenciasMapper;
        $this->aniversarioMapper = $aniversarioMapper;
        $this->logger = $logger;
    }

    public function contarDiasHabilesHastaFecha(\DateTime $inicio, \DateTime $fin, \DateTime $limite): int {
        $cursor = clone $inicio;
        $count = 0;
        while ($cursor <= $fin) {
            if ($cursor > $limite) break;
            if ((int) $cursor->format('N') <= 5) $count++;
            $cursor->modify('+1 day');
        }
        return $count;
    }

    public function calcularAcumuladoPeriodo(
        int $id_empleado,
        int $id_ausencias,
        int $numeroAniversario,
        DateTime $fechaIngreso,
        DateTime $periodoInicio,
        string $periodoInicioStr
    ): array {
        if ($numeroAniversario <= 0) {
            return [0.0, null];
        }

        $anterior = $this->historialvacacionesMapper->getByEmpleadoYAniversario($id_empleado, $numeroAniversario - 1);
        if (!$anterior) {
            return [0.0, null];
        }

        $inicioAnterior = (clone $fechaIngreso)->modify('+' . ($numeroAniversario - 1) . ' years')->format('Y-m-d');
        $finAnterior = $periodoInicioStr;
        $finAnteriorConGracia = (clone $periodoInicio)->modify('+6 months')->format('Y-m-d');

        $disfrutadoAnterior = 0.0;
        $historialAnterior = $this->historialausenciasMapper->GetAusenciasEnRango($inicioAnterior, $finAnteriorConGracia, $id_ausencias);
        foreach ($historialAnterior as $item) {
            if ((int) ($item['id_aniversario'] ?? -1) !== ($numeroAniversario - 1)) continue; // clave
            if ((int) $item['a_gerente'] === 3 || (int) $item['a_socio'] === 3) continue;
            if ((int) $item['a_gerente'] === 2 || (int) $item['a_socio'] === 2) continue;
            if ((int) ($item['solicitar_prima_vacacional'] ?? 0) !== 1) continue;
            $disfrutadoAnterior += (float) $item['dias_solicitados'] - (float) ($item['dias_de_acumulado'] ?? 0);
        }

        $sobrante = ((float) $anterior['dias_derecho']) - $disfrutadoAnterior;

        if ($sobrante <= 0) {
            return [0.0, null];
        }

        $fechaExpiracion = (clone $periodoInicio)->modify('+6 months')->format('Y-m-d');
        return [$sobrante, $fechaExpiracion];
        throw new \RuntimeException('Pega aquí el cuerpo original de calcularAcumuladoPeriodo');
    }

    /**
     * Calcula  el periodo/aniversario actual del empleado.
     */
    public function getPeriodoActualEmpleado(int $id_empleado, int $id_ausencias): ?array {
        $empleado = $this->empleadosMapper->GetMyEmployeeInfoByIdEmpleado((string) $id_empleado);
		if (empty($empleado) || empty($empleado[0]['Ingreso'])) {
			return null;
		}

		$fechaIngreso = new DateTime($empleado[0]['Ingreso']);
		$hoy = new DateTime();

		$numeroAniversario = $hoy->diff($fechaIngreso)->y;

		$periodoInicio = (clone $fechaIngreso)->modify('+' . $numeroAniversario . ' years');
        $periodoFin = (clone $fechaIngreso)->modify('+' . ($numeroAniversario + 1) . ' years');
        $periodoInicioStr = $periodoInicio->format('Y-m-d');
        $periodoFinStr = $periodoFin->format('Y-m-d');
        $limiteConGraciaStr = (clone $periodoFin)->modify('+6 months')->format('Y-m-d');

		$existente = $this->historialvacacionesMapper->getByEmpleadoYAniversario($id_empleado, $numeroAniversario);

		if ($existente) {
            $esManual = (int) ($existente['asignado_manualmente'] ?? 0) === 1;

            if ($esManual) {
                // RH ya confirmó este periodo manualmente
                $diasDerecho = (float) $existente['dias_derecho'];
                $yaCalculado = (int) ($existente['acumulado_calculado'] ?? 0) === 1;

                if ($yaCalculado) {
                    $diasAcumuladosRestantes = (float) ($existente['dias_acumulados_restantes'] ?? 0);
                    $fechaExpiracionAcum = $existente['fecha_expiracion_acumulados'] ?? null;
                } else {
                    [$diasAcumulados, $fechaExpiracionAcum] = $this->calcularAcumuladoPeriodo(
                        $id_empleado, $id_ausencias, $numeroAniversario, $fechaIngreso, $periodoInicio, $periodoInicioStr
                    );
                    $this->historialvacacionesMapper->actualizarAcumulado(
                        $id_empleado, $numeroAniversario, $diasAcumulados, $fechaExpiracionAcum
                    );
                    $diasAcumuladosRestantes = $diasAcumulados;
                }
            } else {
                $yaCalculado = (int) ($existente['acumulado_calculado'] ?? 0) === 1;

                if ($yaCalculado) {
                    $diasDerecho = (float) $existente['dias_derecho'];
                    $diasAcumuladosRestantes = (float) ($existente['dias_acumulados_restantes'] ?? 0);
                    $fechaExpiracionAcum = $existente['fecha_expiracion_acumulados'] ?? null;
                } else {
                    $tieneAsignacionManual = $this->historialvacacionesMapper->tieneAsignacionManual($id_empleado);
                    $tieneAniversarioCero = $this->historialvacacionesMapper->tieneAniversarioCero($id_empleado);

                    if ($tieneAsignacionManual || $tieneAniversarioCero || $numeroAniversario === 0) {
                        $tablaAniversario = $this->aniversarioMapper->GetAniversarioByDate($numeroAniversario);
                        $diasDerecho = !empty($tablaAniversario) ? (float) ($tablaAniversario[0]['dias'] ?? 0) : 0.0;
                    } else {
                        $diasDerecho = 0.0;
                    }

                    [$diasAcumulados, $fechaExpiracionAcum] = $this->calcularAcumuladoPeriodo(
                        $id_empleado, $id_ausencias, $numeroAniversario, $fechaIngreso, $periodoInicio, $periodoInicioStr
                    );

                    $this->historialvacacionesMapper->actualizarDerechoAutomatico(
                        $id_empleado, $numeroAniversario, $diasDerecho, $diasAcumulados, $fechaExpiracionAcum
                    );

                    $diasAcumuladosRestantes = $diasAcumulados;
                }
            }
        } else {
            $tieneAsignacionManual = $this->historialvacacionesMapper->tieneAsignacionManual($id_empleado);
            $tieneAniversarioCero = $this->historialvacacionesMapper->tieneAniversarioCero($id_empleado);

            if ($tieneAsignacionManual || $tieneAniversarioCero || $numeroAniversario === 0) {
                $tablaAniversario = $this->aniversarioMapper->GetAniversarioByDate($numeroAniversario);
                $diasDerecho = !empty($tablaAniversario) ? (float) ($tablaAniversario[0]['dias'] ?? 0) : 0.0;
            } else {
                $diasDerecho = 0.0;
            }

            [$diasAcumulados, $fechaExpiracionAcum] = $this->calcularAcumuladoPeriodo(
                $id_empleado, $id_ausencias, $numeroAniversario, $fechaIngreso, $periodoInicio, $periodoInicioStr
            );

            $this->historialvacacionesMapper->guardarConAcumulado(
                $id_empleado, $numeroAniversario, $periodoInicioStr, $periodoFinStr,
                $diasDerecho, $diasAcumulados, $fechaExpiracionAcum
            );

            $diasAcumuladosRestantes = $diasAcumulados;
        }

		$acumuladoVigente = $diasAcumuladosRestantes > 0
			&& $fechaExpiracionAcum !== null
			&& $hoy <= new DateTime($fechaExpiracionAcum);

		$diasDisfrutados = 0.0;
        $historial = $this->historialausenciasMapper->GetAusenciasEnRango($periodoInicioStr, $limiteConGraciaStr, $id_ausencias);
        foreach ($historial as $item) {
            if ((int) ($item['id_aniversario'] ?? -1) !== $numeroAniversario) continue; // clave
            if ((int) $item['a_gerente'] === 3 || (int) $item['a_socio'] === 3) continue;
            if ((int) $item['a_gerente'] === 2 || (int) $item['a_socio'] === 2) continue;
            if ((int) ($item['solicitar_prima_vacacional'] ?? 0) !== 1) continue;
            $diasDisfrutados += (float) $item['dias_solicitados'] - (float) ($item['dias_de_acumulado'] ?? 0);
        }

		return [
			'numero_aniversario' => $numeroAniversario,
			'fecha_ingreso' => $fechaIngreso->format('Y-m-d'),
			'periodo_inicio' => $periodoInicioStr,
			'periodo_fin' => $periodoFinStr,
			'dias_derecho' => $diasDerecho,
			'dias_disfrutados' => $diasDisfrutados,
			'dias_restantes' => $diasDerecho - $diasDisfrutados,
			'dias_acumulados_restantes' => $acumuladoVigente ? $diasAcumuladosRestantes : 0,
			'fecha_expiracion_acumulados' => $acumuladoVigente ? $fechaExpiracionAcum : null,
			'fecha_limite_periodo_actual' => (clone $periodoFin)->modify('+6 months')->format('Y-m-d'),
		];
	}
}