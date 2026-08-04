<?php

declare(strict_types=1);

namespace OCA\Empleados\Service;

use OCA\Empleados\Db\configuracionesMapper;
use OCA\Empleados\Db\PermisoGrupoMapper;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\IUserSession;

class PermisosService {

	private ?array $configMap = null;
	private ?array $catalog = null;

	/**
	 * Relación entre el nombre lógico del módulo y la configuración global.
	 *
	 * Si un módulo no está aquí, se considera habilitado por defecto.
	 * Esto permite tener permisos como "empleados.hr" sin depender de un switch global.
	 */
	private const MODULE_CONFIG_KEYS = [
		'ahorro' => 'modulo_ahorro',
		'ausencias' => 'modulo_ausencias',
		'clientes' => 'modulo_clientes',
		'compras' => 'modulo_compras',
		'inventario' => 'modulo_inventario',
		'soporte' => 'modulo_soporte',
		'reporte_tiempos' => 'modulo_reporte_tiempos',
	];

	public function __construct(
		private IUserSession $userSession,
		private IUserManager $userManager,
		private IGroupManager $groupManager,
		private configuracionesMapper $configuracionesMapper,
		private PermisoGrupoMapper $permisoGrupoMapper,
	) {
	}

	/*
	|--------------------------------------------------------------------------
	| Usuario y grupos
	|--------------------------------------------------------------------------
	*/

	public function getCurrentUserId(): ?string {
		$user = $this->userSession->getUser();

		return $user?->getUID();
	}

	public function isAdmin(?string $uid = null): bool {
		$uid = $uid ?? $this->getCurrentUserId();

		if ($uid === null || $uid === '') {
			return false;
		}

		return $this->groupManager->isAdmin($uid);
	}

	public function getUserGroupIds(?string $uid = null): array {
		$uid = $uid ?? $this->getCurrentUserId();

		if ($uid === null || $uid === '') {
			return [];
		}

		$user = $this->userManager->get($uid);

		if ($user === null) {
			return [];
		}

		return $this->groupManager->getUserGroupIds($user);
	}

	public function userHasGroup(string $uid, string $groupId): bool {
		return in_array($groupId, $this->getUserGroupIds($uid), true);
	}

	public function userHasAnyGroup(string $uid, array $groupIds): bool {
		$userGroups = $this->getUserGroupIds($uid);

		foreach ($groupIds as $groupId) {
			if (in_array((string)$groupId, $userGroups, true)) {
				return true;
			}
		}

		return false;
	}

	/*
	|--------------------------------------------------------------------------
	| Configuración de módulos
	|--------------------------------------------------------------------------
	*/

	public function getConfig(string $name, string $default = ''): string {
		$configMap = $this->getConfigMap();

		return (string)($configMap[$name] ?? $default);
	}

	public function isModuleEnabled(string $module): bool {
		$module = trim($module);

		if ($module === '') {
			return false;
		}

		$configKey = self::MODULE_CONFIG_KEYS[$module] ?? null;

		if ($configKey === null) {
			return true;
		}

		return $this->isTruthy($this->getConfig($configKey, 'false'));
	}

	/*
	|--------------------------------------------------------------------------
	| API genérica recomendada
	|--------------------------------------------------------------------------
	|
	| Usar estas funciones para código nuevo:
	|
	| canSee('clientes')
	| canSee('clientes.admin')
	| canSee('compras.approve')
	| requireCanSee('empleados.admin')
	|
	*/

	public function canSee(string $permissionKey, ?string $uid = null): bool {
		$permissionKey = trim($permissionKey);

		if ($permissionKey === '') {
			return false;
		}

		if (str_contains($permissionKey, '.')) {
			[$module, $permission] = explode('.', $permissionKey, 2);

			$module = trim($module);
			$permission = trim($permission);

			if ($module === '' || $permission === '') {
				return false;
			}

			return $this->canUsePermission($uid, $module, $permission);
		}

		return $this->canAccessModule($uid, $permissionKey);
	}

	public function canSeeAny(array $permissionKeys, ?string $uid = null): bool {
		foreach ($permissionKeys as $permissionKey) {
			if ($this->canSee((string)$permissionKey, $uid)) {
				return true;
			}
		}

		return false;
	}

