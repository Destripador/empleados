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

class InventarioMovimientoServiceTest extends TestCase {
	public function testCrearEquipoRegistraAlta(): void {
		[$service, $db, $computo, $movimientos] = $this->dependencies();
		$db->expects($this->once())->method('beginTransaction');
		$db->expects($this->once())->method('commit');
		$computo->expects($this->once())->method('create')->willReturn(8);
		$computo->expects($this->once())->method('findById')->with(8)->willReturn([
			'id_equipo' => 8, 'id_empleado' => null, 'estado' => 'activo', 'nombre_dispositivo' => 'Laptop',
		]);
		$movimientos->expects($this->once())->method('insert')->willReturnCallback(function (InventarioMovimiento $movimiento): InventarioMovimiento {
			$this->assertSame(InventarioMovimiento::TIPO_ALTA, $movimiento->getTipoMovimiento());
			$movimiento->setId(1);
			return $movimiento;
		});

		$this->assertSame(8, $service->crearEquipo(['estado' => 'activo', 'nombre_dispositivo' => 'Laptop']));
	}

	public function testReasignarRegistraEmpleadoAnteriorYNuevo(): void {
		[$service, $db, $computo, $movimientos, $empleados] = $this->dependencies();
		$old = ['id_equipo' => 8, 'id_empleado' => 1, 'id_modelo' => 2, 'estado' => 'activo'];
		$new = ['id_empleado' => 2, 'id_modelo' => 2, 'estado' => 'activo'];
		$computo->method('findById')->with(8)->willReturn($old);
		$computo->expects($this->once())->method('updateById');
		$empleados->method('GetMyEmployeeInfoByIdEmpleado')->willReturnCallback(fn(string $id): array => [['Id_user' => $id === '1' ? 'ana' : 'luis']]);
		$empleados->method('getDisplayNameById')->willReturnCallback(fn(int $id): string => $id === 1 ? 'Ana Pérez' : 'Luis Gómez');
		$movimientos->expects($this->once())->method('insert')->willReturnCallback(function (InventarioMovimiento $movimiento): InventarioMovimiento {
			$this->assertSame(InventarioMovimiento::TIPO_REASIGNACION, $movimiento->getTipoMovimiento());
			$this->assertSame('ana', $movimiento->getEmpleadoAnteriorUid());
			$this->assertSame('Luis Gómez', $movimiento->getEmpleadoNuevoNombre());
			return $movimiento;
		});
		$db->expects($this->once())->method('commit');

		$this->assertTrue($service->actualizarEquipo(8, $new));
	}

	public function testEditarSinCambiosNoRegistraMovimiento(): void {
		[$service, $db, $computo, $movimientos] = $this->dependencies();
		$equipo = ['id_equipo' => 8, 'id_empleado' => null, 'id_modelo' => 2, 'estado' => 'activo', 'info' => null];
		$computo->method('findById')->with(8)->willReturn($equipo);
		$computo->expects($this->never())->method('updateById');
		$movimientos->expects($this->never())->method('insert');
		$db->expects($this->never())->method('beginTransaction');

		$this->assertFalse($service->actualizarEquipo(8, $equipo));
	}

	public function testFalloDeActualizacionNoDejaMovimientoHuerfano(): void {
		[$service, $db, $computo, $movimientos] = $this->dependencies();
		$computo->method('findById')->with(8)->willReturn(['id_equipo' => 8, 'estado' => 'activo']);
		$computo->method('updateById')->willThrowException(new \RuntimeException('fallo de escritura'));
		$movimientos->expects($this->never())->method('insert');
		$db->expects($this->once())->method('rollBack');

		$this->expectException(\RuntimeException::class);
		$service->actualizarEquipo(8, ['estado' => 'baja']);
	}

	public function testUsuarioSinPermisoNoConsultaHistorialNiCreaNotas(): void {
		$permissions = $this->createMock(PermisosService::class);
		$permissions->method('canSee')->willReturn(false);
		$permissions->method('canSeeAny')->willReturn(false);
		$history = $this->createMock(InventarioMovimientoService::class);
		$history->expects($this->never())->method('listarHistorial');
		$history->expects($this->never())->method('registrarNota');
		$reflection = new \ReflectionClass(InventarioController::class);
		$controller = $reflection->newInstanceWithoutConstructor();
		foreach (['permisosService' => $permissions, 'movimientoService' => $history] as $name => $value) {
			$reflection->getProperty($name)->setValue($controller, $value);
		}

		$this->assertSame(Http::STATUS_FORBIDDEN, $controller->GetInventarioHistorial(8)->getStatus());
		$this->assertSame(Http::STATUS_FORBIDDEN, $controller->CrearInventarioNota(8, 'Nota')->getStatus());
	}

	private function dependencies(): array {
		$db = $this->createMock(IDBConnection::class);
		$session = $this->createMock(IUserSession::class);
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('actor');
		$user->method('getDisplayName')->willReturn('Usuario Actor');
		$session->method('getUser')->willReturn($user);
		$computo = $this->createMock(InventarioComputoMapper::class);
		$movimientos = $this->createMock(InventarioMovimientoMapper::class);
		$empleados = $this->createMock(empleadosMapper::class);
		$soporte = $this->createMock(SoporteHistorialMapper::class);
		$integration = $this->createMock(SoporteReporteTiempoService::class);

		return [
			new InventarioMovimientoService($db, $session, $computo, $movimientos, $empleados, $soporte, $integration),
			$db, $computo, $movimientos, $empleados, $soporte,
		];
	}
}
