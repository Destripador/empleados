<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class equiposMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'equipos', equipos::class);
	}

	public function GetEquiposList(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('d.Id_equipo', 'd.Id_jefe_equipo', 'd.Nombre')
			->selectAlias($qb->createFunction('COUNT(e.Id_empleados)'), 'cantidad_empleados')
			->from($this->getTableName(), 'd')
			->leftJoin('d', 'empleados', 'e', 'd.Id_equipo = e.Id_equipo')
			->groupBy('d.Id_equipo');

		$result = $qb->executeQuery();
		$users = $result->fetchAll();
		$result->closeCursor();

		return $users;
	}

	public function GetEquipoJefe($id): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('d.Id_equipo', 'd.Id_jefe_equipo', 'd.Nombre')
			->selectAlias($qb->createFunction('COUNT(e.Id_empleados)'), 'cantidad_empleados')
			->from($this->getTableName(), 'd')
			->leftJoin('d', 'empleados', 'e', 'd.Id_equipo = e.Id_equipo')
			->where($qb->expr()->eq('d.Id_equipo', $qb->createNamedParameter($id)))
			->groupBy('d.Id_equipo');

		$result = $qb->executeQuery();
		$users = $result->fetchAll();
		$result->closeCursor();

		return $users;
	}

	public function CheckExistEquipos($id_departamentos): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('Id_equipo', $qb->createNamedParameter($id_departamentos)));

		$result = $qb->executeQuery();
		$users = $result->fetchAll();
		$result->closeCursor();

		return $users;
	}

	public function deleteByIdEmpleado(int $id_departamentos): void {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('Id_equipo', $qb->createNamedParameter($id_departamentos)));

		$qb->executeStatement();
	}

	public function updateEquipos($Id_equipo, $Id_jefe_equipo): void {
		$timestamp = date('Y-m-d');

		if (empty($Id_equipo) && $Id_equipo != 0) { $Id_equipo = null; }
		if (empty($Id_jefe_equipo) && $Id_jefe_equipo != 0) { $Id_jefe_equipo = null; }

		$query = $this->db->getQueryBuilder();
		$query->update($this->getTableName())
			->set('Id_jefe_equipo', $query->createNamedParameter($Id_jefe_equipo))
			->set('updated_at', $query->createNamedParameter($timestamp))
			->where($query->expr()->eq('Id_equipo', $query->createNamedParameter($Id_equipo)));

		$query->executeStatement();
	}

	public function getById(string $id): ?array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('Id_equipo', $qb->createNamedParameter($id)))
			->setMaxResults(1);

		$res = $qb->executeQuery();

		try {
			$row = $res->fetch();
		} finally {
			$res->closeCursor();
		}

		return is_array($row) ? $row : null;
	}

	public function EliminarEquipo(string $Id_equipo): ?array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('Id_equipo', $qb->createNamedParameter($Id_equipo)));

		$result = $qb->executeQuery();
		$row = $result->fetchAssociative();
		$result->closeCursor();

		if (!$row) {
			return null;
		}

		$qb2 = $this->db->getQueryBuilder();
		$qb2->delete($this->getTableName())
			->where($qb2->expr()->eq('Id_equipo', $qb2->createNamedParameter($Id_equipo)));
		$qb2->executeStatement();

		return $row;
	}

	public function deleteByIdReturningRow(string $Id_equipo): ?array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('Id_equipo', $qb->createNamedParameter($Id_equipo)))
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$row = $result->fetchAssociative();
		$result->closeCursor();

		if (!$row) {
			return null;
		}

		$qb2 = $this->db->getQueryBuilder();
		$qb2->delete($this->getTableName())
			->where($qb2->expr()->eq('Id_equipo', $qb2->createNamedParameter($Id_equipo)));

		$qb2->executeStatement();

		return $row;
	}
}