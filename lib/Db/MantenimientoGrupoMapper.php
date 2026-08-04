<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class MantenimientoGrupoMapper extends QBMapper {
	private const TABLE = 'inv_mant_grupos';

	private const WRITABLE_FIELDS = [
		'titulo',
		'id_departamento',
		'departamento_nombre',
		'tipo',
		'fecha_programada',
		'fecha_inicio',
		'fecha_fin',
		'hora_inicio',
		'hora_fin',
		'tecnico_uid',
		'tecnico_nombre',
		'estado_admin',
		'descripcion',
		'creado_por',
		'fecha_creacion',
		'fecha_actualizacion',
	];

	public function __construct(IDBConnection $db) {
		parent::__construct($db, self::TABLE, MantenimientoGrupo::class);
	}

	public function insertGroup(array $data): MantenimientoGrupo {
		$estado = (string)($data['estado_admin'] ?? MantenimientoGrupo::ESTADO_ACTIVE);
		$this->assertValidAdminStatus($estado);
		$now = date('Y-m-d H:i:s');
		$data['estado_admin'] = $estado;
		$data['fecha_creacion'] ??= $now;
		$data['fecha_actualizacion'] ??= $now;

		$qb = $this->db->getQueryBuilder();
		$values = [];
		foreach (self::WRITABLE_FIELDS as $field) {
			if (array_key_exists($field, $data)) {
				$values[$field] = $qb->createNamedParameter($data[$field]);
			}
		}
		$qb->insert(self::TABLE)->values($values)->executeStatement();

		return $this->findById((int)$this->db->lastInsertId(self::TABLE));
	}

	public function findById(int $id): MantenimientoGrupo {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from(self::TABLE)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1);

		return $this->findEntity($qb);
	}

	public function updateBasic(int $id, array $data): MantenimientoGrupo {
		$allowed = array_diff(self::WRITABLE_FIELDS, ['creado_por', 'fecha_creacion', 'estado_admin']);
		$qb = $this->db->getQueryBuilder();
		$qb->update(self::TABLE);
		foreach ($allowed as $field) {
			if (array_key_exists($field, $data)) {
				$qb->set($field, $qb->createNamedParameter($data[$field]));
			}
		}
		$qb->set('fecha_actualizacion', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->executeStatement();

		return $this->findById($id);
	}

	public function cancel(int $id): bool {
		$qb = $this->db->getQueryBuilder();
		return $qb->update(self::TABLE)
			->set('estado_admin', $qb->createNamedParameter(MantenimientoGrupo::ESTADO_CANCELLED))
			->set('fecha_actualizacion', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('estado_admin', $qb->createNamedParameter(MantenimientoGrupo::ESTADO_ACTIVE)))
			->executeStatement() === 1;
	}

	public function findByDateRange(
		string $from,
		string $to,
		?int $departmentId = null,
		?string $technicianUid = null,
		?string $type = null,
		?string $adminStatus = null,
		int $limit = 100,
		int $offset = 0,
	): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from(self::TABLE)
			->where($qb->expr()->lte($qb->createFunction('COALESCE(fecha_inicio, fecha_programada)'), $qb->createNamedParameter($to)))
			->andWhere($qb->expr()->gte($qb->createFunction('COALESCE(fecha_fin, fecha_programada)'), $qb->createNamedParameter($from)))
			->orderBy('fecha_inicio', 'ASC')->addOrderBy('hora_inicio', 'ASC')->addOrderBy('id', 'ASC')
			->setMaxResults(max(1, min(500, $limit)))->setFirstResult(max(0, $offset));
		if ($departmentId !== null) {
			$qb->andWhere($qb->expr()->eq('id_departamento', $qb->createNamedParameter($departmentId, IQueryBuilder::PARAM_INT)));
		}
		if ($technicianUid !== null && trim($technicianUid) !== '') {
			$qb->andWhere($qb->expr()->eq('tecnico_uid', $qb->createNamedParameter(trim($technicianUid))));
		}
		if ($type !== null && trim($type) !== '') {
			$qb->andWhere($qb->expr()->eq('tipo', $qb->createNamedParameter(trim($type))));
		}
		if ($adminStatus !== null && trim($adminStatus) !== '') {
			$this->assertValidAdminStatus($adminStatus);
			$qb->andWhere($qb->expr()->eq('estado_admin', $qb->createNamedParameter($adminStatus)));
		}

		return $this->findEntities($qb);
	}

	public function findCalendarPage(
		string $from,
		string $to,
		?int $departmentId,
		?string $technicianUid,
		?string $type,
		?string $adminStatus,
		?string $search,
		int $limit,
		int $offset,
	): array {
		$qb = $this->db->getQueryBuilder();
		$qb->selectDistinct('g.id')->addSelect(
			'g.titulo', 'g.id_departamento', 'g.departamento_nombre', 'g.tipo',
			'g.fecha_programada', 'g.fecha_inicio', 'g.fecha_fin', 'g.hora_inicio', 'g.hora_fin', 'g.tecnico_uid',
			'g.tecnico_nombre', 'g.estado_admin', 'g.descripcion', 'g.creado_por',
			'g.fecha_creacion', 'g.fecha_actualizacion',
		)->from(self::TABLE, 'g')
			->where($qb->expr()->lte($qb->createFunction('COALESCE(g.fecha_inicio, g.fecha_programada)'), $qb->createNamedParameter($to)))
			->andWhere($qb->expr()->gte($qb->createFunction('COALESCE(g.fecha_fin, g.fecha_programada)'), $qb->createNamedParameter($from)))
			->orderBy('g.fecha_inicio', 'ASC')->addOrderBy('g.hora_inicio', 'ASC')->addOrderBy('g.id', 'ASC')
			->setMaxResults($limit)->setFirstResult($offset);
		$this->applyCalendarFilters($qb, $departmentId, $technicianUid, $type, $adminStatus, $search);
		return $this->findEntities($qb);
	}

	public function countCalendar(
		string $from,
		string $to,
		?int $departmentId,
		?string $technicianUid,
		?string $type,
		?string $adminStatus,
		?string $search,
	): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('COUNT(DISTINCT g.id)'))->from(self::TABLE, 'g')
			->where($qb->expr()->lte($qb->createFunction('COALESCE(g.fecha_inicio, g.fecha_programada)'), $qb->createNamedParameter($to)))
			->andWhere($qb->expr()->gte($qb->createFunction('COALESCE(g.fecha_fin, g.fecha_programada)'), $qb->createNamedParameter($from)));
		$this->applyCalendarFilters($qb, $departmentId, $technicianUid, $type, $adminStatus, $search);
		$result = $qb->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();
		return $count;
	}

	public function getProgress(int $groupId, ?string $today = null): array {
		$today ??= date('Y-m-d');
		$qb = $this->db->getQueryBuilder();
		$todayParam = $qb->createNamedParameter($today);
		$activeParams = array_map(
			fn(string $status) => $qb->createNamedParameter($status),
			MantenimientoEquipo::ESTADOS_ACTIVOS,
		);
		$qb->selectAlias($qb->createFunction('COUNT(*)'), 'total');
		foreach (MantenimientoEquipo::ESTADOS_VALIDOS as $status) {
			$statusParam = $qb->createNamedParameter($status);
			$qb->selectAlias(
				$qb->createFunction("COALESCE(SUM(CASE WHEN estado = $statusParam THEN 1 ELSE 0 END), 0)"),
				$status,
			);
		}
		$qb->selectAlias(
			$qb->createFunction('COALESCE(SUM(CASE WHEN fecha_programada < ' . $todayParam . ' AND estado IN (' . implode(', ', $activeParams) . ') THEN 1 ELSE 0 END), 0)'),
			'overdue',
		)->from('inv_mantenimientos')
			->where($qb->expr()->eq('id_grupo', $qb->createNamedParameter($groupId, IQueryBuilder::PARAM_INT)));

		$result = $qb->executeQuery();
		$row = $result->fetch() ?: [];
		$result->closeCursor();

		$progress = [];
		foreach (array_merge(['total'], MantenimientoEquipo::ESTADOS_VALIDOS, ['overdue']) as $field) {
			$progress[$field] = (int)($row[$field] ?? 0);
		}

		return $progress;
	}

	private function assertValidAdminStatus(string $status): void {
		if (!in_array($status, MantenimientoGrupo::ESTADOS_VALIDOS, true)) {
			throw new \InvalidArgumentException('Estado administrativo de mantenimiento inválido.');
		}
	}

	private function applyCalendarFilters(IQueryBuilder $qb, ?int $departmentId, ?string $technicianUid, ?string $type, ?string $adminStatus, ?string $search): void {
		if ($technicianUid !== null && trim($technicianUid) !== '') {
			$qb->innerJoin('g', 'inv_mantenimientos', 'im_access', $qb->expr()->andX(
				$qb->expr()->eq('im_access.id_grupo', 'g.id'),
				$qb->expr()->eq('im_access.tecnico_uid', $qb->createNamedParameter(trim($technicianUid))),
			));
		}
		if ($departmentId !== null) $qb->andWhere($qb->expr()->eq('g.id_departamento', $qb->createNamedParameter($departmentId, IQueryBuilder::PARAM_INT)));
		if ($type !== null && trim($type) !== '') $qb->andWhere($qb->expr()->eq('g.tipo', $qb->createNamedParameter(trim($type))));
		if ($adminStatus !== null && trim($adminStatus) !== '') {
			$this->assertValidAdminStatus($adminStatus);
			$qb->andWhere($qb->expr()->eq('g.estado_admin', $qb->createNamedParameter(trim($adminStatus))));
		}
		if ($search !== null && trim($search) !== '') {
			$like = '%' . $this->db->escapeLikeParameter(trim($search)) . '%';
			$qb->andWhere($qb->expr()->orX(
				$qb->expr()->iLike('g.titulo', $qb->createNamedParameter($like)),
				$qb->expr()->iLike('g.departamento_nombre', $qb->createNamedParameter($like)),
				$qb->expr()->iLike('g.tecnico_nombre', $qb->createNamedParameter($like)),
			));
		}
	}
}
