<?php

declare(strict_types=1);
namespace OCA\Empleados\Controller;

use OCA\Empleados\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\IRequest;
use OCP\IL10N;
use OCP\IUserSession;
use OCP\IUserManager;
use OCP\IGroupManager;
use OCA\Empleados\Db\empleadosMapper;
use OCA\Empleados\Db\departamentosMapper;
use OCA\Empleados\Db\configuracionesMapper;
use OCA\Empleados\Db\empleados;
use OCA\Empleados\Db\departamentos;
use OCA\Empleados\Db\configuraciones;
use OCA\Empleados\UploadException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;

use OCA\Empleados\Service\PermisosService;
use OCA\Empleados\Service\CompraPermisosService;

require_once 'SimpleXLSXGen.php';
require_once 'SimpleXLSX.php';

/**
 * Controlador para la gestión de áreas en Nextcloud.
 */
class AreasController extends BaseController {

    protected $userSession;
    protected $userManager;
    protected $empleadosMapper;
    protected $departamentosMapper;
    protected $configuracionesMapper;
    protected $l10n;
    protected PermisosService $permisosService;
    protected CompraPermisosService $compraPermisosService;

    public function __construct(
        IRequest $request,
        IUserSession $userSession,
        IUserManager $userManager,
        empleadosMapper $empleadosMapper,
        departamentosMapper $departamentosMapper,
        configuracionesMapper $configuracionesMapper,
        IL10N $l10n,
        IGroupManager $groupManager,
        PermisosService $permisosService,
        CompraPermisosService $compraPermisosService
    ) {
        parent::__construct(Application::APP_ID, $request, $userSession, $groupManager, $empleadosMapper, $configuracionesMapper);

        $this->userSession = $userSession;
        $this->userManager = $userManager;
        $this->empleadosMapper = $empleadosMapper;
        $this->departamentosMapper = $departamentosMapper;
        $this->configuracionesMapper = $configuracionesMapper;
        $this->l10n = $l10n;
        $this->permisosService = $permisosService;
        $this->compraPermisosService = $compraPermisosService;
    }

    /**
     * Obtiene la lista de áreas en formato clave-valor.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GetAreasFix(): DataResponse {
        $this->requireAreasCatalogAccess();
        $areas = array_map(fn($area) => [
            'value' => $area['Id_departamento'],
            'label' => $area['Nombre'],
            'mostrar_clientes' => (bool)($area['mostrar_clientes'] ?? true),
            'mostrar_ausencias' => (bool)($area['mostrar_ausencias'] ?? true),
        ], $this->departamentosMapper->GetAreasList());

        return new DataResponse($areas, Http::STATUS_OK);
    }

    /**
     * Obtiene la lista de áreas.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GetAreasList(): DataResponse {
        $this->permisosService->requireCanSeeAny([
            'empleados.hr',
            'empleados.admin',
            'clientes',
        ]);
        return new DataResponse($this->departamentosMapper->GetAreasList(), Http::STATUS_OK);
    }

    /**
     * Exporta la lista de áreas a un archivo XLSX.
     */
    public function ExportListAreas(): DataResponse {
        $this->requireHumanResourcesAccess();
        $areas = $this->departamentosMapper->GetAreasList();
        $books = [['Id_departamento', 'Id_padre', 'Nombre', 'mostrar_clientes', 'mostrar_ausencias', 'created_at', 'updated_at']];

        foreach ($areas as $area) {
            $books[] = [
                $area['Id_departamento'],
                $area['Id_padre'],
                $area['Nombre'],
                !empty($area['mostrar_clientes']) ? 1 : 0,
                !empty($area['mostrar_ausencias']) ? 1 : 0,
                $area['created_at'],
                $area['updated_at'],
            ];
        }

        \Shuchkin\SimpleXLSXGen::fromArray($books)->downloadAs('areas.xlsx');
        return new DataResponse($books, Http::STATUS_OK);
    }

