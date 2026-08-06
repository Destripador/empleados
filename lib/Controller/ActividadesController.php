<?php

declare(strict_types=1);
namespace OCA\Empleados\Controller;

use OCA\Empleados\AppInfo\Application;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\IRequest;
use OCP\IL10N;
use OCP\IUserSession;
use OCP\IUserManager;
use OCA\Empleados\Db\empleadosMapper;
use OCA\Empleados\Db\actividadesMapper;
use OCA\Empleados\Db\configuracionesMapper;
use OCA\Empleados\Db\actividades;
use OCA\Empleados\UploadException;
use OCA\Empleados\Service\PermisosService;
use OCP\IGroupManager;
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\Http\Client\IClientService;
use OCP\Group\ISubAdmin;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;

require_once 'SimpleXLSXGen.php';
require_once 'SimpleXLSX.php';

/**
 * Controlador para la gestión de actividades en Nextcloud.
 */
class actividadesController extends BaseController {

	protected $userSession;
	protected $userManager;
	protected $empleadosMapper;
	protected $actividadesMapper;
	protected $configuracionesMapper;
	protected $l10n;
	protected $config;
	protected $groupManager;
	protected $urlGenerator;
	protected $clientService;
	protected $subAdmin;
	protected PermisosService $permisosService;

	public function __construct(
		IRequest $request,
		IUserSession $userSession,
		IUserManager $userManager,
		empleadosMapper $empleadosMapper,
		actividadesMapper $actividadesMapper,
		configuracionesMapper $configuracionesMapper,
		IL10N $l10n,
		IConfig $config,
		IGroupManager $groupManager,
		IURLGenerator $urlGenerator,
		IClientService $clientService,
		ISubAdmin $subAdmin,
		PermisosService $permisosService,
	) {
		parent::__construct(Application::APP_ID, $request, $userSession, $groupManager, $empleadosMapper, $configuracionesMapper);

		$this->userSession = $userSession;
		$this->userManager = $userManager;
		$this->empleadosMapper = $empleadosMapper;
		$this->actividadesMapper = $actividadesMapper;
		$this->configuracionesMapper = $configuracionesMapper;
		$this->l10n = $l10n;
		$this->groupManager = $groupManager;
		$this->config = $config;
		$this->urlGenerator = $urlGenerator;
		$this->clientService = $clientService;
		$this->subAdmin = $subAdmin;
		$this->permisosService = $permisosService;
	}

	private function requireClientesAccess(): void {
		$this->permisosService->requireCanSee('clientes');
	}

	private function requireClientesAdminAccess(): void {
		$this->permisosService->requireCanSee('clientes.admin');
	}

	/**
	 * Obtiene la lista de actividades.
	 */
	#[UseSession]
	#[NoAdminRequired]
	public function GetActividades(mixed $manual = false): DataResponse {
		$this->requireClientesAccess();
		$manual = $manual === true || $manual === 1 || $manual === '1' || $manual === 'true';
		if (!$manual) return new DataResponse($this->actividadesMapper->findAll(), Http::STATUS_OK);
		$user = $this->userSession->getUser();
		$employee = $user === null ? [] : $this->empleadosMapper->GetMyEmployeeInfo($user->getUID());
		$row = $employee[0] ?? [];
		$departmentId = isset($row['Id_departamento']) && $row['Id_departamento'] !== null
			? (int)$row['Id_departamento']
			: null;
		return new DataResponse($this->actividadesMapper->findManualAvailable($departmentId), Http::STATUS_OK);
	}

	/**
	 * Obtiene una actividad por ID.
	 */
	#[UseSession]
	#[NoAdminRequired]
	public function findById($id): DataResponse {
		$this->requireClientesAccess();

		return new DataResponse($this->actividadesMapper->findById($id), Http::STATUS_OK);
	}

