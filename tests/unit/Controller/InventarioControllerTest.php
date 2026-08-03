<?php

declare(strict_types=1);

namespace OCA\Empleados\Tests\Unit\Controller;

use OCA\Empleados\Controller\InventarioController;
use OCA\Empleados\Db\InventarioComputoMapper;
use OCA\Empleados\Service\PermisosService;
use OCP\AppFramework\Http;
use PHPUnit\Framework\TestCase;

class InventarioControllerTest extends TestCase {
	public function testGetInventarioEquipoReturnsAuthorizedDevice(): void {
		$permissions = $this->createMock(PermisosService::class);
		$permissions->expects($this->once())->method('canSee')->with('inventario')->willReturn(true);
		$mapper = $this->createMock(InventarioComputoMapper::class);
		$mapper->expects($this->once())->method('findById')->with(17)->willReturn([
			'id_equipo' => 17,
			'nombre_dispositivo' => 'Laptop RH',
		]);

		$response = $this->controller($permissions, $mapper)->GetInventarioEquipo(17);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(17, $response->getData()['data']['id_equipo']);
	}

	public function testGetInventarioEquipoRejectsUnauthorizedUser(): void {
		$permissions = $this->createMock(PermisosService::class);
		$permissions->expects($this->once())->method('canSee')->with('inventario')->willReturn(false);
		$mapper = $this->createMock(InventarioComputoMapper::class);
		$mapper->expects($this->never())->method('findById');

		$response = $this->controller($permissions, $mapper)->GetInventarioEquipo(17);

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
	}

	public function testGetInventarioEquipoReturnsNotFound(): void {
		$permissions = $this->createMock(PermisosService::class);
		$permissions->method('canSee')->with('inventario')->willReturn(true);
		$mapper = $this->createMock(InventarioComputoMapper::class);
		$mapper->expects($this->once())->method('findById')->with(999)->willReturn(null);

		$response = $this->controller($permissions, $mapper)->GetInventarioEquipo(999);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	private function controller(PermisosService $permissions, InventarioComputoMapper $mapper): InventarioController {
		$reflection = new \ReflectionClass(InventarioController::class);
		$controller = $reflection->newInstanceWithoutConstructor();
		foreach (['permisosService' => $permissions, 'computoMapper' => $mapper] as $property => $value) {
			$target = $reflection->getProperty($property);
			$target->setValue($controller, $value);
		}

		return $controller;
	}
}