    /**
     * Importa la lista de áreas desde un archivo XLSX.
     */
    public function ImportListAreas(): DataResponse {
        $this->requireHumanResourcesAccess();
        $file = $this->getUploadedFile('AreafileXLSX');
        if ($xlsx = \Shuchkin\SimpleXLSX::parse($file['tmp_name'])) {
            foreach ($xlsx->rows() as $row) {
                if (!empty($row[0])) {
                    $this->departamentosMapper->updateAreas((string) $row[0], (string) $row[1], (string) $row[2]);
                } else {
                    $timestamp = date('Y-m-d');
                    $area = new departamentos();
                    $area->setid_padre((string) $row[1]);
                    $area->setnombre((string) $row[2]);
                    $area->setcreated_at($timestamp);
                    $area->setupdated_at($timestamp);
                    $this->departamentosMapper->insert($area);
                }
            }
            return new DataResponse(['status' => 'error'], Http::STATUS_BAD_REQUEST);
        }
        return new DataResponse(Http::STATUS_OK);
    }

    /**
     * Elimina un área por ID.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function EliminarArea(int $id_departamento): DataResponse {
        $this->requireHumanResourcesAccess();
        try {
            $this->departamentosMapper->EliminarArea((string) $id_departamento);
            return new DataResponse(Http::STATUS_OK);
        } catch (\Exception $e) {
            return new DataResponse("Error al eliminar el área: " . $e->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Guarda cambios en las áreas.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GuardarCambioArea(
        int $id_departamento,
        string $padre,
        string $nombre,
        $mostrar_clientes = null,
        $mostrar_ausencias = null
    ): DataResponse {
        $this->requireHumanResourcesAccess();
        $this->departamentosMapper->updateAreas(
            (string) $id_departamento,
            $padre,
            $nombre,
            $this->normalizeOptionalBool($mostrar_clientes),
            $this->normalizeOptionalBool($mostrar_ausencias)
        );
        return new DataResponse(Http::STATUS_OK);
    }

    /**
     * Crea una nueva área.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function crearArea(string $nombre, string $padre): DataResponse {
        $this->requireHumanResourcesAccess();
        $timestamp = date('Y-m-d');
        $area = new departamentos();
        $area->setid_padre($padre);
        $area->setnombre($nombre);
        $area->setcreated_at($timestamp);
        $area->setupdated_at($timestamp);
        $area->setMostrarClientes(1);
        $area->setMostrarAusencias(1);
        $this->departamentosMapper->insert($area);
        return new DataResponse(Http::STATUS_OK);
    }

    /**
     * @param mixed $value
     */
    private function normalizeOptionalBool($value): ?bool {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int)$value === 1;
        }
        $normalized = strtolower(trim((string)$value));
        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }
        if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }
        return null;
    }

    /**
     * Obtiene un archivo subido y maneja posibles errores.
     */
    #[UseSession]
    #[NoAdminRequired]
    private function getUploadedFile(string $key): array {
        $this->requireHumanResourcesAccess();

        $file = $this->request->getUploadedFile($key);
        if (empty($file) || ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new UploadException($this->l10n->t('Error en la subida del archivo.'));
        }
        return $file;
    }

    private function requireHumanResourcesAccess(): void {
        $this->permisosService->requireCanSeeAny([
            'empleados.hr',
            'empleados.admin',
        ]);
    }

    /**
     * Catálogo de solo lectura (id/nombre) para etiquetas en módulos
     * como compras, sin conceder gestión de RRHH.
     */
    private function requireCatalogReadAccess(): void {
        $user = $this->userSession->getUser();
        $uid = $user?->getUID();

        if ($uid !== null && $uid !== '' && (
            $this->permisosService->canManageHumanResources($uid)
            || $this->compraPermisosService->canAccessModule($uid)
            || $this->permisosService->canSeeAny([
                'compras.admin',
                'compras.approve',
                'compras.request',
                'compras.accounting',
            ], $uid)
        )) {
            return;
        }

        $this->requireHumanResourcesAccess();
    }

    /**
     * Lectura de catálogo de áreas también para reportes administrativos.
     */
    private function requireAreasCatalogAccess(): void {
        $user = $this->userSession->getUser();
        $uid = $user?->getUID();

        if ($uid !== null && $uid !== '' && $this->permisosService->canSee('reporte_tiempos.admin', $uid)) {
            return;
        }

        $this->requireCatalogReadAccess();
    }
}
