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
use OCP\Constants;
use OCP\Files\IRootFolder;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Share\IManager;
use OCP\Share\IShare;

class PermisosController extends BaseController {

	private const EMPLOYEES_FOLDER = 'EMPLEADOS';

	private const EMPLOYEES_FOLDER_PERMISSIONS =
		Constants::PERMISSION_READ
		| Constants::PERMISSION_UPDATE
		| Constants::PERMISSION_CREATE
		| Constants::PERMISSION_DELETE;

	private IUserManager $userManager;
	private PermisoGrupoMapper $permisoGrupoMapper;
	private PermisosService $permisosService;
	private configuracionesMapper $appConfiguracionesMapper;
	private IRootFolder $rootFolder;
	private IManager $shareManager;

	public function __construct(
		IRequest $request,
		IUserSession $userSession,
		IUserManager $userManager,
		IGroupManager $groupManager,
		IRootFolder $rootFolder,
		IManager $shareManager,
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
		$this->rootFolder = $rootFolder;
		$this->shareManager = $shareManager;
		$this->appConfiguracionesMapper = $configuracionesMapper;
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
				'groups' => array_values(
					array_intersect($allowedGroupIds, $userGroups),
				),
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

		try {
			$groups = $this->request->getParam('groups', []);

			if (!is_array($groups)) {
				$groups = [];
			}

			$groups = array_values(array_unique(array_filter(
				array_map(
					static fn (mixed $group): string => trim((string)$group),
					$groups,
				),
				static fn (string $group): bool => $group !== '',
			)));

			$allowedGroupIds = $this->permisoGrupoMapper
				->findEnabledGroupIds();

			$requestedGroups = array_values(array_intersect(
				$allowedGroupIds,
				$groups,
			));

			$currentGroups = $this->groupManager
				->getUserGroupIds($user);

			/*
			* Primero actualizar solamente las membresías.
			*/
			foreach ($allowedGroupIds as $groupId) {
				$group = $this->groupManager->get($groupId);

				if ($group === null) {
					$group = $this->groupManager->createGroup($groupId);
				}

				if ($group === null) {
					throw new \RuntimeException(
						"El grupo '$groupId' no pudo crearse.",
					);
				}

				$isMember = in_array(
					$groupId,
					$currentGroups,
					true,
				);

				$shouldBeMember = in_array(
					$groupId,
					$requestedGroups,
					true,
				);

				if ($shouldBeMember && !$isMember) {
					$group->addUser($user);
					continue;
				}

				if (!$shouldBeMember && $isMember) {
					$group->removeUser($user);
				}
			}

			/*
			* Determinar una sola vez si alguno de los permisos
			* seleccionados concede acceso a EMPLEADOS.
			*/
			$employeesFolderGroupIds =
				$this->getEmployeesFolderGroupIds();

			$shouldHaveEmployeesFolder = count(array_intersect(
				$requestedGroups,
				$employeesFolderGroupIds,
			)) > 0;

			if ($shouldHaveEmployeesFolder) {
				$this->ensureEmployeesFolderSharedWithUser($uid);
			} else {
				$this->removeEmployeesFolderShareFromUser($uid);
			}

			return $this->usuario($uid);
		} catch (\Throwable $e) {
			return new DataResponse([
				'status' => 'error',
				'message' => sprintf(
					'No fue posible actualizar los permisos: %s',
					$e->getMessage(),
				),
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	#[UseSession]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	public function contexto(): DataResponse {
		$context =
			$this->permisosService->getUserPermissionsContext();

		return new DataResponse([
			'status' => 'ok',
			'data' => $context,
		], Http::STATUS_OK);
	}

	/**
	 * Obtiene los grupos del catálogo que conceden acceso a
	 * Recursos Humanos.
	 *
	 * @return string[]
	 */
	private function getEmployeesFolderGroupIds(): array {
		$groupIds = [];

		foreach ($this->permisoGrupoMapper->findEnabled() as $row) {
			$module = trim((string)($row['module'] ?? ''));
			$permission = trim(
				(string)($row['permission'] ?? ''),
			);
			$groupId = trim((string)($row['group_id'] ?? ''));

			if ($module !== 'empleados') {
				continue;
			}

			if (!in_array($permission, ['hr', 'admin'], true)) {
				continue;
			}

			if ($groupId !== '') {
				$groupIds[] = $groupId;
			}
		}

		return array_values(array_unique($groupIds));
	}

	private function ensureEmployeesFolderSharedWithUser(
		string $uid,
	): void {
		$gestorUid = $this->getGestorUid();

		if ($this->userManager->get($gestorUid) === null) {
			throw new \RuntimeException(
				"El usuario gestor '$gestorUid' no existe.",
			);
		}

		$userFolder = $this->rootFolder->getUserFolder(
			$gestorUid,
		);

		if (
			!$userFolder->nodeExists(
				self::EMPLOYEES_FOLDER,
			)
		) {
			throw new \RuntimeException(
				'La carpeta EMPLEADOS no existe en la cuenta del gestor.',
			);
		}

		$folder = $userFolder->get(
			self::EMPLOYEES_FOLDER,
		);

		/*
		 * Revisar si ya existe el compartido para evitar duplicados.
		 */
		$shares = $this->shareManager->getSharesBy(
			$gestorUid,
			IShare::TYPE_USER,
			$folder,
		);

		foreach ($shares as $share) {
			if ($share->getSharedWith() !== $uid) {
				continue;
			}

			if (
				(int)$share->getPermissions()
				!== self::EMPLOYEES_FOLDER_PERMISSIONS
			) {
				$share->setPermissions(
					self::EMPLOYEES_FOLDER_PERMISSIONS,
				);

				$this->shareManager->updateShare($share);
			}

			return;
		}

		$share = $this->shareManager->newShare();

		$share->setNode($folder);
		$share->setShareType(IShare::TYPE_USER);
		$share->setSharedWith($uid);
		$share->setSharedBy($gestorUid);
		$share->setPermissions(
			self::EMPLOYEES_FOLDER_PERMISSIONS,
		);

		$this->shareManager->createShare($share);
	}

	private function removeEmployeesFolderShareFromUser(
		string $uid,
	): void {
		$gestorUid = $this->getGestorUid();

		$userFolder = $this->rootFolder->getUserFolder(
			$gestorUid,
		);

		if (
			!$userFolder->nodeExists(
				self::EMPLOYEES_FOLDER,
			)
		) {
			return;
		}

		$folder = $userFolder->get(
			self::EMPLOYEES_FOLDER,
		);

		$shares = $this->shareManager->getSharesBy(
			$gestorUid,
			IShare::TYPE_USER,
			$folder,
		);

		foreach ($shares as $share) {
			if ($share->getSharedWith() === $uid) {
				$this->shareManager->deleteShare($share);
			}
		}
	}

	private function getGestorUid(): string {
		$gestor = $this->appConfiguracionesMapper->GetGestor();

		$gestorUid = trim(
			(string)($gestor[0]['Data'] ?? ''),
		);

		if ($gestorUid === '') {
			throw new \RuntimeException(
				'No está configurado el usuario gestor.',
			);
		}

		return $gestorUid;
	}

	private function requirePermissionManagementAccess(): void {
		$this->permisosService->requireCanSeeAny([
			'empleados.hr',
			'empleados.admin',
		]);
	}
}