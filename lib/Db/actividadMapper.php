<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\QueryBuilder\IQueryBuilder;

class actividadMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'empleados_actividades', Actividad::class);
	}

	/** @throws DoesNotExistException|MultipleObjectsReturnedException */
	public function findById(int $id): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('id_actividad', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);

		$result = $qb->executeQuery();
		$data = $result->fetchAll();
		$result->closeCursor();

		return $data;
	}

	/** @return Actividad[] */
	public function findAll(?int $limit = null, int $offset = 0): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->orderBy('id_actividad', 'DESC')
			->setMaxResults($limit)
			->setFirstResult($offset);

		$result = $qb->executeQuery();
		$data = $result->fetchAll();
		$result->closeCursor();

		return $data;
	}

	public function deleteById(int $id): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq('id_actividad', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);

		$qb->executeStatement();
	}

	public function updateActividad(int $id_actividad, string $nombre, string $detalles, float $tiempoestimado): void {
		$query = $this->db->getQueryBuilder();
		$query->update($this->getTableName())
			->set('nombre', $query->createNamedParameter($nombre))
			->set('detalles', $query->createNamedParameter($detalles))
			->set('tiempo_estimado', $query->createNamedParameter($tiempoestimado))
			->where(
				$query->expr()->eq('id_actividad', $query->createNamedParameter($id_actividad, IQueryBuilder::PARAM_INT))
			);

		$query->executeStatement();
	}
}