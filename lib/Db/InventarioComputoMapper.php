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

		$qb->select(
				'c.id_equipo',
				'c.id_empleado',
				'c.id_modelo',
				'c.nombre_dispositivo',
				'c.nombre_sistema',
				'c.numero_serie',
				'c.estado',
				'c.info',
				'c.created_at',
				'c.updated_at',
				'm.marca',
				'm.modelo',
				'm.procesador',
				'm.ram',
				'm.disco_duro',
				'm.tipo'
			)
			->from($this->getTableName(), 'c')
			->leftJoin('c', 'inventario_modelos', 'm', $qb->expr()->eq('m.id_modelo', 'c.id_modelo'))
			->orderBy('c.id_equipo', 'DESC');

		if ($search !== null && trim($search) !== '') {
			$like = '%' . $this->db->escapeLikeParameter(trim($search)) . '%';

			$qb->andWhere(
				$qb->expr()->orX(
					$qb->expr()->iLike('c.nombre_dispositivo', $qb->createNamedParameter($like)),
					$qb->expr()->iLike('c.nombre_sistema', $qb->createNamedParameter($like)),
					$qb->expr()->iLike('c.numero_serie', $qb->createNamedParameter($like)),
					$qb->expr()->iLike('m.marca', $qb->createNamedParameter($like)),
					$qb->expr()->iLike('m.modelo', $qb->createNamedParameter($like))
				)
			);
		}

		if ($estado !== null && trim($estado) !== '') {
			$qb->andWhere(
				$qb->expr()->eq('c.estado', $qb->createNamedParameter($estado))
			);
		}

		if ($idEmpleado !== null) {
			$qb->andWhere(
				$qb->expr()->eq('c.id_empleado', $qb->createNamedParameter($idEmpleado, IQueryBuilder::PARAM_INT))
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
}