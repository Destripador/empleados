<?php

declare(strict_types=1);

namespace OCA\Empleados\Tests\Unit\Service;

use Exception;
use OCA\Empleados\Controller\CompraSolicitudController;
use OCA\Empleados\Db\CompraAutorizacion;
use OCA\Empleados\Db\CompraAutorizacionMapper;
use OCA\Empleados\Db\CompraDetalleMapper;
use OCA\Empleados\Db\CompraHistorial;
use OCA\Empleados\Db\CompraHistorialMapper;
use OCA\Empleados\Db\CompraSolicitud;
use OCA\Empleados\Db\CompraSolicitudMapper;
use OCA\Empleados\Db\empleadosMapper;
use OCA\Empleados\Service\CompraFolioService;
use OCA\Empleados\Service\CompraNotificacionService;
use OCA\Empleados\Service\CompraPermisosService;
use OCA\Empleados\Service\CompraSolicitudService;
use OCP\AppFramework\Http;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

class CompraAprobacionServiceTest extends TestCase {
	public function testSolicitanteEnviaBorradorYAsignaGerentePrimero(): void {
		$d = $this->dependencies($this->solicitud('borrador'));
		$d['db']->expects($this->once())->method('beginTransaction');
		$d['db']->expects($this->once())->method('commit');
		$d['solicitudes']->expects($this->once())->method('cambiarEstadoSiActual')
			->with(7, 'borrador', 'pendiente_autorizacion', 'solicitante', 'fecha_envio')
			->willReturn(true);
		$d['autorizaciones']->expects($this->exactly(2))->method('insertStage')
			->willReturnCallback(function (int $id, array $stage): CompraAutorizacion {
				$this->assertSame(7, $id);
				if ($stage['nivel'] === 1) {
					$this->assertSame('gerente', $stage['rol']);
					$this->assertSame('gerente', $stage['uid']);
				}
				return $this->stage($stage['uid'], $stage['rol'], $stage['nivel']);
			});
		$d['autorizaciones']->method('hasAssignments')->willReturnOnConsecutiveCalls(false, true);
		$d['autorizaciones']->method('findCurrent')->willReturn($this->stage('gerente', 'gerente', 1));
		$d['historial']->expects($this->once())->method('insertHistorial')->willReturn(new CompraHistorial());
		$d['notifications']->expects($this->once())->method('notificarAprobadorActual')
			->with($this->isInstanceOf(CompraSolicitud::class), 'gerente', 'gerente');

		$result = $d['service']->enviarAutorizacion(7, 'solicitante');

		$this->assertSame('borrador', $result['solicitud']->getEstado());
	}

	public function testUsuarioDistintoDelAprobadorRecibe403(): void {
		$service = $this->createMock(CompraSolicitudService::class);
		$service->method('autorizar')->willThrowException(new Exception(
			'No tienes permiso: solamente el aprobador actual puede autorizar esta etapa.'
		));
		$request = $this->createMock(IRequest::class);
		$request->method('getParams')->willReturn([]);
		$permissions = $this->createMock(CompraPermisosService::class);
		$session = $this->createMock(IUserSession::class);
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('intruso');
		$session->method('getUser')->willReturn($user);
		$controller = new CompraSolicitudController('empleados', $request, $service, $permissions, $session);

		$this->assertSame(Http::STATUS_FORBIDDEN, $controller->approve(7)->getStatus());
	}

	public function testGerenteCorrectoAvanzaALaEtapaSocio(): void {
		$d = $this->dependencies($this->solicitud('pendiente_autorizacion'));
		$manager = $this->stage('gerente', 'gerente', 1, 11);
		$partner = $this->stage('socio', 'socio', 2, 12);
		$d['autorizaciones']->method('hasAssignments')->willReturn(true);
		$d['autorizaciones']->method('findCurrent')->willReturnOnConsecutiveCalls($manager, $partner);
		$d['autorizaciones']->expects($this->once())->method('resolvePending')->with(11, 'aprobada', null)->willReturn(true);
		$d['solicitudes']->expects($this->never())->method('cambiarEstadoSiActual');
		$d['historial']->expects($this->once())->method('insertHistorial')->with(
			7,
			'etapa_autorizada',
			'pendiente_autorizacion',
			'pendiente_autorizacion',
			$this->anything(),
			$this->callback(fn(array $metadata): bool => $metadata['rol'] === 'gerente'),
			'gerente'
		)->willReturn(new CompraHistorial());
		$d['notifications']->expects($this->once())->method('notificarAprobadorActual')
			->with($this->anything(), 'socio', 'socio');

		$d['service']->autorizar(7, 'gerente');
	}

	public function testUltimoAprobadorCompletaSolicitud(): void {
		$d = $this->dependencies($this->solicitud('pendiente_autorizacion'));
		$partner = $this->stage('socio', 'socio', 2, 12);
		$d['autorizaciones']->method('hasAssignments')->willReturn(true);
		$d['autorizaciones']->method('findCurrent')->willReturnOnConsecutiveCalls($partner, null);
		$d['autorizaciones']->method('resolvePending')->willReturn(true);
		$d['solicitudes']->expects($this->once())->method('cambiarEstadoSiActual')
			->with(7, 'pendiente_autorizacion', 'autorizada', 'socio', 'fecha_autorizacion')
			->willReturn(true);
		$d['historial']->expects($this->once())->method('insertHistorial')->willReturn(new CompraHistorial());
		$d['notifications']->expects($this->once())->method('notificarSolicitudAutorizada');

		$d['service']->autorizar(7, 'socio', 'Conforme');
	}

