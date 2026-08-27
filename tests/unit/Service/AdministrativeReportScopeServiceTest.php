<?php

declare(strict_types=1);

namespace OCA\Empleados\Tests\Unit\Service;

use OCA\Empleados\Db\empleadosMapper;
use OCA\Empleados\Db\empleadosorganigramaMapper;
use OCA\Empleados\Db\equiposMapper;
use OCA\Empleados\Service\AdministrativeReportScopeService;
use PHPUnit\Framework\TestCase;

final class AdministrativeReportScopeServiceTest extends TestCase {
	public function testCollectDescendantsIsTransitiveAndCycleSafe(): void {
		$service = $this->serviceWithEmptyMappers();

		$result = $service->collectDescendantIds(1, [
			['id_empleado' => 1, 'id_dependiente' => 2],
			['id_empleado' => 2, 'id_dependiente' => 3],
			['id_empleado' => 3, 'id_dependiente' => 1],
			['id_empleado' => 1, 'id_dependiente' => 2],
			['id_empleado' => 1, 'id_dependiente' => 1],
			['id_empleado' => 2, 'id_dependiente' => 99],
		], [1 => true, 2 => true, 3 => true]);

		sort($result, SORT_NUMERIC);
		$this->assertSame([1, 2, 3], $result);
	}

	public function testScopedUserCannotSeeTeamsOutsideTheirHierarchy(): void {
		$employees = $this->createStub(empleadosMapper::class);
		$organigram = $this->createStub(empleadosorganigramaMapper::class);
		$teams = $this->createMock(equiposMapper::class);

		$employees->method('getReportingDirectory')->with(false)->willReturn([
			$this->employee(1, 'boss', 10),
			$this->employee(2, 'manager', 10),
			$this->employee(3, 'worker', 10),
			$this->employee(4, 'outsider', 20),
		]);
		$organigram->method('getRelationIds')->willReturn([
			['id_empleado' => 1, 'id_dependiente' => 2],
			['id_empleado' => 2, 'id_dependiente' => 3],
			['id_empleado' => 3, 'id_dependiente' => 1],
		]);
		$teams->method('getReportingTeams')->willReturn([
			$this->team(10, 1, 'Equipo del jefe'),
			$this->team(11, 2, 'Equipo de B'),
			$this->team(12, 3, 'Equipo de C'),
			$this->team(20, 4, 'Equipo externo'),
		]);
		$teams->expects($this->once())
			->method('getReportingMembers')
			->with([10, 11, 12])
			->willReturn([
				$this->employee(1, 'boss', 10),
				$this->employee(2, 'manager', 11),
				$this->employee(3, 'worker', 12),
			]);

		$service = new AdministrativeReportScopeService($employees, $organigram, $teams);
		$scope = $service->getScope('boss', false);

		$this->assertSame([1, 2, 3], $scope['hierarchy_employee_ids']);
		$this->assertSame([1, 2, 3], $scope['employee_ids']);
		$this->assertSame([10, 11, 12], $scope['team_ids']);
		$this->assertNotNull($service->findTeam($scope['teams'], 10));
		$this->assertNotNull($service->findTeam($scope['teams'], 11));
		$this->assertNotNull($service->findTeam($scope['teams'], 12));
		$this->assertNull($service->findTeam($scope['teams'], 20));
	}

	public function testTeamAncestorsFollowLeaderMembership(): void {
		$employees = $this->createStub(empleadosMapper::class);
		$organigram = $this->createStub(empleadosorganigramaMapper::class);
		$teams = $this->createStub(equiposMapper::class);

		$employees->method('getReportingDirectory')->with(false)->willReturn([
			$this->employee(1, 'boss', 10),
			$this->employee(2, 'manager', 10),
			$this->employee(3, 'lead', 11),
		]);
		$organigram->method('getRelationIds')->willReturn([
			['id_empleado' => 1, 'id_dependiente' => 2],
			['id_empleado' => 2, 'id_dependiente' => 3],
		]);
		$teams->method('getReportingTeams')->willReturn([
			$this->team(10, 1, 'Auditoria SGVH'),
			$this->team(11, 2, 'Auditoria Especial'),
			$this->team(12, 3, 'Celula junior'),
		]);
		$teams->method('getReportingMembers')->willReturn([
			$this->employee(1, 'boss', 10),
			$this->employee(2, 'manager', 10),
			$this->employee(3, 'lead', 11),
		]);

		$service = new AdministrativeReportScopeService($employees, $organigram, $teams);
		$scope = $service->getScope('boss', false);

		$this->assertSame([], $service->getTeamAncestors($scope, 10));
		$this->assertSame([
			['id_equipo' => 10, 'nombre' => 'Auditoria SGVH'],
		], $service->getTeamAncestors($scope, 11));
		$this->assertSame([
			['id_equipo' => 10, 'nombre' => 'Auditoria SGVH'],
			['id_equipo' => 11, 'nombre' => 'Auditoria Especial'],
		], $service->getTeamAncestors($scope, 12));
	}

	private function serviceWithEmptyMappers(): AdministrativeReportScopeService {
		return new AdministrativeReportScopeService(
			$this->createStub(empleadosMapper::class),
			$this->createStub(empleadosorganigramaMapper::class),
			$this->createStub(equiposMapper::class),
		);
	}

	/** @return array<string,mixed> */
	private function employee(int $id, string $uid, int $teamId): array {
		return [
			'Id_empleados' => $id,
			'Id_user' => $uid,
			'displayname' => ucfirst($uid),
			'Id_equipo' => $teamId,
			'Id_departamento' => 1,
			'Ingreso' => '2020-01-01',
			'Estado' => 1,
			'Sueldo' => 0,
		];
	}

	/** @return array<string,mixed> */
	private function team(int $id, int $leaderId, string $name): array {
		return [
			'Id_equipo' => $id,
			'Nombre' => $name,
			'Id_jefe_equipo' => 'leader-' . $leaderId,
			'id_empleado_lider' => $leaderId,
			'nombre_lider' => 'Leader ' . $leaderId,
			'estado_lider' => 1,
		];
	}
}
