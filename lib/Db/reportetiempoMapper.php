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
	public function getResumenGeneral($periodo_inicio = null, $periodo_fin = null, $anio = null): array {
		$qb = $this->db->getQueryBuilder();

		$qb->selectAlias($qb->createFunction('SUM(tiempo_registrado)'), 'total_minutos')
			->selectAlias($qb->createFunction('COUNT(*)'), 'total_reportes')
			->selectAlias($qb->createFunction('COUNT(DISTINCT id_empleado)'), 'empleados_con_reportes')
			->selectAlias($qb->createFunction('COUNT(DISTINCT id_cliente)'), 'proyectos_activos')
			->selectAlias($qb->createFunction('COUNT(DISTINCT id_actividad)'), 'actividades')
			->from($this->getTableName());

		$this->aplicarFiltroPeriodo($qb, $periodo_inicio, $periodo_fin, $anio);

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
	public function getHorasPorEmpleado($periodo_inicio = null, $periodo_fin = null, $anio = null): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('id_empleado')
			->selectAlias($qb->createFunction('SUM(tiempo_registrado)'), 'total_minutos')
			->selectAlias($qb->createFunction('COUNT(*)'), 'total_reportes')
			->from($this->getTableName())
			->groupBy('id_empleado')
			->orderBy('total_minutos', 'DESC');

		$this->aplicarFiltroPeriodo($qb, $periodo_inicio, $periodo_fin, $anio);

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
	public function getHorasPorProyecto($periodo_inicio = null, $periodo_fin = null, $anio = null): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('id_cliente')
			->selectAlias($qb->createFunction('SUM(tiempo_registrado)'), 'total_minutos')
			->selectAlias($qb->createFunction('COUNT(*)'), 'total_reportes')
			->from($this->getTableName())
			->groupBy('id_cliente')
			->orderBy('total_minutos', 'DESC');

		$this->aplicarFiltroPeriodo($qb, $periodo_inicio, $periodo_fin, $anio);

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
	public function getHorasPorActividad($periodo_inicio = null, $periodo_fin = null, $anio = null): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('id_actividad')
			->selectAlias($qb->createFunction('SUM(tiempo_registrado)'), 'total_minutos')
			->selectAlias($qb->createFunction('COUNT(*)'), 'total_reportes')
			->from($this->getTableName())
			->groupBy('id_actividad')
			->orderBy('total_minutos', 'DESC');

		$this->aplicarFiltroPeriodo($qb, $periodo_inicio, $periodo_fin, $anio);

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
	public function getHorasPorDia($periodo_inicio = null, $periodo_fin = null, $anio = null): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('fecha_registro')
			->selectAlias($qb->createFunction('SUM(tiempo_registrado)'), 'total_minutos')
			->selectAlias($qb->createFunction('COUNT(*)'), 'total_reportes')
			->from($this->getTableName())
			->groupBy('fecha_registro')
			->orderBy('fecha_registro', 'ASC');

		$this->aplicarFiltroPeriodo($qb, $periodo_inicio, $periodo_fin, $anio);

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
	public function getReportesPorDia($periodo_inicio = null, $periodo_fin = null, $anio = null): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('fecha_registro')
			->selectAlias($qb->createFunction('COUNT(*)'), 'total_reportes')
			->from($this->getTableName())
			->groupBy('fecha_registro')
			->orderBy('fecha_registro', 'ASC');

		$this->aplicarFiltroPeriodo($qb, $periodo_inicio, $periodo_fin, $anio);

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
	public function getProyectoVsActividad($periodo_inicio = null, $periodo_fin = null, $anio = null): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('id_cliente', 'id_actividad')
			->selectAlias($qb->createFunction('SUM(tiempo_registrado)'), 'total_minutos')
			->selectAlias($qb->createFunction('COUNT(*)'), 'total_reportes')
			->from($this->getTableName())
			->groupBy('id_cliente')
			->addGroupBy('id_actividad')
			->orderBy('id_cliente', 'ASC');

		$this->aplicarFiltroPeriodo($qb, $periodo_inicio, $periodo_fin, $anio);

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
}