	public function testRechazoDetieneFlujo(): void {
		$d = $this->dependencies($this->solicitud('pendiente_autorizacion'));
		$manager = $this->stage('gerente', 'gerente', 1, 11);
		$d['autorizaciones']->method('hasAssignments')->willReturn(true);
		$d['autorizaciones']->method('findCurrent')->willReturn($manager);
		$d['autorizaciones']->expects($this->once())->method('resolvePending')->with(11, 'rechazada', 'Falta información')->willReturn(true);
		$d['autorizaciones']->expects($this->once())->method('cancelPendingBySolicitud')->with(7);
		$d['solicitudes']->expects($this->once())->method('cambiarEstadoSiActual')
			->with(7, 'pendiente_autorizacion', 'rechazada', 'gerente', null)
			->willReturn(true);
		$d['historial']->expects($this->once())->method('insertHistorial')->willReturn(new CompraHistorial());
		$d['notifications']->expects($this->once())->method('notificarSolicitudRechazada');

		$d['service']->rechazar(7, 'gerente', 'Falta información');
	}

	public function testSegundaAprobacionFallaSinDuplicarHistorial(): void {
		$d = $this->dependencies($this->solicitud('pendiente_autorizacion'));
		$d['autorizaciones']->method('hasAssignments')->willReturn(true);
		$d['autorizaciones']->method('findCurrent')->willReturn($this->stage('gerente', 'gerente', 1, 11));
		$d['autorizaciones']->method('resolvePending')->willReturn(false);
		$d['historial']->expects($this->never())->method('insertHistorial');
		$d['db']->expects($this->once())->method('rollBack');

		$this->expectException(Exception::class);
		$d['service']->autorizar(7, 'gerente');
	}

	public function testFalloDeHistorialRevierteLaTransaccion(): void {
		$d = $this->dependencies($this->solicitud('pendiente_autorizacion'));
		$manager = $this->stage('gerente', 'gerente', 1, 11);
		$partner = $this->stage('socio', 'socio', 2, 12);
		$d['autorizaciones']->method('hasAssignments')->willReturn(true);
		$d['autorizaciones']->method('findCurrent')->willReturnOnConsecutiveCalls($manager, $partner);
		$d['autorizaciones']->method('resolvePending')->willReturn(true);
		$d['historial']->method('insertHistorial')->willThrowException(new \RuntimeException('fallo historial'));
		$d['db']->expects($this->once())->method('rollBack');
		$d['db']->expects($this->never())->method('commit');
		$d['notifications']->expects($this->never())->method('notificarAprobadorActual');

		$this->expectException(\RuntimeException::class);
		$d['service']->autorizar(7, 'gerente');
	}

	private function dependencies(CompraSolicitud $solicitud): array {
		$solicitudes = $this->createMock(CompraSolicitudMapper::class);
		$solicitudes->method('find')->with(7)->willReturn($solicitud);
		$detalles = $this->createMock(CompraDetalleMapper::class);
		$detalles->method('findBySolicitud')->willReturn([]);
		$historial = $this->createMock(CompraHistorialMapper::class);
		$historial->method('findBySolicitud')->willReturn([]);
		$folio = $this->createMock(CompraFolioService::class);
		$permissions = $this->createMock(CompraPermisosService::class);
		$permissions->method('canViewSolicitud')->willReturn(true);
		$empleados = $this->createMock(empleadosMapper::class);
		$empleados->method('GetMyEmployeeInfoByIdEmpleado')->willReturn([[
			'Id_empleados' => 10,
			'Id_user' => 'solicitante',
			'Id_gerente' => 'gerente',
			'Id_socio' => 'socio',
		]]);
		$empleados->method('GetMyEmployeeInfo')->willReturnCallback(static fn(string $uid): array => [[
			'Id_empleados' => $uid === 'gerente' ? 20 : 30,
			'Id_user' => $uid,
		]]);
		$notifications = $this->createMock(CompraNotificacionService::class);
		$autorizaciones = $this->createMock(CompraAutorizacionMapper::class);
		$autorizaciones->method('findBySolicitud')->willReturn([]);
		$db = $this->createMock(IDBConnection::class);
		$userManager = $this->createMock(IUserManager::class);
		$users = [];
		foreach (['solicitante', 'gerente', 'socio', 'intruso'] as $uid) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn($uid);
			$user->method('getDisplayName')->willReturn(ucfirst($uid));
			$user->method('isEnabled')->willReturn(true);
			$users[$uid] = $user;
		}
		$userManager->method('get')->willReturnCallback(static fn(string $uid) => $users[$uid] ?? null);

		return [
			'service' => new CompraSolicitudService(
				$solicitudes,
				$detalles,
				$historial,
				$folio,
				$permissions,
				$empleados,
				$notifications,
				$autorizaciones,
				$db,
				$userManager
			),
			'solicitudes' => $solicitudes,
			'historial' => $historial,
			'autorizaciones' => $autorizaciones,
			'notifications' => $notifications,
			'db' => $db,
		];
	}

	private function solicitud(string $estado): CompraSolicitud {
		$solicitud = new CompraSolicitud();
		$solicitud->setIdSolicitud(7);
		$solicitud->setIdUser('solicitante');
		$solicitud->setIdEmpleado('10');
		$solicitud->setFolio('COMP-0007');
		$solicitud->setTitulo('Equipo de cómputo');
		$solicitud->setEstado($estado);
		return $solicitud;
	}

	private function stage(string $uid, string $role, int $level, int $id = 1): CompraAutorizacion {
		$stage = new CompraAutorizacion();
		$stage->setIdAutorizacion($id);
		$stage->setIdSolicitud(7);
		$stage->setIdAutorizador($uid);
		$stage->setAutorizadorNombre(ucfirst($uid));
		$stage->setRol($role);
		$stage->setNivel($level);
		$stage->setEstado('pendiente');
		return $stage;
	}
}
