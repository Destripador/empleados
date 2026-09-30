<?php

declare(strict_types=1);

namespace OCA\Empleados\Service;

use OCA\Empleados\Db\empleadosMapper;
use OCA\Empleados\Db\empleadosorganigramaMapper;
use OCA\Empleados\Db\equiposMapper;

/**
 * Calcula el alcance organizacional de los reportes administrativos.
 *
 * La jerarquia canonica es emp_organigrama (jefe -> dependiente). Los equipos
 * son visibles cuando su lider pertenece a la clausura jerarquica del usuario.
 */
final class AdministrativeReportScopeService {
	public function __construct(
		private empleadosMapper $empleadosMapper,
		private empleadosorganigramaMapper $organigramaMapper,
		private equiposMapper $equiposMapper,
	) {
	}

	/**
	 * @return array{
	 *   global:bool,
	 *   current_employee_id:?int,
	 *   hierarchy_employee_ids:int[],
	 *   employee_ids:int[],
	 *   employees:array<int,array<string,mixed>>,
	 *   team_ids:int[],
	 *   teams:array<int,array<string,mixed>>,
	 *   members_by_team:array<int,array<int,array<string,mixed>>>
	 * }
	 */
	public function getScope(string $uid, bool $global, ?string $periodStart = null): array {
		$allEmployees = $this->normalizeEmployees($this->empleadosMapper->getReportingDirectory(false));
		$activeEmployees = array_filter(
			$allEmployees,
			static fn (array $employee): bool => self::isActive($employee)
				|| self::leftOnOrAfter($employee, $periodStart),
		);
		$employeeById = [];
		$currentEmployeeId = null;
		foreach ($allEmployees as $employee) {
			$id = (int)$employee['id_empleado'];
			$employeeById[$id] = $employee;
			if ((string)$employee['id_user'] === $uid) {
				$currentEmployeeId = $currentEmployeeId === null
					? $id
					: max($currentEmployeeId, $id);
			}
		}

		$validEmployeeIds = array_fill_keys(array_keys($employeeById), true);
		$hierarchyIds = $global
			? array_keys($employeeById)
			: ($currentEmployeeId === null
				? []
				: $this->collectDescendantIds(
					$currentEmployeeId,
					$this->organigramaMapper->getRelationIds(),
					$validEmployeeIds,
				));
		$hierarchySet = array_fill_keys($hierarchyIds, true);

		$teams = [];
		foreach ($this->equiposMapper->getReportingTeams() as $row) {
			$team = $this->normalizeTeam($row);
			if ($team['id_equipo'] <= 0) {
				continue;
			}
			if (!$global) {
				$leaderId = $team['id_empleado_lider'];
				if ($leaderId === null || !isset($hierarchySet[$leaderId])) {
					continue;
				}
			}
			$teams[$team['id_equipo']] = $team;
		}

		$teamIds = array_keys($teams);
		$membersByTeam = array_fill_keys($teamIds, []);
		$visibleEmployeeSet = [];
		$activeEmployeeById = [];
		foreach ($activeEmployees as $employee) {
			$id = (int)$employee['id_empleado'];
			$activeEmployeeById[$id] = $employee;
			if ($global || isset($hierarchySet[$id])) {
				$visibleEmployeeSet[$id] = true;
			}
		}

		foreach ($this->normalizeEmployees($this->equiposMapper->getReportingMembers($teamIds, false)) as $member) {
			if (!self::isActive($member) && !self::leftOnOrAfter($member, $periodStart)) {
				continue;
			}
			$teamId = (int)($member['id_equipo'] ?? 0);
			if (!isset($membersByTeam[$teamId])) {
				continue;
			}
			$membersByTeam[$teamId][] = $member;
			$visibleEmployeeSet[(int)$member['id_empleado']] = true;
		}

		foreach ($teams as $teamId => &$team) {
			$team['cantidad_empleados'] = count($membersByTeam[$teamId] ?? []);
		}
		unset($team);

		$employeeIds = array_map('intval', array_keys($visibleEmployeeSet));
		sort($employeeIds, SORT_NUMERIC);
		sort($hierarchyIds, SORT_NUMERIC);
		$visibleEmployees = array_values(array_filter(
			$activeEmployeeById,
			static fn (array $employee): bool => isset($visibleEmployeeSet[(int)$employee['id_empleado']]),
		));

		return [
			'global' => $global,
			'current_employee_id' => $currentEmployeeId,
			'hierarchy_employee_ids' => array_values(array_unique($hierarchyIds)),
			'employee_ids' => $employeeIds,
			'employees' => $visibleEmployees,
			'team_ids' => array_values(array_map('intval', $teamIds)),
			'teams' => array_values($teams),
			'members_by_team' => $membersByTeam,
		];
	}

