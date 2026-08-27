<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class MonedaMapper extends QBMapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'empleados_monedas', Moneda::class);
	}

	/** @return Moneda[] */
	public function findAll(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())->orderBy('tipo_moneda', 'ASC');
		return $this->findEntities($qb);
	}

	public function find(int $id): Moneda {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)));
		return $this->findEntity($qb);
	}

	public function existeTipo(string $tipoMoneda, ?int $exceptoId = null): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from($this->getTableName())
			->where($qb->expr()->eq('tipo_moneda', $qb->createNamedParameter($tipoMoneda)));
		if ($exceptoId !== null) {
			$qb->andWhere($qb->expr()->neq('id', $qb->createNamedParameter($exceptoId, \PDO::PARAM_INT)));
		}
		return $qb->executeQuery()->fetchOne() !== false;
	}
}