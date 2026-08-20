<?php

declare(strict_types=1);

namespace OCA\Empleados\Tests\Unit\Controller;

use OCA\Empleados\Controller\EspacioController;
use OCA\Empleados\Controller\EstacionamientoController;
use OCA\Empleados\Db\espacioEmpleadosMapper;
use OCA\Empleados\Db\espacioMapper;
use OCA\Empleados\Service\ParkingModeService;
use OCP\AppFramework\Http;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

class ParkingMaintenanceAccessTest extends TestCase {
	public function testRegularUserCannotReadAssignmentsDuringMaintenance(): void {
		$parkingMode = $this->createMock(ParkingModeService::class);
		$parkingMode->method('canViewSensitiveData')->with('alice')->willReturn(false);
		$parkingMode->method('getStatus')->willReturn($this->maintenanceStatus(false));
		$mapper = $this->createMock(espacioEmpleadosMapper::class);
		$mapper->expects($this->never())->method('getEspacioEmpleado');

		$response = $this->parkingController($parkingMode, $mapper)->GetEmpleadosConEspacio();

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
		$this->assertTrue($response->getData()['blocked']);
		$this->assertArrayNotHasKey('empleados', $response->getData());
	}

	public function testOperationalModeReturnsAssignments(): void {
		$parkingMode = $this->createMock(ParkingModeService::class);
		$parkingMode->method('canViewSensitiveData')->with('alice')->willReturn(true);
		$mapper = $this->createMock(espacioEmpleadosMapper::class);
		$mapper->expects($this->once())->method('getEspacioEmpleado')->willReturn([['uid' => 'alice']]);

		$response = $this->parkingController($parkingMode, $mapper)->GetEmpleadosConEspacio();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame([['uid' => 'alice']], $response->getData()['empleados']);
	}

	public function testAdministratorCanReadAssignmentsDuringMaintenance(): void {
		$parkingMode = $this->createMock(ParkingModeService::class);
		$parkingMode->method('canViewSensitiveData')->with('alice')->willReturn(true);
		$mapper = $this->createMock(espacioEmpleadosMapper::class);
		$mapper->expects($this->once())->method('getEspacioEmpleado')->willReturn([]);

		$response = $this->parkingController($parkingMode, $mapper)->GetEmpleadosConEspacio();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testBaseMapEndpointDoesNotQueryMapperWhenBlocked(): void {
		$parkingMode = $this->createMock(ParkingModeService::class);
		$parkingMode->method('canViewSensitiveData')->with('alice')->willReturn(false);
		$parkingMode->method('getStatus')->willReturn($this->maintenanceStatus(false));
		$mapper = $this->createMock(espacioMapper::class);
		$mapper->expects($this->never())->method('findAllOrdered');

		$response = $this->spaceController($parkingMode, $mapper)->GetEspacios();

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
		$this->assertTrue($response->getData()['maintenance']);
	}

	public function testAssignmentMutationRequiresAdministrator(): void {
		$parkingMode = $this->createMock(ParkingModeService::class);
		$parkingMode->method('canManage')->with('alice')->willReturn(false);
		$mapper = $this->createMock(espacioEmpleadosMapper::class);
		$mapper->expects($this->never())->method('deleteByEspacio');

		$response = $this->parkingController($parkingMode, $mapper)->GuardarAsignacion(1, [2]);

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
	}

	private function parkingController(
		ParkingModeService $parkingMode,
		espacioEmpleadosMapper $mapper,
	): EstacionamientoController {
		$reflection = new \ReflectionClass(EstacionamientoController::class);
		$controller = $reflection->newInstanceWithoutConstructor();
		$this->setProperties($reflection, $controller, [
			'userSession' => $this->userSession(),
			'l10n' => $this->l10n(),
			'parkingModeService' => $parkingMode,
			'espacioEmpleadosMapper' => $mapper,
		]);

		return $controller;
	}

	private function spaceController(
		ParkingModeService $parkingMode,
		espacioMapper $mapper,
	): EspacioController {
		$reflection = new \ReflectionClass(EspacioController::class);
		$controller = $reflection->newInstanceWithoutConstructor();
		$this->setProperties($reflection, $controller, [
			'userSession' => $this->userSession(),
			'l10n' => $this->l10n(),
			'parkingModeService' => $parkingMode,
			'espacioMapper' => $mapper,
		]);

		return $controller;
	}

	private function userSession(): IUserSession {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);

		return $session;
	}

	private function l10n(): IL10N {
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn(string $message): string => $message);

		return $l10n;
	}

	private function setProperties(\ReflectionClass $reflection, object $object, array $values): void {
		foreach ($values as $name => $value) {
			$property = $reflection->getProperty($name);
			$property->setValue($object, $value);
		}
	}

	private function maintenanceStatus(bool $canManage): array {
		return [
			'mode' => ParkingModeService::MODE_MAINTENANCE,
			'maintenance' => true,
			'canManage' => $canManage,
			'canViewSensitiveData' => $canManage,
			'reason' => null,
			'startedAt' => '2026-08-18T10:00:00-06:00',
			'until' => null,
		];
	}
}
