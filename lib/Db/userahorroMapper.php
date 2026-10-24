<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCA\Empleados\Service\UserDirectory;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class userahorroMapper extends QBMapper {
	private UserDirectory $userDirectory;

	public function __construct(IDBConnection $db, UserDirectory $userDirectory) {
		parent::__construct($db, 'user_ahorro', userahorro::class);
		$this->userDirectory = $userDirectory;
	}

	public function getUserAhorro(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName());

		return $this->findEntities($qb);
	}

	public function getUsersWithAhorro(): array {
		$qb = $this->db->getQueryBuilder();

		// user_ahorro.id_user holds the employee id, the Nextcloud uid lives in empleados.
		$qb->select('o.*')
			->addSelect('c.Id_user')
			->from($this->getTableName(), 'o')
			->innerJoin('o', 'empleados', 'c', $qb->expr()->eq('c.Id_empleados', 'o.id_user'));

		$result = $qb->executeQuery();
		$users = $result->fetchAll();
		$result->closeCursor();

		return $this->userDirectory->withDisplayNames($users, 'Id_user', 'displayname', true);
	}

	/**
	 * @return list<array{uid: string, displayname: string}>
	 */
	public function getAllUsers(): array {
		return $this->userDirectory->listUsers();
	}

	public function updateAhorroById(int $id_ahorro, string $id_permision, string $state, string $cantidad): void {
		$query = $this->db->getQueryBuilder();
		$query->update($this->getTableName())
			->set('id_permision', $query->createNamedParameter($id_permision))
			->set('state', $query->createNamedParameter($state))
			->set('cantidad', $query->createNamedParameter($cantidad))
			->where($query->expr()->eq('id_ahorro', $query->createNamedParameter($id_ahorro)));

		$query->executeStatement();
	}

	public function updatePermisionUserId(int $id, string $state): void {
		$query = $this->db->getQueryBuilder();
		$query->update($this->getTableName())
			->set('state', $query->createNamedParameter($state))
			->where($query->expr()->eq('id_ahorro', $query->createNamedParameter($id)));

		$query->executeStatement();
	}

	public function updatePermisionByEmpleadoId(int $id, string $state): void {
		$query = $this->db->getQueryBuilder();
		$query->update($this->getTableName())
			->set('state', $query->createNamedParameter($state))
			->where($query->expr()->eq('id_user', $query->createNamedParameter($id)));

		$query->executeStatement();
	}

	public function GetInfoAhorro(int $id_user): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id_user', $qb->createNamedParameter($id_user)));

		$result = $qb->executeQuery();
		$users = $result->fetchAll();
		$result->closeCursor();

		return $users;
	}

	public function GetInfoByIdAhorro(int $id_user): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id_ahorro', $qb->createNamedParameter($id_user)));

		$result = $qb->executeQuery();
		$users = $result->fetchAll();
		$result->closeCursor();

		return $users;
	}

	public function deleteByIdEmpleado(int $id_empleado): void {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('id_user', $qb->createNamedParameter($id_empleado)));

		$qb->executeStatement();
	}
}