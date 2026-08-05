<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;

class boardingMapper extends QBMapper {

	public function __construct(
		IDBConnection $db
	) {
		parent::__construct(
			$db,
			'boarding_catalogo',
			boarding::class
		);

		$this->primaryKey = 'id_boarding';
	}

	/**
	 * Obtener registro del catálogo por ID
	 */
	public function findById(int $id): array {

		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq(
					'id_boarding',
					$qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)
				)
			);

		$result = $qb->executeQuery();
		$data = $result->fetch();
		$result->closeCursor();

		return $data ?: [];
	}

	/**
	 * Obtener todo el catálogo
	 */
	public function findAll(): array {

		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->orderBy('nombre', 'ASC');

		$result = $qb->executeQuery();
		$data = $result->fetchAll();
		$result->closeCursor();

		return $data;
	}

	/**
	 * Obtener catálogo filtrado por on
	 */
	public function findByOn(int $on): array {

		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq(
					'on',
					$qb->createNamedParameter($on, IQueryBuilder::PARAM_INT)
				)
			)
			->orderBy('nombre', 'ASC');

		$result = $qb->executeQuery();
		$data = $result->fetchAll();
		$result->closeCursor();

		return $data;
	}

	/**
	 * Verificar si ya existe un registro con ese nombre (evitar duplicados en el catálogo)
	 */
	public function existeNombre(string $nombre, int $on): bool {

		$qb = $this->db->getQueryBuilder();

		$qb->select(
				$qb->createFunction('COUNT(*)')
			)
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('nombre', $qb->createNamedParameter($nombre))
			)
			->andWhere(
				$qb->expr()->eq('on', $qb->createNamedParameter($on, IQueryBuilder::PARAM_INT))
			);

		return (int)$qb->executeQuery()->fetchOne() > 0;
	}

	/**
	 * Crear un ítem del catálogo
	 */
	public function createBoarding(
		string $nombre,
		int $on = 1
	): boarding {

		$boarding = new boarding();

		$boarding->setNombre($nombre);
		$boarding->setOn($on);

		$this->insert($boarding);

		return $boarding;
	}

	/**
	 * Actualizar nombre / tipo
	 */
	public function updateBoarding(
		int $id_boarding,
		string $nombre,
		int $on
	): void {

		$qb = $this->db->getQueryBuilder();

		$qb->update($this->getTableName())
			->set('nombre', $qb->createNamedParameter($nombre))
			->set('on', $qb->createNamedParameter($on, IQueryBuilder::PARAM_INT))
			->where(
				$qb->expr()->eq(
					'id_boarding',
					$qb->createNamedParameter($id_boarding, IQueryBuilder::PARAM_INT)
				)
			);

		$qb->executeStatement();
	}

	/**
	 * Eliminar un ítem del catálogo.
	 */
	public function deleteById(int $id): void {

		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq(
					'id_boarding',
					$qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)
				)
			);

		$qb->executeStatement();
	}
}