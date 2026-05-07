<?php

declare(strict_types=1);

namespace OCA\Empleados\Controller;

use OCA\Empleados\AppInfo\Application;
use OCA\Empleados\Db\configuracionesMapper;
use OCA\Empleados\Db\empleadosMapper;
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

	private const ALLOWED_GROUPS = [
		'compras_solicitantes' => 'Compras - Solicitantes',
		'compras_autorizadores' => 'Compras - Autorizadores',
		'compras_admin' => 'Compras - Administradores',
		'compras_contabilidad' => 'Compras - Contabilidad',
		'recursos_humanos' => 'Recursos Humanos',
	];

	public function __construct(
		IRequest $request,
		IUserSession $userSession,
		IUserManager $userManager,
		IGroupManager $groupManager,
		empleadosMapper $empleadosMapper,
		configuracionesMapper $configuracionesMapper
	) {
		parent::__construct(
			Application::APP_ID,
			$request,
			$userSession,
			$groupManager,
			$empleadosMapper,
			$configuracionesMapper
		);

		$this->userManager = $userManager;
	}

	#[UseSession]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	public function grupos(): DataResponse {
		$this->checkAccess(['admin', 'recursos_humanos']);

		$data = [];

		foreach (self::ALLOWED_GROUPS as $gid => $label) {
			$data[] = [
				'id' => $gid,
				'label' => $label,
				'exists' => $this->groupManager->get($gid) !== null,
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
		$this->checkAccess(['admin', 'recursos_humanos']);

		$user = $this->userManager->get($uid);

		if ($user === null) {
			return new DataResponse([
				'status' => 'error',
				'message' => 'Usuario no encontrado.',
			], Http::STATUS_NOT_FOUND);
		}

		$userGroups = $this->groupManager->getUserGroupIds($user);
		$allowedGroupIds = array_keys(self::ALLOWED_GROUPS);

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
		$this->checkAccess(['admin', 'recursos_humanos']);

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

		$allowedGroupIds = array_keys(self::ALLOWED_GROUPS);
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
}
