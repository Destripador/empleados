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

	public function testReasignarDirectamenteEsRechazado(): void {
		[$service, $db, $computo, $movimientos, $empleados] = $this->dependencies();
		$old = ['id_equipo' => 8, 'id_empleado' => 1, 'id_modelo' => 2, 'estado' => 'activo'];
		$new = ['id_empleado' => 2, 'id_modelo' => 2, 'estado' => 'activo'];
		$computo->method('findById')->with(8)->willReturn($old);
		$computo->expects($this->never())->method('updateById');
		$movimientos->expects($this->never())->method('insert');
		$db->expects($this->never())->method('beginTransaction');

		$this->expectException(\DomainException::class);
		$service->actualizarEquipo(8, $new);
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

	public function testAsignarEquipoDisponibleActualizaRelacionYRegistraMovimiento(): void {
		[$service, $db, $computo, $movimientos, $empleados] = $this->dependencies();
		$empleados->method('GetMyEmployeeInfoByIdEmpleado')->with('4')->willReturn([['Id_user' => 'ana']]);
		$empleados->method('getDisplayNameById')->with(4)->willReturn('Ana');
		$computo->method('findById')->with(15)->willReturn(['id_equipo' => 15, 'id_empleado' => null, 'estado' => 'activo']);
		$computo->expects($this->once())->method('updateEmpleado')->with(15, 4, null)->willReturn(true);
		$movimientos->expects($this->once())->method('insert')->willReturnCallback(function (InventarioMovimiento $movimiento): InventarioMovimiento {
			$this->assertSame(InventarioMovimiento::TIPO_ASIGNACION, $movimiento->getTipoMovimiento());
			$this->assertSame('ana', $movimiento->getEmpleadoNuevoUid());
			return $movimiento;
		});
		$db->expects($this->once())->method('commit');

		$service->asignarEquipo(15, 4);
	}

	public function testAsignarEquipoOcupadoPorOtroEmpleadoEsRechazado(): void {
		[$service, $db, $computo, $movimientos, $empleados] = $this->dependencies();
		$empleados->method('GetMyEmployeeInfoByIdEmpleado')->willReturn([['Id_user' => 'ana']]);
		$computo->method('findById')->with(15)->willReturn(['id_equipo' => 15, 'id_empleado' => 9, 'estado' => 'activo']);
		$computo->expects($this->never())->method('updateEmpleado');
		$movimientos->expects($this->never())->method('insert');
		$db->expects($this->once())->method('rollBack');

		$this->expectException(\DomainException::class);
		$service->asignarEquipo(15, 4);
	}

	public function testSincronizarAgregaTercerEquipoYNormalizaDuplicados(): void {
		[$service, $db, $computo, $movimientos, $empleados] = $this->dependencies();
		$empleados->method('GetMyEmployeeInfoByIdEmpleado')->willReturn([['Id_user' => 'ana']]);
		$empleados->method('getDisplayNameById')->willReturn('Ana');
		$computo->method('findByEmpleado')->with(4)->willReturn([
			['id_equipo' => 15],
			['id_equipo' => 27],
		]);
		$computo->method('findById')->with(33)->willReturn(['id_equipo' => 33, 'id_empleado' => null, 'estado' => 'activo']);
		$computo->expects($this->once())->method('updateEmpleado')->with(33, 4, null)->willReturn(true);
		$movimientos->expects($this->once())->method('insert')->willReturn(new InventarioMovimiento());
		$db->expects($this->once())->method('commit');

		$service->sincronizarEquiposEmpleado(4, [15, '27', 33, 33, '']);
	}

	public function testSincronizarDesasignaUnoDeDosSinAfectarElOtro(): void {
		[$service, $db, $computo, $movimientos, $empleados] = $this->dependencies();
		$empleados->method('GetMyEmployeeInfoByIdEmpleado')->willReturn([['Id_user' => 'ana']]);
		$empleados->method('getDisplayNameById')->willReturn('Ana');
		$computo->method('findByEmpleado')->with(4)->willReturn([
			['id_equipo' => 15],
			['id_equipo' => 27],
		]);
		$computo->method('findById')->with(27)->willReturn(['id_equipo' => 27, 'id_empleado' => 4, 'estado' => 'activo']);
		$computo->expects($this->once())->method('updateEmpleado')->with(27, null, 4)->willReturn(true);
		$movimientos->expects($this->once())->method('insert')->willReturnCallback(function (InventarioMovimiento $movimiento): InventarioMovimiento {
			$this->assertSame(InventarioMovimiento::TIPO_DESASIGNACION, $movimiento->getTipoMovimiento());
			return $movimiento;
		});
		$db->expects($this->once())->method('commit');

		$service->sincronizarEquiposEmpleado(4, [15]);
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
