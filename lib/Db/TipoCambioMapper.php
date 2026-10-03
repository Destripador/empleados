<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\DB\Exception as DBException;

class TipoCambioMapper extends QBMapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'empleados_tipo_cambio', TipoCambio::class);
	}

	/** @return TipoCambio[] */
	public function findByRango(int $idMoneda, string $fechaInicio, string $fechaFin): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id_moneda', $qb->createNamedParameter($idMoneda, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->gte('fecha', $qb->createNamedParameter($fechaInicio)))
			->andWhere($qb->expr()->lte('fecha', $qb->createNamedParameter($fechaFin)))
			->orderBy('fecha', 'ASC');
		return $this->findEntities($qb);
	}

	public function existeFecha(int $idMoneda, string $fecha): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from($this->getTableName())
			->where($qb->expr()->eq('id_moneda', $qb->createNamedParameter($idMoneda, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('fecha', $qb->createNamedParameter($fecha)));
		return $qb->executeQuery()->fetchOne() !== false;
	}

	/**
	 * Intenta insertar; si ya existe (choque del índice único
	 * empl_tc_moneda_fecha_uidx) actualiza el valor en su lugar.
	 */
	public function upsert(int $idMoneda, string $fecha, float $valor): void {
		$qb = $this->db->getQueryBuilder();
		$qb->insert($this->getTableName())
			->values([
				'id_moneda' => $qb->createNamedParameter($idMoneda, IQueryBuilder::PARAM_INT),
				'fecha' => $qb->createNamedParameter($fecha),
				'valor' => $qb->createNamedParameter($valor),
			]);
		try {
			$qb->executeStatement();
		} catch (DBException $e) {
			// Solo lo tratamos como "ya existe" si es realmente un choque
			// de llave única; cualquier otro error de BD se vuelve a lanzar.
			if ($e->getReason() !== DBException::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
				throw $e;
			}

			$update = $this->db->getQueryBuilder();
			$update->update($this->getTableName())
				->set('valor', $update->createNamedParameter($valor))
				->where($update->expr()->eq('id_moneda', $update->createNamedParameter($idMoneda, IQueryBuilder::PARAM_INT)))
				->andWhere($update->expr()->eq('fecha', $update->createNamedParameter($fecha)))
				->executeStatement();
		}
	}

	/**
	 * Busca el tipo de cambio del día hábil ANTERIOR a $fecha
	 */
	public function findAnterior(int $idMoneda, string $fecha): ?TipoCambio {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('id_moneda', $qb->createNamedParameter($idMoneda, IQueryBuilder::PARAM_INT))
			)
			->andWhere(
				$qb->expr()->lt('fecha', $qb->createNamedParameter($fecha))
			)
			->orderBy('fecha', 'DESC')
			->setMaxResults(1);

		try {
			return $this->findEntity($qb);
		} catch (\OCP\AppFramework\Db\DoesNotExistException $e) {
			return null;
		}
	}
}