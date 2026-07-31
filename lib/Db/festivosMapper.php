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
	 * Obtener festivo por fecha (MM-DD)
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
	 * Verificar si existe un festivo en la fecha indicada (MM-DD)
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
	 * Indica si un id de festivo corresponde a un festivo oficial (no editable/borrable).
	 * Devuelve false también si el id no existe, para que el controller decida el 404.
	 */
	public function esOficial(int $id): bool {

		$qb = $this->db->getQueryBuilder();

		$qb->select('oficial')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq(
					'id_festivo',
					$qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)
				)
			);

		$result = $qb->executeQuery();
		$oficial = $result->fetchOne();
		$result->closeCursor();

		return $oficial !== false && (int)$oficial === 1;
	}

	/**
	 * Crear festivo.
	 *
	 * Para festivos fijos (creados a mano por HR/empresa) solo se necesitan
	 * $nombre y $fecha; el resto de parámetros son para el seeding de los
	 * festivos oficiales (fijos u variables) desde la migración.
	 */
	public function createFestivo(
		string $nombre,
		string $fecha,
		string $tipo = 'fijo',
		int $oficial = 0,
		?int $reglaMes = null,
		?int $reglaSemana = null,
		?int $reglaDiaSemana = null,
		?int $anioCalculado = null
	): festivos {

		$festivo = new festivos();

		$festivo->setNombre($nombre);
		$festivo->setFecha($fecha);
		$festivo->setTipo($tipo);
		$festivo->setOficial($oficial);
		$festivo->setReglaMes($reglaMes);
		$festivo->setReglaSemana($reglaSemana);
		$festivo->setReglaDiaSemana($reglaDiaSemana);
		$festivo->setAnioCalculado($anioCalculado);

		$this->insert($festivo);

		return $festivo;
	}

	/**
	 * Actualizar festivo (nombre/fecha). Pensado solo para festivos NO oficiales;
	 * el controller es responsable de verificar esOficial() antes de llamar esto.
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
	 * Eliminar festivo. El controller debe verificar esOficial() antes de llamar esto.
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

	/**
	 * Eliminar todos los festivos NO oficiales.
	 * Los oficiales se preservan porque el job de recálculo depende de ellos
	 * y volverlos a sembrar requeriría re-ejecutar la migración.
	 */
	public function deleteAll(): void {

		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq('oficial', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT))
			);

		$qb->executeStatement();
	}

	/**
	 * Festivos de tipo 'variable' (su fecha depende del año, ej. "tercer lunes de marzo").
	 * Usado por el background job para recalcular la fecha cada año.
	 */
	public function findVariables(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('tipo', $qb->createNamedParameter('variable')));
		return $qb->executeQuery()->fetchAll();
	}

	public function actualizarFechaCalculada(int $id, string $fecha, int $anio): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('fecha', $qb->createNamedParameter($fecha))
			->set('anio_calculado', $qb->createNamedParameter($anio))
			->where($qb->expr()->eq('id_festivo', $qb->createNamedParameter($id)));
		$qb->executeStatement();
	}
}