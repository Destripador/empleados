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

	public function findAll(?string $search = null, ?string $estado = null, ?int $idEmpleado = null): array {
		$qb = $this->db->getQueryBuilder();

		$qb->selectAlias('c.id_equipo', 'id_equipo')
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
				$qb->expr()->eq('e.Equipo_asignado', 'c.id_equipo')
			)
			->orderBy('c.id_equipo', 'DESC');

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
				$qb->expr()->eq('e.Id_empleados', $qb->createNamedParameter($idEmpleado, IQueryBuilder::PARAM_INT))
			);
		}

		$result = $qb->executeQuery();
		$data = $result->fetchAll();
		$result->closeCursor();

		return $data;
	}

	public function findById(int $id): ?array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('id_equipo', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			)
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		return $row ?: null;
	}

	public function findByEmpleado(int $idEmpleado): array {
		return $this->findAll(null, null, $idEmpleado);
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

	public function deleteById(int $id): void {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq('id_equipo', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);

		$qb->executeStatement();
	}

	public function findAllForSelect(?int $currentEquipoId = null, bool $onlyAvailable = true): array {
		$qb = $this->db->getQueryBuilder();

		$qb->selectAlias('c.id_equipo', 'id_equipo')
			->selectAlias('c.nombre_dispositivo', 'nombre_dispositivo')
			->selectAlias('c.nombre_sistema', 'nombre_sistema')
			->selectAlias('c.numero_serie', 'numero_serie')
			->selectAlias('c.estado', 'estado')
			->selectAlias('m.marca', 'marca')
			->selectAlias('m.modelo', 'modelo')
			->selectAlias('e.Id_empleados', 'empleado_id')
			->selectAlias('e.Id_user', 'empleado_uid')
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
				$qb->expr()->eq('e.Equipo_asignado', 'c.id_equipo')
			)
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
				$qb->expr()->isNull('e.Id_empleados')
			);

			if ($currentEquipoId !== null && $currentEquipoId > 0) {
				$availableOrCurrent->add(
					$qb->expr()->eq(
						'c.id_equipo',
						$qb->createNamedParameter($currentEquipoId, IQueryBuilder::PARAM_INT)
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
			];
		}, $rows);
	}
}