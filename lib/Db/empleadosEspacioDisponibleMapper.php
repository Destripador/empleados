<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\QueryBuilder\IQueryBuilder;

class empleadosEspacioDisponibleMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'emp_esp_disp', empleadosEspacioDisponible::class);
	}

	public function findCurrentLiberados(): array {
		$qb = $this->db->getQueryBuilder();
		$today = (new \DateTime())->format('Y-m-d');

		$qb->select('ee.id_espacio')
			->from('emp_esp_disp', 'ed')
			->innerJoin('ed', 'espacio_empleados', 'ee', $qb->expr()->eq('ed.id_espacio_empleado', 'ee.id_espacio_empleado'))
			->where($qb->expr()->eq('ed.fecha', $qb->createNamedParameter($today)));
		
		$result = $qb->executeQuery();
		$data = $result->fetchAll(\PDO::FETCH_COLUMN);
		$result->closeCursor();
		return $data;
	}

	public function findExisting(int $id_asignacion, string $fecha): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id_espacio_empleado', $qb->createNamedParameter($id_asignacion)))
			->andWhere($qb->expr()->eq('fecha', $qb->createNamedParameter($fecha)));

		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();
		return (bool)$row;
	}

	public function findByAsignacion(int $id_asignacion): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id_espacio_empleado', $qb->createNamedParameter($id_asignacion)))
			->andWhere($qb->expr()->gte('fecha', $qb->createNamedParameter((new \DateTime())->format('Y-m-d'))))
			->orderBy('fecha', 'DESC');

		return $this->findEntities($qb);
	}

	public function findById(int $id): empleadosEspacioDisponible {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id_emp_esp_disp', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

		return $this->findEntity($qb);
	}

	public function deleteById(int $id): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('id_emp_esp_disp', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		
		$qb->executeStatement();
	}

	/**
     * Obtiene todos los registros de disponibilidad para una asignación en una fecha específica
     */
    public function findRecordsByDate(int $id_asignacion, string $fecha): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id_espacio_empleado', $qb->createNamedParameter($id_asignacion, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('fecha', $qb->createNamedParameter($fecha)));

        return $this->findEntities($qb);
    }

	/**
	 * Obtiene disponibilidad futura para consulta pública
	 */
	public function findPublicHistory(int $id_espacio): array {
		$qb = $this->db->getQueryBuilder();
		$today = (new \DateTime())->format('Y-m-d');

		$qb->select('ed.*')
		->from($this->getTableName(), 'ed')
		->innerJoin('ed', 'espacio_empleados', 'ee', $qb->expr()->eq('ed.id_espacio_empleado', 'ee.id_espacio_empleado'))
		->where($qb->expr()->eq('ee.id_espacio', $qb->createNamedParameter($id_espacio, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)))
		->andWhere($qb->expr()->gte('ed.fecha', $qb->createNamedParameter($today)))
		->orderBy('ed.fecha', 'ASC');

		$result = $qb->executeQuery();
		$data = $result->fetchAll();
		$result->closeCursor();

		return $data;
	}
}