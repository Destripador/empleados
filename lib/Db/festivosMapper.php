<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;

class festivosMapper extends QBMapper {

	public function __construct(
		IDBConnection $db
	) {
		parent::__construct(
			$db,
			'empleados_festivos',
			festivos::class
		);

		$this->primaryKey = 'id_festivo';
	}

	/**
	 * Obtener festivo por ID
	 */
	public function findById(int $id): array {

		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq(
					'id_festivo',
					$qb->createNamedParameter(
						$id,
						IQueryBuilder::PARAM_INT
					)
				)
			);

		$result = $qb->executeQuery();
		$data = $result->fetch();
		$result->closeCursor();

		return $data ?: [];
	}

	/**
	 * Obtener todos los festivos
	 */
	public function findAll(): array {

		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->orderBy('fecha', 'ASC');

		$result = $qb->executeQuery();
		$data = $result->fetchAll();
		$result->closeCursor();

		return $data;
	}

	/**
	 * Obtener festivo por fecha
	 */
	public function findByFecha(string $fecha): array {

		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq(
					'fecha',
					$qb->createNamedParameter($fecha)
				)
			);

		$result = $qb->executeQuery();
		$data = $result->fetch();
		$result->closeCursor();

		return $data ?: [];
	}

	/**
	 * Obtener festivos de un año
	 */
	public function findByYear(int $year): array {

		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->like(
					'fecha',
					$qb->createNamedParameter($year . '-%')
				)
			)
			->orderBy('fecha', 'ASC');

		$result = $qb->executeQuery();
		$data = $result->fetchAll();
		$result->closeCursor();

		return $data;
	}

	/**
	 * Verificar si existe un festivo en la fecha indicada
	 */
	public function existeFecha(string $fecha): bool {

		$qb = $this->db->getQueryBuilder();

		$qb->select(
				$qb->createFunction('COUNT(*)')
			)
			->from($this->getTableName())
			->where(
				$qb->expr()->eq(
					'fecha',
					$qb->createNamedParameter($fecha)
				)
			);

		return (int)$qb->executeQuery()->fetchOne() > 0;
	}

	/**
	 * Crear festivo
	 */
	public function createFestivo(
		string $nombre,
		string $fecha
	): festivos {

		$festivo = new festivos();

		$festivo->setNombre($nombre);
		$festivo->setFecha($fecha);

		$this->insert($festivo);

		return $festivo;
	}

	/**
	 * Actualizar festivo
	 */
	public function updateFestivo(
		int $id_festivo,
		string $nombre,
		string $fecha
	): void {

		$qb = $this->db->getQueryBuilder();

		$qb->update($this->getTableName())
			->set(
				'nombre',
				$qb->createNamedParameter($nombre)
			)
			->set(
				'fecha',
				$qb->createNamedParameter($fecha)
			)
			->where(
				$qb->expr()->eq(
					'id_festivo',
					$qb->createNamedParameter(
						$id_festivo,
						IQueryBuilder::PARAM_INT
					)
				)
			);

		$qb->executeStatement();
	}

	/**
	 * Eliminar festivo
	 */
	public function deleteById(int $id): void {

		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq(
					'id_festivo',
					$qb->createNamedParameter(
						$id,
						IQueryBuilder::PARAM_INT
					)
				)
			);

		$qb->executeStatement();
	}
}