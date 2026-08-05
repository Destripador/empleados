<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class departamentosMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'departamentos', departamentos::class);
	}

	public function GetAreasList(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('d.Id_departamento', 'd.Id_padre', 'd.Nombre', 'd.created_at', 'd.updated_at')
			->selectAlias($qb->createFunction('COUNT(e.Id_empleados)'), 'cantidad_empleados')
			->from($this->getTableName(), 'd')
			->leftJoin('d', 'empleados', 'e', 'd.Id_departamento = e.Id_departamento')
			->groupBy('d.Id_departamento');

		$result = $qb->executeQuery();
		$users = $result->fetchAll();
		$result->closeCursor();

		return $users;
	}

	public function CheckExistAreas($id_departamentos): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('Id_departamento', $qb->createNamedParameter($id_departamentos)));

		$result = $qb->executeQuery();
		$users = $result->fetchAll();
		$result->closeCursor();

		return $users;
	}

	public function deleteByIdEmpleado(int $id_departamentos): void {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('Id_departamento', $qb->createNamedParameter($id_departamentos)));

		$qb->executeStatement();
	}

	public function updateAreas(string $Id_departamento, string $Id_padre, string $Nombre): void {
		$timestamp = date('Y-m-d');

		if (empty($Id_departamento) && $Id_departamento != 0) { $Id_departamento = null; }
		if (empty($Id_padre) && $Id_padre != 0) { $Id_padre = null; }
		if (empty($Nombre) && $Nombre != 0) { $Nombre = null; }

		$query = $this->db->getQueryBuilder();
		$query->update($this->getTableName())
			->set('Id_padre', $query->createNamedParameter($Id_padre))
			->set('Nombre', $query->createNamedParameter($Nombre))
			->set('updated_at', $query->createNamedParameter($timestamp))
			->where($query->expr()->eq('Id_departamento', $query->createNamedParameter($Id_departamento)));

		$query->executeStatement();
	}

	public function EliminarArea(string $id_departamento): void {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('Id_departamento', $qb->createNamedParameter($id_departamento)));

		$qb->executeStatement();
	}

	/**
	 * Devuelve la jerarquía completa de departamentos.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function findHierarchy(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select(
			'd.Id_departamento',
			'd.Id_padre',
			'd.Nombre'
		)
			->from($this->getTableName(), 'd')
			->orderBy('d.Id_departamento', 'ASC');

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return $rows;
	}

	/**
	 * Devuelve los datos mínimos de un departamento o null si no existe.
	 *
	 * @return array<string, mixed>|null
	 */
	public function findDepartmentRow(int $id): ?array {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('d.Id_departamento', 'd.Id_padre', 'd.Nombre')
			->from($this->getTableName(), 'd')
			->where($qb->expr()->eq('d.Id_departamento', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1)
			->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		return $row === false ? null : $row;
	}
}
