<?php

declare(strict_types=1);

namespace OCA\Empleados\Controller;

use OCA\Empleados\AppInfo\Application;
use OCA\Empleados\Db\PermisoGrupoMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IGroupManager;
use OCP\IRequest;

class PermisoGruposController extends Controller {

	private const BASE_GROUPS = [
		[
			'id' => 'empleados',
			'label' => 'Empleados',
			'description' => 'Grupo base para usuarios que pueden interactuar con el módulo de empleados.',
		],
		[
			'id' => 'recursos_humanos',
			'label' => 'Recursos Humanos',
			'description' => 'Grupo base para administración de recursos humanos.',
		],
	];

	public function __construct(
		IRequest $request,
		private PermisoGrupoMapper $permisoGrupoMapper,
		private IGroupManager $groupManager
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoCSRFRequired]
	#[AdminRequired]
	public function index(): DataResponse {
		$data = [];

		foreach ($this->permisoGrupoMapper->findAllCatalog() as $row) {
			$groupId = (string)$row['group_id'];

			$data[] = [
				'id' => (int)$row['id'],
				'module' => (string)$row['module'],
				'permission' => (string)$row['permission'],
				'group_id' => $groupId,
				'label' => (string)$row['label'],
				'description' => $row['description'] ?? '',
				'restricted' => ((int)$row['restricted']) === 1,
				'enabled' => ((int)$row['enabled']) === 1,
				'sort_order' => (int)$row['sort_order'],
				'exists' => $this->groupManager->get($groupId) !== null,
			];
		}

		return new DataResponse([
			'status' => 'ok',
			'data' => $data,
		], Http::STATUS_OK);
	}

	#[AdminRequired]
	public function create(): DataResponse {
		$payload = $this->getPayload();

		$validation = $this->validatePayload($payload);

		if ($validation !== null) {
			return $validation;
		}

		$module = trim((string)$payload['module']);
		$permission = trim((string)$payload['permission']);
		$groupId = trim((string)$payload['group_id']);

		if ($this->permisoGrupoMapper->catalogEntryExists($module, $permission, $groupId)) {
			return new DataResponse([
				'status' => 'error',
				'message' => 'Ya existe un permiso con ese módulo, permiso y grupo.',
			], Http::STATUS_CONFLICT);
		}

		$this->permisoGrupoMapper->createCatalogEntry(
			$module,
			$permission,
			$groupId,
			trim((string)$payload['label']),
			$this->nullableString($payload['description'] ?? null),
			$this->toBool($payload['restricted'] ?? false) ? 1 : 0,
			$this->toBool($payload['enabled'] ?? true) ? 1 : 0,
			(int)($payload['sort_order'] ?? 0)
		);

		return new DataResponse([
			'status' => 'ok',
			'message' => 'Permiso creado correctamente.',
		], Http::STATUS_CREATED);
	}

	#[AdminRequired]
	public function update(int $id): DataResponse {
		$current = $this->permisoGrupoMapper->findById($id);

		if ($current === null) {
			return new DataResponse([
				'status' => 'error',
				'message' => 'Permiso no encontrado.',
			], Http::STATUS_NOT_FOUND);
		}

		$payload = $this->getPayload();

		$validation = $this->validatePayload($payload);

		if ($validation !== null) {
			return $validation;
		}

		$module = trim((string)$payload['module']);
		$permission = trim((string)$payload['permission']);
		$groupId = trim((string)$payload['group_id']);

		if ($this->permisoGrupoMapper->catalogEntryExists($module, $permission, $groupId, $id)) {
			return new DataResponse([
				'status' => 'error',
				'message' => 'Ya existe otro permiso con ese módulo, permiso y grupo.',
			], Http::STATUS_CONFLICT);
		}

		$this->permisoGrupoMapper->updateCatalogEntry(
			$id,
			$module,
			$permission,
			$groupId,
			trim((string)$payload['label']),
			$this->nullableString($payload['description'] ?? null),
			$this->toBool($payload['restricted'] ?? false) ? 1 : 0,
			$this->toBool($payload['enabled'] ?? true) ? 1 : 0,
			(int)($payload['sort_order'] ?? 0)
		);

		return new DataResponse([
			'status' => 'ok',
			'message' => 'Permiso actualizado correctamente.',
		], Http::STATUS_OK);
	}

	#[AdminRequired]
	public function enable(int $id): DataResponse {
		return $this->setEnabled($id, 1);
	}

	#[AdminRequired]
	public function disable(int $id): DataResponse {
		return $this->setEnabled($id, 0);
	}

	private function setEnabled(int $id, int $enabled): DataResponse {
		$current = $this->permisoGrupoMapper->findById($id);

		if ($current === null) {
			return new DataResponse([
				'status' => 'error',
				'message' => 'Permiso no encontrado.',
			], Http::STATUS_NOT_FOUND);
		}

		$this->permisoGrupoMapper->setEnabled($id, $enabled);

		return new DataResponse([
			'status' => 'ok',
			'message' => $enabled === 1
				? 'Permiso activado correctamente.'
				: 'Permiso desactivado correctamente.',
		], Http::STATUS_OK);
	}

