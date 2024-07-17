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


class empleadosMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'empleados', empleados::class);
	}

    public function GetUserLists(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName(), 'o')
			->innerJoin('o', 'users', 'c', $qb->expr()->eq('uid', 'id_user'));
		
		$result = $qb->execute();
		$users = $result->fetchAll();
		$result->closeCursor();
	
		return $users;
	}

	public function getAllUsers(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from('users', 'o')
			->innerJoin('o', 'accounts', 'c', $qb->expr()->eq('o.uid', 'c.uid'));
			

		$result = $qb->execute();
		$users = $result->fetchAll();
		$result->closeCursor();
	
		return $users;
	}

	public function deleteByIdEmpleado(int $id_empleados): void {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('Id_empleados', $qb->createNamedParameter($id_empleados)));
			

		$result = $qb->execute();
	}

}