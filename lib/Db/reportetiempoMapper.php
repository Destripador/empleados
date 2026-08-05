<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;

class reportetiempoMapper extends QBMapper {
	protected string $primaryKey = 'id_reporte';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'empleados_rep_tiempos', reportetiempo::class);
	}

	/**
	 * Costos reales por empresa y empleado visible.
	 *
	 * El equipo configurado determina el rol actual, pero las horas siempre provienen
	 * de los reportes reales del periodo. Así se conservan participantes históricos
	 * que ya no aparecen en la configuración actual de la empresa.
	 */
	public function getCostosPorLider(
		$periodo_inicio = null,
		$periodo_fin = null,
		$anio = null,
		array $idEmpleadosVisibles = []
	): array {
		$periodo = $this->normalizarPeriodoCostos($periodo_inicio, $periodo_fin, $anio);
		$idEmpleadosVisibles = $this->normalizarIdsCostos($idEmpleadosVisibles);

		if (empty($idEmpleadosVisibles)) {
			return $this->crearRespuestaCostosVacia($periodo);
		}

		$inicio = sprintf('%04d-%02d-01', $periodo['anio'], $periodo['periodo_inicio']);
		$fin = (new \DateTimeImmutable(sprintf(
			'%04d-%02d-01',
			$periodo['anio'],
			$periodo['periodo_fin']
		)))->modify('last day of this month')->format('Y-m-d');

		$directorio = $this->getDirectorioCostosEmpleados($idEmpleadosVisibles);

		if (empty($directorio)) {
			return $this->crearRespuestaCostosVacia($periodo);
		}

		$idEmpleadosExistentes = array_keys($directorio);
		$reportes = $this->getReportesCostosPorEmpresaEmpleado(
			$inicio,
			$fin,
			$idEmpleadosExistentes
		);
		$reportesPorEmpresa = [];

		foreach ($reportes as $reporte) {
			$idCliente = (int)($reporte['id_cliente'] ?? 0);
			$idEmpleado = (int)($reporte['id_empleado'] ?? 0);

			if (
				$idCliente <= 0
				|| $idCliente === 99999
				|| !isset($directorio[$idEmpleado])
			) {
				continue;
			}

			$reportesPorEmpresa[$idCliente][$idEmpleado] = [
				'total_minutos' => (float)($reporte['total_minutos'] ?? 0),
				'minutos_cargables' => (float)($reporte['minutos_cargables'] ?? 0),
			];
		}

		$clientesConfigurados = $this->getClientesConfiguradosCostos();
		$empresasBase = [];

		foreach ($clientesConfigurados as $cliente) {
			$idCliente = (int)($cliente['id_cliente'] ?? 0);

			if ($idCliente <= 0 || $idCliente === 99999) {
				continue;
			}

			$idLider = (int)($cliente['lider_proyecto'] ?? 0);
			$liderVisible = isset($directorio[$idLider]) ? $idLider : 0;
			$idColaboradores = $this->normalizarColaboradoresCostos(
				$cliente['colaboradores'] ?? null,
				$liderVisible,
				$idEmpleadosExistentes
			);
			$idReportantes = array_keys($reportesPorEmpresa[$idCliente] ?? []);
			$idParticipantes = $this->normalizarIdsCostos(array_merge(
				$liderVisible > 0 ? [$liderVisible] : [],
				$idColaboradores,
				$idReportantes
			));

			if (empty($idParticipantes)) {
				continue;
			}

			$empresasBase[$idCliente] = [
				'cliente' => $cliente,
				'id_lider' => $liderVisible,
				'id_colaboradores' => $idColaboradores,
				'id_participantes' => $idParticipantes,
				'reportes' => $reportesPorEmpresa[$idCliente] ?? [],
			];
		}

		$metricasFinancieras = $this->getMetricasHonorariosCostos(
			array_keys($empresasBase),
			$inicio,
			$fin
		);
		$empresas = [];
		$empleados = [];

		foreach ($empresasBase as $idCliente => $empresaBase) {
			$cliente = $empresaBase['cliente'];
			$idLider = $empresaBase['id_lider'];
			$idColaboradores = $empresaBase['id_colaboradores'];
			$idColaboradoresMap = array_fill_keys($idColaboradores, true);
			$participantes = [];
			$totalMinutos = 0.0;
			$minutosCargables = 0.0;
			$costoLaboral = 0.0;
			$costoCargable = 0.0;

			foreach ($empresaBase['id_participantes'] as $idEmpleado) {
				$empleado = $directorio[$idEmpleado];
				$reporte = $empresaBase['reportes'][$idEmpleado] ?? [];
				$minutosEmpleado = (float)($reporte['total_minutos'] ?? 0);
				$minutosCargablesEmpleado = (float)($reporte['minutos_cargables'] ?? 0);
				$costoHora = (float)($empleado['costo_hora'] ?? 0);
				$costoEmpleado = ($minutosEmpleado / 60) * $costoHora;
				$costoCargableEmpleado = ($minutosCargablesEmpleado / 60) * $costoHora;
				$rol = $idEmpleado === $idLider
					? 'lider'
					: (isset($idColaboradoresMap[$idEmpleado])
						? 'colaborador'
						: 'participante_historico');
				$metricasEmpleadoEmpresa = $this->normalizarMetricasCostosAgregadas(
					$minutosEmpleado,
					$minutosCargablesEmpleado,
					$costoEmpleado,
					$costoCargableEmpleado
				);
				$participante = array_merge(
					$this->crearIdentidadEmpleadoCostos($empleado, $rol),
					$metricasEmpleadoEmpresa
				);
				$participantes[] = $participante;

				if (!isset($empleados[$idEmpleado])) {
					$empleados[$idEmpleado] = array_merge(
						$this->crearIdentidadEmpleadoCostos($empleado),
						[
							'empresas_count' => 0,
							'total_minutos' => 0.0,
							'minutos_cargables' => 0.0,
							'minutos_internos' => 0.0,
							'horas_internas' => 0.0,
							'costo_laboral_real' => 0.0,
							'costo_laboral_interno' => 0.0,
							'costo_cargable_real' => 0.0,
							'empresas' => [],
						]
					);
				}

				$empleados[$idEmpleado]['empresas'][] = [
					'id_cliente' => $idCliente,
					'nombre_cliente' => (string)($cliente['nombre_cliente'] ?? ''),
					'rol_asignacion' => $rol,
					'horas_totales' => $metricasEmpleadoEmpresa['horas_totales'],
					'horas_cargables' => $metricasEmpleadoEmpresa['horas_cargables'],
					'costo_laboral_real' => $metricasEmpleadoEmpresa['costo_laboral_real'],
				];
				$empleados[$idEmpleado]['empresas_count']++;
				$empleados[$idEmpleado]['total_minutos'] += $minutosEmpleado;
				$empleados[$idEmpleado]['minutos_cargables'] += $minutosCargablesEmpleado;
				$empleados[$idEmpleado]['costo_laboral_real'] += $costoEmpleado;
				$empleados[$idEmpleado]['costo_cargable_real'] += $costoCargableEmpleado;

				$totalMinutos += $minutosEmpleado;
				$minutosCargables += $minutosCargablesEmpleado;
				$costoLaboral += $costoEmpleado;
				$costoCargable += $costoCargableEmpleado;
			}

			usort($participantes, static function (array $primero, array $segundo): int {
				$orden = [
					'lider' => 0,
					'colaborador' => 1,
					'participante_historico' => 2,
				];
				$comparacionRol = ($orden[$primero['rol_asignacion']] ?? 3)
					<=> ($orden[$segundo['rol_asignacion']] ?? 3);

				return $comparacionRol !== 0
					? $comparacionRol
					: strcasecmp($primero['displayname'], $segundo['displayname']);
			});

			$finanzas = $metricasFinancieras[$idCliente]
				?? $this->crearMetricasHonorariosCostosVacias();
			$otrosCostos = 0.0;
			$participacionOficina = 0.0;
			$finanzasComparables = (bool)(
				$finanzas['metricas_financieras_comparables']
				?? true
			);
			$ingresoPeriodo = $finanzasComparables
				? (float)$finanzas['ingreso_periodo']
				: null;
			$utilidadOperativa = $ingresoPeriodo !== null
				? $ingresoPeriodo
					- $costoLaboral
					- $otrosCostos
					- $participacionOficina
				: null;
			$metricasEmpresa = $this->normalizarMetricasCostosAgregadas(
				$totalMinutos,
				$minutosCargables,
				$costoLaboral,
				$costoCargable
			);
			$idClientePadre = (int)($cliente['cliente_padre'] ?? 0);
			$nombreGrupoPadre = isset($empresasBase[$idClientePadre])
				? (string)($empresasBase[$idClientePadre]['cliente']['nombre_cliente'] ?? '')
				: null;

			$empresas[] = array_merge([
				'id_cliente' => $idCliente,
				'nombre_cliente' => (string)($cliente['nombre_cliente'] ?? ''),
				'cliente_padre' => $idClientePadre > 0 ? $idClientePadre : null,
				'nombre_grupo_padre' => $nombreGrupoPadre,
				'estado' => (int)($cliente['estado'] ?? 0),
				'lider' => $idLider > 0
					? $this->crearIdentidadEmpleadoCostos($directorio[$idLider], 'lider')
					: null,
				'colaboradores' => array_map(
					fn (int $idEmpleado): array => $this->crearIdentidadEmpleadoCostos(
						$directorio[$idEmpleado],
						'colaborador'
					),
					$idColaboradores
				),
				'participantes' => $participantes,
				'empleados_count' => count($participantes),
			], $metricasEmpresa, $finanzas, [
				'otros_costos' => $otrosCostos,
				'participacion_oficina_nacional' => $participacionOficina,
				'utilidad_operativa' => $utilidadOperativa !== null
					? round($utilidadOperativa, 2)
					: null,
				'muo' => $ingresoPeriodo !== null && $ingresoPeriodo > 0
					? round(($utilidadOperativa / $ingresoPeriodo) * 100, 2)
					: ($ingresoPeriodo === null ? null : 0.0),
			]);
		}

		foreach ($empleados as &$empleado) {
			$metricas = $this->normalizarMetricasCostosAgregadas(
				$empleado['total_minutos'],
				$empleado['minutos_cargables'],
				$empleado['costo_laboral_real'],
				$empleado['costo_cargable_real']
			);
			$empleado = array_merge($empleado, $metricas);
			unset($empleado['costo_cargable_real']);
		}
		unset($empleado);

		foreach ($this->getReportesInternosPorEmpleado($inicio, $fin, $idEmpleadosExistentes) as $interno) {
			$idEmpleado = (int)($interno['id_empleado'] ?? 0);
			if (!isset($directorio[$idEmpleado])) continue;
			if (!isset($empleados[$idEmpleado])) {
				$empleados[$idEmpleado] = array_merge($this->crearIdentidadEmpleadoCostos($directorio[$idEmpleado]), [
					'empresas_count' => 0,
					'total_minutos' => 0.0,
					'minutos_cargables' => 0.0,
					'minutos_internos' => 0.0,
					'horas_internas' => 0.0,
					'costo_laboral_real' => 0.0,
					'costo_laboral_interno' => 0.0,
					'empresas' => [],
				]);
			}
			$minutos = (float)($interno['total_minutos'] ?? 0);
			$costo = ($minutos / 60) * (float)($directorio[$idEmpleado]['costo_hora'] ?? 0);
			$actual = $empleados[$idEmpleado];
			$empleados[$idEmpleado] = array_merge($actual, $this->normalizarMetricasCostosAgregadas(
				(float)($actual['total_minutos'] ?? 0) + $minutos,
				(float)($actual['minutos_cargables'] ?? 0),
				(float)($actual['costo_laboral_real'] ?? 0) + $costo,
				(float)($actual['costo_cargable_estimado'] ?? 0)
			));
			$empleados[$idEmpleado]['minutos_internos'] = round(
				(float)($actual['minutos_internos'] ?? 0) + $minutos,
				2
			);
			$empleados[$idEmpleado]['horas_internas'] = round(
				$empleados[$idEmpleado]['minutos_internos'] / 60,
				2
			);
			$empleados[$idEmpleado]['costo_laboral_interno'] = round(
				(float)($actual['costo_laboral_interno'] ?? 0) + $costo,
				2
			);
		}

		usort(
			$empresas,
			static fn (array $primera, array $segunda): int
				=> strcasecmp($primera['nombre_cliente'], $segunda['nombre_cliente'])
		);
		usort(
			$empleados,
			static fn (array $primero, array $segundo): int
				=> strcasecmp($primero['displayname'], $segundo['displayname'])
		);

		$lideres = array_values(array_filter(
			$empleados,
			static function (array $empleado): bool {
				return array_reduce(
					$empleado['empresas'],
					static fn (bool $esLider, array $empresa): bool
						=> $esLider || $empresa['rol_asignacion'] === 'lider',
					false
				);
			}
		));
		$kpis = $this->crearKpisCostos($empresas, count($empleados), count($lideres));
		$totalMinutosEmpleados = array_sum(array_column($empleados, 'total_minutos'));
		$minutosCargablesEmpleados = array_sum(array_column($empleados, 'minutos_cargables'));
		$costoLaboralEmpleados = array_sum(array_column($empleados, 'costo_laboral_real'));
		$costoCargableEmpleados = array_sum(array_column($empleados, 'costo_cargable_estimado'));
		$minutosInternosEmpleados = array_sum(array_column($empleados, 'minutos_internos'));
		$costoInternoEmpleados = array_sum(array_column($empleados, 'costo_laboral_interno'));
		$kpis = array_merge($kpis, $this->normalizarMetricasCostosAgregadas(
			(float)$totalMinutosEmpleados,
			(float)$minutosCargablesEmpleados,
			(float)$costoLaboralEmpleados,
			(float)$costoCargableEmpleados
		));
		$kpis['minutos_internos'] = round((float)$minutosInternosEmpleados, 2);
		$kpis['horas_internas'] = round((float)$minutosInternosEmpleados / 60, 2);
		$kpis['costo_laboral_interno'] = round((float)$costoInternoEmpleados, 2);

		return [
			'periodo' => $periodo,
			'kpis' => $kpis,
			'empresas' => $empresas,
			'empleados' => array_values($empleados),
			// Compatibilidad temporal con consumidores anteriores.
			'lideres' => $lideres,
		];
	}

	private function getReportesInternosPorEmpleado(string $inicio, string $fin, array $ids): array {
		if ($ids === []) return [];
		$qb = $this->db->getQueryBuilder();
		$qb->select('r.id_empleado')
			->selectAlias($qb->createFunction('COALESCE(SUM(r.tiempo_registrado), 0)'), 'total_minutos')
			->from($this->getTableName(), 'r')
			->where($qb->expr()->in('r.id_empleado', $qb->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)))
				->andWhere($this->internalWorkExpression($qb, 'r'))
			->andWhere($qb->expr()->gte('r.fecha_registro', $qb->createNamedParameter($inicio)))
			->andWhere($qb->expr()->lte('r.fecha_registro', $qb->createNamedParameter($fin)))
			->groupBy('r.id_empleado');
		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();
		return $rows;
	}

	/**
	 * Directorio laboral limitado estrictamente a los IDs visibles de la sesión.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function getDirectorioCostosEmpleados(array $idEmpleadosVisibles): array {
		$idEmpleadosVisibles = $this->normalizarIdsCostos($idEmpleadosVisibles);

		if (empty($idEmpleadosVisibles)) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->selectAlias('e.Id_empleados', 'id_empleado')
			->selectAlias('e.Id_user', 'uid')
			->selectAlias('u.displayname', 'displayname')
			->selectAlias('e.Sueldo', 'costo_hora')
			->from('empleados', 'e')
			->leftJoin('e', 'users', 'u', 'u.uid = e.Id_user')
			->where($qb->expr()->in(
				'e.Id_empleados',
				$qb->createNamedParameter(
					$idEmpleadosVisibles,
					IQueryBuilder::PARAM_INT_ARRAY
				)
			));

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();
		$directorio = [];

		foreach ($rows as $row) {
			$idEmpleado = (int)($row['id_empleado'] ?? 0);

			if ($idEmpleado <= 0) {
				continue;
			}

			$uid = (string)($row['uid'] ?? '');
			$directorio[$idEmpleado] = [
				'id_empleado' => $idEmpleado,
				'uid' => $uid,
				'displayname' => (string)($row['displayname'] ?? $uid),
				'costo_hora' => (float)($row['costo_hora'] ?? 0),
			];
		}

		return $directorio;
	}

	/**
	 * Reportes reales del periodo agrupados por empresa y empleado visible.
	 */
	private function getReportesCostosPorEmpresaEmpleado(
		string $fechaInicio,
		string $fechaFin,
		array $idEmpleadosVisibles
	): array {
		$idEmpleadosVisibles = $this->normalizarIdsCostos($idEmpleadosVisibles);

		if (empty($idEmpleadosVisibles)) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$actividadValida = $qb->expr()->orX(
			$qb->expr()->isNull('r.id_actividad'),
			$qb->expr()->neq(
				'r.id_actividad',
				$qb->createNamedParameter(99999, IQueryBuilder::PARAM_INT)
			)
		);

		$qb->selectAlias('r.id_cliente', 'id_cliente')
			->selectAlias('r.id_empleado', 'id_empleado')
			->selectAlias(
				$qb->createFunction('COALESCE(SUM(r.tiempo_registrado), 0)'),
				'total_minutos'
			)
			->selectAlias(
				$qb->createFunction(
					'COALESCE(SUM(CASE WHEN a.cargable = 1 THEN r.tiempo_registrado ELSE 0 END), 0)'
				),
				'minutos_cargables'
			)
			->from('empleados_rep_tiempos', 'r')
			->leftJoin('r', 'empleados_actividades', 'a', 'a.id_actividad = r.id_actividad')
			->where($qb->expr()->in(
				'r.id_empleado',
				$qb->createNamedParameter(
					array_map('strval', $idEmpleadosVisibles),
					IQueryBuilder::PARAM_STR_ARRAY
				)
			))
			->andWhere($qb->expr()->gte(
				'r.fecha_registro',
				$qb->createNamedParameter($fechaInicio)
			))
			->andWhere($qb->expr()->lte(
				'r.fecha_registro',
				$qb->createNamedParameter($fechaFin)
			))
				->andWhere($this->clientWorkExpression($qb, 'r'))
			->andWhere($actividadValida)
			->groupBy('r.id_cliente', 'r.id_empleado');

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return $rows;
	}

	/**
	 * Configuración mínima de empresas. El alcance se aplica después de normalizar
	 * el JSON de colaboradores y cruzarlo con el directorio visible.
	 */
	private function getClientesConfiguradosCostos(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->selectAlias('c.id', 'id_cliente')
			->selectAlias('c.nombre', 'nombre_cliente')
			->selectAlias('c.lider_proyecto', 'lider_proyecto')
			->selectAlias('c.colaboradores', 'colaboradores')
			->selectAlias('c.cliente_padre', 'cliente_padre')
			->selectAlias('c.estado', 'estado')
			->from('empleados_clientes', 'c')
			->where($qb->expr()->neq(
				'c.id',
				$qb->createNamedParameter(99999, IQueryBuilder::PARAM_INT)
			));

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return $rows;
	}

	private function normalizarColaboradoresCostos(
		$colaboradores,
		int $idLider,
		array $idEmpleadosVisibles
	): array {
		$normalizados = $colaboradores;

		for ($intento = 0; $intento < 2 && is_string($normalizados); $intento++) {
			$decodificados = json_decode($normalizados, true);

			if (json_last_error() !== JSON_ERROR_NONE) {
				return [];
			}

			$normalizados = $decodificados;
		}

		if (!is_array($normalizados)) {
			return [];
		}

		$ids = [];

		foreach ($normalizados as $idEmpleado) {
			if (!is_int($idEmpleado) && !is_string($idEmpleado) && !is_float($idEmpleado)) {
				continue;
			}

			$id = (int)$idEmpleado;

			if ($id > 0 && $id !== $idLider) {
				$ids[] = $id;
			}
		}

		$ids = $this->normalizarIdsCostos($ids);
		$visibles = array_fill_keys($this->normalizarIdsCostos($idEmpleadosVisibles), true);

		return array_values(array_filter(
			$ids,
			static fn (int $idEmpleado): bool => isset($visibles[$idEmpleado])
		));
	}

	/**
	 * Métricas de honorarios independientes de los reportes para evitar multiplicar
	 * horas o importes al unir dos relaciones uno-a-muchos.
	 *
	 * No existe actualmente una fuente de otros costos ni participación nacional;
	 * esos conceptos permanecen explícitamente en cero al construir cada empresa.
	 */
	private function getMetricasHonorariosCostos(
		array $idClientes,
		string $fechaInicio,
		string $fechaFin
	): array {
		$idClientes = $this->normalizarIdsCostos($idClientes);

		if (empty($idClientes)) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->selectAlias('h.id_cliente', 'id_cliente')
			->selectAlias('p.pfecha_inicio', 'pfecha_inicio')
			->selectAlias('p.pfecha_fin', 'pfecha_fin')
			->selectAlias('p.importe_parcialidad', 'importe_parcialidad')
			->selectAlias('p.pagado', 'pagado')
			->selectAlias('p.fecha_pago', 'fecha_pago')
			->selectAlias('h.tipo_moneda', 'tipo_moneda')
			->from('empleados_honorarios', 'h')
			->innerJoin(
				'h',
				'empleados_honorarios_p',
				'p',
				'p.id_honorario = h.id_honorario'
			)
			->where($qb->expr()->in(
				'h.id_cliente',
				$qb->createNamedParameter($idClientes, IQueryBuilder::PARAM_INT_ARRAY)
			));

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();
		$metricas = [];
		$cobradoAcumulado = [];
		$monedas = [];

		foreach ($rows as $row) {
			$idCliente = (int)($row['id_cliente'] ?? 0);

			if ($idCliente <= 0) {
				continue;
			}

			if (!isset($metricas[$idCliente])) {
				$metricas[$idCliente] = $this->crearMetricasHonorariosCostosVacias();
				$cobradoAcumulado[$idCliente] = 0.0;
			}

			$importe = (float)($row['importe_parcialidad'] ?? 0);
			$inicioParcialidad = $this->normalizarFechaCostos($row['pfecha_inicio'] ?? null);
			$finParcialidad = $this->normalizarFechaCostos($row['pfecha_fin'] ?? null)
				?? $inicioParcialidad;
			$fechaPago = $this->normalizarFechaCostos($row['fecha_pago'] ?? null);
			$estadoPago = (int)($row['pagado'] ?? 0);
			$tipoMoneda = strtoupper(trim((string)($row['tipo_moneda'] ?? 'MXN')))
				?: 'MXN';
			$esProyectado = $inicioParcialidad !== null
				&& $inicioParcialidad <= $fechaFin;
			$esIngresoPeriodo = $finParcialidad !== null
				&& $finParcialidad >= $fechaInicio
				&& $finParcialidad <= $fechaFin;
			$esCobroPeriodo = in_array($estadoPago, [1, 2], true)
				&& $fechaPago !== null
				&& $fechaPago >= $fechaInicio
				&& $fechaPago <= $fechaFin;

			if ($esProyectado || $esIngresoPeriodo || $esCobroPeriodo) {
				$monedas[$idCliente][$tipoMoneda] = true;
			}

			if ($esProyectado) {
				$metricas[$idCliente]['honorario_proyectado_acumulado'] += $importe;

				if (
					in_array($estadoPago, [1, 2], true)
					&& $fechaPago !== null
					&& $fechaPago <= $fechaFin
				) {
					$cobradoAcumulado[$idCliente] += $importe;
				}
			}

			if ($esIngresoPeriodo) {
				$metricas[$idCliente]['ingreso_periodo'] += $importe;
			}

			if ($esCobroPeriodo) {
				$metricas[$idCliente]['honorario_cobrado_periodo'] += $importe;
			}
		}

		foreach ($metricas as $idCliente => &$metrica) {
			$metrica['saldo_pendiente'] = max(
				0.0,
				$metrica['honorario_proyectado_acumulado']
					- ($cobradoAcumulado[$idCliente] ?? 0.0)
			);

			foreach ([
				'honorario_proyectado_acumulado',
				'ingreso_periodo',
				'honorario_cobrado_periodo',
				'saldo_pendiente',
			] as $claveMetrica) {
				$metrica[$claveMetrica] = round((float)$metrica[$claveMetrica], 2);
			}
			$monedasCliente = array_keys($monedas[$idCliente] ?? []);
			sort($monedasCliente);
			$comparables = empty($monedasCliente)
				|| $monedasCliente === ['MXN'];
			$metrica['monedas_honorarios'] = $monedasCliente;
			$metrica['metricas_financieras_comparables'] = $comparables;

			if (!$comparables) {
				$metrica['honorario_proyectado_acumulado'] = null;
				$metrica['ingreso_periodo'] = null;
				$metrica['honorario_cobrado_periodo'] = null;
				$metrica['saldo_pendiente'] = null;
			}
		}
		unset($metrica);

		return $metricas;
	}

	private function normalizarFechaCostos($fecha): ?string {
		if (!is_string($fecha)) {
			return null;
		}

		$fecha = trim($fecha);

		return preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) === 1
			? $fecha
			: null;
	}

	private function crearMetricasHonorariosCostosVacias(): array {
		return [
			'honorario_proyectado_acumulado' => 0.0,
			'ingreso_periodo' => 0.0,
			'honorario_cobrado_periodo' => 0.0,
			'saldo_pendiente' => 0.0,
			'monedas_honorarios' => [],
			'metricas_financieras_comparables' => true,
		];
	}

	private function crearIdentidadEmpleadoCostos(
		array $empleado,
		?string $rolAsignacion = null
	): array {
		$identidad = [
			'id_empleado' => (int)($empleado['id_empleado'] ?? 0),
			'uid' => (string)($empleado['uid'] ?? ''),
			'displayname' => (string)(
				$empleado['displayname']
				?? $empleado['uid']
				?? ''
			),
		];

		if ($rolAsignacion !== null) {
			$identidad['rol_asignacion'] = $rolAsignacion;
		}

		return $identidad;
	}

	private function normalizarMetricasCostosAgregadas(
		float $totalMinutos,
		float $minutosCargables,
		float $costoLaboral,
		float $costoCargable
	): array {
		$minutosNoCargables = max(0.0, $totalMinutos - $minutosCargables);

		return [
			'total_minutos' => round($totalMinutos, 2),
			'minutos_cargables' => round($minutosCargables, 2),
			'minutos_no_cargables' => round($minutosNoCargables, 2),
			'horas_totales' => round($totalMinutos / 60, 2),
			'horas_cargables' => round($minutosCargables / 60, 2),
			'horas_no_cargables' => round($minutosNoCargables / 60, 2),
			'porcentaje_cargable' => $totalMinutos > 0
				? round(($minutosCargables / $totalMinutos) * 100, 2)
				: 0.0,
			'costo_laboral_real' => round($costoLaboral, 2),
			'costo_total_estimado' => round($costoLaboral, 2),
			'costo_cargable_estimado' => round($costoCargable, 2),
		];
	}

	private function crearKpisCostos(
		array $empresas,
		int $totalEmpleados,
		int $totalLideres
	): array {
		$totalMinutos = 0.0;
		$minutosCargables = 0.0;
		$costoLaboral = 0.0;
		$costoCargable = 0.0;
		$honorarioProyectado = 0.0;
		$ingresoPeriodo = 0.0;
		$honorarioCobrado = 0.0;
		$saldoPendiente = 0.0;
		$otrosCostos = 0.0;
		$participacionOficina = 0.0;
		$utilidadOperativa = 0.0;
		$finanzasComparables = true;

		foreach ($empresas as $empresa) {
			$totalMinutos += (float)($empresa['total_minutos'] ?? 0);
			$minutosCargables += (float)($empresa['minutos_cargables'] ?? 0);
			$costoLaboral += (float)($empresa['costo_laboral_real'] ?? 0);
			$costoCargable += (float)($empresa['costo_cargable_estimado'] ?? 0);
			$empresaComparable = (bool)(
				$empresa['metricas_financieras_comparables']
				?? true
			);
			$finanzasComparables = $finanzasComparables && $empresaComparable;

			if ($empresaComparable) {
				$honorarioProyectado += (float)($empresa['honorario_proyectado_acumulado'] ?? 0);
				$ingresoPeriodo += (float)($empresa['ingreso_periodo'] ?? 0);
				$honorarioCobrado += (float)($empresa['honorario_cobrado_periodo'] ?? 0);
				$saldoPendiente += (float)($empresa['saldo_pendiente'] ?? 0);
				$utilidadOperativa += (float)($empresa['utilidad_operativa'] ?? 0);
			}
			$otrosCostos += (float)($empresa['otros_costos'] ?? 0);
			$participacionOficina += (float)(
				$empresa['participacion_oficina_nacional']
				?? 0
			);
		}

		return array_merge([
			'total_lideres' => $totalLideres,
			'total_empleados' => $totalEmpleados,
			'total_empresas' => count($empresas),
		], $this->normalizarMetricasCostosAgregadas(
			$totalMinutos,
			$minutosCargables,
			$costoLaboral,
			$costoCargable
		), [
			'honorario_proyectado_acumulado' => $finanzasComparables
				? round($honorarioProyectado, 2)
				: null,
			'ingreso_periodo' => $finanzasComparables
				? round($ingresoPeriodo, 2)
				: null,
			'honorario_cobrado_periodo' => $finanzasComparables
				? round($honorarioCobrado, 2)
				: null,
			'saldo_pendiente' => $finanzasComparables
				? round($saldoPendiente, 2)
				: null,
			'otros_costos' => round($otrosCostos, 2),
			'participacion_oficina_nacional' => round($participacionOficina, 2),
			'utilidad_operativa' => $finanzasComparables
				? round($utilidadOperativa, 2)
				: null,
			'muo' => $finanzasComparables && $ingresoPeriodo > 0
				? round(($utilidadOperativa / $ingresoPeriodo) * 100, 2)
				: ($finanzasComparables ? 0.0 : null),
			'metricas_financieras_comparables' => $finanzasComparables,
		]);
	}

	/**
	 * Obtiene una empresa activa cuyo líder pertenece al alcance visible.
	 */
	public function getCostosEmpresaVisible(int $idCliente, array $idEmpleadosVisibles): array {
		$idEmpleadosVisibles = $this->normalizarIdsCostos($idEmpleadosVisibles);

		if ($idCliente <= 0 || $idCliente === 99999 || empty($idEmpleadosVisibles)) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$parentJoin = $qb->expr()->andX(
			$qb->expr()->eq('p.id', 'c.cliente_padre'),
			$qb->expr()->in(
				'p.lider_proyecto',
				$qb->createNamedParameter($idEmpleadosVisibles, IQueryBuilder::PARAM_INT_ARRAY)
			)
		);

		$qb->selectAlias('c.id', 'id')
			->selectAlias('c.nombre', 'nombre')
			->selectAlias('c.lider_proyecto', 'lider_proyecto')
			->selectAlias('p.id', 'cliente_padre')
			->selectAlias('p.nombre', 'nombre_grupo_padre')
			->from('empleados_clientes', 'c')
			->innerJoin('c', 'empleados', 'e', 'e.Id_empleados = c.lider_proyecto')
			->leftJoin('c', 'empleados_clientes', 'p', $parentJoin)
			->where($qb->expr()->eq(
				'c.id',
				$qb->createNamedParameter($idCliente, IQueryBuilder::PARAM_INT)
			))
			->andWhere($qb->expr()->eq(
				'c.estado',
				$qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)
			))
			->andWhere($qb->expr()->eq(
				'e.Estado',
				$qb->createNamedParameter('1', IQueryBuilder::PARAM_STR)
			))
			->andWhere($qb->expr()->in(
				'c.lider_proyecto',
				$qb->createNamedParameter($idEmpleadosVisibles, IQueryBuilder::PARAM_INT_ARRAY)
			))
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		if (!$row) {
			return [];
		}

		return [
			'id' => (int)($row['id'] ?? 0),
			'nombre' => (string)($row['nombre'] ?? ''),
			'lider_proyecto' => (int)($row['lider_proyecto'] ?? 0),
			'cliente_padre' => isset($row['cliente_padre']) ? (int)$row['cliente_padre'] : null,
			'nombre_grupo_padre' => $row['nombre_grupo_padre'] ?? null,
		];
	}

	/**
	 * Valida y devuelve en lote las actividades requeridas.
	 */
	public function getCostosActividades(array $idActividades): array {
		$idActividades = $this->normalizarIdsCostos($idActividades);
		$idActividades = array_values(array_filter(
			$idActividades,
			static fn (int $id): bool => $id !== 99999
		));

		if (empty($idActividades)) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('id_actividad', 'nombre', 'cargable')
			->from('empleados_actividades')
			->where($qb->expr()->in(
				'id_actividad',
				$qb->createNamedParameter($idActividades, IQueryBuilder::PARAM_INT_ARRAY)
			))
			->orderBy('nombre', 'ASC');

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return array_map(static function (array $row): array {
			return [
				'id_actividad' => (int)($row['id_actividad'] ?? 0),
				'nombre' => (string)($row['nombre'] ?? ''),
				'cargable' => (int)($row['cargable'] ?? 0),
			];
		}, $rows);
	}

	/**
	 * Catálogo mínimo de actividades para el módulo de Costos.
	 */
	public function getCostosActividadesDisponibles(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id_actividad', 'nombre', 'cargable')
			->from('empleados_actividades')
			->where($qb->expr()->neq(
				'id_actividad',
				$qb->createNamedParameter(99999, IQueryBuilder::PARAM_INT)
			))
			->orderBy('nombre', 'ASC');

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return array_map(static function (array $row): array {
			return [
				'id_actividad' => (int)($row['id_actividad'] ?? 0),
				'nombre' => (string)($row['nombre'] ?? ''),
				'cargable' => (int)($row['cargable'] ?? 0),
			];
		}, $rows);
	}

	/**
	 * Datos laborales mínimos de candidatos activos dentro del alcance.
	 */
	public function getCostosEmpleadosBase(array $idEmpleadosVisibles): array {
		$idEmpleadosVisibles = $this->normalizarIdsCostos($idEmpleadosVisibles);

		if (empty($idEmpleadosVisibles)) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->selectAlias('e.Id_empleados', 'id_empleado')
			->selectAlias('e.Id_user', 'uid')
			->selectAlias('u.displayname', 'displayname')
			->selectAlias('e.Sueldo', 'costo_hora')
			->selectAlias('d.Nombre', 'area')
			->selectAlias('p.Nombre', 'puesto')
			->selectAlias('p.nivel', 'nivel_puesto')
			->from('empleados', 'e')
			->innerJoin('e', 'users', 'u', 'u.uid = e.Id_user')
			->leftJoin('e', 'departamentos', 'd', 'd.Id_departamento = e.Id_departamento')
			->leftJoin('e', 'puestos', 'p', 'p.Id_puestos = e.Id_puesto')
			->where($qb->expr()->in(
				'e.Id_empleados',
				$qb->createNamedParameter($idEmpleadosVisibles, IQueryBuilder::PARAM_INT_ARRAY)
			))
			->andWhere($qb->expr()->eq(
				'e.Estado',
				$qb->createNamedParameter('1', IQueryBuilder::PARAM_STR)
			))
			->orderBy('u.displayname', 'ASC');

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return array_map(static function (array $row): array {
			$costoRaw = $row['costo_hora'] ?? null;

			return [
				'id_empleado' => (int)($row['id_empleado'] ?? 0),
				'uid' => (string)($row['uid'] ?? ''),
				'displayname' => (string)($row['displayname'] ?? ''),
				'area' => $row['area'] ?? null,
				'puesto' => $row['puesto'] ?? null,
				'nivel_puesto' => isset($row['nivel_puesto']) ? (int)$row['nivel_puesto'] : null,
				'costo_hora' => $costoRaw === null || $costoRaw === ''
					? null
					: (float)$costoRaw,
			];
		}, $rows);
	}

	/**
	 * Horas de proyecto reportadas por candidato dentro del periodo.
	 */
	public function getCostosHorasPeriodo(
		array $idEmpleadosVisibles,
		string $fechaInicio,
		string $fechaFin
	): array {
		$idEmpleadosVisibles = $this->normalizarIdsCostos($idEmpleadosVisibles);

		if (empty($idEmpleadosVisibles)) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('r.id_empleado')
			->selectAlias(
				$qb->createFunction('COALESCE(SUM(r.tiempo_registrado), 0)'),
				'minutos_reportados'
			)
			->selectAlias($qb->createFunction('COUNT(*)'), 'registros_periodo')
			->selectAlias($qb->createFunction('MAX(r.fecha_registro)'), 'ultimo_reporte_periodo')
			->from('empleados_rep_tiempos', 'r')
			->where($qb->expr()->in(
				'r.id_empleado',
				$qb->createNamedParameter($idEmpleadosVisibles, IQueryBuilder::PARAM_INT_ARRAY)
			))
			->andWhere($qb->expr()->gte(
				'r.fecha_registro',
				$qb->createNamedParameter($fechaInicio)
			))
			->andWhere($qb->expr()->lte(
				'r.fecha_registro',
				$qb->createNamedParameter($fechaFin)
			))
			->andWhere($qb->expr()->neq(
				'r.id_cliente',
				$qb->createNamedParameter(99999, IQueryBuilder::PARAM_INT)
			))
			->andWhere($qb->expr()->neq(
				'r.id_actividad',
				$qb->createNamedParameter(99999, IQueryBuilder::PARAM_INT)
			))
			->groupBy('r.id_empleado');

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return $rows;
	}

	/**
	 * Experiencia histórica agregada por candidato en una sola consulta.
	 */
	public function getCostosExperiencia(
		array $idEmpleadosVisibles,
		array $idActividades,
		int $idCliente,
		string $fechaCorteDoceMeses,
		string $fechaReferenciaExperiencia
	): array {
		$idEmpleadosVisibles = $this->normalizarIdsCostos($idEmpleadosVisibles);
		$idActividades = $this->normalizarIdsCostos($idActividades);

		if (empty($idEmpleadosVisibles)) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$condicionActividades = '1 = 0';

		if (!empty($idActividades)) {
			$condicionActividades = (string)$qb->expr()->in(
				'r.id_actividad',
				$qb->createNamedParameter($idActividades, IQueryBuilder::PARAM_INT_ARRAY)
			);
		}

		$condicionEmpresa = $idCliente > 0
			? (string)$qb->expr()->eq(
				'r.id_cliente',
				$qb->createNamedParameter($idCliente, IQueryBuilder::PARAM_INT)
			)
			: '1 = 0';
		$condicionReciente = (string)$qb->expr()->gte(
			'r.fecha_registro',
			$qb->createNamedParameter($fechaCorteDoceMeses)
		);

		$qb->select('r.id_empleado')
			->selectAlias(
				$qb->createFunction('COALESCE(SUM(r.tiempo_registrado), 0)'),
				'minutos_historicos'
			)
			->selectAlias(
				$qb->createFunction(
					'COALESCE(SUM(CASE WHEN a.cargable = 1 THEN r.tiempo_registrado ELSE 0 END), 0)'
				),
				'minutos_cargables_historicos'
			)
			->selectAlias($qb->createFunction('COUNT(*)'), 'registros_historicos')
			->selectAlias(
				$qb->createFunction("SUM(CASE WHEN {$condicionReciente} THEN 1 ELSE 0 END)"),
				'registros_12_meses'
			)
			->selectAlias(
				$qb->createFunction('COUNT(DISTINCT r.id_cliente)'),
				'empresas_atendidas'
			)
			->selectAlias($qb->createFunction('MAX(r.fecha_registro)'), 'ultimo_reporte')
			->selectAlias(
				$qb->createFunction(
					"COALESCE(SUM(CASE WHEN {$condicionActividades} THEN r.tiempo_registrado ELSE 0 END), 0)"
				),
				'minutos_actividades'
			)
			->selectAlias(
				$qb->createFunction(
					"COALESCE(SUM(CASE WHEN {$condicionActividades} AND {$condicionReciente} THEN r.tiempo_registrado ELSE 0 END), 0)"
				),
				'minutos_actividades_12_meses'
			)
			->selectAlias(
				$qb->createFunction(
					"SUM(CASE WHEN {$condicionActividades} THEN 1 ELSE 0 END)"
				),
				'registros_actividades'
			)
			->selectAlias(
				$qb->createFunction(
					"COUNT(DISTINCT CASE WHEN {$condicionActividades} THEN r.id_cliente ELSE NULL END)"
				),
				'empresas_actividades'
			)
			->selectAlias(
				$qb->createFunction(
					"COALESCE(SUM(CASE WHEN {$condicionEmpresa} THEN r.tiempo_registrado ELSE 0 END), 0)"
				),
				'minutos_empresa'
			)
			->selectAlias(
				$qb->createFunction(
					"COALESCE(SUM(CASE WHEN {$condicionEmpresa} AND a.cargable = 1 THEN r.tiempo_registrado ELSE 0 END), 0)"
				),
				'minutos_cargables_empresa'
			)
			->selectAlias(
				$qb->createFunction(
					"MAX(CASE WHEN {$condicionEmpresa} THEN r.fecha_registro ELSE NULL END)"
				),
				'ultimo_reporte_empresa'
			)
			->selectAlias(
				$qb->createFunction(
					"COUNT(DISTINCT CASE WHEN {$condicionEmpresa} THEN r.id_actividad ELSE NULL END)"
				),
				'actividades_empresa'
			)
			->from('empleados_rep_tiempos', 'r')
			->leftJoin('r', 'empleados_actividades', 'a', 'a.id_actividad = r.id_actividad')
			->where($qb->expr()->in(
				'r.id_empleado',
				$qb->createNamedParameter($idEmpleadosVisibles, IQueryBuilder::PARAM_INT_ARRAY)
			))
			->andWhere($qb->expr()->neq(
				'r.id_cliente',
				$qb->createNamedParameter(99999, IQueryBuilder::PARAM_INT)
			))
			->andWhere($qb->expr()->neq(
				'r.id_actividad',
				$qb->createNamedParameter(99999, IQueryBuilder::PARAM_INT)
			))
			->andWhere($qb->expr()->lte(
				'r.fecha_registro',
				$qb->createNamedParameter($fechaReferenciaExperiencia)
			))
			->groupBy('r.id_empleado');

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return $rows;
	}

	/**
	 * Desglose agregado por actividad para filtros y explicación en frontend.
	 */
	public function getCostosExperienciaPorActividad(
		array $idEmpleadosVisibles,
		array $idActividades,
		string $fechaCorteDoceMeses,
		string $fechaReferenciaExperiencia
	): array {
		$idEmpleadosVisibles = $this->normalizarIdsCostos($idEmpleadosVisibles);
		$idActividades = array_values(array_filter(
			$this->normalizarIdsCostos($idActividades),
			static fn (int $id): bool => $id !== 99999
		));

		if (empty($idEmpleadosVisibles) || empty($idActividades)) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$condicionReciente = (string)$qb->expr()->gte(
			'r.fecha_registro',
			$qb->createNamedParameter($fechaCorteDoceMeses)
		);

		$qb->select('r.id_empleado', 'r.id_actividad')
			->selectAlias(
				$qb->createFunction('COALESCE(SUM(r.tiempo_registrado), 0)'),
				'minutos_actividad'
			)
			->selectAlias(
				$qb->createFunction(
					"COALESCE(SUM(CASE WHEN {$condicionReciente} THEN r.tiempo_registrado ELSE 0 END), 0)"
				),
				'minutos_actividad_12_meses'
			)
			->selectAlias($qb->createFunction('COUNT(*)'), 'registros_actividad')
			->selectAlias($qb->createFunction('MAX(r.fecha_registro)'), 'ultimo_reporte_actividad')
			->from('empleados_rep_tiempos', 'r')
			->where($qb->expr()->in(
				'r.id_empleado',
				$qb->createNamedParameter($idEmpleadosVisibles, IQueryBuilder::PARAM_INT_ARRAY)
			))
			->andWhere($qb->expr()->in(
				'r.id_actividad',
				$qb->createNamedParameter($idActividades, IQueryBuilder::PARAM_INT_ARRAY)
			))
			->andWhere($qb->expr()->neq(
				'r.id_cliente',
				$qb->createNamedParameter(99999, IQueryBuilder::PARAM_INT)
			))
			->andWhere($qb->expr()->lte(
				'r.fecha_registro',
				$qb->createNamedParameter($fechaReferenciaExperiencia)
			))
			->groupBy('r.id_empleado')
			->addGroupBy('r.id_actividad');

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return $rows;
	}

	/**
	 * Ausencias plenamente aprobadas y solapadas con el periodo.
	 */
	public function getCostosAusenciasAprobadas(
		array $idEmpleadosVisibles,
		string $fechaInicio,
		string $fechaFin
	): array {
		$idEmpleadosVisibles = $this->normalizarIdsCostos($idEmpleadosVisibles);

		if (empty($idEmpleadosVisibles)) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->selectAlias('au.id_empleado', 'id_empleado')
			->selectAlias('h.fecha_de', 'fecha_de')
			->selectAlias('h.fecha_hasta', 'fecha_hasta')
			->selectAlias('h.dias_solicitados', 'dias_solicitados')
			->from('historial_ausencias', 'h')
			->innerJoin('h', 'ausencias', 'au', 'au.id_ausencias = h.id_ausencias')
			->where($qb->expr()->in(
				'au.id_empleado',
				$qb->createNamedParameter($idEmpleadosVisibles, IQueryBuilder::PARAM_INT_ARRAY)
			))
			->andWhere($qb->expr()->lte(
				'h.fecha_de',
				$qb->createNamedParameter($fechaFin . ' 23:59:59')
			))
			->andWhere($qb->expr()->gte(
				'h.fecha_hasta',
				$qb->createNamedParameter($fechaInicio . ' 00:00:00')
			))
			->andWhere($qb->expr()->eq(
				'h.a_gerente',
				$qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)
			))
			->andWhere($qb->expr()->eq(
				'h.a_socio',
				$qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)
			))
			->andWhere($qb->expr()->eq(
				'h.a_capital_humano',
				$qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)
			));

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return $rows;
	}

	private function normalizarIdsCostos(array $ids): array {
		return array_values(array_unique(array_filter(
			array_map('intval', $ids),
			static fn (int $id): bool => $id > 0
		)));
	}

	private function crearRespuestaCostosVacia(array $periodo): array {
		return [
			'periodo' => $periodo,
			'kpis' => [
				'total_lideres' => 0,
				'total_empleados' => 0,
				'total_empresas' => 0,
				'total_minutos' => 0.0,
				'minutos_cargables' => 0.0,
				'minutos_no_cargables' => 0.0,
				'minutos_internos' => 0.0,
				'horas_totales' => 0.0,
				'horas_cargables' => 0.0,
				'horas_no_cargables' => 0.0,
				'horas_internas' => 0.0,
				'porcentaje_cargable' => 0.0,
				'costo_total_estimado' => 0.0,
				'costo_cargable_estimado' => 0.0,
				'costo_laboral_real' => 0.0,
				'costo_laboral_interno' => 0.0,
				'honorario_proyectado_acumulado' => 0.0,
				'ingreso_periodo' => 0.0,
				'honorario_cobrado_periodo' => 0.0,
				'saldo_pendiente' => 0.0,
				'otros_costos' => 0.0,
				'participacion_oficina_nacional' => 0.0,
				'utilidad_operativa' => 0.0,
				'muo' => 0.0,
				'metricas_financieras_comparables' => true,
			],
			'empresas' => [],
			'empleados' => [],
			'lideres' => [],
		];
	}

	private function normalizarPeriodoCostos($periodo_inicio, $periodo_fin, $anio): array {
		$mesActual = (int)(new \DateTimeImmutable())->format('n');
		$anioActual = (int)(new \DateTimeImmutable())->format('Y');
		$inicio = $periodo_inicio === null ? $mesActual : max(1, min(12, (int)$periodo_inicio));
		$fin = $periodo_fin === null ? $mesActual : max(1, min(12, (int)$periodo_fin));

		if ($inicio > $fin) {
			[$inicio, $fin] = [$fin, $inicio];
		}

		return [
			'periodo_inicio' => $inicio,
			'periodo_fin' => $fin,
			'anio' => $anio === null ? $anioActual : max(1, (int)$anio),
		];
	}

	private function aplicarFiltroPeriodo(IQueryBuilder $qb, $periodo_inicio = null, $periodo_fin = null, $anio = null): void {
		if ($anio === null || $periodo_inicio === null || $periodo_fin === null) {
			return;
		}

		$periodo_inicio = max(1, min(12, (int)$periodo_inicio));
		$periodo_fin = max(1, min(12, (int)$periodo_fin));

		if ($periodo_inicio > $periodo_fin) {
			[$periodo_inicio, $periodo_fin] = [$periodo_fin, $periodo_inicio];
		}

		$inicio = sprintf('%04d-%02d-01', (int)$anio, $periodo_inicio);

		$fin = (new \DateTimeImmutable(sprintf('%04d-%02d-01', (int)$anio, $periodo_fin)))
			->modify('last day of this month')
			->format('Y-m-d');

		$qb->andWhere($qb->expr()->gte(
			'fecha_registro',
			$qb->createNamedParameter($inicio)
		));

		$qb->andWhere($qb->expr()->lte(
			'fecha_registro',
			$qb->createNamedParameter($fin)
		));
	}

	/**
	 * Reportes de un empleado.
	 *
	 * @return array
	 */
	public function findById($id, int $limit = 20, int $offset = 0, $periodo_inicio = null, $periodo_fin = null, $anio = null): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('r.*')
			->selectAlias('a.nombre', 'actividad_nombre')
			->selectAlias('a.cargable', 'cargable')
			->selectAlias('s.id_equipo', 'id_equipo')
			->selectAlias('e.nombre_dispositivo', 'nombre_dispositivo')
			->from($this->getTableName(), 'r')
			->leftJoin('r', 'empleados_actividades', 'a', 'a.id_actividad = r.id_actividad')
			->leftJoin('r', 'soporte_historial', 's', "r.origen = 'soporte_ti' AND s.id_soporte = r.origen_id")
			->leftJoin('s', 'inventario_computo', 'e', 'e.id_equipo = s.id_equipo')
			->where(
				$qb->expr()->eq(
					'r.id_empleado',
					$qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)
				)
			)
			->orderBy('r.id_reporte', 'DESC');

		$this->aplicarFiltroPeriodo($qb, $periodo_inicio, $periodo_fin, $anio);

		if ($limit > 0) {
			$qb->setMaxResults($limit)
				->setFirstResult($offset);
		}

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return $this->normalizeWorkTypes($rows);
	}

	/**
	 * Todos los reportes del periodo.
	 *
	 * @return reportetiempo[]
	 */
	public function findAll(int $limit = 100, int $offset = 0, $periodo_inicio = null, $periodo_fin = null, $anio = null): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->orderBy('id_reporte', 'DESC');

		$this->aplicarFiltroPeriodo($qb, $periodo_inicio, $periodo_fin, $anio);

		if ($limit > 0) {
			$qb->setMaxResults($limit)
				->setFirstResult($offset);
		}

		return $this->findEntities($qb);
	}

	/**
	 * Reportes pertenecientes exclusivamente a empleados dentro del alcance visible.
	 *
	 * @return reportetiempo[]
	 */
	public function findAllByEmployeeIds(
		array $idEmpleados,
		int $limit = 100,
		int $offset = 0,
		$periodo_inicio = null,
		$periodo_fin = null,
		$anio = null
	): array {
		$idEmpleados = array_values(array_unique(array_filter(
			array_map('intval', $idEmpleados),
			static fn (int $id): bool => $id > 0
		)));

		if (empty($idEmpleados)) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();

		$qb->select('r.*')
			->selectAlias('a.nombre', 'actividad_nombre')
			->selectAlias('a.cargable', 'cargable')
			->selectAlias('s.id_equipo', 'id_equipo')
			->selectAlias('e.nombre_dispositivo', 'nombre_dispositivo')
			->from($this->getTableName(), 'r')
			->leftJoin('r', 'empleados_actividades', 'a', 'a.id_actividad = r.id_actividad')
			->leftJoin('r', 'soporte_historial', 's', "r.origen = 'soporte_ti' AND s.id_soporte = r.origen_id")
			->leftJoin('s', 'inventario_computo', 'e', 'e.id_equipo = s.id_equipo')
			->where($qb->expr()->in(
				'r.id_empleado',
				$qb->createNamedParameter($idEmpleados, IQueryBuilder::PARAM_INT_ARRAY)
			))
			->orderBy('r.id_reporte', 'DESC');

		$this->aplicarFiltroPeriodo($qb, $periodo_inicio, $periodo_fin, $anio);

		if ($limit > 0) {
			$qb->setMaxResults($limit)
				->setFirstResult($offset);
		}

		return $this->findEntities($qb);
	}

	/**
	 * KPIs generales del periodo.
	 *
	 * No calcula costo_total porque esta tabla no tiene sueldo.
	 */
	public function getResumenGeneral(
		$periodo_inicio = null,
		$periodo_fin = null,
		$anio = null,
		array $idEmpleados = []
	): array {
		$qb = $this->db->getQueryBuilder();
		$client = "(r.tipo_trabajo = 'cliente' OR (r.tipo_trabajo IS NULL AND r.id_cliente IS NOT NULL AND r.id_cliente <> 99999 AND (r.id_actividad IS NULL OR r.id_actividad <> 99999)))";
		$internal = "(r.tipo_trabajo = 'interno' OR (r.tipo_trabajo IS NULL AND r.id_cliente IS NULL AND (r.id_actividad IS NULL OR r.id_actividad <> 99999)))";
		$absence = "(r.tipo_trabajo = 'ausencia' OR (r.tipo_trabajo IS NULL AND (r.id_cliente = 99999 OR r.id_actividad = 99999)))";

		$qb->selectAlias($qb->createFunction('COALESCE(SUM(r.tiempo_registrado), 0)'), 'total_minutos')
			->selectAlias($qb->createFunction("COALESCE(SUM(CASE WHEN $client THEN r.tiempo_registrado ELSE 0 END), 0)"), 'minutos_cliente')
			->selectAlias($qb->createFunction("COALESCE(SUM(CASE WHEN $internal THEN r.tiempo_registrado ELSE 0 END), 0)"), 'minutos_internos')
			->selectAlias($qb->createFunction("COALESCE(SUM(CASE WHEN $absence THEN r.tiempo_registrado ELSE 0 END), 0)"), 'minutos_ausencia')
			->selectAlias($qb->createFunction("COALESCE(SUM(CASE WHEN $client AND a.cargable = 1 THEN r.tiempo_registrado ELSE 0 END), 0)"), 'minutos_cargables')
			->selectAlias($qb->createFunction('COUNT(*)'), 'total_reportes')
			->selectAlias($qb->createFunction('COUNT(DISTINCT r.id_empleado)'), 'empleados_con_reportes')
			->selectAlias($qb->createFunction("COUNT(DISTINCT CASE WHEN $client THEN r.id_cliente ELSE NULL END)"), 'proyectos_activos')
			->selectAlias($qb->createFunction('COUNT(DISTINCT r.id_actividad)'), 'actividades')
			->from($this->getTableName(), 'r')
			->leftJoin('r', 'empleados_actividades', 'a', 'a.id_actividad = r.id_actividad');

		$this->aplicarFiltroPeriodo($qb, $periodo_inicio, $periodo_fin, $anio);
		$this->aplicarFiltroEmpleados($qb, $idEmpleados);

		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		$totalMinutos = (float)($row['total_minutos'] ?? 0);
		$totalHoras = $totalMinutos / 60;
		$totalReportes = (int)($row['total_reportes'] ?? 0);
		$clientMinutes = (float)($row['minutos_cliente'] ?? 0);
		$internalMinutes = (float)($row['minutos_internos'] ?? 0);
		$absenceMinutes = (float)($row['minutos_ausencia'] ?? 0);
		$billableMinutes = (float)($row['minutos_cargables'] ?? 0);
		$workedMinutes = $clientMinutes + $internalMinutes;

		return [
			'total_minutos' => $totalMinutos,
			'horas_reportadas' => $totalHoras,
			'total_reportes' => $totalReportes,
			'promedio_horas_reporte' => $totalReportes > 0 ? $totalHoras / $totalReportes : 0,
			'empleados_con_reportes' => (int)($row['empleados_con_reportes'] ?? 0),
			'proyectos_activos' => (int)($row['proyectos_activos'] ?? 0),
			'actividades' => (int)($row['actividades'] ?? 0),
			'minutos_cliente' => $clientMinutes,
			'minutos_internos' => $internalMinutes,
			'minutos_ausencia' => $absenceMinutes,
			'minutos_cargables' => $billableMinutes,
			'minutos_no_cargables' => max(0, $totalMinutos - $billableMinutes),
			'horas_cliente' => $clientMinutes / 60,
			'horas_internas' => $internalMinutes / 60,
			'horas_ausencia' => $absenceMinutes / 60,
			'horas_cargables' => $billableMinutes / 60,
			'horas_no_cargables' => max(0, $totalMinutos - $billableMinutes) / 60,
			'porcentaje_interno' => $workedMinutes > 0 ? ($internalMinutes / $workedMinutes) * 100 : 0,
		];
	}

	/**
	 * Horas agrupadas por empleado.
	 */
	public function getHorasPorEmpleado($periodo_inicio = null, $periodo_fin = null, $anio = null, array $idEmpleados = []): array {
		$qb = $this->db->getQueryBuilder();
		$internal = "(tipo_trabajo = 'interno' OR (tipo_trabajo IS NULL AND id_cliente IS NULL AND (id_actividad IS NULL OR id_actividad <> 99999)))";
		$client = "(tipo_trabajo = 'cliente' OR (tipo_trabajo IS NULL AND id_cliente IS NOT NULL AND id_cliente <> 99999 AND (id_actividad IS NULL OR id_actividad <> 99999)))";
		$absence = "(tipo_trabajo = 'ausencia' OR (tipo_trabajo IS NULL AND (id_cliente = 99999 OR id_actividad = 99999)))";

		$qb->select('id_empleado')
			->selectAlias($qb->createFunction('SUM(tiempo_registrado)'), 'total_minutos')
			->selectAlias($qb->createFunction("SUM(CASE WHEN $client THEN tiempo_registrado ELSE 0 END)"), 'minutos_cliente')
			->selectAlias($qb->createFunction("SUM(CASE WHEN $internal THEN tiempo_registrado ELSE 0 END)"), 'minutos_internos')
			->selectAlias($qb->createFunction("SUM(CASE WHEN $absence THEN tiempo_registrado ELSE 0 END)"), 'minutos_ausencia')
			->selectAlias($qb->createFunction('COUNT(*)'), 'total_reportes')
			->from($this->getTableName())
			->groupBy('id_empleado')
			->orderBy('total_minutos', 'DESC');

		$this->aplicarFiltroPeriodo($qb, $periodo_inicio, $periodo_fin, $anio);
		$this->aplicarFiltroEmpleados($qb, $idEmpleados);

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return array_map(static function ($row) {
			$totalMinutos = (float)($row['total_minutos'] ?? 0);

			return [
				'id_empleado' => (int)($row['id_empleado'] ?? 0),
				'total_minutos' => $totalMinutos,
					'horas' => $totalMinutos / 60,
					'minutos_cliente' => (float)($row['minutos_cliente'] ?? 0),
					'minutos_internos' => (float)($row['minutos_internos'] ?? 0),
					'minutos_ausencia' => (float)($row['minutos_ausencia'] ?? 0),
				'total_reportes' => (int)($row['total_reportes'] ?? 0),
			];
		}, $rows);
	}

	/**
	 * Desglose multidimensional de trabajo interno. La agregación se realiza en PHP
	 * para evitar funciones de fecha específicas de MariaDB, PostgreSQL o SQLite.
	 */
	public function getTrabajoInternoAgrupado($periodo_inicio = null, $periodo_fin = null, $anio = null, array $idEmpleados = []): array {
		$idEmpleados = array_values(array_unique(array_filter(array_map('intval', $idEmpleados))));
		$empty = [
			'por_area' => [],
			'por_empleado' => [],
			'por_actividad' => [],
			'por_mes' => [],
			'por_origen' => [],
		];
		if ($idEmpleados === []) return $empty;

		$qb = $this->db->getQueryBuilder();
		$qb->select('r.id_empleado', 'r.id_actividad', 'r.fecha_registro', 'r.origen', 'r.tiempo_registrado')
			->selectAlias('a.nombre', 'actividad_nombre')
			->selectAlias('e.Id_user', 'uid')
			->selectAlias('e.Id_departamento', 'id_departamento')
			->selectAlias('e.Sueldo', 'costo_hora')
			->selectAlias('u.displayname', 'empleado_nombre')
			->selectAlias('d.Nombre', 'area_nombre')
			->from($this->getTableName(), 'r')
			->leftJoin('r', 'empleados_actividades', 'a', 'a.id_actividad = r.id_actividad')
			->leftJoin('r', 'empleados', 'e', 'e.Id_empleados = r.id_empleado')
			->leftJoin('e', 'users', 'u', 'u.uid = e.Id_user')
			->leftJoin('e', 'departamentos', 'd', 'd.Id_departamento = e.Id_departamento')
			->where($this->internalWorkExpression($qb, 'r'))
			->andWhere($qb->expr()->in('r.id_empleado', $qb->createNamedParameter($idEmpleados, IQueryBuilder::PARAM_INT_ARRAY)));

		if ($anio !== null && $periodo_inicio !== null && $periodo_fin !== null) {
			$startMonth = max(1, min(12, (int)$periodo_inicio));
			$endMonth = max(1, min(12, (int)$periodo_fin));
			if ($startMonth > $endMonth) [$startMonth, $endMonth] = [$endMonth, $startMonth];
			$start = sprintf('%04d-%02d-01', (int)$anio, $startMonth);
			$end = (new \DateTimeImmutable(sprintf('%04d-%02d-01', (int)$anio, $endMonth)))
				->modify('last day of this month')->format('Y-m-d');
			$qb->andWhere($qb->expr()->gte('r.fecha_registro', $qb->createNamedParameter($start)))
				->andWhere($qb->expr()->lte('r.fecha_registro', $qb->createNamedParameter($end)));
		}

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();
		$groups = $empty;
		$append = static function (array &$target, string $key, array $identity, float $minutes, float $cost): void {
			if (!isset($target[$key])) $target[$key] = array_merge($identity, ['minutos' => 0.0, 'horas' => 0.0, 'costo_laboral' => 0.0, 'reportes' => 0]);
			$target[$key]['minutos'] += $minutes;
			$target[$key]['horas'] = $target[$key]['minutos'] / 60;
			$target[$key]['costo_laboral'] += $cost;
			$target[$key]['reportes']++;
		};

		foreach ($rows as $row) {
			$minutes = (float)($row['tiempo_registrado'] ?? 0);
			$cost = ($minutes / 60) * (float)($row['costo_hora'] ?? 0);
			$employeeId = (int)($row['id_empleado'] ?? 0);
			$activityId = (int)($row['id_actividad'] ?? 0);
			$areaId = (int)($row['id_departamento'] ?? 0);
			$origin = trim((string)($row['origen'] ?? '')) ?: 'legado';
			$month = substr((string)($row['fecha_registro'] ?? ''), 0, 7);
			$append($groups['por_area'], (string)$areaId, ['id_area' => $areaId, 'nombre' => (string)($row['area_nombre'] ?? 'Sin área')], $minutes, $cost);
			$append($groups['por_empleado'], (string)$employeeId, ['id_empleado' => $employeeId, 'nombre' => (string)($row['empleado_nombre'] ?? $row['uid'] ?? '')], $minutes, $cost);
			$append($groups['por_actividad'], (string)$activityId, ['id_actividad' => $activityId, 'nombre' => (string)($row['actividad_nombre'] ?? '')], $minutes, $cost);
			$append($groups['por_mes'], $month, ['mes' => $month], $minutes, $cost);
			$append($groups['por_origen'], $origin, ['origen' => $origin], $minutes, $cost);
		}

		foreach ($groups as &$items) $items = array_values($items);
		unset($items);
		return $groups;
	}

	/**
	 * Horas agrupadas por proyecto / cliente.
	 */
	public function getHorasPorProyecto($periodo_inicio = null, $periodo_fin = null, $anio = null, array $idEmpleados = []): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('id_cliente')
			->selectAlias($qb->createFunction('SUM(tiempo_registrado)'), 'total_minutos')
			->selectAlias($qb->createFunction('COUNT(*)'), 'total_reportes')
			->from($this->getTableName())
			->groupBy('id_cliente')
			->orderBy('total_minutos', 'DESC');

		$this->aplicarFiltroPeriodo($qb, $periodo_inicio, $periodo_fin, $anio);
		$this->aplicarFiltroEmpleados($qb, $idEmpleados);
		$qb->andWhere($this->clientWorkExpression($qb));

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return array_map(static function ($row) {
			$totalMinutos = (float)($row['total_minutos'] ?? 0);

			return [
				'id_cliente' => (int)($row['id_cliente'] ?? 0),
				'total_minutos' => $totalMinutos,
				'horas' => $totalMinutos / 60,
				'total_reportes' => (int)($row['total_reportes'] ?? 0),
			];
		}, $rows);
	}

	/**
	 * Horas agrupadas por actividad.
	 */
	public function getHorasPorActividad($periodo_inicio = null, $periodo_fin = null, $anio = null, array $idEmpleados = []): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('id_actividad')
			->selectAlias($qb->createFunction('SUM(tiempo_registrado)'), 'total_minutos')
			->selectAlias($qb->createFunction('COUNT(*)'), 'total_reportes')
			->from($this->getTableName())
			->groupBy('id_actividad')
			->orderBy('total_minutos', 'DESC');

		$this->aplicarFiltroPeriodo($qb, $periodo_inicio, $periodo_fin, $anio);
		$this->aplicarFiltroEmpleados($qb, $idEmpleados);

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return array_map(static function ($row) {
			$totalMinutos = (float)($row['total_minutos'] ?? 0);

			return [
				'id_actividad' => (int)($row['id_actividad'] ?? 0),
				'total_minutos' => $totalMinutos,
				'horas' => $totalMinutos / 60,
				'total_reportes' => (int)($row['total_reportes'] ?? 0),
			];
		}, $rows);
	}

	/**
	 * Horas agrupadas por día.
	 */
	public function getHorasPorDia($periodo_inicio = null, $periodo_fin = null, $anio = null, array $idEmpleados = []): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('fecha_registro')
			->selectAlias($qb->createFunction('SUM(tiempo_registrado)'), 'total_minutos')
			->selectAlias($qb->createFunction('COUNT(*)'), 'total_reportes')
			->from($this->getTableName())
			->groupBy('fecha_registro')
			->orderBy('fecha_registro', 'ASC');

		$this->aplicarFiltroPeriodo($qb, $periodo_inicio, $periodo_fin, $anio);
		$this->aplicarFiltroEmpleados($qb, $idEmpleados);

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return array_map(static function ($row) {
			$totalMinutos = (float)($row['total_minutos'] ?? 0);

			return [
				'fecha_registro' => $row['fecha_registro'],
				'total_minutos' => $totalMinutos,
				'horas' => $totalMinutos / 60,
				'total_reportes' => (int)($row['total_reportes'] ?? 0),
			];
		}, $rows);
	}

	/**
	 * Cantidad de reportes agrupados por día.
	 */
	public function getReportesPorDia($periodo_inicio = null, $periodo_fin = null, $anio = null, array $idEmpleados = []): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('fecha_registro')
			->selectAlias($qb->createFunction('COUNT(*)'), 'total_reportes')
			->from($this->getTableName())
			->groupBy('fecha_registro')
			->orderBy('fecha_registro', 'ASC');

		$this->aplicarFiltroPeriodo($qb, $periodo_inicio, $periodo_fin, $anio);
		$this->aplicarFiltroEmpleados($qb, $idEmpleados);

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return array_map(static function ($row) {
			return [
				'fecha_registro' => $row['fecha_registro'],
				'total_reportes' => (int)($row['total_reportes'] ?? 0),
			];
		}, $rows);
	}

	/**
	 * Datos para gráfica apilada:
	 * Proyecto / cliente vs actividad.
	 */
	public function getProyectoVsActividad($periodo_inicio = null, $periodo_fin = null, $anio = null, array $idEmpleados = []): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('id_cliente', 'id_actividad')
			->selectAlias($qb->createFunction('SUM(tiempo_registrado)'), 'total_minutos')
			->selectAlias($qb->createFunction('COUNT(*)'), 'total_reportes')
			->from($this->getTableName())
			->groupBy('id_cliente')
			->addGroupBy('id_actividad')
			->orderBy('id_cliente', 'ASC');

		$this->aplicarFiltroPeriodo($qb, $periodo_inicio, $periodo_fin, $anio);
		$this->aplicarFiltroEmpleados($qb, $idEmpleados);
		$qb->andWhere($this->clientWorkExpression($qb));

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return array_map(static function ($row) {
			$totalMinutos = (float)($row['total_minutos'] ?? 0);

			return [
				'id_cliente' => (int)($row['id_cliente'] ?? 0),
				'id_actividad' => (int)($row['id_actividad'] ?? 0),
				'total_minutos' => $totalMinutos,
				'horas' => $totalMinutos / 60,
				'total_reportes' => (int)($row['total_reportes'] ?? 0),
			];
		}, $rows);
	}

	public function deleteById(int $id): void {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq(
					'id_reporte',
					$qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)
				)
			);

		$qb->executeStatement();
	}

	public function findReportById(int $id): ?array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('id_reporte', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1);
		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();
		return $row ?: null;
	}

	public function findByOrigin(string $origin, int $originId): ?array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('origen', $qb->createNamedParameter($origin)))
			->andWhere($qb->expr()->eq('origen_id', $qb->createNamedParameter($originId, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1);
		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();
		return $row ?: null;
	}

	public function createIntegrated(array $data): int {
		$now = date('Y-m-d H:i:s');
		$qb = $this->db->getQueryBuilder();
		$qb->insert($this->getTableName())->values([
			'id_empleado' => $qb->createNamedParameter($data['id_empleado'], IQueryBuilder::PARAM_INT),
			'id_cliente' => $qb->createNamedParameter(null),
			'id_actividad' => $qb->createNamedParameter($data['id_actividad'], IQueryBuilder::PARAM_INT),
			'descripcion' => $qb->createNamedParameter($data['descripcion']),
			'tiempo_registrado' => $qb->createNamedParameter($data['tiempo_registrado']),
			'fecha_registro' => $qb->createNamedParameter($data['fecha_registro']),
			'origen' => $qb->createNamedParameter($data['origen']),
			'origen_id' => $qb->createNamedParameter($data['origen_id'], IQueryBuilder::PARAM_INT),
			'tipo_trabajo' => $qb->createNamedParameter(reportetiempo::TIPO_INTERNO),
			'created_at' => $qb->createNamedParameter($now),
			'updated_at' => $qb->createNamedParameter($now),
		])->executeStatement();
		return (int)$this->db->lastInsertId($this->getTableName());
	}

	public function updateIntegrated(string $origin, int $originId, array $data): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('id_empleado', $qb->createNamedParameter($data['id_empleado'], IQueryBuilder::PARAM_INT))
			->set('id_cliente', $qb->createNamedParameter(null))
			->set('id_actividad', $qb->createNamedParameter($data['id_actividad'], IQueryBuilder::PARAM_INT))
			->set('descripcion', $qb->createNamedParameter($data['descripcion']))
			->set('tiempo_registrado', $qb->createNamedParameter($data['tiempo_registrado']))
			->set('fecha_registro', $qb->createNamedParameter($data['fecha_registro']))
			->set('tipo_trabajo', $qb->createNamedParameter(reportetiempo::TIPO_INTERNO))
			->set('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->where($qb->expr()->eq('origen', $qb->createNamedParameter($origin)))
			->andWhere($qb->expr()->eq('origen_id', $qb->createNamedParameter($originId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	public function deleteByOrigin(string $origin, int $originId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('origen', $qb->createNamedParameter($origin)))
			->andWhere($qb->expr()->eq('origen_id', $qb->createNamedParameter($originId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	public function countOrphanSupportReports(): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('COUNT(*)'))
			->from($this->getTableName(), 'r')
			->leftJoin('r', 'soporte_historial', 's', 's.id_soporte = r.origen_id')
			->where($qb->expr()->eq('r.origen', $qb->createNamedParameter('soporte_ti')))
			->andWhere($qb->expr()->isNull('s.id_soporte'));
		$result = $qb->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();
		return $count;
	}

	public function updateReporte($id_reporte, $id_actividad, $id_empleado, $descripcion, $tiemporegistrado, $fecha, $idCliente = null, ?string $workType = null, ?string $origin = null): void {
		$timestamp = date('Y-m-d H:i:s');

		$query = $this->db->getQueryBuilder();

		$result = $query->update($this->getTableName())
			->set('id_actividad', $query->createNamedParameter($id_actividad))
			->set('descripcion', $query->createNamedParameter($descripcion))
			->set('tiempo_registrado', $query->createNamedParameter($tiemporegistrado))
			->set('fecha_registro', $query->createNamedParameter($fecha))
			->set('id_cliente', $query->createNamedParameter($idCliente))
			->set('tipo_trabajo', $query->createNamedParameter($workType))
			->set('origen', $query->createNamedParameter($origin))
			->set('origen_id', $query->createNamedParameter(null))
			->set('updated_at', $query->createNamedParameter($timestamp))
			->where(
				$query->expr()->eq(
					'id_reporte',
					$query->createNamedParameter($id_reporte)
				)
			)
			->andWhere(
				$query->expr()->eq(
					'id_empleado',
					$query->createNamedParameter($id_empleado)
				)
			)
			->executeStatement();

		if ($result === 0) {
			throw new \Exception('No fue posible actualizar el reporte solicitado.');
		}
	}

	public function getResumenDiaByEmpleado(int $idEmpleado, string $fecha): array {
		$qb = $this->db->getQueryBuilder();

		$qb->selectAlias($qb->createFunction('COUNT(*)'), 'registros')
			->selectAlias($qb->createFunction('COALESCE(SUM(tiempo_registrado), 0)'), 'minutos_reportados')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq(
					'id_empleado',
					$qb->createNamedParameter($idEmpleado, IQueryBuilder::PARAM_INT)
				)
			)
			->andWhere(
				$qb->expr()->eq(
					'fecha_registro',
					$qb->createNamedParameter($fecha)
				)
			);

		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		return [
			'registros' => (int)($row['registros'] ?? 0),
			'minutos_reportados' => (float)($row['minutos_reportados'] ?? 0),
		];
	}

	private function aplicarFiltroEmpleados(IQueryBuilder $qb, array $idEmpleados): void {
		$idEmpleados = array_values(array_unique(array_filter(array_map('intval', $idEmpleados))));

		if (empty($idEmpleados)) {
			$qb->andWhere($qb->expr()->eq('id_empleado', $qb->createNamedParameter(-1, IQueryBuilder::PARAM_INT)));
			return;
		}

		$qb->andWhere(
			$qb->expr()->in(
				'id_empleado',
				$qb->createNamedParameter($idEmpleados, IQueryBuilder::PARAM_INT_ARRAY)
			)
		);
	}

	public function deleteByFechaRangoAusencia(int $id_empleado, string $fecha_de, string $fecha_hasta): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('id_empleado', $qb->createNamedParameter($id_empleado)))
			->andWhere($qb->expr()->eq('id_cliente', $qb->createNamedParameter(99999)))
			->andWhere($qb->expr()->eq('id_actividad', $qb->createNamedParameter(99999)))
			->andWhere($qb->expr()->gte('fecha_registro', $qb->createNamedParameter($fecha_de)))
			->andWhere($qb->expr()->lte('fecha_registro', $qb->createNamedParameter($fecha_hasta)));
		$qb->executeStatement();
	}

	private function internalWorkExpression(IQueryBuilder $qb, string $alias = ''): mixed {
		$prefix = $alias === '' ? '' : $alias . '.';
		return $qb->expr()->orX(
			$qb->expr()->eq($prefix . 'tipo_trabajo', $qb->createNamedParameter(reportetiempo::TIPO_INTERNO)),
			$qb->expr()->andX(
				$qb->expr()->isNull($prefix . 'tipo_trabajo'),
				$qb->expr()->isNull($prefix . 'id_cliente'),
				$qb->expr()->orX(
					$qb->expr()->isNull($prefix . 'id_actividad'),
					$qb->expr()->neq($prefix . 'id_actividad', $qb->createNamedParameter(99999, IQueryBuilder::PARAM_INT)),
				),
			),
		);
	}

	private function clientWorkExpression(IQueryBuilder $qb, string $alias = ''): mixed {
		$prefix = $alias === '' ? '' : $alias . '.';
		return $qb->expr()->orX(
			$qb->expr()->eq($prefix . 'tipo_trabajo', $qb->createNamedParameter(reportetiempo::TIPO_CLIENTE)),
			$qb->expr()->andX(
				$qb->expr()->isNull($prefix . 'tipo_trabajo'),
				$qb->expr()->isNotNull($prefix . 'id_cliente'),
				$qb->expr()->neq($prefix . 'id_cliente', $qb->createNamedParameter(99999, IQueryBuilder::PARAM_INT)),
				$qb->expr()->orX(
					$qb->expr()->isNull($prefix . 'id_actividad'),
					$qb->expr()->neq($prefix . 'id_actividad', $qb->createNamedParameter(99999, IQueryBuilder::PARAM_INT)),
				),
			),
		);
	}

	private function normalizeWorkTypes(array $rows): array {
		foreach ($rows as &$row) {
			$type = trim((string)($row['tipo_trabajo'] ?? ''));
			if ($type !== '') continue;
			if ((int)($row['id_cliente'] ?? 0) === 99999 || (int)($row['id_actividad'] ?? 0) === 99999) $type = reportetiempo::TIPO_AUSENCIA;
			elseif (($row['id_cliente'] ?? null) === null || ($row['origen'] ?? null) === 'soporte_ti') $type = reportetiempo::TIPO_INTERNO;
			else $type = reportetiempo::TIPO_CLIENTE;
			$row['tipo_trabajo'] = $type;
		}
		unset($row);
		return $rows;
	}
}
