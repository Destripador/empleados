<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\QueryBuilder\IQueryBuilder;

class clientesMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'empleados_clientes', Cliente::class);
	}

	/** Básico: obtener por ID */
	/** @throws DoesNotExistException|MultipleObjectsReturnedException */
	public function findById(int $id): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('id_cliente', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);

		$result = $qb->executeQuery();
		$data = $result->fetchAll();
		$result->closeCursor();

		return $data;
	}

	/** Listado simple */
	/** @return Cliente[] */
	public function findAll(?int $limit = null, int $offset = 0): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(
				'p.id_cliente',
				'p.nombre',
				'p.detalles',
				'p.cliente_padre',
				$qb->createFunction('COUNT(c.id_cliente) AS child_count')
			)
			->from($this->getTableName(), 'p')
			->leftJoin('p', $this->getTableName(), 'c',
				$qb->expr()->eq('c.cliente_padre', 'p.id_cliente')
			)
			->groupBy('p.id_cliente', 'p.nombre', 'p.detalles', 'p.cliente_padre')
			->orderBy('p.id_cliente', 'ASC')
			->setMaxResults($limit)
			->setFirstResult($offset);

		$result = $qb->executeQuery();
		$data = $result->fetchAll();
		$result->closeCursor();

		return $data;
	}

	/** Borrado rápido por ID */
	public function deleteById(int $id): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq('id_cliente', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);

		$qb->executeStatement();
	}

	public function updateClientes(int $id_clientes, string $nombre, ?string $detalles, ?int $cliente_padre): void {
		$query = $this->db->getQueryBuilder();
		$query->update($this->getTableName())
			->set('nombre', $query->createNamedParameter($nombre))
			->set('detalles', $query->createNamedParameter($detalles))
			->set('cliente_padre', $query->createNamedParameter($cliente_padre))
			->where(
				$query->expr()->eq('id_cliente', $query->createNamedParameter($id_clientes, IQueryBuilder::PARAM_INT))
			);

		$query->executeStatement();
	}
}