	/** Inactivo cuya baja ocurrió dentro o después del inicio del periodo. */
		private static function leftOnOrAfter(array $employee, ?string $periodStart): bool {
		if ($periodStart === null || self::isActive($employee)) {
			return false;
		}
		$baja = $employee['Fecha_baja'] ?? null;
		return is_string($baja) && $baja !== '' && substr($baja, 0, 10) >= $periodStart;
	}

	/**
	 * Recorre la jerarquia iterativamente. El conjunto visitado protege contra
	 * ciclos, aristas duplicadas y autorreferencias.
	 *
	 * @param array<int,array{id_empleado:int,id_dependiente:int}> $relations
	 * @param array<int,bool>|null $validEmployeeIds
	 * @return int[]
	 */
	public function collectDescendantIds(
		int $rootEmployeeId,
		array $relations,
		?array $validEmployeeIds = null,
	): array {
		if ($rootEmployeeId <= 0) {
			return [];
		}
		if ($validEmployeeIds !== null && !isset($validEmployeeIds[$rootEmployeeId])) {
			return [];
		}

		$children = [];
		foreach ($relations as $relation) {
			$managerId = (int)($relation['id_empleado'] ?? 0);
			$dependentId = (int)($relation['id_dependiente'] ?? 0);
			if ($managerId <= 0 || $dependentId <= 0 || $managerId === $dependentId) {
				continue;
			}
			if (
				$validEmployeeIds !== null
				&& (!isset($validEmployeeIds[$managerId]) || !isset($validEmployeeIds[$dependentId]))
			) {
				continue;
			}
			$children[$managerId][$dependentId] = true;
		}

		$visited = [];
		$pending = [$rootEmployeeId];
		while ($pending !== []) {
			$current = array_pop($pending);
			if (isset($visited[$current])) {
				continue;
			}
			$visited[$current] = true;
			foreach (array_keys($children[$current] ?? []) as $childId) {
				if (!isset($visited[$childId])) {
					$pending[] = (int)$childId;
				}
			}
		}

		return array_values(array_map('intval', array_keys($visited)));
	}

	/**
	 * @param array<int,array<string,mixed>> $teams
	 * @return array<string,mixed>|null
	 */
	public function findTeam(array $teams, int $teamId): ?array {
		foreach ($teams as $team) {
			if ((int)($team['id_equipo'] ?? 0) === $teamId) {
				return $team;
			}
		}

		return null;
	}

	/**
	 * @param array<string,mixed> $scope
	 * @return array<int,array<string,mixed>>
	 */
	public function getDependentTeams(array $scope, int $teamId): array {
		$memberIds = [];
		foreach ($scope['members_by_team'][$teamId] ?? [] as $member) {
			$memberIds[(int)($member['id_empleado'] ?? 0)] = true;
		}

		return array_values(array_filter(
			$scope['teams'] ?? [],
			static function (array $team) use ($teamId, $memberIds): bool {
				return (int)($team['id_equipo'] ?? 0) !== $teamId
					&& isset($memberIds[(int)($team['id_empleado_lider'] ?? 0)]);
			},
		));
	}

