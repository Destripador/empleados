<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class equiposMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'equipos', equipos::class);
	}

	public function GetEquiposList(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('d.Id_equipo', 'd.Id_jefe_equipo', 'd.Nombre', 'd.created_at', 'd.updated_at')
			->selectAlias($qb->createFunction('COUNT(e.Id_empleados)'), 'cantidad_empleados')
			->from($this->getTableName(), 'd')
			->leftJoin('d', 'empleados', 'e', 'd.Id_equipo = e.Id_equipo')
			->groupBy('d.Id_equipo');

		$result = $qb->executeQuery();
		$users = $result->fetchAll();
		$result->closeCursor();

		return $users;
	}

	/** @return array<int,array<string,mixed>> */
	public function getReportingTeams(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('t.Id_equipo', 't.Id_jefe_equipo', 't.Nombre', 't.created_at', 't.updated_at')
			->selectAlias('leader.Id_empleados', 'id_empleado_lider')
			->selectAlias('leader.Estado', 'estado_lider')
			->selectAlias('u.displayname', 'nombre_lider')
			->from($this->getTableName(), 't')
			->leftJoin('t', 'empleados', 'leader', 'leader.Id_user = t.Id_jefe_equipo')
			->leftJoin('leader', 'users', 'u', 'u.uid = leader.Id_user')
			->orderBy('t.Nombre', 'ASC');

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return $rows;
	}

	/**
	 * @param int[] $teamIds
	 * @return array<int,array<string,mixed>>
	 */
	public function getReportingMembers(array $teamIds, bool $activeOnly = true): array {
		$teamIds = array_values(array_unique(array_filter(
			array_map('intval', $teamIds),
			static fn (int $id): bool => $id > 0,
		)));
		if ($teamIds === []) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select(
			'e.Id_empleados',
			'e.Id_user',
			'e.Id_equipo',
			'e.Id_departamento',
			'e.Ingreso',
			'e.Estado',
			'e.Sueldo'
		)
			->selectAlias('u.displayname', 'displayname')
			->from('empleados', 'e')
			->leftJoin('e', 'users', 'u', 'u.uid = e.Id_user')
			->where($qb->expr()->in(
				'e.Id_equipo',
				$qb->createNamedParameter($teamIds, IQueryBuilder::PARAM_INT_ARRAY)
			))
			->orderBy('u.displayname', 'ASC');

		if ($activeOnly) {
			$qb->andWhere($qb->expr()->eq(
				'e.Estado',
				$qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)
			));
		}

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return $rows;
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

	public function updateEquipos(
		string $Id_equipo,
		string $nombre,
		string $Id_jefe_equipo
	): void {
		$timestamp = date('Y-m-d');

		$query = $this->db->getQueryBuilder();

		$query->update($this->getTableName())
			->set('Nombre', $query->createNamedParameter($nombre))
			->set('Id_jefe_equipo', $query->createNamedParameter($Id_jefe_equipo))
			->set('updated_at', $query->createNamedParameter($timestamp))
			->where(
				$query->expr()->eq(
					'Id_equipo',
					$query->createNamedParameter($Id_equipo)
				)
			);

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

	public function deleteByIdReturningRow(string $idEquipo): ?array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('Id_equipo', $qb->createNamedParameter($idEquipo)))
			->setMaxResults(1);

		$result = method_exists($qb, 'executeQuery')
			? $qb->executeQuery()
			: $qb->execute();

		$row = $result->fetch();
		$result->closeCursor();

		if (!$row) {
			return null;
		}

		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('Id_equipo', $qb->createNamedParameter($idEquipo)));

		if (method_exists($qb, 'executeStatement')) {
			$qb->executeStatement();
		} else {
			$qb->execute();
		}

		return $row;
	}
}
