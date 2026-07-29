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
	public function GetActividades(): DataResponse {
		$this->requireClientesAccess();

		return new DataResponse($this->actividadesMapper->findAll(), Http::STATUS_OK);
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

		return new DataResponse($this->actividadesMapper->deleteById($id), Http::STATUS_OK);
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
		bool $cargable
	): DataResponse {
		$this->requireClientesAdminAccess();

		$tipo = strtolower(trim($tipo));
		if ($tipo === 'horas') {
			$tiempoestimado *= 60;
		}

		$this->actividadesMapper->updateActividad(
			$id_actividad,
			$nombre,
			$detalles,
			$tiempoestimado,
			$cargable
		);

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
		?bool $cargable
	): DataResponse {
		$this->requireClientesAdminAccess();

		$tipo = strtolower(trim($tipo));
		if ($tipo === 'horas') {
			$tiempoestimado *= 60;
		}

		$actividad = new actividades();
		$actividad->setnombre($nombre);
		$actividad->setdetalles($detalles);
		$actividad->settiempo_estimado($tiempoestimado);
		$actividad->setcargable($cargable);
		$this->actividadesMapper->insert($actividad);

		return new DataResponse(['status' => 'ok'], Http::STATUS_OK);
	}

	/**
	 * Exporta la lista de actividades a un archivo XLSX.
	 */
	public function ExportarActividades(): DataResponse {
		$this->requireClientesAdminAccess();

		$actividad = $this->actividadesMapper->findAll();
		$books = [['id_actividad', 'nombre', 'detalles', 'tiempo_estimado', 'tiempo_real', 'cargable']];

		foreach ($actividad as $item) {
			$books[] = [
				$item['id_actividad'],
				$item['nombre'],
				$item['detalles'],
				$item['tiempo_estimado'],
				$item['tiempo_real'],
				$item['cargable'],
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

		foreach (array_slice($xlsx->rows(), 1) as $row) {
			if (!empty($row[0])) {
				$this->actividadesMapper->updateActividad(
					(int)$row[0],
					(string)($row[1] ?? ''),
					$row[2] ?? null,
					(float)($row[3] ?? 0),
					(bool)($row[4] ?? false)
				);
			} else {
				$actividad = new actividades();
				$actividad->setNombre((string)($row[1] ?? ''));
				$actividad->setDetalles($row[2] ?? null);
				$actividad->setTiempo_estimado((float)($row[3] ?? 0));
				$actividad->setCargable((bool)($row[4] ?? false));
				$this->actividadesMapper->insert($actividad);
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
