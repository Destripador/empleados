<?php

declare(strict_types=1);

namespace OCA\Empleados\Tests\Unit\Controller;

use OCA\Empleados\Config\PermissionCatalog;
use OCA\Empleados\Controller\PermisoGruposController;
use OCA\Empleados\Db\PermisoGrupoMapper;
use OCA\Empleados\Service\PermissionCatalogInitializer;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

final class PermisoGruposControllerTest extends TestCase {
	public function testRepairOfEmptyStructureCreatesEveryOfficialEnabledGroup(): void {
		$request = $this->createMock(IRequest::class);
		$mapper = $this->createMock(PermisoGrupoMapper::class);
		$initializer = $this->createMock(PermissionCatalogInitializer::class);
		$initializer->method('initialize')->willReturn(PermissionCatalog::entries());
		$rows = array_map(
			static fn (array $entry, int $index): array => $entry + ['id' => $index + 1],
			PermissionCatalog::entries(),
			array_keys(PermissionCatalog::entries()),
		);
		$mapper->method('findAllCatalog')->willReturn($rows);

		$createdGroup = $this->createMock(IGroup::class);
		$groups = $this->createMock(IGroupManager::class);
		$groups->method('get')->willReturn(null);
		$created = [];
		$groups->method('createGroup')->willReturnCallback(static function (string $id) use (&$created, $createdGroup): IGroup {
			$created[] = $id;
			return $createdGroup;
		});

		$controller = new PermisoGruposController($request, $mapper, $groups, $initializer);
		$controller->repararEstructura();

		$expected = array_values(array_unique(array_merge(
			array_column(PermissionCatalog::baseGroups(), 'id'),
			array_column(PermissionCatalog::entries(), 'group_id'),
		)));
		sort($expected);
		sort($created);
		$this->assertSame($expected, $created);
	}

	public function testRepairCreatesOnlyMissingEnabledGroupsAndDoesNotTouchMembers(): void {
		$request = $this->createMock(IRequest::class);
		$mapper = $this->createMock(PermisoGrupoMapper::class);
		$initializer = $this->createMock(PermissionCatalogInitializer::class);
		$initializer->expects($this->once())->method('initialize')->willReturn([]);
		$mapper->method('findAllCatalog')->willReturn([
			$this->row('inventario', 'admin', 'ti_admin', 1),
			$this->row('personalizado', 'view', 'custom_disabled', 0),
		]);

		$existingBase = $this->createMock(IGroup::class);
		$existingInventory = $this->createMock(IGroup::class);
		$createdGroup = $this->createMock(IGroup::class);
		$existingBase->expects($this->never())->method('addUser');
		$existingBase->expects($this->never())->method('removeUser');
		$existingInventory->expects($this->never())->method('addUser');
		$existingInventory->expects($this->never())->method('removeUser');

		$groups = $this->createMock(IGroupManager::class);
		$groups->method('get')->willReturnCallback(static fn (string $id): ?IGroup => match ($id) {
			'empleados' => $existingBase,
			'ti_admin' => $existingInventory,
			default => null,
		});
		$created = [];
		$groups->method('createGroup')->willReturnCallback(static function (string $id) use (&$created, $createdGroup): IGroup {
			$created[] = $id;
			return $createdGroup;
		});

		$controller = new PermisoGruposController($request, $mapper, $groups, $initializer);
		$response = $controller->repararEstructura();

		$this->assertSame(['recursos_humanos'], $created);
		$this->assertNotContains('custom_disabled', $created);
		$this->assertSame('ok', $response->getData()['status']);
	}

	/** @return array<string, mixed> */
	private function row(string $module, string $permission, string $groupId, int $enabled): array {
		return [
			'id' => 1,
			'module' => $module,
			'permission' => $permission,
			'group_id' => $groupId,
			'label' => $groupId,
			'description' => '',
			'restricted' => 0,
			'enabled' => $enabled,
			'sort_order' => 0,
		];
	}
}