	/**
	 * Cadena de equipos padre hasta el equipo actual, usando la misma relacion
	 * que los equipos dependientes: el lider del hijo pertenece al equipo padre.
	 *
	 * @param array<string,mixed> $scope
	 * @return array<int,array{id_equipo:int,nombre:string}>
	 */
	public function getTeamAncestors(array $scope, int $teamId): array {
		$teamsById = [];
		foreach ($scope['teams'] ?? [] as $team) {
			$id = (int)($team['id_equipo'] ?? 0);
			if ($id > 0) {
				$teamsById[$id] = $team;
			}
		}

		$ancestors = [];
		$visited = [$teamId => true];
		$currentId = $teamId;

		while (isset($teamsById[$currentId])) {
			$leaderId = (int)($teamsById[$currentId]['id_empleado_lider'] ?? 0);
			$parent = null;
			foreach ($teamsById as $candidateId => $candidate) {
				if (isset($visited[$candidateId]) || $leaderId <= 0) {
					continue;
				}
				foreach ($scope['members_by_team'][$candidateId] ?? [] as $member) {
					if ((int)($member['id_empleado'] ?? 0) === $leaderId) {
						$parent = $candidate;
						break 2;
					}
				}
			}
			if ($parent === null) {
				break;
			}

			$parentId = (int)$parent['id_equipo'];
			$visited[$parentId] = true;
			array_unshift($ancestors, [
				'id_equipo' => $parentId,
				'nombre' => (string)($parent['nombre'] ?? ''),
			]);
			$currentId = $parentId;
		}

		return $ancestors;
	}

	/** @param array<int,array<string,mixed>> $rows */
	private function normalizeEmployees(array $rows): array {
		$employees = [];
		foreach ($rows as $row) {
			$id = (int)($row['Id_empleados'] ?? $row['id_empleados'] ?? $row['id_empleado'] ?? 0);
			if ($id <= 0) {
				continue;
			}
			$uid = trim((string)($row['Id_user'] ?? $row['id_user'] ?? ''));
			$name = trim((string)($row['displayname'] ?? $row['DisplayName'] ?? $uid));
			$employees[] = [
				'id_empleado' => $id,
				'Id_empleados' => $id,
				'id_user' => $uid,
				'Id_user' => $uid,
				'nombre' => $name !== '' ? $name : $uid,
				'displayname' => $name !== '' ? $name : $uid,
				'id_equipo' => self::nullablePositiveInt($row['Id_equipo'] ?? $row['id_equipo'] ?? null),
				'Id_equipo' => self::nullablePositiveInt($row['Id_equipo'] ?? $row['id_equipo'] ?? null),
				'id_departamento' => self::nullablePositiveInt($row['Id_departamento'] ?? $row['id_departamento'] ?? null),
				'Id_departamento' => self::nullablePositiveInt($row['Id_departamento'] ?? $row['id_departamento'] ?? null),
				'Ingreso' => $row['Ingreso'] ?? $row['ingreso'] ?? null,
				'Estado' => $row['Estado'] ?? $row['estado'] ?? null,
				'Fecha_baja' => $row['Fecha_baja'] ?? $row['fecha_baja'] ?? null,
				'Sueldo' => (float)($row['Sueldo'] ?? $row['sueldo'] ?? 0),
			];
		}

		return $employees;
	}

	/** @param array<string,mixed> $row */
	private function normalizeTeam(array $row): array {
		$leaderUid = trim((string)($row['Id_jefe_equipo'] ?? $row['id_jefe_equipo'] ?? ''));
		$leaderName = trim((string)($row['nombre_lider'] ?? ''));
		$leaderId = self::nullablePositiveInt($row['id_empleado_lider'] ?? null);

		return [
			'id_equipo' => (int)($row['Id_equipo'] ?? $row['id_equipo'] ?? 0),
			'nombre' => trim((string)($row['Nombre'] ?? $row['nombre'] ?? '')),
			'id_jefe_equipo' => $leaderUid !== '' ? $leaderUid : null,
			'id_empleado_lider' => $leaderId,
			'nombre_lider' => $leaderName !== '' ? $leaderName : ($leaderUid !== '' ? $leaderUid : null),
			'lider_activo' => $leaderId !== null && self::isActive(['Estado' => $row['estado_lider'] ?? null]),
		];
	}

	/** @param array<string,mixed> $employee */
	private static function isActive(array $employee): bool {
		$value = $employee['Estado'] ?? $employee['estado'] ?? null;
		return $value === true || $value === 1 || $value === '1';
	}

	private static function nullablePositiveInt(mixed $value): ?int {
		$id = (int)$value;
		return $id > 0 ? $id : null;
	}
}
