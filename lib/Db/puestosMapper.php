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


class puestosMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'puestos', puestos::class);
	}

	public function GetPuestosList(): array {
		$qb = $this->db->getQueryBuilder();

		/* 
			SELECT d.nombre, COUNT(e.id_empleados) AS cantidad_empleados
			FROM oc_departamentos d
			LEFT JOIN oc_empleados e ON d.Nombre = e.Id_puestos
			GROUP BY d.Id_puestos;
		*/
		$qb->select('d.Id_puestos', 'd.Nombre')
			->selectAlias($qb->createFunction('COUNT(e.Id_empleados)'), 'cantidad_empleados')
			->from($this->getTableName(), 'd')
			->leftJoin('d', 'empleados', 'e', 'd.Id_puestos = e.Id_puesto')
			->groupBy('d.Id_puestos');
			
		$result = $qb->execute();
		$users = $result->fetchAll();
		$result->closeCursor();
	
		return $users;
	}

	public function CheckExistPuestos($id_departamentos): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('Id_puestos', $qb->createNamedParameter($id_departamentos)));
			
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

	public function updatePuestos(string $Id_puestos, string $Nombre ): void {
		$timestamp = date('Y-m-d');

		if(empty($Id_puestos) && $Id_puestos != 0){ $Id_puestos = null; }
		if(empty($Nombre) && $Nombre != 0){ $Nombre = null; }
		$query = $this->db->getQueryBuilder();
		$query->update($this->getTableName())
			->set('Nombre', $query->createNamedParameter($Nombre))
			->set('updated_at', $query->createNamedParameter($timestamp))
			->where($query->expr()->eq('Id_puestos', $query->createNamedParameter($Id_puestos)));
	
		$query->execute();
	}

	public function EliminarPuesto(string $Id_puestos): void {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('Id_puestos', $qb->createNamedParameter($Id_puestos)));

		$result = $qb->execute();
	}

}