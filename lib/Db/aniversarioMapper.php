<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class aniversarioMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'aniversarios', aniversarios::class);
	}

	public function GetAniversarios(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName());

		$result = $qb->executeQuery();
		$aniversarios = $result->fetchAll();
		$result->closeCursor();

		return $aniversarios;
	}

	public function CheckExistAreas($id_aniversarios): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('Id_departamento', $qb->createNamedParameter($id_aniversarios)));

		$result = $qb->executeQuery();
		$users = $result->fetchAll();
		$result->closeCursor();

		return $users;
	}

	public function deleteByIdEmpleado(int $id_aniversarios): void {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('id_aniversario', $qb->createNamedParameter($id_aniversarios)));

		$qb->executeStatement();
	}

	public function updateAniversarios(int $id_aniversario, int $numero_aniversario, float $dias): void {
		$query = $this->db->getQueryBuilder();
		$query->update($this->getTableName())
			->set('numero_aniversario', $query->createNamedParameter($numero_aniversario))
			->set('dias', $query->createNamedParameter($dias))
			->where($query->expr()->eq('id_aniversario', $query->createNamedParameter($id_aniversario)));

		$query->executeStatement();
	}

	public function VaciarAniversarios(): void {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName());

		$qb->executeStatement();
	}

	public function EliminarArea(string $id_departamento): void {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('Id_departamento', $qb->createNamedParameter($id_departamento)));

		$qb->executeStatement();
	}

	public function GetAniversarioByDate(int $ingreso): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('numero_aniversario', $qb->createNamedParameter($ingreso)));

		$result = $qb->executeQuery();
		$aniversarios = $result->fetchAll();
		$result->closeCursor();

		return $aniversarios;
	}

	public function updateAniversarioByNumero(int $numero_aniversario, int $nuevo_numero_aniversario, float $dias): void {
		$query = $this->db->getQueryBuilder();
		$query->update($this->getTableName())
			->set('numero_aniversario', $query->createNamedParameter($nuevo_numero_aniversario))
			->set('dias', $query->createNamedParameter($dias))
			->where($query->expr()->eq('numero_aniversario', $query->createNamedParameter($numero_aniversario)));

		$query->executeStatement();
	}

	public function deleteByNumeroAniversario(int $numero_aniversario): void {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('numero_aniversario', $qb->createNamedParameter($numero_aniversario)));

		$qb->executeStatement();
	}
}