<?php

declare(strict_types=1);

namespace OCA\Empleados\Tests\Unit\Controller;

use OCA\Empleados\Controller\reportetiempoController;
use OCA\Empleados\Db\actividadesMapper;
use OCA\Empleados\Db\clientesMapper;
use OCA\Empleados\Db\configuracionesMapper;
use OCA\Empleados\Db\departamentosMapper;
use OCA\Empleados\Db\empleadosMapper;
use OCA\Empleados\Db\empleadosorganigramaMapper;
use OCA\Empleados\Db\equiposMapper;
use OCA\Empleados\Db\festivosMapper;
use OCA\Empleados\Db\historialausenciasMapper;
use OCA\Empleados\Db\reportetiempoMapper;
use OCA\Empleados\Service\AdministrativeReportScopeService;
use OCA\Empleados\Service\PermisosService;
use OCA\Empleados\Service\ReporteTiempoComplianceService;
use OCA\Empleados\Service\VacacionesCalculoService;
use OCP\AppFramework\Http;
use OCP\Group\ISubAdmin;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Mail\IMailer;
use OCP\Notification\IManager as INotificationManager;
use PHPUnit\Framework\TestCase;

final class AdminTeamReportAccessTest extends TestCase {
	public function testForeignTeamAndEmployeeIdsReturnForbidden(): void {
		$employees = $this->createStub(empleadosMapper::class);
		$employees->method('getReportingDirectory')->with(false)->willReturn([[
			'Id_empleados' => 1,
			'Id_user' => 'boss',
			'displayname' => 'Boss',
			'Id_equipo' => 10,
			'Id_departamento' => 1,
			'Ingreso' => '2020-01-01',
			'Estado' => 1,
			'Sueldo' => 0,
		]]);
		$employees->method('GetMyEmployeeInfo')->willReturn([[
			'Id_empleados' => 1,
			'Id_user' => 'boss',
		]]);
		$organigram = $this->createStub(empleadosorganigramaMapper::class);
		$organigram->method('getRelationIds')->willReturn([]);
		$teams = $this->createStub(equiposMapper::class);
		$teams->method('getReportingTeams')->willReturn([[
			'Id_equipo' => 10,
			'Nombre' => 'Visible',
			'Id_jefe_equipo' => 'boss',
			'id_empleado_lider' => 1,
			'nombre_lider' => 'Boss',
			'estado_lider' => 1,
		]]);
		$teams->method('getReportingMembers')->willReturn([]);

		$scope = new AdministrativeReportScopeService($employees, $organigram, $teams);
		$configurations = $this->createStub(configuracionesMapper::class);
		$configurations->method('GetConfig')->willReturn([]);
		$user = $this->createStub(IUser::class);
		$user->method('getUID')->willReturn('boss');
		$session = $this->createStub(IUserSession::class);
		$session->method('getUser')->willReturn($user);
		$permissions = $this->createMock(PermisosService::class);
		$permissions->expects($this->exactly(2))->method('requireCanSeeAny');
		$permissions->method('canSee')->willReturn(false);

		$controller = new reportetiempoController(
			$this->createStub(IRequest::class),
			$session,
			$this->createStub(IUserManager::class),
			$employees,
			$this->createStub(reportetiempoMapper::class),
			$configurations,
			$this->createStub(clientesMapper::class),
			$this->createStub(actividadesMapper::class),
			$this->createStub(historialausenciasMapper::class),
			$this->createStub(IL10N::class),
			$this->createStub(IConfig::class),
			$this->createStub(IGroupManager::class),
			$this->createStub(IURLGenerator::class),
			$this->createStub(IClientService::class),
			$this->createStub(IMailer::class),
			$this->createStub(ISubAdmin::class),
			$this->createStub(INotificationManager::class),
			$this->createStub(VacacionesCalculoService::class),
			$permissions,
			$this->createStub(departamentosMapper::class),
			new ReporteTiempoComplianceService(),
			$scope,
			$this->createStub(festivosMapper::class),
		);

		$teamResponse = $controller->GetAdminTeamReport(999);
		$employeeResponse = $controller->findById(999);

		$this->assertSame(Http::STATUS_FORBIDDEN, $teamResponse->getStatus());
		$this->assertSame(Http::STATUS_FORBIDDEN, $employeeResponse->getStatus());
	}
}