	public function requireCanSee(string $permissionKey, ?string $uid = null): void {
		if ($this->canSee($permissionKey, $uid)) {
			return;
		}

		throw new \Exception('🚫 No tienes permiso para acceder a este apartado. Contacta al administrador.');
	}

	public function requireCanSeeAny(array $permissionKeys, ?string $uid = null): void {
		if ($this->canSeeAny($permissionKeys, $uid)) {
			return;
		}

		throw new \Exception('🚫 No tienes permiso para acceder a este apartado. Contacta al administrador.');
	}

	/*
	|--------------------------------------------------------------------------
	| API por módulo / permiso
	|--------------------------------------------------------------------------
	*/

	public function canAccessModule(?string $uid, string $module): bool {
		$uid = $uid ?? $this->getCurrentUserId();
		$module = trim($module);

		if ($uid === null || $uid === '' || $module === '') {
			return false;
		}

		if (!$this->isModuleEnabled($module)) {
			return false;
		}

		if ($this->isAdmin($uid)) {
			return true;
		}

		$groupIds = [];

		foreach ($this->getCatalog() as $permission) {
			if ((string)$permission['module'] === $module) {
				$groupIds[] = (string)$permission['group_id'];
			}
		}

		return $this->userHasAnyGroup($uid, array_values(array_unique($groupIds)));
	}

	public function canUsePermission(?string $uid, string $module, string $permissionName): bool {
		$uid = $uid ?? $this->getCurrentUserId();
		$module = trim($module);
		$permissionName = trim($permissionName);

		if ($uid === null || $uid === '' || $module === '' || $permissionName === '') {
			return false;
		}

		if (!$this->isModuleEnabled($module)) {
			return false;
		}

		if ($this->isAdmin($uid)) {
			return true;
		}

		$groupIds = [];

		foreach ($this->getCatalog() as $permission) {
			if (
				(string)$permission['module'] === $module
				&& (string)$permission['permission'] === $permissionName
			) {
				$groupIds[] = (string)$permission['group_id'];
			}
		}

		return $this->userHasAnyGroup($uid, array_values(array_unique($groupIds)));
	}

	public function canUseAnyPermission(?string $uid, string $module, array $permissionNames): bool {
		foreach ($permissionNames as $permissionName) {
			if ($this->canUsePermission($uid, $module, (string)$permissionName)) {
				return true;
			}
		}

		return false;
	}

	/*
	|--------------------------------------------------------------------------
	| Wrappers existentes / compatibilidad
	|--------------------------------------------------------------------------
	|
	| Estos pueden seguir existiendo para código viejo o nombres cómodos.
	| Para código nuevo, preferir canSee() o requireCanSee().
	|
	*/

	public function canManageHumanResources(?string $uid = null): bool {
		return $this->canSeeAny([
			'empleados.hr',
			'empleados.admin',
		], $uid);
	}

	public function canAdminPurchases(?string $uid = null): bool {
		return $this->canSee('compras.admin', $uid);
	}

	public function canApprovePurchases(?string $uid = null): bool {
		return $this->canSee('compras.approve', $uid);
	}

	public function canRequestPurchases(?string $uid = null): bool {
		return $this->canSee('compras.request', $uid);
	}

	public function canViewAccountingPurchases(?string $uid = null): bool {
		return $this->canSee('compras.accounting', $uid);
	}

	public function canManageInventory(?string $uid = null): bool {
		return $this->canSee('inventario.admin', $uid);
	}

	public function canWorkMaintenance(?string $uid = null): bool {
		return $this->canManageInventory($uid) || $this->canSee('inventario.technician', $uid);
	}

	public function canViewInventory(?string $uid = null): bool {
		return $this->canWorkMaintenance($uid) || $this->canSee('inventario.view', $uid);
	}

	public function canManagePermissionsCatalog(?string $uid = null): bool {
		return $this->isAdmin($uid);
	}

	/*
	|--------------------------------------------------------------------------
	| Contexto para frontend
	|--------------------------------------------------------------------------
	*/

