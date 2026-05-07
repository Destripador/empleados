<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class CompraHistorialMapper extends QBMapper {

	private const TABLE = 'emp_comp_historial';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, self::TABLE, CompraHistorial::class);
	}

	public function find(int $id): CompraHistorial {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from(self::TABLE)
			->where($qb->expr()->eq(
				'id_historial',
				$qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)
			))
			->setMaxResults(1);

		return $this->findEntity($qb);
	}

	public function findBySolicitud(int $idSolicitud): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from(self::TABLE)
			->where($qb->expr()->eq(
				'id_solicitud',
				$qb->createNamedParameter($idSolicitud, IQueryBuilder::PARAM_INT)
			))
			->orderBy('created_at', 'ASC');

		return $this->findEntities($qb);
	}

	public function insertHistorial(
		int $idSolicitud,
		string $accion,
		?string $estadoAnterior,
		?string $estadoNuevo,
		?string $comentario,
		?array $metadata,
		?string $createdBy
	): CompraHistorial {
		$qb = $this->db->getQueryBuilder();

		$qb->insert(self::TABLE)->values([
			'id_solicitud' => $qb->createNamedParameter($idSolicitud, IQueryBuilder::PARAM_INT),
			'accion' => $qb->createNamedParameter($accion, IQueryBuilder::PARAM_STR),
			'estado_anterior' => $qb->createNamedParameter($estadoAnterior),
			'estado_nuevo' => $qb->createNamedParameter($estadoNuevo),
			'comentario' => $qb->createNamedParameter($comentario),
			'metadata' => $qb->createNamedParameter($metadata !== null ? json_encode($metadata) : null),
			'created_by' => $qb->createNamedParameter($createdBy),
		]);

		$this->executeStatement($qb);

		$id = (int)$this->db->lastInsertId();

		return $this->find($id);
	}

	private function executeStatement(IQueryBuilder $qb): int {
		if (method_exists($qb, 'executeStatement')) {
			return $qb->executeStatement();
		}

		return $qb->execute();
	}
}
