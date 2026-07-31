<?php

declare(strict_types=1);

namespace OCA\Empleados\Service;

use DateTimeImmutable;
use InvalidArgumentException;
use OCA\Empleados\Db\aniversarioMapper;
use OCA\Empleados\Db\ausenciasMapper;
use OCA\Empleados\Db\empleadosMapper;
use OCA\Empleados\Db\historialausenciasMapper;
use OCA\Empleados\Db\historialvacacionesMapper;
use Psr\Log\LoggerInterface;

/**
 * Orquesta la reconstrucción de vacaciones a partir de datos fuente.
 *
 * Los saldos nunca se incrementan ni decrementan de forma acumulativa. Se
 * vuelven a proyectar desde Ingreso, la tabla de derechos y las solicitudes
 * activas, por lo que ejecutar este servicio varias veces produce el mismo
 * resultado.
 */
final class VacacionesCalculoService {
	public function __construct(
		private empleadosMapper $empleadosMapper,
		private ausenciasMapper $ausenciasMapper,
		private historialvacacionesMapper $historialvacacionesMapper,
		private historialausenciasMapper $historialausenciasMapper,
		private aniversarioMapper $aniversarioMapper,
		private VacationPeriodCalculator $calculator,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Calcula en backend los días laborables solicitados.
	 */
	public function calcularDiasSolicitados(
		string $fechaDe,
		string $fechaHasta,
		bool $medioDia = false,
	): float {
		$inicio = $this->calculator->parseDate($fechaDe);
		$fin = $this->calculator->parseDate($fechaHasta);

		if ($fin < $inicio) {
			throw new InvalidArgumentException('La fecha final no puede ser anterior a la inicial.');
		}

		if ($medioDia) {
			if ($inicio != $fin || (int)$inicio->format('N') > 5) {
				throw new InvalidArgumentException('El medio día solo aplica a un único día laborable.');
			}

			return 0.5;
		}

		$dias = $this->contarDiasHabiles($inicio, $fin);
		if ($dias <= 0) {
			throw new InvalidArgumentException('La solicitud debe incluir al menos un día laborable.');
		}

		return (float)$dias;
	}

	public function numeroAniversario(string $fechaIngreso, ?DateTimeImmutable $fecha = null): int {
		$ingreso = $this->calculator->parseDate($fechaIngreso);
		$referencia = $this->normalizarHoy($fecha);

		return $this->calculator->completedAnniversaries($ingreso, $referencia);
	}

	/**
	 * @return array{inicio: string, fin: string, expiracion_acumulado: string}
	 */
	public function obtenerCalendarioPeriodo(string $fechaIngreso, int $numero): array {
		$periodo = $this->calculator->period(
			$this->calculator->parseDate($fechaIngreso),
			$numero,
		);

		return [
			'inicio' => $periodo['start']->format('Y-m-d'),
			'fin' => $periodo['end']->format('Y-m-d'),
			'expiracion_acumulado' => $periodo['carry_expires']->format('Y-m-d'),
		];
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function getPeriodoActualEmpleado(
		int $idEmpleado,
		int $idAusencias,
		?DateTimeImmutable $hoy = null,
	): ?array {
		$resultado = $this->recalcularEmpleado($idEmpleado, $idAusencias, $hoy);

		return $resultado['actual'] ?? null;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function recalcularPorAusencias(
		int $idAusencias,
		?DateTimeImmutable $hoy = null,
	): ?array {
		$registro = $this->ausenciasMapper->GetAusenciasById($idAusencias);
		if (empty($registro)) {
			return null;
		}

		return $this->recalcularEmpleado(
			(int)$registro[0]['id_empleado'],
			$idAusencias,
			$hoy,
		);
	}

	/**
	 * Reconstruye y persiste todos los periodos válidos del empleado.
	 *
	 * @return array{
	 *     actual: array<string, mixed>,
	 *     periodos: array<int, array<string, mixed>>,
	 *     solicitudes_fuera_de_calendario: float
	 * }|null
	 */
	public function recalcularEmpleado(
		int $idEmpleado,
		int $idAusencias,
		?DateTimeImmutable $hoy = null,
	): ?array {
		$contexto = $this->obtenerContextoEmpleado($idEmpleado, $idAusencias);
		if ($contexto === null) {
			return null;
		}

		$hoy = $this->normalizarHoy($hoy);
		$solicitudes = $this->historialausenciasMapper->getSolicitudesParaCalculo($idAusencias);
		$proyeccion = $this->proyectar(
			$idEmpleado,
			$idAusencias,
			$contexto['ingreso'],
			$solicitudes,
			$hoy,
		);

		$this->historialvacacionesMapper->marcarNoVigentes($idEmpleado);
		foreach ($proyeccion['periodos'] as $periodo) {
			$this->historialvacacionesMapper->upsertCalculado($periodo);
		}

		foreach ($proyeccion['asignaciones'] as $asignacion) {
			if (
				!isset($asignacion['id'])
				|| !is_int($asignacion['id'])
				|| $asignacion['id'] <= 0
			) {
				continue;
			}

			if ($asignacion['numero_aniversario'] === null) {
				$this->historialausenciasMapper->actualizarAsignacion(
					$asignacion['id'],
					0.0,
					0.0,
				);
				continue;
			}

			$this->historialausenciasMapper->actualizarClasificacion(
				$asignacion['id'],
				$asignacion['numero_aniversario'],
				$asignacion['dias_de_acumulado'],
				$asignacion['dias_de_periodo'],
			);
		}

		$actual = $proyeccion['actual'];
		$this->ausenciasMapper->updateAusenciasById(
			$idEmpleado,
			(int)$actual['numero_aniversario'],
			(float)$actual['dias_periodo_disponibles'],
		);

		return [
			'actual' => $actual,
			'periodos' => $proyeccion['periodos'],
			'solicitudes_fuera_de_calendario' => $proyeccion['solicitudes_fuera_de_calendario'],
		];
	}

	/**
	 * Evalúa una solicitud nueva o editada sin confiar en repartos enviados por
	 * el navegador y sin escribir todavía en la base.
	 *
	 * @param int[] $excluirIds
	 * @return array{
	 *     dias_solicitados: float,
	 *     id_aniversario: int,
	 *     dias_de_acumulado: float,
	 *     dias_de_periodo: float,
	 *     dias_excedentes: float
	 * }
	 */
	public function evaluarSolicitud(
		int $idEmpleado,
		int $idAusencias,
		string $fechaDe,
		string $fechaHasta,
		bool $medioDia,
		bool $consumeVacaciones,
		bool $esAnticipada,
		array $excluirIds = [],
		?DateTimeImmutable $hoy = null,
	): array {
		$contexto = $this->obtenerContextoEmpleado($idEmpleado, $idAusencias);
		if ($contexto === null) {
			throw new InvalidArgumentException('Empleado sin fecha de ingreso o registro de ausencias.');
		}

		$dias = $this->calcularDiasSolicitados($fechaDe, $fechaHasta, $medioDia);
		$excluir = array_fill_keys(array_map('intval', $excluirIds), true);
		$solicitudes = array_values(array_filter(
			$this->historialausenciasMapper->getSolicitudesParaCalculo($idAusencias),
			static fn(array $fila): bool => !isset(
				$excluir[(int)($fila['id_historial_ausencias'] ?? 0)]
			),
		));

		$solicitudes[] = [
			'_clave_calculo' => '__borrador__',
			'_es_borrador' => true,
			'id_historial_ausencias' => 0,
			'id_ausencias' => $idAusencias,
			'id_aniversario' => null,
			'fecha_de' => $fechaDe,
			'fecha_hasta' => $fechaHasta,
			'dias_solicitados' => $dias,
			'dias_de_acumulado' => 0,
			'dias_de_periodo' => 0,
			'solicitar_prima_vacacional' => $consumeVacaciones ? 1 : 0,
			'privado' => $esAnticipada ? 1 : 0,
			'a_gerente' => 0,
			'a_socio' => 0,
			'a_capital_humano' => 0,
			'timestamp' => $this->normalizarHoy($hoy)->format('Y-m-d H:i:s'),
		];

		$proyeccion = $this->proyectar(
			$idEmpleado,
			$idAusencias,
			$contexto['ingreso'],
			$solicitudes,
			$this->normalizarHoy($hoy),
		);

		$borrador = $proyeccion['asignaciones']['__borrador__'] ?? null;
		if ($borrador === null || $borrador['numero_aniversario'] === null) {
			throw new InvalidArgumentException('La solicitud queda fuera del calendario vacacional válido.');
		}

		return [
			'dias_solicitados' => $dias,
			'id_aniversario' => (int)$borrador['numero_aniversario'],
			'dias_de_acumulado' => (float)$borrador['dias_de_acumulado'],
			'dias_de_periodo' => (float)$borrador['dias_de_periodo'],
			'dias_excedentes' => (float)$borrador['dias_excedentes'],
		];
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function obtenerContextoEmpleado(int $idEmpleado, int $idAusencias): ?array {
		$empleado = $this->empleadosMapper->GetMyEmployeeInfoByIdEmpleado((string)$idEmpleado);
		if (empty($empleado) || empty($empleado[0]['Ingreso'])) {
			return null;
		}

		$ausencias = $this->ausenciasMapper->GetAusenciasById($idAusencias);
		if (
			empty($ausencias)
			|| (int)$ausencias[0]['id_empleado'] !== $idEmpleado
		) {
			return null;
		}

		return [
			'ingreso' => $this->calculator->parseDate((string)$empleado[0]['Ingreso']),
			'empleado' => $empleado[0],
			'ausencias' => $ausencias[0],
		];
	}

	/**
	 * @param array<int, array<string, mixed>> $solicitudes
	 * @return array{
	 *     actual: array<string, mixed>,
	 *     periodos: array<int, array<string, mixed>>,
	 *     asignaciones: array<int|string, array<string, mixed>>,
	 *     solicitudes_fuera_de_calendario: float
	 * }
	 */
	private function proyectar(
		int $idEmpleado,
		int $idAusencias,
		DateTimeImmutable $ingreso,
		array $solicitudes,
		DateTimeImmutable $hoy,
	): array {
		$numeroActual = $this->calculator->completedAnniversaries($ingreso, $hoy);
		$existentes = [];
		foreach ($this->historialvacacionesMapper->getByEmpleado($idEmpleado) as $fila) {
			$existentes[(int)$fila['numero_aniversario']] = $fila;
		}

		$solicitudesPorPeriodo = [];
		$asignaciones = [];
		$fueraDeCalendario = 0.0;
		$maximoPeriodo = $numeroActual;

		foreach ($solicitudes as $indice => $solicitud) {
			if ((int)($solicitud['solicitar_prima_vacacional'] ?? 0) !== 1) {
				continue;
			}

			$clave = $solicitud['_clave_calculo']
				?? (int)($solicitud['id_historial_ausencias'] ?? $indice);
			$id = (int)($solicitud['id_historial_ausencias'] ?? 0);
			$dias = max(0.0, (float)($solicitud['dias_solicitados'] ?? 0));
			$numero = $this->resolverPeriodoSolicitud(
				$solicitud,
				$ingreso,
				$hoy,
				$numeroActual,
			);

			$asignaciones[$clave] = [
				'id' => $id,
				'numero_aniversario' => $numero,
				'dias_de_acumulado' => 0.0,
				'dias_de_periodo' => 0.0,
				'dias_excedentes' => 0.0,
			];

			if (!$this->solicitudCuentaComoConsumo($solicitud)) {
				continue;
			}

			if ($numero === null || $numero < 0 || $numero > $numeroActual + 1) {
				$fueraDeCalendario += $dias;
				$asignaciones[$clave]['dias_excedentes'] = $dias;
				continue;
			}

			$maximoPeriodo = max($maximoPeriodo, $numero);
			$solicitudesPorPeriodo[$numero][] = [
				'clave' => $clave,
				'fila' => $solicitud,
				'dias' => $dias,
			];
		}

		$diasPorAniversario = $this->cargarDerechosPorAniversario();
		$periodos = [];

		for ($numero = 0; $numero <= $maximoPeriodo; $numero++) {
			$calendario = $this->calculator->period($ingreso, $numero);
			$existente = $existentes[$numero] ?? null;
			$diasDerecho = $this->resolverDiasDerecho(
				$numero,
				$numeroActual,
				$existente,
				$diasPorAniversario,
			);

			if (
				$existente !== null
				&& (int)($existente['acumulado_asignado_manualmente'] ?? 0) === 1
			) {
				$diasAcumulados = max(0.0, (float)($existente['dias_acumulados'] ?? 0));
			} elseif ($numero > 0) {
				$diasAcumulados = (float)$periodos[$numero - 1]['dias_periodo_disponibles'];
			} else {
				$diasAcumulados = 0.0;
			}

			$diasPeriodoUsados = 0.0;
			$diasAcumuladosUsados = 0.0;
			$acumuladoDisponibleParaAsignar = $diasAcumulados;

			foreach ($solicitudesPorPeriodo[$numero] ?? [] as $item) {
				$fila = $item['fila'];
				$dias = (float)$item['dias'];
				$diasElegibles = min(
					$dias,
					$this->diasElegiblesParaAcumulado(
						$fila,
						$calendario['carry_expires'],
						$dias,
					),
				);
				$asignacion = $this->calculator->allocate(
					$dias,
					min($acumuladoDisponibleParaAsignar, $diasElegibles),
				);
				$disponiblePeriodoAntes = max(0.0, $diasDerecho - $diasPeriodoUsados);
				$excedenteSolicitud = max(
					0.0,
					(float)$asignacion['current_used'] - $disponiblePeriodoAntes,
				);

				$diasAcumuladosUsados += (float)$asignacion['carry_used'];
				$diasPeriodoUsados += (float)$asignacion['current_used'];
				$acumuladoDisponibleParaAsignar = (float)$asignacion['carry_remaining'];

				$asignaciones[$item['clave']]['dias_de_acumulado'] = (float)$asignacion['carry_used'];
				$asignaciones[$item['clave']]['dias_de_periodo'] = (float)$asignacion['current_used'];
				$asignaciones[$item['clave']]['dias_excedentes'] = $excedenteSolicitud;
			}

			$balance = $this->calculator->balance(
				$diasDerecho,
				$diasPeriodoUsados,
				$diasAcumulados,
				$diasAcumuladosUsados,
				$calendario['carry_expires'],
				$hoy,
			);

			$periodos[$numero] = [
				'id_empleado' => $idEmpleado,
				'numero_aniversario' => $numero,
				'periodo_inicio' => $calendario['start']->format('Y-m-d'),
				'periodo_fin' => $calendario['end']->format('Y-m-d'),
				'fecha_ingreso_base' => $ingreso->format('Y-m-d'),
				'dias_derecho' => (float)$balance['current_grant'],
				'dias_periodo_usados' => (float)$balance['current_used'],
				'dias_periodo_disponibles' => (float)$balance['current_remaining'],
				'dias_acumulados' => (float)$balance['carry_grant'],
				'dias_acumulados_usados' => (float)$balance['carry_used'],
				'dias_acumulados_restantes' => (float)$balance['carry_remaining'],
				'dias_acumulados_vencidos' => (float)$balance['carry_expired'],
				'dias_excedentes' => (float)$balance['excess'],
				'fecha_expiracion_acumulados' => $diasAcumulados > 0
					? $calendario['carry_expires']->format('Y-m-d')
					: null,
				'vigente' => $ingreso <= $hoy && $numero <= $numeroActual ? 1 : 0,
			];
		}

		if ($fueraDeCalendario > 0 && isset($periodos[$numeroActual])) {
			$periodos[$numeroActual]['dias_excedentes'] += $fueraDeCalendario;
		}

		$actual = $this->presentarPeriodoActual(
			$periodos[$numeroActual],
			$ingreso,
			$numeroActual,
		);

		return [
			'actual' => $actual,
			'periodos' => $periodos,
			'asignaciones' => $asignaciones,
			'solicitudes_fuera_de_calendario' => $fueraDeCalendario,
		];
	}

	/**
	 * @param array<string, mixed> $solicitud
	 */
	private function resolverPeriodoSolicitud(
		array $solicitud,
		DateTimeImmutable $ingreso,
		DateTimeImmutable $hoy,
		int $numeroActual,
	): ?int {
		try {
			$fecha = $this->calculator->parseDate(
				substr((string)($solicitud['fecha_de'] ?? ''), 0, 10)
			);
		} catch (InvalidArgumentException $e) {
			$this->logger->warning('Solicitud con fecha inválida durante recálculo vacacional', [
				'app' => 'empleados',
				'id_historial_ausencias' => $solicitud['id_historial_ausencias'] ?? null,
				'exception' => $e,
			]);
			return null;
		}

		if ($fecha < $ingreso) {
			return null;
		}

		if (
			(int)($solicitud['privado'] ?? 0) === 1
			&& !empty($solicitud['_es_borrador'])
		) {
			return $numeroActual + 1;
		}

		if (
			(int)($solicitud['privado'] ?? 0) === 1
			&& isset($solicitud['id_aniversario'])
			&& (int)$solicitud['id_aniversario'] >= 0
		) {
			return (int)$solicitud['id_aniversario'];
		}

		return $this->calculator->completedAnniversaries($ingreso, $fecha);
	}

	/**
	 * Las solicitudes pendientes reservan saldo para impedir sobreasignación.
	 * Aprobadas y pendientes cuentan; rechazadas o canceladas no cuentan.
	 *
	 * @param array<string, mixed> $solicitud
	 */
	private function solicitudCuentaComoConsumo(array $solicitud): bool {
		foreach (['a_gerente', 'a_socio', 'a_capital_humano'] as $estado) {
			if (in_array((int)($solicitud[$estado] ?? 0), [2, 3], true)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param array<string, mixed> $solicitud
	 */
	private function diasElegiblesParaAcumulado(
		array $solicitud,
		DateTimeImmutable $expiracion,
		float $diasSolicitados,
	): float {
		try {
			$inicio = $this->calculator->parseDate(
				substr((string)($solicitud['fecha_de'] ?? ''), 0, 10)
			);
			$fin = $this->calculator->parseDate(
				substr((string)($solicitud['fecha_hasta'] ?? ''), 0, 10)
			);
		} catch (InvalidArgumentException) {
			return 0.0;
		}

		if ($inicio > $expiracion || $fin < $inicio) {
			return 0.0;
		}

		$limite = $fin < $expiracion ? $fin : $expiracion;
		$dias = (float)$this->contarDiasHabiles($inicio, $limite);

		return min($diasSolicitados, $dias);
	}

	private function contarDiasHabiles(DateTimeImmutable $inicio, DateTimeImmutable $fin): int {
		$dias = 0;
		for ($cursor = $inicio; $cursor <= $fin; $cursor = $cursor->modify('+1 day')) {
			if ((int)$cursor->format('N') <= 5) {
				$dias++;
			}
		}

		return $dias;
	}

	/**
	 * @return array<int, float>
	 */
	private function cargarDerechosPorAniversario(): array {
		$derechos = [];
		foreach ($this->aniversarioMapper->GetAniversarios() as $fila) {
			$derechos[(int)$fila['numero_aniversario']] = max(0.0, (float)$fila['dias']);
		}
		ksort($derechos);

		return $derechos;
	}

	/**
	 * @param array<string, mixed>|null $existente
	 * @param array<int, float> $derechos
	 */
	private function resolverDiasDerecho(
		int $numero,
		int $numeroActual,
		?array $existente,
		array $derechos,
	): float {
		if ($existente !== null && (int)($existente['asignado_manualmente'] ?? 0) === 1) {
			return max(0.0, (float)$existente['dias_derecho']);
		}

		// Los derechos históricos ya congelados no se reescriben silenciosamente.
		if ($existente !== null && $numero < $numeroActual) {
			return max(0.0, (float)$existente['dias_derecho']);
		}

		if (isset($derechos[$numero])) {
			return $derechos[$numero];
		}

		$ultimo = 0.0;
		foreach ($derechos as $aniversario => $dias) {
			if ($aniversario > $numero) {
				break;
			}
			$ultimo = $dias;
		}

		return $ultimo;
	}

	/**
	 * @param array<string, mixed> $periodo
	 * @return array<string, mixed>
	 */
	private function presentarPeriodoActual(
		array $periodo,
		DateTimeImmutable $ingreso,
		int $numeroActual,
	): array {
		$limite = $this->calculator->period($ingreso, $numeroActual + 1)['carry_expires'];
		$diasPeriodo = (float)$periodo['dias_periodo_disponibles'];
		$diasAcumulados = (float)$periodo['dias_acumulados_restantes'];

		return array_merge($periodo, [
			'dias_disfrutados' => (float)$periodo['dias_periodo_usados']
				+ (float)$periodo['dias_acumulados_usados'],
			'dias_restantes' => $diasPeriodo,
			'dias_periodo_disponibles' => $diasPeriodo,
			'dias_acumulados_disponibles' => $diasAcumulados,
			'dias_totales_disponibles' => round($diasPeriodo + $diasAcumulados, 2),
			'fecha_limite_periodo_actual' => $limite->format('Y-m-d'),
		]);
	}

	private function normalizarHoy(?DateTimeImmutable $hoy): DateTimeImmutable {
		$hoy ??= new DateTimeImmutable('today');

		return $this->calculator->parseDate($hoy->format('Y-m-d'));
	}
}
