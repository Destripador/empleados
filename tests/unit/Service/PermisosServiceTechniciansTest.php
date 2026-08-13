<?php

declare(strict_types=1);

namespace OCA\Empleados\Tests\Unit\Service;

use OCA\Empleados\Db\configuracionesMapper;
use OCA\Empleados\Db\PermisoGrupoMapper;
use OCA\Empleados\Service\PermisosService;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

class PermisosServiceTechniciansTest extends TestCase {
	public function testGetsUniqueUsersFromGroupsConfiguredForExactPermission(): void {
		$session = $this->createMock(IUserSession::class);
		$users = $this->createMock(IUserManager::class);
		$users->method('search')->willReturn([$this->user('global-admin'), $this->user('regular')]);
		$groups = $this->createMock(IGroupManager::class);
		$groups->method('isAdmin')->willReturnCallback(fn(string $uid): bool => $uid === 'global-admin');
		$config = $this->createMock(configuracionesMapper::class);
		$config->method('GetConfig')->willReturn([['Nombre' => 'modulo_inventario', 'Data' => 'true']]);
		$catalog = $this->createMock(PermisoGrupoMapper::class);
		$catalog->method('findEnabled')->willReturn([
			['module' => 'inventario', 'permission' => 'technician', 'group_id' => 'configured-techs'],
			['module' => 'inventario', 'permission' => 'technician', 'group_id' => 'second-techs'],
			['module' => 'inventario', 'permission' => 'admin', 'group_id' => 'inventory-admins'],
		]);
		$groupUsers = [
			'configured-techs' => [$this->user('zeta'), $this->user('shared')],
			'second-techs' => [$this->user('alpha'), $this->user('shared')],
		];
		$groups->method('get')->willReturnCallback(function (string $groupId) use ($groupUsers): ?IGroup {
			if (!isset($groupUsers[$groupId])) return null;
			$group = $this->createMock(IGroup::class);
			$group->method('getUsers')->willReturn($groupUsers[$groupId]);
			return $group;
		});
		$service = new PermisosService($session, $users, $groups, $config, $catalog);

		$this->assertSame(['alpha', 'shared', 'zeta'], $service->getUsersWithPermission('inventario.technician'));
		$this->assertSame(['global-admin'], $service->getUsersWithPermission('inventario.admin'));
		$this->assertSame([], $service->getUsersWithPermission('inventario.view'));
	}

	private function user(string $uid): IUser {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		return $user;
	}
}
