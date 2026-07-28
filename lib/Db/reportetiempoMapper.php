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
	 * Costos estimados y horas reportadas personalmente por los líderes de proyecto.
	 *
	 * La consulta se agrupa por líder y cliente para evitar consultas adicionales al
	 * construir el resumen. Las condiciones del periodo viven en el LEFT JOIN para
	 * conservar líderes y empresas que no tienen reportes.
	 */
	public function getCostosPorLider(
		$periodo_inicio = null,
		$periodo_fin = null,
		$anio = null,
		array $idEmpleadosVisibles = []
	): array {
		$periodo = $this->normalizarPeriodoCostos($periodo_inicio, $periodo_fin, $anio);
		$idEmpleadosVisibles = array_values(array_unique(array_filter(array_map(
			'intval',
			$idEmpleadosVisibles
		))));

		if (empty($idEmpleadosVisibles)) {
			return $this->crearRespuestaCostosVacia($periodo);
		}

		$inicio = sprintf('%04d-%02d-01', $periodo['anio'], $periodo['periodo_inicio']);
		$fin = (new \DateTimeImmutable(sprintf(
			'%04d-%02d-01',
			$periodo['anio'],
			$periodo['periodo_fin']
		)))->modify('last day of this month')->format('Y-m-d');

		$qb = $this->db->getQueryBuilder();
		$reportJoin = $qb->expr()->andX(
			$qb->expr()->eq('r.id_cliente', 'c.id'),
			$qb->expr()->eq('r.id_empleado', 'c.lider_proyecto'),
			$qb->expr()->gte('r.fecha_registro', $qb->createNamedParameter($inicio)),
			$qb->expr()->lte('r.fecha_registro', $qb->createNamedParameter($fin)),
			$qb->expr()->neq('r.id_cliente', $qb->createNamedParameter(99999, IQueryBuilder::PARAM_INT)),
			$qb->expr()->neq('r.id_actividad', $qb->createNamedParameter(99999, IQueryBuilder::PARAM_INT))
		);
		$parentJoin = $qb->expr()->andX(
			$qb->expr()->eq('p.id', 'c.cliente_padre'),
			$qb->expr()->in(
				'p.lider_proyecto',
				$qb->createNamedParameter(
					$idEmpleadosVisibles,
					IQueryBuilder::PARAM_INT_ARRAY
				)
			)
		);

		$qb->selectAlias('e.Id_empleados', 'id_empleado')
			->selectAlias('e.Id_user', 'uid')
			->selectAlias('u.displayname', 'displayname')
			->selectAlias('e.Sueldo', 'sueldo_hora')
			->selectAlias('c.id', 'id_cliente')
			->selectAlias('c.nombre', 'nombre_cliente')
			->selectAlias('p.id', 'cliente_padre')
			->selectAlias('p.nombre', 'nombre_grupo_padre')
			->selectAlias('c.estado', 'estado')
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
			->from('empleados_clientes', 'c')
			->innerJoin('c', 'empleados', 'e', 'c.lider_proyecto = e.Id_empleados')
			->leftJoin('e', 'users', 'u', 'u.uid = e.Id_user')
			->leftJoin('c', 'empleados_clientes', 'p', $parentJoin)
			->leftJoin('c', 'empleados_rep_tiempos', 'r', $reportJoin)
			->leftJoin('r', 'empleados_actividades', 'a', 'a.id_actividad = r.id_actividad')
			->where($qb->expr()->isNotNull('c.lider_proyecto'))
			->andWhere($qb->expr()->neq(
				'c.id',
				$qb->createNamedParameter(99999, IQueryBuilder::PARAM_INT)
			))
			->andWhere($qb->expr()->gt(
				'c.lider_proyecto',
				$qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)
			))
			->andWhere($qb->expr()->eq(
				'e.Estado',
				$qb->createNamedParameter('1', IQueryBuilder::PARAM_STR)
			))
			->andWhere($qb->expr()->in(
				'c.lider_proyecto',
				$qb->createNamedParameter(
					$idEmpleadosVisibles,
					IQueryBuilder::PARAM_INT_ARRAY
				)
			))
			->groupBy(
				'e.Id_empleados',
				'e.Id_user',
				'u.displayname',
				'e.Sueldo',
				'c.id',
				'c.nombre',
				'p.id',
				'p.nombre',
				'c.estado'
			)
			->orderBy('u.displayname', 'ASC')
			->addOrderBy('c.nombre', 'ASC');

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		$lideres = [];

		foreach ($rows as $row) {
			$idEmpleado = (int)($row['id_empleado'] ?? 0);

			if (!isset($lideres[$idEmpleado])) {
				$lideres[$idEmpleado] = [
					'id_empleado' => $idEmpleado,
					'uid' => (string)($row['uid'] ?? ''),
					'displayname' => (string)($row['displayname'] ?? ''),
					'sueldo_hora' => (float)($row['sueldo_hora'] ?? 0),
					'empresas_count' => 0,
					'total_minutos' => 0.0,
					'minutos_cargables' => 0.0,
					'empresas' => [],
				];
			}

			$totalMinutos = (float)($row['total_minutos'] ?? 0);
			$minutosCargables = (float)($row['minutos_cargables'] ?? 0);
			$sueldoHora = (float)($row['sueldo_hora'] ?? 0);
			$empresa = $this->normalizarMetricasCostos(
				$totalMinutos,
				$minutosCargables,
				$sueldoHora
			);

			$empresa = array_merge([
				'id_cliente' => (int)($row['id_cliente'] ?? 0),
				'nombre_cliente' => (string)($row['nombre_cliente'] ?? ''),
				'cliente_padre' => isset($row['cliente_padre']) ? (int)$row['cliente_padre'] : null,
				'nombre_grupo_padre' => $row['nombre_grupo_padre'] ?? null,
				'estado' => (int)($row['estado'] ?? 0),
			], $empresa);

			$lideres[$idEmpleado]['empresas'][] = $empresa;
			$lideres[$idEmpleado]['empresas_count']++;
			$lideres[$idEmpleado]['total_minutos'] += $totalMinutos;
			$lideres[$idEmpleado]['minutos_cargables'] += $minutosCargables;
		}

		$kpis = [
			'total_lideres' => count($lideres),
			'total_empresas' => 0,
			'total_minutos' => 0.0,
			'minutos_cargables' => 0.0,
			'costo_total_estimado' => 0.0,
			'costo_cargable_estimado' => 0.0,
		];

		foreach ($lideres as &$lider) {
			$metricas = $this->normalizarMetricasCostos(
				$lider['total_minutos'],
				$lider['minutos_cargables'],
				$lider['sueldo_hora']
			);
			$lider = array_merge($lider, $metricas);
			unset($lider['sueldo_hora']);
			$kpis['total_empresas'] += $lider['empresas_count'];
			$kpis['total_minutos'] += $lider['total_minutos'];
			$kpis['minutos_cargables'] += $lider['minutos_cargables'];
			$kpis['costo_total_estimado'] += $lider['costo_total_estimado'];
			$kpis['costo_cargable_estimado'] += $lider['costo_cargable_estimado'];
		}
		unset($lider);

		$metricasGenerales = $this->normalizarMetricasCostos(
			$kpis['total_minutos'],
			$kpis['minutos_cargables'],
			0
		);
		$costoTotalEstimado = $kpis['costo_total_estimado'];
		$costoCargableEstimado = $kpis['costo_cargable_estimado'];
		$kpis = array_merge($kpis, $metricasGenerales, [
			'costo_total_estimado' => $costoTotalEstimado,
			'costo_cargable_estimado' => $costoCargableEstimado,
		]);

		return [
			'periodo' => $periodo,
			'kpis' => $kpis,
			'lideres' => array_values($lideres),
		];
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
				'total_empresas' => 0,
				'total_minutos' => 0.0,
				'minutos_cargables' => 0.0,
				'minutos_no_cargables' => 0.0,
				'horas_totales' => 0.0,
				'horas_cargables' => 0.0,
				'horas_no_cargables' => 0.0,
				'porcentaje_cargable' => 0.0,
				'costo_total_estimado' => 0.0,
				'costo_cargable_estimado' => 0.0,
			],
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

	private function normalizarMetricasCostos(
		float $totalMinutos,
		float $minutosCargables,
		float $sueldoHora
	): array {
		$minutosNoCargables = max(0, $totalMinutos - $minutosCargables);
		$horasTotales = $totalMinutos / 60;
		$horasCargables = $minutosCargables / 60;

		return [
			'total_minutos' => round($totalMinutos, 2),
			'minutos_cargables' => round($minutosCargables, 2),
			'minutos_no_cargables' => round($minutosNoCargables, 2),
			'horas_totales' => round($horasTotales, 2),
			'horas_cargables' => round($horasCargables, 2),
			'horas_no_cargables' => round($minutosNoCargables / 60, 2),
			'porcentaje_cargable' => $totalMinutos > 0
				? round(($minutosCargables / $totalMinutos) * 100, 2)
				: 0.0,
			'costo_total_estimado' => round($horasTotales * $sueldoHora, 2),
			'costo_cargable_estimado' => round($horasCargables * $sueldoHora, 2),
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

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq(
					'id_empleado',
					$qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)
				)
			)
			->orderBy('id_reporte', 'DESC');

		$this->aplicarFiltroPeriodo($qb, $periodo_inicio, $periodo_fin, $anio);

		if ($limit > 0) {
			$qb->setMaxResults($limit)
				->setFirstResult($offset);
		}

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return $rows;
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

		$qb->selectAlias($qb->createFunction('COALESCE(SUM(tiempo_registrado), 0)'), 'total_minutos')
			->selectAlias($qb->createFunction('COUNT(*)'), 'total_reportes')
			->selectAlias($qb->createFunction('COUNT(DISTINCT id_empleado)'), 'empleados_con_reportes')
			->selectAlias($qb->createFunction('COUNT(DISTINCT id_cliente)'), 'proyectos_activos')
			->selectAlias($qb->createFunction('COUNT(DISTINCT id_actividad)'), 'actividades')
			->from($this->getTableName());

		$this->aplicarFiltroPeriodo($qb, $periodo_inicio, $periodo_fin, $anio);
		$this->aplicarFiltroEmpleados($qb, $idEmpleados);

		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		$totalMinutos = (float)($row['total_minutos'] ?? 0);
		$totalHoras = $totalMinutos / 60;
		$totalReportes = (int)($row['total_reportes'] ?? 0);

		return [
			'total_minutos' => $totalMinutos,
			'horas_reportadas' => $totalHoras,
			'total_reportes' => $totalReportes,
			'promedio_horas_reporte' => $totalReportes > 0 ? $totalHoras / $totalReportes : 0,
			'empleados_con_reportes' => (int)($row['empleados_con_reportes'] ?? 0),
			'proyectos_activos' => (int)($row['proyectos_activos'] ?? 0),
			'actividades' => (int)($row['actividades'] ?? 0),
		];
	}

	/**
	 * Horas agrupadas por empleado.
	 */
	public function getHorasPorEmpleado($periodo_inicio = null, $periodo_fin = null, $anio = null, array $idEmpleados = []): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('id_empleado')
			->selectAlias($qb->createFunction('SUM(tiempo_registrado)'), 'total_minutos')
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
				'total_reportes' => (int)($row['total_reportes'] ?? 0),
			];
		}, $rows);
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

	public function updateReporte($id_reporte, $id_actividad, $id_empleado, $descripcion, $tiemporegistrado, $fecha): void {
		$timestamp = date('Y-m-d H:i:s');

		$query = $this->db->getQueryBuilder();

		$result = $query->update($this->getTableName())
			->set('id_actividad', $query->createNamedParameter($id_actividad))
			->set('descripcion', $query->createNamedParameter($descripcion))
			->set('tiempo_registrado', $query->createNamedParameter($tiemporegistrado))
			->set('fecha_registro', $query->createNamedParameter($fecha))
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
			->andWhere('TIMESTAMPDIFF(MINUTE, created_at, NOW()) < 40')
			->executeStatement();

		if ($result === 0) {
			throw new \Exception('Update bloqueado: el reporte ya tiene más de 40 minutos y no se puede modificar.');
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
}
