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
use OCA\Empleados\Service\BitacoraService;

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
	protected BitacoraService $bitacoraService;

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
		BitacoraService $bitacoraService,
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
		$this->bitacoraService = $bitacoraService;
	}

	private function requireClientesAccess(): void {
		$this->permisosService->requireCanSee('clientes');
	}

	private function requireClientesAdminAccess(): void {
		$this->permisosService->requireCanSee('clientes.admin');
	}

	/**
	 * Registra un movimiento del módulo "actividades" en la bitácora general.
	 */
	private function registrarMovimiento(
		?string $uidActor,
		?int $idReferencia,
		?string $nombreAfectado,
		string $tipo,
		string $mensaje
	): void {
		$this->bitacoraService->registrar('actividades', $uidActor, null, $nombreAfectado, $tipo, $mensaje, $idReferencia);
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

		// --- Capturamos el nombre ANTES de eliminar, para la bitácora ---
		$existente = $this->actividadesMapper->findById((int) $id);
		$nombreActividad = $existente[0]['nombre'] ?? ('Actividad ' . $id);

		try {
			$this->actividadesMapper->deleteById((int)$id);
		} catch (\RuntimeException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_CONFLICT);
		}

		// --- Movimiento (bitácora) ---
		$user = $this->userSession->getUser();
		$uid = $user->getUID();

		$mensaje = sprintf('%s ha eliminado la actividad "%s".', $user->getDisplayName(), $nombreActividad);
		$this->registrarMovimiento($uid, (int) $id, $nombreActividad, 'eliminacion', $mensaje);

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

		// --- Capturamos el estado ANTES de editar, para el diff en bitácora ---
		$antesRows = $this->actividadesMapper->findById($id_actividad);
		$antes = $antesRows[0] ?? null;

		try {
			$this->actividadesMapper->updateActividad(
				$id_actividad, $nombre, $detalles, $tiempoestimado, $cargable,
				$tipo_actividad, $alcance, $area_ids,
			);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		// --- Movimiento (bitácora) ---
		$user = $this->userSession->getUser();
		$uid = $user->getUID();

		$cambios = [];
		if ($antes) {
			if (($antes['nombre'] ?? '') !== $nombre) {
				$cambios[] = sprintf('nombre "%s" → "%s"', $antes['nombre'] ?? '', $nombre);
			}
			if ((float) ($antes['tiempo_estimado'] ?? 0) !== $tiempoestimado) {
				$cambios[] = sprintf('tiempo estimado %s → %s min', $antes['tiempo_estimado'] ?? 0, $tiempoestimado);
			}
			if ((bool) ($antes['cargable'] ?? false) !== $cargable) {
				$cambios[] = $cargable ? 'ahora es cargable' : 'ya no es cargable';
			}
			if (($antes['tipo_actividad'] ?? actividades::TIPO_CLIENTE) !== $tipo_actividad) {
				$cambios[] = sprintf('tipo "%s" → "%s"', $antes['tipo_actividad'] ?? '', $tipo_actividad);
			}
			if (($antes['alcance'] ?? actividades::ALCANCE_GLOBAL) !== $alcance) {
				$cambios[] = sprintf('alcance "%s" → "%s"', $antes['alcance'] ?? '', $alcance);
			}
		}

		$mensaje = sprintf(
			'%s ha editado la actividad "%s"%s.',
			$user->getDisplayName(),
			$nombre,
			!empty($cambios) ? (': ' . implode(', ', $cambios)) : ''
		);

		$this->registrarMovimiento($uid, $id_actividad, $nombre, 'edicion', $mensaje);

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

		// --- Movimiento (bitácora) ---
		$user = $this->userSession->getUser();
		$uid = $user->getUID();

		$mensaje = sprintf(
			'%s ha creado la actividad "%s" (%s, %s min%s).',
			$user->getDisplayName(),
			trim($nombre),
			$tipo_actividad,
			(string) $tiempoestimado,
			$cargable ? ', cargable' : ''
		);

		$this->registrarMovimiento($uid, $id, trim($nombre), 'creacion', $mensaje);

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

		$file = $this->request->getUploadedFile('ActividadesfileXLSX');

		if (empty($file) || ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
			return new DataResponse([
				'status' => 'error',
				'message' => 'Error en la subida del archivo.'
			], Http::STATUS_BAD_REQUEST);
		}

		$xlsx = \Shuchkin\SimpleXLSX::parse($file['tmp_name']);

		if (!$xlsx) {
			return new DataResponse([
				'status' => 'error',
				'message' => 'No se pudo leer el archivo XLSX.'
			], Http::STATUS_BAD_REQUEST);
		}

		$rows = $xlsx->rows();

		if (empty($rows)) {
			return new DataResponse([
				'status' => 'error',
				'message' => 'El archivo está vacío.'
			], Http::STATUS_BAD_REQUEST);
		}

		$headers = array_map(
			static fn($value): string =>
				strtolower(trim((string)$value)),
			$rows[0]
		);

		$requiredHeaders = [
			'id_actividad',
			'nombre',
			'detalles',
			'tiempo_estimado',
			'cargable',
		];

		foreach ($requiredHeaders as $required) {
			if (!in_array($required, $headers, true)) {
				return new DataResponse([
					'status' => 'error',
					'message' => "Falta la columna '{$required}' en el archivo."
				], Http::STATUS_BAD_REQUEST);
			}
		}

		$column = static function (
			string $name,
			?int $fallback = null
		) use ($headers): ?int {

			$index = array_search($name, $headers, true);

			if ($index !== false) {
				return (int)$index;
			}

			return $fallback;
		};

		$hasTypeColumn = $column('tipo_actividad') !== null;
		$hasScopeColumn = $column('alcance') !== null;
		$hasAreasColumn = $column('area_ids') !== null;

		$created = 0;
		$updated = 0;
		$skipped = 0;

		foreach (array_slice($rows, 1) as $offset => $row) {

			$excelRow = $offset + 2;

			$idValue = $column('id_actividad') !== null
				? ($row[$column('id_actividad')] ?? null)
				: null;

			$id = null;

			if ($idValue !== null && trim((string)$idValue) !== '') {
				$id = (int)$idValue;
			}
			$name = trim(
				(string)(
					$row[$column('nombre')] ?? ''
				)
			);

			if ($name === '') {
				$skipped++;
				continue;
			}

			$detalles = null;

			if ($column('detalles') !== null) {
				$detallesValue = $row[$column('detalles')] ?? null;

				if ($detallesValue !== null && trim((string)$detallesValue) !== '') {
					$detalles = (string)$detallesValue;
				}
			}

			$tiempoEstimado = 0;

			if ($column('tiempo_estimado') !== null) {
				$value = $row[$column('tiempo_estimado')] ?? 0;

				if ($value !== null && trim((string)$value) !== '') {
					$tiempoEstimado = (float)$value;
				}
			}
			
			$billableValue = strtolower(
				trim(
					(string)(
						$row[$column('cargable')] ?? '0'
					)
				)
			);

			$billable = in_array(
				$billableValue,
				['1', 'true', 'si', 'sí', 'yes'],
				true
			);

			$type = actividades::TIPO_CLIENTE;

			if ($hasTypeColumn) {
				$typeValue = trim(
					(string)(
						$row[$column('tipo_actividad')] ?? ''
					)
				);

				if ($typeValue !== '') {
					$type = $typeValue;
				}
			}

			$scope = actividades::ALCANCE_GLOBAL;

			if ($hasScopeColumn) {
				$scopeValue = trim(
					(string)(
						$row[$column('alcance')] ?? ''
					)
				);

				if ($scopeValue !== '') {
					$scope = $scopeValue;
				}
			}

			$areaIds = [];

			if ($hasAreasColumn) {
				$areasValue = trim(
					(string)(
						$row[$column('area_ids')] ?? ''
					)
				);

				if ($areasValue !== '') {
					$areaIds = array_values(
						array_filter(
							array_map(
								'intval',
								preg_split(
									'/\s*,\s*/',
									$areasValue
								) ?: []
							),
							static fn($id) => $id > 0
						)
					);
				}
			}

			try {

				if ($id !== null && $id > 0) {

					$existing = $this->actividadesMapper->findById($id);

					if (empty($existing)) {
						return new DataResponse([
							'status' => 'error',
							'message' =>
								"Fila {$excelRow}: la actividad con ID {$id} no existe."
						], Http::STATUS_BAD_REQUEST);
					}

					if (!$hasTypeColumn) {
						$type = (string)(
							$existing[0]['tipo_actividad']
							?? actividades::TIPO_CLIENTE
						);
					}

					if (!$hasScopeColumn) {
						$scope = (string)(
							$existing[0]['alcance']
							?? actividades::ALCANCE_GLOBAL
						);
					}

					if (!$hasAreasColumn) {
						$areaIds = $existing[0]['area_ids'] ?? [];
					}

					$this->actividadesMapper->updateActividad(
						$id,
						$name,
						$detalles,
						$tiempoEstimado,
						$billable,
						$type,
						$scope,
						$areaIds
					);

					$updated++;
				} else {
					$this->actividadesMapper->createActivity(
						$name,
						$detalles,
						$tiempoEstimado,
						$billable,
						$type,
						$scope,
						$areaIds
					);

					$created++;
				}

			} catch (\InvalidArgumentException $e) {

				return new DataResponse([
					'status' => 'error',
					'message' =>
						"Fila {$excelRow}: {$e->getMessage()}"
				], Http::STATUS_BAD_REQUEST);
			}
		}

		// --- Movimiento (bitácora) ---
		if ($created > 0 || $updated > 0) {
			$user = $this->userSession->getUser();
			$uid = $user->getUID();

			$mensaje = sprintf(
				'%s ha importado actividades desde un archivo XLSX: %d creada(s), %d actualizada(s)%s.',
				$user->getDisplayName(),
				$created,
				$updated,
				$skipped > 0 ? sprintf(', %d omitida(s)', $skipped) : ''
			);

			$this->registrarMovimiento($uid, null, null, 'importacion', $mensaje);
		}

		return new DataResponse([
			'status' => 'ok',
			'message' => 'Importación completada correctamente.',
			'created' => $created,
			'updated' => $updated,
			'skipped' => $skipped,
		], Http::STATUS_OK);
	}
}