	public function getUserPermissionsContext(?string $uid = null): array {
		$uid = $uid ?? $this->getCurrentUserId();

		if ($uid === null || $uid === '') {
			return [
				'uid' => null,
				'is_admin' => false,
				'groups' => [],
				'modules' => [],
			];
		}

		$isAdmin = $this->isAdmin($uid);
		$userGroups = $this->getUserGroupIds($uid);
		$modules = [];

		foreach ($this->getCatalog() as $permission) {
			$module = (string)$permission['module'];
			$permissionName = (string)$permission['permission'];
			$groupId = (string)$permission['group_id'];

			if (!isset($modules[$module])) {
				$modules[$module] = [
					'enabled' => $this->isModuleEnabled($module),
					'view' => false,
					'permissions' => [],
				];
			}

			$hasPermission = $modules[$module]['enabled']
				&& (
					$isAdmin
					|| in_array($groupId, $userGroups, true)
				);

			$currentPermissionValue = (bool)($modules[$module]['permissions'][$permissionName] ?? false);
			$newPermissionValue = $currentPermissionValue || $hasPermission;

			$modules[$module]['permissions'][$permissionName] = $newPermissionValue;
			$modules[$module][$permissionName] = $newPermissionValue;

			if ($newPermissionValue) {
				$modules[$module]['view'] = true;
			}
		}

		return [
			'uid' => $uid,
			'is_admin' => $isAdmin,
			'groups' => $userGroups,
			'modules' => $modules,
		];
	}

	public function getEnabledCatalog(): array {
		return $this->getCatalog();
	}

	/**
	 * Devuelve los UIDs de usuarios pertenecientes a los grupos que conceden
	 * exactamente el permiso indicado. La relación permiso-grupo siempre se
	 * obtiene del catálogo habilitado, nunca de nombres de grupo codificados.
	 *
	 * @return string[]
	 */
	public function getUsersWithPermission(string $permissionKey): array {
		$permissionKey = trim($permissionKey);
		if ($permissionKey === '' || !str_contains($permissionKey, '.')) {
			return [];
		}

		[$module, $permissionName] = array_map('trim', explode('.', $permissionKey, 2));
		if ($module === '' || $permissionName === '' || !$this->isModuleEnabled($module)) {
			return [];
		}

		$uids = [];
		foreach ($this->getCatalog() as $permission) {
			if ((string)$permission['module'] !== $module || (string)$permission['permission'] !== $permissionName) {
				continue;
			}

			$group = $this->groupManager->get((string)$permission['group_id']);
			if ($group === null) {
				continue;
			}

			foreach ($group->getUsers() as $user) {
				$uid = trim((string)$user->getUID());
				if ($uid !== '') {
					$uids[$uid] = true;
				}
			}
		}

		// Los administradores globales reciben los permisos de la aplicación por
		// la misma regla usada en canUsePermission(), aunque no pertenezcan al
		// grupo configurable de administración del inventario.
		if ($permissionName === 'admin') {
			foreach ($this->userManager->search('', null, null) as $user) {
				$uid = trim((string)$user->getUID());
				if ($uid !== '' && $this->groupManager->isAdmin($uid)) {
					$uids[$uid] = true;
				}
			}
		}

		$items = array_keys($uids);
		sort($items, SORT_NATURAL | SORT_FLAG_CASE);
		return $items;
	}

	/*
	|--------------------------------------------------------------------------
	| Internos
	|--------------------------------------------------------------------------
	*/

	private function getCatalog(): array {
		if ($this->catalog !== null) {
			return $this->catalog;
		}

		$this->catalog = $this->permisoGrupoMapper->findEnabled();

		return $this->catalog;
	}

	private function getConfigMap(): array {
		if ($this->configMap !== null) {
			return $this->configMap;
		}

		$config = $this->configuracionesMapper->GetConfig();
		$map = [];

		foreach ($config as $row) {
			$name = $row['Nombre'] ?? $row['nombre'] ?? null;
			$value = $row['Data'] ?? $row['data'] ?? '';

			if ($name !== null) {
				$map[(string)$name] = (string)$value;
			}
		}

		$this->configMap = $map;

		return $this->configMap;
	}

	private function isTruthy($value): bool {
		if (is_bool($value)) {
			return $value;
		}

		if (is_int($value)) {
			return $value === 1;
		}

		$value = strtolower(trim((string)$value));

		return in_array($value, ['1', 'true', 'yes', 'si', 'sí', 'on'], true);
	}
}
