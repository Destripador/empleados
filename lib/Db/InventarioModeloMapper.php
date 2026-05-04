<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;

class InventarioModeloMapper extends QBMapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'inventario_modelos', InventarioModelo::class);
	}

	public function findAll(?string $search = null, ?string $tipo = null): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->orderBy('marca', 'ASC')
			->addOrderBy('modelo', 'ASC');

		if ($search !== null && trim($search) !== '') {
			$like = '%' . $this->db->escapeLikeParameter(trim($search)) . '%';

			$qb->andWhere(
				$qb->expr()->orX(
					$qb->expr()->iLike('marca', $qb->createNamedParameter($like)),
					$qb->expr()->iLike('modelo', $qb->createNamedParameter($like)),
					$qb->expr()->iLike('procesador', $qb->createNamedParameter($like)),
					$qb->expr()->iLike('ram', $qb->createNamedParameter($like)),
					$qb->expr()->iLike('disco_duro', $qb->createNamedParameter($like))
				)
			);
		}

		if ($tipo !== null && trim($tipo) !== '') {
			$qb->andWhere(
				$qb->expr()->eq('tipo', $qb->createNamedParameter($tipo))
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
				$qb->expr()->eq('id_modelo', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			)
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		return $row ?: null;
	}

	public function create(array $data): int {
		$now = date('Y-m-d H:i:s');

		$qb = $this->db->getQueryBuilder();

		$qb->insert($this->getTableName())
			->values([
				'marca' => $qb->createNamedParameter($data['marca'] ?? null),
				'modelo' => $qb->createNamedParameter($data['modelo'] ?? null),
				'procesador' => $qb->createNamedParameter($data['procesador'] ?? null),
				'ram' => $qb->createNamedParameter($data['ram'] ?? null),
				'disco_duro' => $qb->createNamedParameter($data['disco_duro'] ?? null),
				'tipo' => $qb->createNamedParameter($data['tipo'] ?? null),
				'touch' => $qb->createNamedParameter(!empty($data['touch']) ? 1 : 0, IQueryBuilder::PARAM_INT),
				'created_at' => $qb->createNamedParameter($now),
				'updated_at' => $qb->createNamedParameter($now),
			]);

		$qb->executeStatement();

		return (int) $this->db->lastInsertId($this->getTableName());
	}

	public function updateById(int $id, array $data): void {
		$qb = $this->db->getQueryBuilder();

		$qb->update($this->getTableName())
			->set('marca', $qb->createNamedParameter($data['marca'] ?? null))
			->set('modelo', $qb->createNamedParameter($data['modelo'] ?? null))
			->set('procesador', $qb->createNamedParameter($data['procesador'] ?? null))
			->set('ram', $qb->createNamedParameter($data['ram'] ?? null))
			->set('disco_duro', $qb->createNamedParameter($data['disco_duro'] ?? null))
			->set('tipo', $qb->createNamedParameter($data['tipo'] ?? null))
			->set('touch', $qb->createNamedParameter(!empty($data['touch']) ? 1 : 0, IQueryBuilder::PARAM_INT))
			->set('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->where(
				$qb->expr()->eq('id_modelo', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);

		$qb->executeStatement();
	}

	public function deleteById(int $id): void {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq('id_modelo', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);

		$qb->executeStatement();
	}
}