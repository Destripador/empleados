<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class MantenimientoEquipoMapper extends QBMapper {
	private const TABLE = 'inv_mantenimientos';
	private const WRITABLE_FIELDS = [
		'id_grupo', 'id_equipo', 'equipo_nombre', 'equipo_identificador',
		'id_modelo', 'modelo_nombre', 'numero_serie', 'id_empleado',
		'empleado_uid', 'empleado_nombre', 'id_departamento', 'departamento_nombre',
		'tecnico_uid', 'tecnico_nombre', 'tipo', 'fecha_programada',
		'hora_inicio_programada', 'hora_fin_programada', 'fecha_inicio_real',
		'fecha_fin_real', 'estado', 'resultado', 'acciones_realizadas',
		'incidencias', 'repuestos', 'observaciones', 'proxima_fecha', 'creado_por',
		'actualizado_por', 'fecha_creacion', 'fecha_actualizacion',
	];

	public function __construct(IDBConnection $db) {
		parent::__construct($db, self::TABLE, MantenimientoEquipo::class);
	}

	public function insertMaintenance(array $data): MantenimientoEquipo {
		$estado = (string)($data['estado'] ?? MantenimientoEquipo::ESTADO_PENDING);
		$this->assertValidStatus($estado);
		$now = date('Y-m-d H:i:s');
		$data['estado'] = $estado;
		$data['fecha_creacion'] ??= $now;
		$data['fecha_actualizacion'] ??= $now;
		$data['actualizado_por'] ??= $data['creado_por'] ?? '';

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

	/** The caller owns the transaction so group, rows, checks and audit can be atomic. */
	public function insertMany(array $rows): array {
		return array_map(fn(array $row): MantenimientoEquipo => $this->insertMaintenance($row), $rows);
	}

	public function findById(int $id): MantenimientoEquipo {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from(self::TABLE)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1);
		return $this->findEntity($qb);
	}

	public function findByGroup(int $groupId): array {
		return $this->findPageByGroup($groupId, 500, 0);
	}

	public function findPageByGroup(int $groupId, int $limit = 50, int $offset = 0): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from(self::TABLE)
			->where($qb->expr()->eq('id_grupo', $qb->createNamedParameter($groupId, IQueryBuilder::PARAM_INT)))
			->orderBy('fecha_programada', 'ASC')->addOrderBy('hora_inicio_programada', 'ASC')->addOrderBy('id', 'ASC')
			->setMaxResults(max(1, min(500, $limit)))->setFirstResult(max(0, $offset));
		return $this->findEntities($qb);
	}

	public function countByGroup(int $groupId): int {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select($qb->createFunction('COUNT(*)'))->from(self::TABLE)
			->where($qb->expr()->eq('id_grupo', $qb->createNamedParameter($groupId, IQueryBuilder::PARAM_INT)))
			->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();
		return $count;
	}

	public function findPageByGroupFiltered(int $groupId, ?string $status, ?string $technicianUid, ?string $search, int $limit, int $offset): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from(self::TABLE)
			->where($qb->expr()->eq('id_grupo', $qb->createNamedParameter($groupId, IQueryBuilder::PARAM_INT)))
			->orderBy('fecha_programada', 'ASC')->addOrderBy('hora_inicio_programada', 'ASC')->addOrderBy('id', 'ASC')
			->setMaxResults($limit)->setFirstResult($offset);
		$this->applyGroupFilters($qb, $status, $technicianUid, $search);
		return $this->findEntities($qb);
	}

	public function countByGroupFiltered(int $groupId, ?string $status, ?string $technicianUid, ?string $search): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('COUNT(*)'))->from(self::TABLE)
			->where($qb->expr()->eq('id_grupo', $qb->createNamedParameter($groupId, IQueryBuilder::PARAM_INT)));
		$this->applyGroupFilters($qb, $status, $technicianUid, $search);
		$result = $qb->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();
		return $count;
	}

	public function technicianHasGroupAccess(int $groupId, string $technicianUid): bool {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('id')->from(self::TABLE)
			->where($qb->expr()->eq('id_grupo', $qb->createNamedParameter($groupId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('tecnico_uid', $qb->createNamedParameter($technicianUid)))
			->setMaxResults(1)->executeQuery();
		$exists = $result->fetchOne() !== false;
		$result->closeCursor();
		return $exists;
	}

	public function technicianHasEquipmentAccess(int $equipmentId, string $technicianUid): bool {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('id')->from(self::TABLE)
			->where($qb->expr()->eq('id_equipo', $qb->createNamedParameter($equipmentId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('tecnico_uid', $qb->createNamedParameter($technicianUid)))
			->setMaxResults(1)->executeQuery();
		$exists = $result->fetchOne() !== false;
		$result->closeCursor();
		return $exists;
	}

	public function findHistoryByEquipment(int $equipmentId, int $limit = 50, int $offset = 0): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from(self::TABLE)
			->where($qb->expr()->eq('id_equipo', $qb->createNamedParameter($equipmentId, IQueryBuilder::PARAM_INT)))
			->orderBy('fecha_programada', 'DESC')->addOrderBy('id', 'DESC')
			->setMaxResults(max(1, min(500, $limit)))->setFirstResult(max(0, $offset));
		return $this->findEntities($qb);
	}

	public function findHistoryFiltered(int $equipmentId, ?string $type, ?string $status, ?string $from, ?string $to, int $limit, int $offset): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from(self::TABLE)
			->where($qb->expr()->eq('id_equipo', $qb->createNamedParameter($equipmentId, IQueryBuilder::PARAM_INT)))
			->orderBy('fecha_programada', 'DESC')->addOrderBy('id', 'DESC')
			->setMaxResults($limit)->setFirstResult($offset);
		$this->applyHistoryFilters($qb, $type, $status, $from, $to);
		return $this->findEntities($qb);
	}

	public function countHistoryFiltered(int $equipmentId, ?string $type, ?string $status, ?string $from, ?string $to): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('COUNT(*)'))->from(self::TABLE)
			->where($qb->expr()->eq('id_equipo', $qb->createNamedParameter($equipmentId, IQueryBuilder::PARAM_INT)));
		$this->applyHistoryFilters($qb, $type, $status, $from, $to);
		$result = $qb->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();
		return $count;
	}

	public function findActiveByEquipment(int $equipmentId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from(self::TABLE)
			->where($qb->expr()->eq('id_equipo', $qb->createNamedParameter($equipmentId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->in('estado', $this->statusParameters($qb, MantenimientoEquipo::ESTADOS_ACTIVOS)))
			->orderBy('fecha_programada', 'ASC')->addOrderBy('id', 'ASC');
		return $this->findEntities($qb);
	}

	public function findPossibleDuplicates(int $equipmentId, string $type, string $from, string $to): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('m.*')->from(self::TABLE, 'm')
			->innerJoin('m', 'inv_mant_grupos', 'g', $qb->expr()->eq('g.id', 'm.id_grupo'))
			->where($qb->expr()->eq('m.id_equipo', $qb->createNamedParameter($equipmentId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('m.tipo', $qb->createNamedParameter($type)))
			->andWhere($qb->expr()->in('m.estado', $this->statusParameters($qb, MantenimientoEquipo::ESTADOS_ACTIVOS)))
			->andWhere($qb->expr()->lte($qb->createFunction('COALESCE(g.fecha_inicio, m.fecha_programada)'), $qb->createNamedParameter($to)))
			->andWhere($qb->expr()->gte($qb->createFunction('COALESCE(g.fecha_fin, m.fecha_programada)'), $qb->createNamedParameter($from)))
			->orderBy('m.fecha_programada', 'ASC')->addOrderBy('m.id', 'ASC');
		return $this->findEntities($qb);
	}

	public function updateTechnician(int $id, ?string $uid, ?string $name, string $updatedBy): MantenimientoEquipo {
		return $this->updateFields($id, ['tecnico_uid' => $uid, 'tecnico_nombre' => $name], $updatedBy);
	}

	public function updateSchedule(int $id, string $date, ?string $start, ?string $end, string $updatedBy): MantenimientoEquipo {
		return $this->updateFields($id, [
			'fecha_programada' => $date,
			'hora_inicio_programada' => $start,
			'hora_fin_programada' => $end,
		], $updatedBy);
	}

	public function updateStatus(int $id, string $status, string $updatedBy, ?string $startedAt = null, ?string $finishedAt = null): MantenimientoEquipo {
		$this->assertValidStatus($status);
		$data = ['estado' => $status];
		if ($startedAt !== null) $data['fecha_inicio_real'] = $startedAt;
		if ($finishedAt !== null) $data['fecha_fin_real'] = $finishedAt;
		return $this->updateFields($id, $data, $updatedBy);
	}

	public function updateStatusIfCurrent(int $id, string $expectedStatus, string $status, string $updatedBy, ?string $startedAt = null, ?string $finishedAt = null): ?MantenimientoEquipo {
		$this->assertValidStatus($expectedStatus);
		$this->assertValidStatus($status);
		$data = ['estado' => $status];
		if ($startedAt !== null) $data['fecha_inicio_real'] = $startedAt;
		if ($finishedAt !== null) $data['fecha_fin_real'] = $finishedAt;
		return $this->updateFieldsIfCurrent($id, $expectedStatus, $data, $updatedBy);
	}

	public function rescheduleIfCurrent(int $id, string $expectedStatus, string $date, ?string $start, ?string $end, string $updatedBy): ?MantenimientoEquipo {
		return $this->updateFieldsIfCurrent($id, $expectedStatus, [
			'fecha_programada' => $date,
			'hora_inicio_programada' => $start,
			'hora_fin_programada' => $end,
			'estado' => MantenimientoEquipo::ESTADO_RESCHEDULED,
		], $updatedBy);
	}

	public function scheduleIfCurrent(int $id, string $expectedStatus, string $date, ?string $start, ?string $end, string $updatedBy): ?MantenimientoEquipo {
		return $this->updateFieldsIfCurrent($id, $expectedStatus, [
			'fecha_programada' => $date,
			'hora_inicio_programada' => $start,
			'hora_fin_programada' => $end,
			'estado' => MantenimientoEquipo::ESTADO_SCHEDULED,
		], $updatedBy);
	}

	public function updateTechnicianIfCurrent(int $id, string $expectedStatus, ?string $uid, ?string $name, string $updatedBy): ?MantenimientoEquipo {
		return $this->updateFieldsIfCurrent($id, $expectedStatus, [
			'tecnico_uid' => $uid,
			'tecnico_nombre' => $name,
		], $updatedBy);
	}

	public function saveResult(int $id, array $result, string $updatedBy): MantenimientoEquipo {
		$allowed = ['resultado', 'acciones_realizadas', 'incidencias', 'repuestos', 'observaciones', 'proxima_fecha'];
		return $this->updateFields($id, array_intersect_key($result, array_flip($allowed)), $updatedBy);
	}

	public function saveResultIfCurrent(int $id, string $expectedStatus, array $result, string $updatedBy): ?MantenimientoEquipo {
		$allowed = ['resultado', 'acciones_realizadas', 'incidencias', 'repuestos', 'observaciones', 'proxima_fecha'];
		return $this->updateFieldsIfCurrent($id, $expectedStatus, array_intersect_key($result, array_flip($allowed)), $updatedBy);
	}

	public function findByDateRange(string $from, string $to, ?int $departmentId = null, ?string $technicianUid = null, ?string $type = null, ?string $status = null, int $limit = 100, int $offset = 0): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from(self::TABLE)
			->where($qb->expr()->gte('fecha_programada', $qb->createNamedParameter($from)))
			->andWhere($qb->expr()->lte('fecha_programada', $qb->createNamedParameter($to)))
			->orderBy('fecha_programada', 'ASC')->addOrderBy('hora_inicio_programada', 'ASC')->addOrderBy('id', 'ASC')
			->setMaxResults(max(1, min(500, $limit)))->setFirstResult(max(0, $offset));
		if ($departmentId !== null) $qb->andWhere($qb->expr()->eq('id_departamento', $qb->createNamedParameter($departmentId, IQueryBuilder::PARAM_INT)));
		if ($technicianUid !== null && trim($technicianUid) !== '') $qb->andWhere($qb->expr()->eq('tecnico_uid', $qb->createNamedParameter(trim($technicianUid))));
		if ($type !== null && trim($type) !== '') $qb->andWhere($qb->expr()->eq('tipo', $qb->createNamedParameter(trim($type))));
		if ($status !== null && trim($status) !== '') {
			$this->assertValidStatus($status);
			$qb->andWhere($qb->expr()->eq('estado', $qb->createNamedParameter($status)));
		}
		return $this->findEntities($qb);
	}

	public function findOverdue(string $today, int $limit = 100, int $offset = 0): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from(self::TABLE)
			->where($qb->expr()->lt('fecha_programada', $qb->createNamedParameter($today)))
			->andWhere($qb->expr()->in('estado', $this->statusParameters($qb, MantenimientoEquipo::ESTADOS_ACTIVOS)))
			->orderBy('fecha_programada', 'ASC')->addOrderBy('id', 'ASC')
			->setMaxResults(max(1, min(500, $limit)))->setFirstResult(max(0, $offset));
		return $this->findEntities($qb);
	}

	public function findOverdueFiltered(string $today, ?int $departmentId, ?string $technicianUid, ?string $type, int $limit, int $offset): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from(self::TABLE)
			->where($qb->expr()->lt('fecha_programada', $qb->createNamedParameter($today)))
			->andWhere($qb->expr()->in('estado', $this->statusParameters($qb, MantenimientoEquipo::ESTADOS_ACTIVOS)))
			->orderBy('fecha_programada', 'ASC')->addOrderBy('id', 'ASC')
			->setMaxResults($limit)->setFirstResult($offset);
		$this->applyMaintenanceFilters($qb, $departmentId, $technicianUid, $type);
		return $this->findEntities($qb);
	}

	public function countOverdueFiltered(string $today, ?int $departmentId, ?string $technicianUid, ?string $type): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('COUNT(*)'))->from(self::TABLE)
			->where($qb->expr()->lt('fecha_programada', $qb->createNamedParameter($today)))
			->andWhere($qb->expr()->in('estado', $this->statusParameters($qb, MantenimientoEquipo::ESTADOS_ACTIVOS)));
		$this->applyMaintenanceFilters($qb, $departmentId, $technicianUid, $type);
		$result = $qb->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();
		return $count;
	}

	private function updateFields(int $id, array $data, string $updatedBy): MantenimientoEquipo {
		$qb = $this->db->getQueryBuilder();
		$qb->update(self::TABLE);
		foreach ($data as $field => $value) {
			if (in_array($field, self::WRITABLE_FIELDS, true)) {
				$qb->set($field, $qb->createNamedParameter($value));
			}
		}
		$qb->set('actualizado_por', $qb->createNamedParameter($updatedBy))
			->set('fecha_actualizacion', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->executeStatement();
		return $this->findById($id);
	}

	private function updateFieldsIfCurrent(int $id, string $expectedStatus, array $data, string $updatedBy): ?MantenimientoEquipo {
		$qb = $this->db->getQueryBuilder();
		$qb->update(self::TABLE);
		foreach ($data as $field => $value) {
			if (in_array($field, self::WRITABLE_FIELDS, true)) {
				$qb->set($field, $qb->createNamedParameter($value));
			}
		}
		$affected = $qb->set('actualizado_por', $qb->createNamedParameter($updatedBy))
			->set('fecha_actualizacion', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('estado', $qb->createNamedParameter($expectedStatus)))
			->executeStatement();
		return $affected === 1 ? $this->findById($id) : null;
	}

	private function statusParameters(IQueryBuilder $qb, array $statuses): array {
		return array_map(fn(string $status) => $qb->createNamedParameter($status), $statuses);
	}

	private function applyGroupFilters(IQueryBuilder $qb, ?string $status, ?string $technicianUid, ?string $search): void {
		if ($status !== null && trim($status) !== '') {
			$this->assertValidStatus($status);
			$qb->andWhere($qb->expr()->eq('estado', $qb->createNamedParameter(trim($status))));
		}
		if ($technicianUid !== null && trim($technicianUid) !== '') $qb->andWhere($qb->expr()->eq('tecnico_uid', $qb->createNamedParameter(trim($technicianUid))));
		if ($search !== null && trim($search) !== '') {
			$like = '%' . $this->db->escapeLikeParameter(trim($search)) . '%';
			$qb->andWhere($qb->expr()->orX(
				$qb->expr()->iLike('equipo_nombre', $qb->createNamedParameter($like)),
				$qb->expr()->iLike('equipo_identificador', $qb->createNamedParameter($like)),
				$qb->expr()->iLike('modelo_nombre', $qb->createNamedParameter($like)),
			));
		}
	}

	private function applyMaintenanceFilters(IQueryBuilder $qb, ?int $departmentId, ?string $technicianUid, ?string $type): void {
		if ($departmentId !== null) $qb->andWhere($qb->expr()->eq('id_departamento', $qb->createNamedParameter($departmentId, IQueryBuilder::PARAM_INT)));
		if ($technicianUid !== null && trim($technicianUid) !== '') $qb->andWhere($qb->expr()->eq('tecnico_uid', $qb->createNamedParameter(trim($technicianUid))));
		if ($type !== null && trim($type) !== '') $qb->andWhere($qb->expr()->eq('tipo', $qb->createNamedParameter(trim($type))));
	}

	private function applyHistoryFilters(IQueryBuilder $qb, ?string $type, ?string $status, ?string $from, ?string $to): void {
		if ($type !== null && trim($type) !== '') $qb->andWhere($qb->expr()->eq('tipo', $qb->createNamedParameter(trim($type))));
		if ($status !== null && trim($status) !== '') {
			$this->assertValidStatus($status);
			$qb->andWhere($qb->expr()->eq('estado', $qb->createNamedParameter(trim($status))));
		}
		if ($from !== null) $qb->andWhere($qb->expr()->gte('fecha_programada', $qb->createNamedParameter($from)));
		if ($to !== null) $qb->andWhere($qb->expr()->lte('fecha_programada', $qb->createNamedParameter($to)));
	}

	private function assertValidStatus(string $status): void {
		if (!in_array($status, MantenimientoEquipo::ESTADOS_VALIDOS, true)) {
			throw new \InvalidArgumentException('Estado de mantenimiento inválido.');
		}
	}
}
