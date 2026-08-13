<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\QueryBuilder\IQueryBuilder;

class espacioEmpleadosMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'espacio_empleados', espacioEmpleados::class);
	}

	/** Obtiene los IDs de empleados asignados a un espacio */
	public function findIdsByEspacio(int $id_espacio): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id_empleados')
			->from($this->getTableName())
			->where($qb->expr()->eq('id_espacio', $qb->createNamedParameter($id_espacio, IQueryBuilder::PARAM_INT)));

		$result = $qb->executeQuery();
		$ids = $result->fetchAll(\PDO::FETCH_COLUMN);
		$result->closeCursor();
		return $ids;
	}

	/** Borra todas las asignaciones de un espacio para re-insertar */
	public function deleteByEspacio(int $id_espacio): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('id_espacio', $qb->createNamedParameter($id_espacio)));
		$qb->executeStatement();
	}

	/* Obtener empleados con espacio asignado*/
	public function getEspacioEmpleado(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('ee.id_espacio_empleado', 'ee.id_espacio', 'es.numero', 'ee.id_empleados', 'u.uid', 'u.displayname')
			->from($this->getTableName(), 'ee')
			->innerJoin('ee', 'espacio', 'es', $qb->expr()->eq('ee.id_espacio', 'es.id_espacio'))
			->innerJoin('ee', 'empleados', 'e', $qb->expr()->eq('ee.id_empleados', 'e.id_empleados'))
			->innerJoin('e', 'users', 'u', $qb->expr()->eq('e.Id_user', 'u.uid'))
			->where($qb->expr()->eq('e.Estado', $qb->createNamedParameter(1)));

		$result = $qb->executeQuery();
		$empleados = $result->fetchAll();
		$result->closeCursor();
		return $empleados;
	}

	public function findById(int $id): espacioEmpleados {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id_espacio_empleado', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

		return $this->findEntity($qb);
	}
}