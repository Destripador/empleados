<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use DateTime;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

use OCP\AppFramework\Db\DoesNotExistException;


class departamentosMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'departamentos', departamentos::class);
	}

	public function GetAreasList(): array {
		$qb = $this->db->getQueryBuilder();

		/* 
			SELECT d.nombre, COUNT(e.id_empleados) AS cantidad_empleados
			FROM oc_departamentos d
			LEFT JOIN oc_empleados e ON d.Nombre = e.Id_departamento
			GROUP BY d.Id_departamento;
		*/
		$qb->select('d.Id_departamento', 'd.Id_padre', 'd.Nombre')
			->selectAlias($qb->createFunction('COUNT(e.Id_empleados)'), 'cantidad_empleados')
			->from($this->getTableName(), 'd')
			->leftJoin('d', 'empleados', 'e', 'd.Id_departamento = e.Id_departamento')
			->groupBy('d.Id_departamento');
			
		$result = $qb->execute();
		$users = $result->fetchAll();
		$result->closeCursor();
	
		return $users;
	}

	public function CheckExistAreas($id_departamentos): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('Id_departamento', $qb->createNamedParameter($id_departamentos)));
			
		$result = $qb->execute();
		$users = $result->fetchAll();
		$result->closeCursor();
	
		return $users;
	}

	public function deleteByIdEmpleado(int $id_departamentos): void {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('Nombre', $qb->createNamedParameter($Nombre)));

		$result = $qb->execute();
	}

	public function updateAreas(string $Id_departamento, string $Id_padre, string $Nombre ): void {
		$timestamp = date('Y-m-d');

		if(empty($Id_departamento) && $Id_departamento != 0){ $Id_departamento = null; }
		if(empty($Id_padre) && $Id_padre != 0){ $Id_padre = null; }
		if(empty($Nombre) && $Nombre != 0){ $Nombre = null; }
		$query = $this->db->getQueryBuilder();
		$query->update($this->getTableName())
			->set('Id_padre', $query->createNamedParameter($Id_padre))
			->set('Nombre', $query->createNamedParameter($Nombre))
			->set('updated_at', $query->createNamedParameter($timestamp))
			->where($query->expr()->eq('Id_departamento', $query->createNamedParameter($Id_departamento)));
	
		$query->execute();
	}

	public function EliminarArea(string $id_departamento): void {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('Id_departamento', $qb->createNamedParameter($id_departamento)));

		$result = $qb->execute();
	}

}