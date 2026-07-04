<?php

declare(strict_types=1);

namespace OCA\Empleados\Controller;

use OCA\Empleados\AppInfo\Application;
use OCA\Empleados\Db\configuracionesMapper;
use OCA\Empleados\Db\empleadosMapper;
use OCA\Empleados\Db\PermisoGrupoMapper;
use OCA\Empleados\Service\PermisosService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\DataResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;

class PermisosController extends BaseController {

	private IUserManager $userManager;
	private PermisoGrupoMapper $permisoGrupoMapper;
	private PermisosService $permisosService;

	public function __construct(
		IRequest $request,
		IUserSession $userSession,
		IUserManager $userManager,
		IGroupManager $groupManager,
		empleadosMapper $empleadosMapper,
		configuracionesMapper $configuracionesMapper,
		PermisoGrupoMapper $permisoGrupoMapper,
		PermisosService $permisosService
	) {
		parent::__construct(
			Application::APP_ID,
			$request,
			$userSession,
			$groupManager,
			$empleadosMapper,
			$configuracionesMapper,
		);

		$this->userManager = $userManager;
		$this->permisoGrupoMapper = $permisoGrupoMapper;
		$this->permisosService = $permisosService;
	}

	#[UseSession]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	public function grupos(): DataResponse {
		$this->requirePermissionManagementAccess();

		$data = [];

		foreach ($this->permisoGrupoMapper->findEnabled() as $row) {
			$groupId = (string)$row['group_id'];

			$data[] = [
				'id' => $groupId,
				'label' => (string)$row['label'],
				'description' => $row['description'] ?? '',
				'module' => (string)$row['module'],
				'permission' => (string)$row['permission'],
				'restricted' => ((int)$row['restricted']) === 1,
				'enabled' => ((int)$row['enabled']) === 1,
				'exists' => $this->groupManager->get($groupId) !== null,
			];
		}

		return new DataResponse([
			'status' => 'ok',
			'data' => $data,
		], Http::STATUS_OK);
	}

	#[UseSession]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	public function usuario(string $uid): DataResponse {
		$this->requirePermissionManagementAccess();

		$user = $this->userManager->get($uid);

		if ($user === null) {
			return new DataResponse([
				'status' => 'error',
				'message' => 'Usuario no encontrado.',
			], Http::STATUS_NOT_FOUND);
		}

		$userGroups = $this->groupManager->getUserGroupIds($user);
		$allowedGroupIds = $this->permisoGrupoMapper->findEnabledGroupIds();

		return new DataResponse([
			'status' => 'ok',
			'data' => [
				'uid' => $uid,
				'displayName' => $user->getDisplayName(),
				'groups' => array_values(array_intersect($allowedGroupIds, $userGroups)),
			],
		], Http::STATUS_OK);
	}

	#[UseSession]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	public function actualizarUsuario(string $uid): DataResponse {
		$this->requirePermissionManagementAccess();

		$user = $this->userManager->get($uid);

		if ($user === null) {
			return new DataResponse([
				'status' => 'error',
				'message' => 'Usuario no encontrado.',
			], Http::STATUS_NOT_FOUND);
		}

		$groups = $this->request->getParam('groups', []);

		if (!is_array($groups)) {
			$groups = [];
		}

		$allowedGroupIds = $this->permisoGrupoMapper->findEnabledGroupIds();
		$requestedGroups = array_values(array_intersect($allowedGroupIds, $groups));
		$currentGroups = $this->groupManager->getUserGroupIds($user);

		foreach ($allowedGroupIds as $gid) {
			$group = $this->groupManager->get($gid);

			if ($group === null) {
				$group = $this->groupManager->createGroup($gid);
			}

			if ($group === null) {
				continue;
			}

			$isMember = in_array($gid, $currentGroups, true);
			$shouldBeMember = in_array($gid, $requestedGroups, true);

			if ($shouldBeMember && !$isMember) {
				$group->addUser($user);
			}

			if (!$shouldBeMember && $isMember) {
				$group->removeUser($user);
			}
		}

		return $this->usuario($uid);
	}

	#[UseSession]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	public function contexto(): DataResponse {
		$context = $this->permisosService->getUserPermissionsContext();

		return new DataResponse([
			'status' => 'ok',
			'data' => $context,
		], Http::STATUS_OK);
	}

	private function requirePermissionManagementAccess(): void {
		$this->permisosService->requireCanSeeAny([
			'empleados.hr',
			'empleados.admin',
		]);
	}
}