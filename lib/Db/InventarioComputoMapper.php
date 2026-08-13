<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;

class InventarioComputoMapper extends QBMapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'inventario_computo', InventarioComputo::class);
	}

	public function findAll(
		?string $search = null,
		?string $estado = null,
		?int $idEmpleado = null,
		?string $asignacion = null,
		?int $idModelo = null,
		?int $limit = 25,
		int $offset = 0
	): array {
		$qb = $this->db->getQueryBuilder();

		$this->selectEquipoDetalle($qb)
			->from($this->getTableName(), 'c')
			->leftJoin('c', 'inventario_modelos', 'm', $qb->expr()->eq('m.id_modelo', 'c.id_modelo'))
			->leftJoin('c', 'empleados', 'e', $qb->expr()->eq('c.id_empleado', 'e.Id_empleados'))
			->leftJoin('e', 'users', 'u', $qb->expr()->eq('u.uid', 'e.Id_user'))
			->orderBy('c.id_equipo', 'DESC')
			->setFirstResult($offset);
		if ($limit !== null) {
			$qb->setMaxResults($limit);
		}

		$this->applyEquipoFilters($qb, $search, $estado, $idEmpleado, $asignacion, $idModelo);

		$result = $qb->executeQuery();
		$data = $result->fetchAll();
		$result->closeCursor();

		return $data;
	}

	public function countAll(?string $search = null, ?string $estado = null, ?int $idEmpleado = null, ?string $asignacion = null, ?int $idModelo = null): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('COUNT(DISTINCT c.id_equipo)'))
			->from($this->getTableName(), 'c')
			->leftJoin('c', 'inventario_modelos', 'm', $qb->expr()->eq('m.id_modelo', 'c.id_modelo'))
			->leftJoin('c', 'empleados', 'e', $qb->expr()->eq('c.id_empleado', 'e.Id_empleados'))
			->leftJoin('e', 'users', 'u', $qb->expr()->eq('u.uid', 'e.Id_user'));

		$this->applyEquipoFilters($qb, $search, $estado, $idEmpleado, $asignacion, $idModelo);
		$result = $qb->executeQuery();
		$total = (int)$result->fetchOne();
		$result->closeCursor();

		return $total;
	}

	public function findAssignedEmployees(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->selectDistinct('e.Id_empleados AS id_empleado')
			->addSelect('e.Id_user AS uid', 'u.displayname AS displayname')
			->from('empleados', 'e')
			->innerJoin('e', $this->getTableName(), 'c', $qb->expr()->eq('c.id_empleado', 'e.Id_empleados'))
			->leftJoin('e', 'users', 'u', $qb->expr()->eq('u.uid', 'e.Id_user'))
			->orderBy('u.displayname', 'ASC')
			->addOrderBy('e.Id_user', 'ASC');

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return array_map(static fn(array $row): array => [
			'id_empleado' => (int)$row['id_empleado'],
			'uid' => (string)$row['uid'],
			'displayname' => (string)($row['displayname'] ?: $row['uid']),
		], $rows);
	}

	private function selectEquipoDetalle(IQueryBuilder $qb): IQueryBuilder {
		return $qb->selectAlias('c.id_equipo', 'id_equipo')
			->selectAlias('c.id_empleado', 'id_empleado')
			->selectAlias('c.id_modelo', 'id_modelo')
			->selectAlias('c.nombre_dispositivo', 'nombre_dispositivo')
			->selectAlias('c.nombre_sistema', 'nombre_sistema')
			->selectAlias('c.numero_serie', 'numero_serie')
			->selectAlias('c.estado', 'estado')
			->selectAlias('c.info', 'info')
			->selectAlias('c.created_at', 'created_at')
			->selectAlias('c.updated_at', 'updated_at')
			->selectAlias('m.marca', 'marca')
			->selectAlias('m.modelo', 'modelo')
			->selectAlias('m.procesador', 'procesador')
			->selectAlias('m.ram', 'ram')
			->selectAlias('m.disco_duro', 'disco_duro')
			->selectAlias('m.tipo', 'tipo')
			->selectAlias('e.Id_empleados', 'empleado_id')
			->selectAlias('e.Id_user', 'empleado_uid')
			->selectAlias('e.Numero_empleado', 'numero_empleado')
			->selectAlias('u.displayname', 'empleado_displayname');
	}

	private function applyEquipoFilters(IQueryBuilder $qb, ?string $search, ?string $estado, ?int $idEmpleado, ?string $asignacion, ?int $idModelo): void {
		if ($search !== null && trim($search) !== '') {
			$like = '%' . $this->db->escapeLikeParameter(trim($search)) . '%';

			$qb->andWhere(
				$qb->expr()->orX(
					$qb->expr()->iLike('c.nombre_dispositivo', $qb->createNamedParameter($like, IQueryBuilder::PARAM_STR)),
					$qb->expr()->iLike('c.nombre_sistema', $qb->createNamedParameter($like, IQueryBuilder::PARAM_STR)),
					$qb->expr()->iLike('c.numero_serie', $qb->createNamedParameter($like, IQueryBuilder::PARAM_STR)),
					$qb->expr()->iLike('m.marca', $qb->createNamedParameter($like, IQueryBuilder::PARAM_STR)),
					$qb->expr()->iLike('m.modelo', $qb->createNamedParameter($like, IQueryBuilder::PARAM_STR)),
					$qb->expr()->iLike('e.Id_user', $qb->createNamedParameter($like, IQueryBuilder::PARAM_STR)),
					$qb->expr()->iLike('u.displayname', $qb->createNamedParameter($like, IQueryBuilder::PARAM_STR)),
					$qb->expr()->iLike('e.Numero_empleado', $qb->createNamedParameter($like, IQueryBuilder::PARAM_STR))
				)
			);
		}

		if ($estado !== null && trim($estado) !== '') {
			$qb->andWhere(
				$qb->expr()->eq('c.estado', $qb->createNamedParameter($estado, IQueryBuilder::PARAM_STR))
			);
		}

		if ($idEmpleado !== null) {
			$qb->andWhere(
				$qb->expr()->eq('c.id_empleado', $qb->createNamedParameter($idEmpleado, IQueryBuilder::PARAM_INT))
			);
		}

		if ($asignacion === 'asignado') {
			$qb->andWhere($qb->expr()->isNotNull('c.id_empleado'));
		} elseif ($asignacion === 'sin_asignar') {
			$qb->andWhere($qb->expr()->isNull('c.id_empleado'));
		}

		if ($idModelo !== null) {
			$qb->andWhere($qb->expr()->eq('c.id_modelo', $qb->createNamedParameter($idModelo, IQueryBuilder::PARAM_INT)));
		}
	}

	public function findById(int $id): ?array {
		$qb = $this->db->getQueryBuilder();

		$this->selectEquipoDetalle($qb)
			->from($this->getTableName(), 'c')
			->leftJoin('c', 'inventario_modelos', 'm', $qb->expr()->eq('m.id_modelo', 'c.id_modelo'))
			->leftJoin('c', 'empleados', 'e', $qb->expr()->eq('c.id_empleado', 'e.Id_empleados'))
			->leftJoin('e', 'users', 'u', $qb->expr()->eq('u.uid', 'e.Id_user'))
			->where(
				$qb->expr()->eq('c.id_equipo', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			)
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		return $row ?: null;
	}

	public function findByEmpleado(int $idEmpleado): array {
		return $this->findAll(null, null, $idEmpleado, null, null, null, 0);
	}

	public function create(array $data): int {
		$now = date('Y-m-d H:i:s');

		$qb = $this->db->getQueryBuilder();

		$qb->insert($this->getTableName())
			->values([
				'id_empleado' => $qb->createNamedParameter($data['id_empleado'] ?? null, IQueryBuilder::PARAM_INT),
				'id_modelo' => $qb->createNamedParameter($data['id_modelo'] ?? null, IQueryBuilder::PARAM_INT),
				'nombre_dispositivo' => $qb->createNamedParameter($data['nombre_dispositivo'] ?? null),
				'nombre_sistema' => $qb->createNamedParameter($data['nombre_sistema'] ?? null),
				'numero_serie' => $qb->createNamedParameter($data['numero_serie'] ?? null),
				'estado' => $qb->createNamedParameter($data['estado'] ?? 'activo'),
				'info' => $qb->createNamedParameter($data['info'] ?? null),
				'created_at' => $qb->createNamedParameter($now),
				'updated_at' => $qb->createNamedParameter($now),
			]);

		$qb->executeStatement();

		return (int) $this->db->lastInsertId($this->getTableName());
	}

	public function updateById(int $id, array $data): void {
		$qb = $this->db->getQueryBuilder();

		$qb->update($this->getTableName())
			->set('id_empleado', $qb->createNamedParameter($data['id_empleado'] ?? null, IQueryBuilder::PARAM_INT))
			->set('id_modelo', $qb->createNamedParameter($data['id_modelo'] ?? null, IQueryBuilder::PARAM_INT))
			->set('nombre_dispositivo', $qb->createNamedParameter($data['nombre_dispositivo'] ?? null))
			->set('nombre_sistema', $qb->createNamedParameter($data['nombre_sistema'] ?? null))
			->set('numero_serie', $qb->createNamedParameter($data['numero_serie'] ?? null))
			->set('estado', $qb->createNamedParameter($data['estado'] ?? 'activo'))
			->set('info', $qb->createNamedParameter($data['info'] ?? null))
			->set('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->where(
				$qb->expr()->eq('id_equipo', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);

		$qb->executeStatement();
	}

	public function updateEmpleado(int $idEquipo, ?int $idEmpleado, ?int $expectedEmpleado): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('id_empleado', $qb->createNamedParameter($idEmpleado, IQueryBuilder::PARAM_INT))
			->set('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->where($qb->expr()->eq('id_equipo', $qb->createNamedParameter($idEquipo, IQueryBuilder::PARAM_INT)));
		if ($expectedEmpleado === null) {
			$qb->andWhere($qb->expr()->isNull('id_empleado'));
		} else {
			$qb->andWhere($qb->expr()->eq('id_empleado', $qb->createNamedParameter($expectedEmpleado, IQueryBuilder::PARAM_INT)));
		}

		return $qb->executeStatement() === 1;
	}

	public function deleteById(int $id): void {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq('id_equipo', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);

		$qb->executeStatement();
	}

	public function findAllForSelect(?int $idEmpleado = null, bool $onlyAvailable = true): array {
		$qb = $this->db->getQueryBuilder();

		$qb->selectAlias('c.id_equipo', 'id_equipo')
			->selectAlias('c.nombre_dispositivo', 'nombre_dispositivo')
			->selectAlias('c.nombre_sistema', 'nombre_sistema')
			->selectAlias('c.numero_serie', 'numero_serie')
			->selectAlias('c.estado', 'estado')
			->selectAlias('m.marca', 'marca')
			->selectAlias('m.modelo', 'modelo')
			->selectAlias('c.id_empleado', 'id_empleado')
			->selectAlias('e.Id_empleados', 'empleado_id')
			->selectAlias('e.Id_user', 'empleado_uid')
			->selectAlias('e.Numero_empleado', 'numero_empleado')
			->selectAlias('u.displayname', 'empleado_displayname')
			->from($this->getTableName(), 'c')
			->leftJoin(
				'c',
				'inventario_modelos',
				'm',
				$qb->expr()->eq('m.id_modelo', 'c.id_modelo')
			)
			->leftJoin(
				'c',
				'empleados',
				'e',
				$qb->expr()->eq('c.id_empleado', 'e.Id_empleados')
			)
			->leftJoin('e', 'users', 'u', $qb->expr()->eq('u.uid', 'e.Id_user'))
			->orderBy('c.nombre_dispositivo', 'ASC');

		$qb->andWhere(
			$qb->expr()->orX(
				$qb->expr()->isNull('c.estado'),
				$qb->expr()->notIn(
					'c.estado',
					[
						$qb->createNamedParameter('baja', IQueryBuilder::PARAM_STR),
						$qb->createNamedParameter('inactivo', IQueryBuilder::PARAM_STR),
						$qb->createNamedParameter('inactive', IQueryBuilder::PARAM_STR),
					]
				)
			)
		);

		if ($onlyAvailable) {
			$availableOrCurrent = $qb->expr()->orX(
				$qb->expr()->isNull('c.id_empleado')
			);

			if ($idEmpleado !== null && $idEmpleado > 0) {
				$availableOrCurrent->add(
					$qb->expr()->eq(
						'c.id_empleado',
						$qb->createNamedParameter($idEmpleado, IQueryBuilder::PARAM_INT)
					)
				);
			}

			$qb->andWhere($availableOrCurrent);
		}

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return array_map(function (array $row): array {
			$modelo = trim(($row['marca'] ?? '') . ' ' . ($row['modelo'] ?? ''));

			$labelParts = array_filter([
				$row['nombre_dispositivo'] ?? '',
				$row['nombre_sistema'] ?? '',
				$row['numero_serie'] ?? '',
				$modelo,
			]);

			$label = implode(' - ', $labelParts);

			if ($label === '') {
				$label = 'Equipo #' . ($row['id_equipo'] ?? '');
			}

			if (!empty($row['empleado_uid'])) {
				$label .= ' — asignado a ' . $row['empleado_uid'];
			}

			return [
				'value' => (int)$row['id_equipo'],
				'label' => $label,
				'id_equipo' => (int)$row['id_equipo'],
				'nombre_dispositivo' => $row['nombre_dispositivo'] ?? '',
				'nombre_sistema' => $row['nombre_sistema'] ?? '',
				'numero_serie' => $row['numero_serie'] ?? '',
				'estado' => $row['estado'] ?? '',
				'marca' => $row['marca'] ?? '',
				'modelo' => $row['modelo'] ?? '',
				'empleado_id' => $row['empleado_id'] ?? null,
				'empleado_uid' => $row['empleado_uid'] ?? null,
				'empleado_displayname' => $row['empleado_displayname'] ?? null,
				'numero_empleado' => $row['numero_empleado'] ?? null,
			];
		}, $rows);
	}

	/**
	 * Equipos cuyo custodio actual pertenece al departamento indicado.
	 * Los descendientes solo se consideran cuando se solicitan explícitamente.
	 */
	public function findByDepartamento(
		int $idDepartamento,
		?string $search = null,
		bool $includeInactive = false,
		bool $includeDescendants = false,
		array $descendantIds = [],
		int $limit = 25,
		int $offset = 0,
	): array {
		$qb = $this->db->getQueryBuilder();
		$completed = $qb->createNamedParameter(MantenimientoEquipo::ESTADO_COMPLETED);
		$active = $this->maintenanceStatusParameters($qb);
		$this->selectDepartamentoCampaignFields($qb)
			->selectAlias(
				$qb->createFunction("MAX(CASE WHEN im.estado = $completed THEN im.fecha_programada ELSE NULL END)"),
				'ultimo_mantenimiento',
			)
			->selectAlias(
				$qb->createFunction('MIN(CASE WHEN im.estado IN (' . implode(', ', $active) . ') THEN im.fecha_programada ELSE NULL END)'),
				'proximo_mantenimiento_activo',
			)
			->from($this->getTableName(), 'c')
			->innerJoin('c', 'empleados', 'e', $qb->expr()->eq('c.id_empleado', 'e.Id_empleados'))
			->leftJoin('e', 'users', 'u', $qb->expr()->eq('u.uid', 'e.Id_user'))
			->innerJoin('e', 'departamentos', 'd', $qb->expr()->eq('d.Id_departamento', 'e.Id_departamento'))
			->leftJoin('c', 'inventario_modelos', 'm', $qb->expr()->eq('m.id_modelo', 'c.id_modelo'))
			->leftJoin('c', 'inv_mantenimientos', 'im', $qb->expr()->eq('im.id_equipo', 'c.id_equipo'))
			->groupBy(
				'c.id_equipo', 'c.nombre_dispositivo', 'c.nombre_sistema', 'c.id_modelo',
				'm.modelo', 'm.marca', 'c.numero_serie', 'c.estado', 'c.id_empleado',
				'e.Id_user', 'u.displayname', 'e.Id_departamento', 'd.Nombre',
			)
			->orderBy('c.nombre_dispositivo', 'ASC')->addOrderBy('c.id_equipo', 'ASC')
			->setMaxResults(max(1, min(500, $limit)))->setFirstResult(max(0, $offset));

		$this->applyDepartamentoCampaignFilters(
			$qb,
			$idDepartamento,
			$search,
			$includeInactive,
			$includeDescendants,
			$descendantIds,
		);

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();
		return $rows;
	}

	public function countByDepartamento(
		int $idDepartamento,
		?string $search = null,
		bool $includeInactive = false,
		bool $includeDescendants = false,
		array $descendantIds = [],
	): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('COUNT(DISTINCT c.id_equipo)'))
			->from($this->getTableName(), 'c')
			->innerJoin('c', 'empleados', 'e', $qb->expr()->eq('c.id_empleado', 'e.Id_empleados'))
			->leftJoin('e', 'users', 'u', $qb->expr()->eq('u.uid', 'e.Id_user'))
			->innerJoin('e', 'departamentos', 'd', $qb->expr()->eq('d.Id_departamento', 'e.Id_departamento'))
			->leftJoin('c', 'inventario_modelos', 'm', $qb->expr()->eq('m.id_modelo', 'c.id_modelo'));

		$this->applyDepartamentoCampaignFilters(
			$qb,
			$idDepartamento,
			$search,
			$includeInactive,
			$includeDescendants,
			$descendantIds,
		);

		$result = $qb->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();
		return $count;
	}

	/** Returns current database-backed snapshots for explicitly selected equipment. */
	public function findCampaignEquipmentByIds(array $equipmentIds): array {
		$ids = array_values(array_unique(array_filter(
			array_map('intval', $equipmentIds),
			static fn(int $id): bool => $id > 0,
		)));
		if ($ids === []) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->selectAlias('c.id_equipo', 'id_equipo')
			->selectAlias('c.nombre_dispositivo', 'nombre_dispositivo')
			->selectAlias('c.nombre_sistema', 'nombre_sistema')
			->selectAlias('c.id_modelo', 'id_modelo')
			->selectAlias('m.modelo', 'modelo')
			->selectAlias('m.marca', 'marca')
			->selectAlias('c.numero_serie', 'numero_serie')
			->selectAlias('c.estado', 'estado')
			->selectAlias('c.id_empleado', 'id_empleado')
			->selectAlias('e.Id_user', 'empleado_uid')
			->selectAlias('u.displayname', 'empleado_nombre')
			->selectAlias('e.Id_departamento', 'id_departamento')
			->selectAlias('d.Nombre', 'departamento_nombre')
			->from($this->getTableName(), 'c')
			->leftJoin('c', 'inventario_modelos', 'm', $qb->expr()->eq('m.id_modelo', 'c.id_modelo'))
			->leftJoin('c', 'empleados', 'e', $qb->expr()->eq('c.id_empleado', 'e.Id_empleados'))
			->leftJoin('e', 'users', 'u', $qb->expr()->eq('u.uid', 'e.Id_user'))
			->leftJoin('e', 'departamentos', 'd', $qb->expr()->eq('d.Id_departamento', 'e.Id_departamento'))
			->where($qb->expr()->in('c.id_equipo', array_map(
				fn(int $id) => $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT),
				$ids,
			)))
			->orderBy('c.id_equipo', 'ASC');

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();
		return $rows;
	}

	private function selectDepartamentoCampaignFields(IQueryBuilder $qb): IQueryBuilder {
		return $qb->selectAlias('c.id_equipo', 'id_equipo')
			->selectAlias('c.nombre_dispositivo', 'nombre_dispositivo')
			->selectAlias('c.nombre_sistema', 'nombre_sistema')
			->selectAlias('c.id_modelo', 'id_modelo')
			->selectAlias('m.modelo', 'modelo')
			->selectAlias('m.marca', 'marca')
			->selectAlias('c.numero_serie', 'numero_serie')
			->selectAlias('c.estado', 'estado')
			->selectAlias('c.id_empleado', 'id_empleado')
			->selectAlias('e.Id_user', 'empleado_uid')
			->selectAlias('u.displayname', 'empleado_nombre')
			->selectAlias('e.Id_departamento', 'id_departamento')
			->selectAlias('d.Nombre', 'departamento_nombre');
	}

	private function applyDepartamentoCampaignFilters(
		IQueryBuilder $qb,
		int $idDepartamento,
		?string $search,
		bool $includeInactive,
		bool $includeDescendants,
		array $descendantIds,
	): void {
		$departmentIds = [$idDepartamento];
		if ($includeDescendants) {
			$departmentIds = array_values(array_unique(array_merge(
				$departmentIds,
				array_filter(array_map('intval', $descendantIds), static fn(int $id): bool => $id > 0),
			)));
		}
		$qb->andWhere($qb->expr()->in(
			'e.Id_departamento',
			array_map(fn(int $id) => $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT), $departmentIds),
		));

		if (!$includeInactive) {
			$excluded = ['baja', 'inactivo', 'inactive', 'eliminado', 'deleted'];
			$qb->andWhere($qb->expr()->orX(
				$qb->expr()->isNull('c.estado'),
				$qb->expr()->notIn('c.estado', array_map(
					fn(string $state) => $qb->createNamedParameter($state),
					$excluded,
				)),
			));
		}

		if ($search !== null && trim($search) !== '') {
			$like = '%' . $this->db->escapeLikeParameter(trim($search)) . '%';
			$qb->andWhere($qb->expr()->orX(
				$qb->expr()->iLike('c.nombre_dispositivo', $qb->createNamedParameter($like)),
				$qb->expr()->iLike('c.nombre_sistema', $qb->createNamedParameter($like)),
				$qb->expr()->iLike('c.numero_serie', $qb->createNamedParameter($like)),
				$qb->expr()->iLike('m.modelo', $qb->createNamedParameter($like)),
				$qb->expr()->iLike('m.marca', $qb->createNamedParameter($like)),
				$qb->expr()->iLike('e.Id_user', $qb->createNamedParameter($like)),
				$qb->expr()->iLike('u.displayname', $qb->createNamedParameter($like)),
				$qb->expr()->iLike('d.Nombre', $qb->createNamedParameter($like)),
			));
		}
	}

	private function maintenanceStatusParameters(IQueryBuilder $qb): array {
		return array_map(
			fn(string $status) => $qb->createNamedParameter($status),
			MantenimientoEquipo::ESTADOS_ACTIVOS,
		);
	}
}
