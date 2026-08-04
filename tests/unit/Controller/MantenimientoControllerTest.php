<?php

declare(strict_types=1);

namespace OCA\Empleados\Tests\Unit\Controller;

use OCA\Empleados\Controller\MantenimientoController;
use OCA\Empleados\Db\configuracionesMapper;
use OCA\Empleados\Db\empleadosMapper;
use OCA\Empleados\Exception\MantenimientoConflictException;
use OCA\Empleados\Exception\MantenimientoNotFoundException;
use OCA\Empleados\Exception\MantenimientoStorageException;
use OCA\Empleados\Exception\MantenimientoTransitionException;
use OCA\Empleados\Exception\MantenimientoValidationException;
use OCA\Empleados\Service\MantenimientoService;
use OCA\Empleados\Service\PermisosService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class MantenimientoControllerTest extends TestCase {
	public function testRejectsAnonymousUserAndDisabledModule(): void {
		$d = $this->dependencies('admin', false);
		$this->assertSame(Http::STATUS_UNAUTHORIZED, $d['controller']->groups('2026-08-01', '2026-08-02')->getStatus());

		$d = $this->dependencies('admin', true, false);
		$this->assertSame(Http::STATUS_FORBIDDEN, $d['controller']->groups('2026-08-01', '2026-08-02')->getStatus());
	}

	public function testAdminTechnicianAndViewerCanListWithCorrectTechnicianScope(): void {
		foreach (['admin' => 'someone', 'technician' => 'tech', 'view' => 'someone'] as $role => $expectedTechnician) {
			$d = $this->dependencies($role);
			$d['service']->expects($this->once())->method('listGroups')->with(
				'2026-08-01', '2026-08-31', null, $expectedTechnician, null, null, null, 50, 0,
			)->willReturn(['items' => [], 'total' => 0, 'limit' => 50, 'offset' => 0]);

			$response = $d['controller']->groups('2026-08-01', '2026-08-31', null, 'someone');

			$this->assertSame(Http::STATUS_OK, $response->getStatus());
		}
	}

	public function testUserWithoutMaintenancePermissionReceivesForbidden(): void {
		$d = $this->dependencies('none');
		$d['service']->expects($this->never())->method('listGroups');

		$this->assertSame(Http::STATUS_FORBIDDEN, $d['controller']->groups('2026-08-01', '2026-08-31')->getStatus());
	}

	public function testTechniciansReturnsAuthorizedEnabledUsersWithoutPrivateData(): void {
		$d = $this->dependencies('admin');
		$d['permissions']->expects($this->exactly(2))->method('getUsersWithPermission')->willReturnMap([
			['inventario.technician', ['tech', 'both', 'disabled', 'missing']],
			['inventario.admin', ['admin-tech', 'both']],
		]);
		$users = [
			'tech' => $this->catalogUser('tech', 'Zeta Técnico'),
			'both' => $this->catalogUser('both', 'Ana Técnica'),
			'disabled' => $this->catalogUser('disabled', 'Deshabilitado', false),
			'admin-tech' => $this->catalogUser('admin-tech', ''),
		];
		$d['userManager']->method('get')->willReturnCallback(fn(string $uid) => $users[$uid] ?? null);

		$response = $d['controller']->technicians();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$items = $response->getData()['data']['items'];
		$this->assertSame([
			['uid' => 'admin-tech', 'displayName' => 'admin-tech'],
			['uid' => 'both', 'displayName' => 'Ana Técnica'],
			['uid' => 'tech', 'displayName' => 'Zeta Técnico'],
		], $items);
		$this->assertSame(['uid', 'displayName'], array_keys($items[0]));
	}

	public function testTechniciansRejectsUserWithoutAccessAndDisabledModule(): void {
		$this->assertSame(Http::STATUS_FORBIDDEN, $this->dependencies('none')['controller']->technicians()->getStatus());
		$this->assertSame(Http::STATUS_FORBIDDEN, $this->dependencies('admin', true, false)['controller']->technicians()->getStatus());
	}

	public function testTechnicianCanOpenAssignedButNotForeignMaintenance(): void {
		$d = $this->dependencies('technician');
		$d['service']->method('getMaintenanceRecord')->willReturnOnConsecutiveCalls(
			$this->maintenance('tech'), $this->maintenance('other'),
		);
		$d['service']->expects($this->once())->method('getMaintenance')->willReturn($this->detail());

		$this->assertSame(Http::STATUS_OK, $d['controller']->maintenance(11)->getStatus());
		$this->assertSame(Http::STATUS_FORBIDDEN, $d['controller']->maintenance(12)->getStatus());
	}

	public function testViewerDetailDoesNotExposeRestrictedFieldsOrAudit(): void {
		$d = $this->dependencies('view');
		$d['service']->method('getMaintenanceRecord')->willReturn($this->maintenance('tech'));
		$d['service']->method('getMaintenance')->willReturn($this->detail());

		$data = $d['controller']->maintenance(11)->getData()['data'];

		$this->assertSame([], $data['audit']);
		$this->assertArrayNotHasKey('observaciones', $data['maintenance']);
		$this->assertArrayNotHasKey('tecnico_uid', $data['maintenance']);
		$this->assertArrayNotHasKey('observacion', $data['checklist'][0]);
	}

	public function testOnlyAdminCreatesGroupAndActorComesFromSession(): void {
		$d = $this->dependencies('admin');
		$d['service']->expects($this->once())->method('createGroup')->with(
			$this->callback(fn(array $group): bool => $group['titulo'] === 'Agosto'
				&& $group['include_descendants'] === false
				&& $group['fecha_inicio'] === '2026-08-20'
				&& $group['fecha_fin'] === '2026-08-24'),
			[8, 9], 'admin', 'Administrador', false,
		)->willReturn(['group' => ['id' => 7], 'maintenances' => [], 'progress' => [], 'warnings' => []]);

		$response = $d['controller']->createGroup('Agosto', 4, 'false', 'preventive', '2026-08-20', '2026-08-24', '09:00', '10:00', null, null, [8, 9], 'false');

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());

		foreach (['technician', 'view'] as $role) {
			$d = $this->dependencies($role);
			$d['service']->expects($this->never())->method('createGroup');
			$this->assertSame(Http::STATUS_FORBIDDEN, $d['controller']->createGroup('Agosto', 4, false, 'preventive', '2026-08-20', '2026-08-24', null, null, null, null, [8])->getStatus());
		}
	}

	public function testCreateGroupMapsInvalidPeriodToBadRequest(): void {
		$d = $this->dependencies('admin');
		$d['service']->method('createGroup')->willThrowException(new MantenimientoValidationException('La fecha final no puede ser anterior a la fecha inicial.'));

		$response = $d['controller']->createGroup('Agosto', 4, false, 'preventive', '2026-08-24', '2026-08-20', null, null, null, null, [8]);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame('maintenance_validation_error', $response->getData()['error']['code']);
	}

	public function testDuplicateIdsAreDelegatedToDomainValidationAndOversizedListIsRejected(): void {
		$d = $this->dependencies('admin');
		$d['service']->method('createGroup')->willThrowException(new MantenimientoValidationException('La petición contiene equipos repetidos.'));
		$response = $d['controller']->createGroup('Agosto', 4, false, 'preventive', '2026-08-20', '2026-08-24', null, null, null, null, [8, 8]);
		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());

		$d = $this->dependencies('admin');
		$d['service']->expects($this->never())->method('createGroup');
		$response = $d['controller']->createGroup('Agosto', 4, false, 'preventive', '2026-08-20', '2026-08-24', null, null, null, null, range(1, 1001));
		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testAdminAndAssignedTechnicianCanStartButViewerAndForeignTechnicianCannot(): void {
		foreach (['admin' => 'other', 'technician' => 'tech'] as $role => $assigned) {
			$d = $this->dependencies($role);
			$d['service']->method('getMaintenanceRecord')->willReturn($this->maintenance($assigned));
			$d['service']->expects($this->once())->method('startMaintenance')->willReturn($this->maintenance($assigned, 'in_progress'));
			$d['service']->method('getGroupProgress')->willReturn(['total' => 1]);
			$this->assertSame(Http::STATUS_OK, $d['controller']->start(11)->getStatus());
		}

		foreach (['technician' => 'other', 'view' => 'tech'] as $role => $assigned) {
			$d = $this->dependencies($role);
			$d['service']->method('getMaintenanceRecord')->willReturn($this->maintenance($assigned));
			$d['service']->expects($this->never())->method('startMaintenance');
			$this->assertSame(Http::STATUS_FORBIDDEN, $d['controller']->start(11)->getStatus());
		}
	}

	public function testAssignedTechnicianCanScheduleWithSanitizedPayloadAndProgress(): void {
		$d = $this->dependencies('technician');
		$d['service']->method('getMaintenanceRecord')->willReturn($this->maintenance('tech'));
		$d['service']->expects($this->once())->method('scheduleMaintenance')
			->with(11, '2026-08-12', '09:00', '10:00', 'tech', 'Técnico')
			->willReturn($this->maintenance('tech', 'scheduled'));
		$d['service']->method('getGroupProgress')->willReturn(['scheduled' => 1, 'percentage' => 0.0]);

		$response = $d['controller']->schedule(11, '2026-08-12', '09:00', '10:00');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(1, $response->getData()['data']['groupProgress']['scheduled']);
	}

	public function testAssignedTechnicianUpdatesChecklistButForeignCannot(): void {
		$d = $this->dependencies('technician');
		$d['service']->method('getMaintenanceRecord')->willReturn($this->maintenance('tech'));
		$d['service']->expects($this->once())->method('updateChecklist')->with(
			11,
			[['clave' => 'external_cleaning', 'resultado' => 'attention', 'observacion' => 'Revisar']],
			'tech', 'Técnico',
		)->willReturn([]);
		$this->assertSame(Http::STATUS_OK, $d['controller']->updateChecklist(11, [['key' => 'external_cleaning', 'result' => 'attention', 'observation' => 'Revisar', 'label' => 'Ignored']])->getStatus());

		$d = $this->dependencies('technician');
		$d['service']->method('getMaintenanceRecord')->willReturn($this->maintenance('other'));
		$d['service']->expects($this->never())->method('updateChecklist');
		$this->assertSame(Http::STATUS_FORBIDDEN, $d['controller']->updateChecklist(11, [['key' => 'external_cleaning', 'result' => 'ok']])->getStatus());
	}

	public function testTechnicianCannotAssignTechnicianCancelGroupOrCancelMaintenance(): void {
		$d = $this->dependencies('technician');
		$d['service']->expects($this->never())->method('assignTechnician');
		$d['service']->expects($this->never())->method('cancelGroup');
		$d['service']->expects($this->never())->method('cancelMaintenance');

		$this->assertSame(Http::STATUS_FORBIDDEN, $d['controller']->assignTechnician(11, 'tech')->getStatus());
		$this->assertSame(Http::STATUS_FORBIDDEN, $d['controller']->cancelGroup(7, 'Motivo')->getStatus());
		$this->assertSame(Http::STATUS_FORBIDDEN, $d['controller']->cancel(11, 'Motivo')->getStatus());
	}

	public function testAdminCannotAssignUserWithoutInventoryTechnicianPermission(): void {
		$d = $this->dependencies('admin');
		$d['service']->expects($this->never())->method('assignTechnician');

		$response = $d['controller']->assignTechnician(11, 'unauthorized-user');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame('maintenance_validation_error', $response->getData()['error']['code']);
	}

	public function testDomainExceptionsMapToConsistentHttpErrors(): void {
		$cases = [
			[new MantenimientoValidationException('bad'), Http::STATUS_BAD_REQUEST, 'maintenance_validation_error'],
			[new MantenimientoNotFoundException('missing'), Http::STATUS_NOT_FOUND, 'maintenance_not_found'],
			[new MantenimientoTransitionException('transition'), Http::STATUS_CONFLICT, 'maintenance_transition_error'],
			[new MantenimientoConflictException('duplicates', [['id_equipo' => 8, 'id_mantenimiento_existente' => 44]]), Http::STATUS_CONFLICT, 'maintenance_duplicate_conflict'],
			[new MantenimientoStorageException('SQL secret'), Http::STATUS_INTERNAL_SERVER_ERROR, 'maintenance_storage_error'],
		];
		foreach ($cases as [$exception, $status, $code]) {
			$d = $this->dependencies('admin');
			$d['service']->method('listGroups')->willThrowException($exception);
			$response = $d['controller']->groups('2026-08-01', '2026-08-02');
			$body = $response->getData();
			$this->assertSame($status, $response->getStatus());
			$this->assertSame($code, $body['error']['code']);
			if ($exception instanceof MantenimientoStorageException) $this->assertStringNotContainsString('SQL', $body['error']['message']);
			if ($exception instanceof MantenimientoConflictException) $this->assertSame(8, $body['error']['conflicts'][0]['id_equipo']);
		}
	}

	public function testUnexpectedExceptionIsLoggedWithoutPayloadAndReturnsGeneric500(): void {
		$d = $this->dependencies('admin');
		$d['service']->method('listGroups')->willThrowException(new \RuntimeException('sensitive internals'));
		$d['logger']->expects($this->once())->method('error')->with(
			'Falló un endpoint de mantenimiento.',
			$this->callback(fn(array $context): bool => $context['operation'] === 'list_groups' && $context['actorUid'] === 'admin' && !isset($context['payload'])),
		);

		$response = $d['controller']->groups('2026-08-01', '2026-08-02');

		$this->assertSame(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
		$this->assertStringNotContainsString('sensitive', $response->getData()['error']['message']);
	}

	public function testInvalidDatesRangesLimitsBooleansAndIdsAreRejectedSafely(): void {
		$d = $this->dependencies('admin');
		foreach ([
			fn() => $d['controller']->groups('2026-02-30', '2026-03-01'),
			fn() => $d['controller']->groups('2026-08-03', '2026-08-01'),
			fn() => $d['controller']->groups('2025-01-01', '2026-08-01'),
			fn() => $d['controller']->groups('2026-08-01', '2026-08-02', null, null, null, null, null, 201),
			fn() => $d['controller']->groups('2026-08-01', '2026-08-02', null, null, null, 'overdue'),
			fn() => $d['controller']->eligibleEquipment(-1),
			fn() => $d['controller']->eligibleEquipment(4, 'not-a-bool'),
		] as $request) {
			$this->assertSame(Http::STATUS_BAD_REQUEST, $request()->getStatus());
		}
	}

	public function testTextFalseIsNotConvertedToTrue(): void {
		$d = $this->dependencies('admin');
		$d['service']->expects($this->once())->method('listEligibleEquipmentByDepartment')
			->with(4, false, false, null, 50, 0)
			->willReturn(['items' => [], 'total' => 0, 'departmentIds' => [4]]);

		$this->assertSame(Http::STATUS_OK, $d['controller']->eligibleEquipment(4, 'false', 'false')->getStatus());
	}

	public function testNewRoutesAreUniqueAndSpecificRoutesPrecedeGenericMaintenanceId(): void {
		$routes = require __DIR__ . '/../../../appinfo/routes.php';
		$new = array_values(array_filter($routes['routes'], fn(array $route): bool => str_starts_with($route['url'], '/inventario/mantenimientos')));
		$keys = array_map(fn(array $route): string => $route['verb'] . ' ' . $route['url'], $new);
		$this->assertCount(count(array_unique($keys)), $keys);
		$urls = array_column($new, 'url');
		$this->assertLessThan(array_search('/inventario/mantenimientos/{id}', $urls, true), array_search('/inventario/mantenimientos/tecnicos', $urls, true));
		$this->assertLessThan(array_search('/inventario/mantenimientos/{id}', $urls, true), array_search('/inventario/mantenimientos/atrasados', $urls, true));
		$this->assertLessThan(array_search('/inventario/mantenimientos/{id}', $urls, true), array_search('/inventario/mantenimientos/equipos/{id}/historial', $urls, true));
	}

	public function testAllEndpointMethodsUseSessionWithoutDisablingCsrf(): void {
		$reflection = new \ReflectionClass(MantenimientoController::class);
		$methods = [
			'technicians', 'groups', 'createGroup', 'group', 'groupMaintenances', 'cancelGroup', 'assignGroupTechnician',
			'eligibleEquipment', 'maintenance', 'start', 'complete', 'reschedule', 'cancel', 'notApplicable',
			'assignTechnician', 'updateChecklist', 'updateWork', 'equipmentHistory', 'overdue', 'duplicates',
		];
		foreach ($methods as $method) {
			$attributes = array_map(fn(\ReflectionAttribute $attribute): string => $attribute->getName(), $reflection->getMethod($method)->getAttributes());
			$this->assertContains(UseSession::class, $attributes);
			$this->assertContains(NoAdminRequired::class, $attributes);
		}
		$this->assertStringNotContainsString('NoCSRFRequired', file_get_contents($reflection->getFileName()));
	}

	private function dependencies(string $role, bool $authenticated = true, bool $moduleEnabled = true): array {
		$request = $this->createMock(IRequest::class);
		$session = $this->createMock(IUserSession::class);
		if ($authenticated) {
			$user = $this->createMock(IUser::class);
			$uid = $role === 'technician' ? 'tech' : ($role === 'view' ? 'viewer' : ($role === 'none' ? 'none' : 'admin'));
			$user->method('getUID')->willReturn($uid);
			$user->method('getDisplayName')->willReturn(match ($role) { 'technician' => 'Técnico', 'view' => 'Consulta', 'none' => 'Sin permiso', default => 'Administrador' });
			$session->method('getUser')->willReturn($user);
		} else {
			$session->method('getUser')->willReturn(null);
		}
		$groups = $this->createMock(IGroupManager::class);
		$employees = $this->createMock(empleadosMapper::class);
		$config = $this->createMock(configuracionesMapper::class);
		$config->method('GetConfig')->willReturn([]);
		$service = $this->createMock(MantenimientoService::class);
		$permissions = $this->createMock(PermisosService::class);
		$permissions->method('isModuleEnabled')->willReturn($moduleEnabled);
		$permissions->method('canManageInventory')->willReturnCallback(fn(?string $uid = null): bool => $role === 'admin' && in_array($uid, [null, 'admin', 'admin-tech', 'both', 'tech'], true));
		$permissions->method('canSee')->willReturnCallback(fn(string $permission, ?string $uid = null): bool => match ($permission) {
			'inventario.technician' => $role === 'technician' || in_array($uid, ['tech', 'both'], true),
			'inventario.view' => $role === 'view',
			default => false,
		});
		$logger = $this->createMock(LoggerInterface::class);
		$userManager = $this->createMock(IUserManager::class);
		return [
			'controller' => new MantenimientoController($request, $session, $groups, $employees, $config, $service, $permissions, $userManager, $logger),
			'service' => $service, 'permissions' => $permissions, 'userManager' => $userManager, 'logger' => $logger,
		];
	}

	private function catalogUser(string $uid, string $displayName, bool $enabled = true): IUser {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$user->method('getDisplayName')->willReturn($displayName);
		$user->method('isEnabled')->willReturn($enabled);
		return $user;
	}

	private function maintenance(string $technicianUid, string $status = 'pending'): array {
		return [
			'id' => 11, 'id_grupo' => 7, 'id_equipo' => 8, 'tecnico_uid' => $technicianUid,
			'tecnico_nombre' => 'Técnico', 'estado' => $status, 'observaciones' => 'Interno',
			'incidencias' => 'Interno', 'repuestos' => 'Interno', 'resultado' => 'Interno',
			'empleado_uid' => 'employee', 'numero_serie' => 'SERIE',
		];
	}

	private function detail(): array {
		return [
			'maintenance' => $this->maintenance('tech'),
			'checklist' => [['clave' => 'external_cleaning', 'resultado' => 'ok', 'observacion' => 'Interna', 'actualizado_por' => 'tech']],
			'audit' => [['tipo_cambio' => 'created']],
		];
	}
}