	/**
	 * Elimina una actividad.
	 */
	#[UseSession]
	#[NoAdminRequired]
	public function deleteById($id): DataResponse {
		$this->requireClientesAdminAccess();
		try {
			$this->actividadesMapper->deleteById((int)$id);
		} catch (\RuntimeException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_CONFLICT);
		}
		return new DataResponse('ok', Http::STATUS_OK);
	}

	/**
	 * Guarda cambios en una actividad.
	 */
	#[UseSession]
	#[NoAdminRequired]
	public function modificarActividad(
		int $id_actividad,
		string $nombre,
		string $detalles,
		float $tiempoestimado,
		string $tipo,
		bool $cargable,
		string $tipo_actividad = actividades::TIPO_CLIENTE,
		string $alcance = actividades::ALCANCE_GLOBAL,
		array $area_ids = [],
	): DataResponse {
		$this->requireClientesAdminAccess();

		$tipo = strtolower(trim($tipo));
		if ($tipo === 'horas') {
			$tiempoestimado *= 60;
		}

		try {
			$this->actividadesMapper->updateActividad(
				$id_actividad, $nombre, $detalles, $tiempoestimado, $cargable,
				$tipo_actividad, $alcance, $area_ids,
			);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse('ok', Http::STATUS_OK);
	}

	/**
	 * Crea una nueva actividad.
	 */
	#[UseSession]
	#[NoAdminRequired]
	public function crearActividad(
		string $nombre,
		?string $detalles,
		float $tiempoestimado,
		string $tipo,
		?bool $cargable,
		string $tipo_actividad = actividades::TIPO_CLIENTE,
		string $alcance = actividades::ALCANCE_GLOBAL,
		array $area_ids = [],
	): DataResponse {
		$this->requireClientesAdminAccess();

		$tipo = strtolower(trim($tipo));
		if ($tipo === 'horas') {
			$tiempoestimado *= 60;
		}

		try {
			$id = $this->actividadesMapper->createActivity(
				trim($nombre), $detalles, $tiempoestimado, (bool)$cargable,
				$tipo_actividad, $alcance, $area_ids,
			);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse(['status' => 'ok', 'id_actividad' => $id], Http::STATUS_OK);
	}

	/**
	 * Exporta la lista de actividades a un archivo XLSX.
	 */
	public function ExportarActividades(): DataResponse {
		$this->requireClientesAdminAccess();

		$actividad = $this->actividadesMapper->findAll();
		$books = [['id_actividad', 'nombre', 'detalles', 'tiempo_estimado', 'tiempo_real', 'cargable', 'tipo_actividad', 'alcance', 'area_ids']];

		foreach ($actividad as $item) {
			$books[] = [
				$item['id_actividad'],
				$item['nombre'],
				$item['detalles'],
				$item['tiempo_estimado'],
				$item['tiempo_real'],
				$item['cargable'],
				$item['tipo_actividad'] ?? actividades::TIPO_CLIENTE,
				$item['alcance'] ?? actividades::ALCANCE_GLOBAL,
				implode(',', $item['area_ids'] ?? []),
			];
		}

		\Shuchkin\SimpleXLSXGen::fromArray($books)->downloadAs('actividades.xlsx');

		return new DataResponse($books, Http::STATUS_OK);
	}

	/**
	 * Importa la lista de actividades desde un archivo XLSX.
	 */
	public function ImportarActividades(): DataResponse {
		$this->requireClientesAdminAccess();

		$file = $this->getUploadedFile('ActividadesfileXLSX');
		$xlsx = \Shuchkin\SimpleXLSX::parse($file['tmp_name']);

		if (!$xlsx) {
			return new DataResponse(['status' => 'error'], Http::STATUS_BAD_REQUEST);
		}

		$rows = $xlsx->rows();
		$headers = array_map(static fn($value): string => strtolower(trim((string)$value)), $rows[0] ?? []);
		$column = static function (string $name, int $fallback) use ($headers): int {
			$index = array_search($name, $headers, true);
			return $index === false ? $fallback : (int)$index;
		};
		$hasTypeColumn = in_array('tipo_actividad', $headers, true);
		$hasScopeColumn = in_array('alcance', $headers, true);
		$hasAreasColumn = in_array('area_ids', $headers, true) || in_array('areas', $headers, true);
		foreach (array_slice($rows, 1) as $offset => $row) {
			$type = (string)($row[$column('tipo_actividad', 6)] ?? actividades::TIPO_CLIENTE);
			$scope = (string)($row[$column('alcance', 7)] ?? actividades::ALCANCE_GLOBAL);
			$areaIds = array_values(array_filter(array_map(
				'intval',
				preg_split('/\s*,\s*/', trim((string)($row[$column('area_ids', $column('areas', 8))] ?? ''))) ?: [],
			)));
			$billableValue = strtolower(trim((string)($row[$column('cargable', 5)] ?? '0')));
			$billable = in_array($billableValue, ['1', 'true', 'si', 'sí', 'yes'], true);
			$name = trim((string)($row[$column('nombre', 1)] ?? ''));
			if ($name === '') continue;
			try {
			if (!empty($row[0])) {
				$existing = $this->actividadesMapper->findById((int)$row[0]);
				if ($existing === []) throw new \InvalidArgumentException('La actividad seleccionada no existe.');
				$type = $hasTypeColumn ? $type : (string)($existing[0]['tipo_actividad'] ?? actividades::TIPO_CLIENTE);
				$scope = $hasScopeColumn ? $scope : (string)($existing[0]['alcance'] ?? actividades::ALCANCE_GLOBAL);
				$areaIds = $hasAreasColumn ? $areaIds : ($existing[0]['area_ids'] ?? []);
				$this->actividadesMapper->updateActividad(
					(int)$row[0],
					$name,
					$row[$column('detalles', 2)] ?? null,
					(float)($row[$column('tiempo_estimado', 3)] ?? 0),
					$billable,
					$type,
					$scope,
					$areaIds,
				);
			} else {
				$this->actividadesMapper->createActivity(
					$name,
					$row[$column('detalles', 2)] ?? null,
					(float)($row[$column('tiempo_estimado', 3)] ?? 0),
					$billable,
					$type,
					$scope,
					$areaIds,
				);
			}
			} catch (\InvalidArgumentException $e) {
				return new DataResponse([
					'status' => 'error',
					'message' => 'Fila ' . ($offset + 2) . ': ' . $e->getMessage(),
				], Http::STATUS_BAD_REQUEST);
			}
		}

		return new DataResponse(['status' => 'ok'], Http::STATUS_OK);
	}

	/**
	 * Obtiene un archivo subido y maneja posibles errores.
	 */
	private function getUploadedFile(string $key): array {
		$this->requireClientesAdminAccess();

		$file = $this->request->getUploadedFile($key);
		if (empty($file) || ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
			throw new UploadException($this->l10n->t('Error en la subida del archivo.'));
		}

		return $file;
	}
}
