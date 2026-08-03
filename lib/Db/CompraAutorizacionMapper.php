<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class CompraAutorizacionMapper extends QBMapper {
	private const TABLE = 'emp_comp_autoriza';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, self::TABLE, CompraAutorizacion::class);
	}

	public function findBySolicitud(int $idSolicitud): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from(self::TABLE)
			->where($qb->expr()->eq('id_solicitud', $qb->createNamedParameter($idSolicitud, IQueryBuilder::PARAM_INT)))
			->orderBy('nivel', 'ASC')
			->addOrderBy('id_autorizacion', 'ASC');

		return $this->findEntities($qb);
	}

	public function findCurrent(int $idSolicitud): ?CompraAutorizacion {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from(self::TABLE)
			->where($qb->expr()->eq('id_solicitud', $qb->createNamedParameter($idSolicitud, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('estado', $qb->createNamedParameter('pendiente', IQueryBuilder::PARAM_STR)))
			->orderBy('nivel', 'ASC')
			->addOrderBy('id_autorizacion', 'ASC')
			->setMaxResults(1);

		$entities = $this->findEntities($qb);
		return $entities[0] ?? null;
	}

	public function hasAssignments(int $idSolicitud): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('COUNT(*)'))
			->from(self::TABLE)
			->where($qb->expr()->eq('id_solicitud', $qb->createNamedParameter($idSolicitud, IQueryBuilder::PARAM_INT)));
		$result = $qb->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();
		return $count > 0;
	}

	public function isAssigned(int $idSolicitud, string $uid): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('COUNT(*)'))
			->from(self::TABLE)
			->where($qb->expr()->eq('id_solicitud', $qb->createNamedParameter($idSolicitud, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('id_autorizador', $qb->createNamedParameter($uid, IQueryBuilder::PARAM_STR)));
		$result = $qb->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();
		return $count > 0;
	}

	public function insertStage(int $idSolicitud, array $stage): CompraAutorizacion {
		$qb = $this->db->getQueryBuilder();
		$qb->insert(self::TABLE)->values([
			'id_solicitud' => $qb->createNamedParameter($idSolicitud, IQueryBuilder::PARAM_INT),
			'id_autorizador' => $qb->createNamedParameter($stage['uid'], IQueryBuilder::PARAM_STR),
			'id_empleado_autorizador' => $qb->createNamedParameter($stage['id_empleado']),
			'autorizador_nombre' => $qb->createNamedParameter($stage['nombre'], IQueryBuilder::PARAM_STR),
			'rol' => $qb->createNamedParameter($stage['rol'], IQueryBuilder::PARAM_STR),
			'nivel' => $qb->createNamedParameter($stage['nivel'], IQueryBuilder::PARAM_INT),
			'estado' => $qb->createNamedParameter('pendiente', IQueryBuilder::PARAM_STR),
		]);
		$this->executeStatement($qb);

		$id = (int)$this->db->lastInsertId(self::TABLE);
		return $this->find($id);
	}

	public function find(int $id): CompraAutorizacion {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from(self::TABLE)
			->where($qb->expr()->eq('id_autorizacion', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1);

		return $this->findEntity($qb);
	}

	public function resolvePending(int $id, string $estado, ?string $comentario): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->update(self::TABLE)
			->set('estado', $qb->createNamedParameter($estado, IQueryBuilder::PARAM_STR))
			->set('comentario', $qb->createNamedParameter($comentario))
			->set('fecha_autorizacion', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->set('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->where($qb->expr()->eq('id_autorizacion', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('estado', $qb->createNamedParameter('pendiente', IQueryBuilder::PARAM_STR)));

		return $this->executeStatement($qb) === 1;
	}

	public function cancelPendingBySolicitud(int $idSolicitud): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update(self::TABLE)
			->set('estado', $qb->createNamedParameter('cancelada', IQueryBuilder::PARAM_STR))
			->set('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->where($qb->expr()->eq('id_solicitud', $qb->createNamedParameter($idSolicitud, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('estado', $qb->createNamedParameter('pendiente', IQueryBuilder::PARAM_STR)));
		$this->executeStatement($qb);
	}

	private function executeStatement(IQueryBuilder $qb): int {
		if (method_exists($qb, 'executeStatement')) {
			return $qb->executeStatement();
		}

		return $qb->execute();
	}
}
