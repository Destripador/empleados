<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class InventarioMovimientoMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'inv_movimientos', InventarioMovimiento::class);
	}

	public function findByEquipo(int $idEquipo, int $limit, int $offset): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('id_equipo', $qb->createNamedParameter($idEquipo, IQueryBuilder::PARAM_INT)))
			->orderBy('fecha', 'DESC')->addOrderBy('id', 'DESC')
			->setMaxResults($limit)->setFirstResult($offset);

		return $this->findEntities($qb);
	}

	public function countByEquipo(int $idEquipo): int {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select($qb->createFunction('COUNT(*)'))->from($this->getTableName())
			->where($qb->expr()->eq('id_equipo', $qb->createNamedParameter($idEquipo, IQueryBuilder::PARAM_INT)))
			->executeQuery();
		$total = (int)$result->fetchOne();
		$result->closeCursor();

		return $total;
	}
}