	private function validatePayload(array $payload): ?DataResponse {
		$required = [
			'module',
			'permission',
			'group_id',
			'label',
		];

		foreach ($required as $field) {
			if (!isset($payload[$field]) || trim((string)$payload[$field]) === '') {
				return new DataResponse([
					'status' => 'error',
					'message' => "Falta el campo requerido: {$field}.",
				], Http::STATUS_BAD_REQUEST);
			}
		}

		$groupId = trim((string)$payload['group_id']);

		if (!preg_match('/^[a-zA-Z0-9_.@-]+$/', $groupId)) {
			return new DataResponse([
				'status' => 'error',
				'message' => 'El ID del grupo solo puede contener letras, números, guion bajo, punto, arroba o guion medio.',
			], Http::STATUS_BAD_REQUEST);
		}

		return null;
	}

	private function getPayload(): array {
		$params = $this->request->getParams();

		unset($params['_route']);
		unset($params['id']);

		return $params;
	}

	private function nullableString($value): ?string {
		if ($value === null) {
			return null;
		}

		$value = trim((string)$value);

		return $value === '' ? null : $value;
	}

	private function toBool($value): bool {
		if (is_bool($value)) {
			return $value;
		}

		if (is_string($value)) {
			return in_array(strtolower($value), ['1', 'true', 'yes', 'si', 'sí'], true);
		}

		return (bool)$value;
	}

	#[NoCSRFRequired]
	#[AdminRequired]
	public function estructura(): DataResponse {
		$baseGroups = [];
		$catalogGroups = [];
		$missingBase = [];
		$missingCatalog = [];

		foreach (self::BASE_GROUPS as $baseGroup) {
			$groupId = $baseGroup['id'];
			$exists = $this->groupManager->get($groupId) !== null;

			$item = [
				'type' => 'base',
				'id' => $groupId,
				'label' => $baseGroup['label'],
				'description' => $baseGroup['description'],
				'exists' => $exists,
				'required' => true,
			];

			$baseGroups[] = $item;

			if (!$exists) {
				$missingBase[] = $item;
			}
		}

		foreach ($this->permisoGrupoMapper->findAllCatalog() as $row) {
			$groupId = (string)$row['group_id'];
			$exists = $this->groupManager->get($groupId) !== null;
			$enabled = ((int)$row['enabled']) === 1;

			$item = [
				'type' => 'permission',
				'catalog_id' => (int)$row['id'],
				'id' => $groupId,
				'module' => (string)$row['module'],
				'permission' => (string)$row['permission'],
				'label' => (string)$row['label'],
				'description' => $row['description'] ?? '',
				'restricted' => ((int)$row['restricted']) === 1,
				'enabled' => $enabled,
				'exists' => $exists,
				'required' => $enabled,
			];

			$catalogGroups[] = $item;

			if ($enabled && !$exists) {
				$missingCatalog[] = $item;
			}
		}

		return new DataResponse([
			'status' => 'ok',
			'data' => [
				'summary' => [
					'base_total' => count($baseGroups),
					'base_missing' => count($missingBase),
					'catalog_total' => count($catalogGroups),
					'catalog_missing' => count($missingCatalog),
					'missing_total' => count($missingBase) + count($missingCatalog),
				],
				'base_groups' => $baseGroups,
				'catalog_groups' => $catalogGroups,
				'missing_base_groups' => $missingBase,
				'missing_catalog_groups' => $missingCatalog,
			],
		], Http::STATUS_OK);
	}

	#[AdminRequired]
	public function repararEstructura(): DataResponse {
		$created = [];
		$alreadyExists = [];
		$failed = [];

		$groupsToCheck = [];

		foreach (self::BASE_GROUPS as $baseGroup) {
			$groupsToCheck[$baseGroup['id']] = [
				'id' => $baseGroup['id'],
				'label' => $baseGroup['label'],
				'type' => 'base',
			];
		}

		foreach ($this->permisoGrupoMapper->findAllCatalog() as $row) {
			if (((int)$row['enabled']) !== 1) {
				continue;
			}

			$groupId = (string)$row['group_id'];

			$groupsToCheck[$groupId] = [
				'id' => $groupId,
				'label' => (string)$row['label'],
				'type' => 'permission',
				'module' => (string)$row['module'],
				'permission' => (string)$row['permission'],
			];
		}

		foreach ($groupsToCheck as $groupId => $groupData) {
			$group = $this->groupManager->get($groupId);

			if ($group !== null) {
				$alreadyExists[] = $groupData;
				continue;
			}

			$createdGroup = $this->groupManager->createGroup($groupId);

			if ($createdGroup === null) {
				$failed[] = $groupData;
				continue;
			}

			$created[] = $groupData;
		}

		return new DataResponse([
			'status' => empty($failed) ? 'ok' : 'partial',
			'message' => empty($failed)
				? 'Estructura de grupos reparada correctamente.'
				: 'Algunos grupos no pudieron crearse.',
			'data' => [
				'created' => $created,
				'already_exists' => $alreadyExists,
				'failed' => $failed,
			],
		], empty($failed) ? Http::STATUS_OK : Http::STATUS_MULTI_STATUS);
	}
	#[NoCSRFRequired]
	#[AdminRequired]
	public function gruposNextcloud(): DataResponse {
		$search = trim((string)$this->request->getParam('search', ''));
		$groups = $this->groupManager->search($search);

		$data = [];

		foreach ($groups as $group) {
			$gid = $group->getGID();

			$data[] = [
				'id' => $gid,
				'label' => $gid,
			];
		}

		return new DataResponse([
			'status' => 'ok',
			'data' => $data,
		], Http::STATUS_OK);
	}
}