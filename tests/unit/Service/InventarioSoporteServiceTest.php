<?php

declare(strict_types=1);

namespace OCA\Empleados\Tests\Unit\Service;

use OCA\Empleados\Controller\InventarioController;
use OCA\Empleados\Db\empleadosMapper;
use OCA\Empleados\Db\InventarioComputoMapper;
use OCA\Empleados\Db\InventarioMovimiento;
use OCA\Empleados\Db\InventarioMovimientoMapper;
use OCA\Empleados\Db\SoporteHistorialMapper;
use OCA\Empleados\Service\InventarioMovimientoService;
use OCA\Empleados\Service\PermisosService;
use OCA\Empleados\Service\SoporteReporteTiempoService;
use OCP\AppFramework\Http;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

class InventarioSoporteServiceTest extends TestCase {
	public function testCreacionValidaGeneraSoporteYMovimiento(): void {
		[$service, $db, $computo, $movimientos, $empleados, $soporte] = $this->dependencies();
		$computo->expects($this->once())->method('findById')->with(8)->willReturn([
			'id_equipo' => 8,
			'empleado_id' => 3,
			'empleado_uid' => 'ana',
			'estado' => 'activo',
		]);
		$empleados->method('GetMyEmployeeInfoByIdEmpleado')->with('3')->willReturn([['Id_user' => 'ana']]);
		$empleados->method('getDisplayNameById')->with(3)->willReturn('Ana Pérez');
		$soporte->expects($this->once())->method('create')->with($this->callback(function (array $data): bool {
			return $data['id_equipo'] === 8
				&& $data['accion'] === 'reparacion · alta'
				&& $data['usuario_actual'] === 'ana'
				&& $data['usuario_soporte'] === 'tecnico';
		}))->willReturn(31);
		$movimientos->expects($this->once())->method('insert')->with($this->callback(function (InventarioMovimiento $movimiento): bool {
			return $movimiento->getTipoMovimiento() === InventarioMovimiento::TIPO_REPARACION
				&& str_contains((string)$movimiento->getDescripcion(), 'pantalla');
		}))->willReturnCallback(fn(InventarioMovimiento $movimiento): InventarioMovimiento => $movimiento);
		$db->expects($this->once())->method('beginTransaction');
		$db->expects($this->once())->method('commit');

		$result = $service->registrarSoporte(8, 'Revisión de pantalla', 'reparacion', 'alta', null, 75, '2026-08-01 10:00:00');

		$this->assertSame(31, $result['id_soporte']);
	}

	public function testEquipoInexistenteNoCreaSoporte(): void {
		[$service, $db, $computo, $movimientos, , $soporte] = $this->dependencies();
		$computo->expects($this->once())->method('findById')->with(999)->willReturn(null);
		$soporte->expects($this->never())->method('create');
		$movimientos->expects($this->never())->method('insert');
		$db->expects($this->never())->method('beginTransaction');

		$this->expectException(\RuntimeException::class);
		$service->registrarSoporte(999, 'Diagnóstico', 'diagnostico', 'media', null, 30);
	}

	public function testUsuarioSinPermisoNoPuedeCrearSoporte(): void {
		$permissions = $this->createMock(PermisosService::class);
		$permissions->expects($this->once())->method('canSee')->with('inventario.admin')->willReturn(false);
		$service = $this->createMock(InventarioMovimientoService::class);
		$service->expects($this->never())->method('registrarSoporte');
		$reflection = new \ReflectionClass(InventarioController::class);
		$controller = $reflection->newInstanceWithoutConstructor();
		foreach (['permisosService' => $permissions, 'movimientoService' => $service] as $name => $value) {
			$reflection->getProperty($name)->setValue($controller, $value);
		}

		$response = $controller->CrearSoporteEquipo(8, null, 'Diagnóstico', null, null, null, 'diagnostico', 'media');

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
	}

	public function testPeticionRepetidaNoGeneraDosRegistros(): void {
		[$service, $db, $computo, $movimientos, , $soporte] = $this->dependencies();
		$computo->expects($this->exactly(2))->method('findById')->with(8)->willReturn([
			'id_equipo' => 8,
			'estado' => 'activo',
		]);
		$soporte->expects($this->exactly(2))->method('findRecentDuplicate')
			->willReturnOnConsecutiveCalls(null, ['id_soporte' => 31]);
		$soporte->expects($this->once())->method('create')->willReturn(31);
		$movimientos->expects($this->once())->method('insert')
			->willReturnCallback(fn(InventarioMovimiento $movimiento): InventarioMovimiento => $movimiento);
		$db->expects($this->exactly(2))->method('beginTransaction');
		$db->expects($this->exactly(2))->method('commit');

		$first = $service->registrarSoporte(8, 'Reinicio inesperado', 'diagnostico', 'media', null, 30, '2026-08-01 10:00:00');
		$second = $service->registrarSoporte(8, 'Reinicio inesperado', 'diagnostico', 'media', null, 30, '2026-08-01 10:00:00');

		$this->assertSame(31, $first['id_soporte']);
		$this->assertTrue($second['duplicado']);
	}

	public function testFalloDeReporteRevierteSoporteYMovimiento(): void {
		[$service, $db, $computo, $movimientos, , $soporte, $integration] = $this->dependencies();
		$computo->method('findById')->willReturn(['id_equipo' => 8, 'estado' => 'activo']);
		$soporte->method('create')->willReturn(31);
		$movimientos->method('insert')->willReturnCallback(fn(InventarioMovimiento $movement) => $movement);
		$integration->method('crearDesdeSoporte')->willThrowException(new \RuntimeException('fallo de reporte'));
		$db->expects($this->once())->method('rollBack');

		$this->expectException(\RuntimeException::class);
		$service->registrarSoporte(8, 'Fallo', 'diagnostico', 'media', null, 30, '2026-08-01 10:00:00');
	}

	private function dependencies(): array {
		$db = $this->createMock(IDBConnection::class);
		$session = $this->createMock(IUserSession::class);
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('tecnico');
		$user->method('getDisplayName')->willReturn('Técnico TI');
		$session->method('getUser')->willReturn($user);
		$computo = $this->createMock(InventarioComputoMapper::class);
		$movimientos = $this->createMock(InventarioMovimientoMapper::class);
		$empleados = $this->createMock(empleadosMapper::class);
		$soporte = $this->createMock(SoporteHistorialMapper::class);
		$integration = $this->createMock(SoporteReporteTiempoService::class);
		$integration->method('validarDuracion')->willReturnCallback(fn($value): int => (int)$value);
		$integration->method('normalizarFecha')->willReturnCallback(fn($value): string => $value ?: '2026-08-01 10:00:00');
		$soporte->method('findById')->willReturnCallback(fn(int $id): array => [
			'id_soporte' => $id, 'id_equipo' => 8, 'accion' => 'diagnostico · media',
			'detalles' => 'Soporte', 'fecha' => '2026-08-01 10:00:00',
			'usuario_soporte' => 'tecnico', 'duracion_minutos' => 30,
		]);

		return [
			new InventarioMovimientoService($db, $session, $computo, $movimientos, $empleados, $soporte, $integration),
			$db,
			$computo,
			$movimientos,
			$empleados,
			$soporte,
			$integration,
		];
	}
}
