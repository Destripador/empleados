<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;

class SoporteHistorialMapper extends QBMapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'soporte_historial', SoporteHistorial::class);
	}

	public function findByEquipo(int $idEquipo): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('id_equipo', $qb->createNamedParameter($idEquipo, IQueryBuilder::PARAM_INT))
			)
			->orderBy('fecha', 'DESC')
			->addOrderBy('id_soporte', 'DESC');

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
				$qb->expr()->eq('id_soporte', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
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
				'id_equipo' => $qb->createNamedParameter($data['id_equipo'], IQueryBuilder::PARAM_INT),
				'accion' => $qb->createNamedParameter($data['accion'] ?? null),
				'detalles' => $qb->createNamedParameter($data['detalles'] ?? null),
				'fecha' => $qb->createNamedParameter($data['fecha'] ?? $now),
				'usuario_actual' => $qb->createNamedParameter($data['usuario_actual'] ?? null),
				'usuario_soporte' => $qb->createNamedParameter($data['usuario_soporte'] ?? null),
				'created_at' => $qb->createNamedParameter($now),
				'updated_at' => $qb->createNamedParameter($now),
			]);

		$qb->executeStatement();

		return (int) $this->db->lastInsertId($this->getTableName());
	}

	public function updateById(int $id, array $data): void {
		$qb = $this->db->getQueryBuilder();

		$qb->update($this->getTableName())
			->set('accion', $qb->createNamedParameter($data['accion'] ?? null))
			->set('detalles', $qb->createNamedParameter($data['detalles'] ?? null))
			->set('fecha', $qb->createNamedParameter($data['fecha'] ?? null))
			->set('usuario_actual', $qb->createNamedParameter($data['usuario_actual'] ?? null))
			->set('usuario_soporte', $qb->createNamedParameter($data['usuario_soporte'] ?? null))
			->set('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->where(
				$qb->expr()->eq('id_soporte', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);

		$qb->executeStatement();
	}

	public function deleteById(int $id): void {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq('id_soporte', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);

		$qb->executeStatement();
	}
}