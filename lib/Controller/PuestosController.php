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
use OCA\Empleados\Db\empleadosMapper;
use OCA\Empleados\Db\puestosMapper;
use OCA\Empleados\Db\configuracionesMapper;
use OCA\Empleados\Db\empleados;
use OCA\Empleados\Db\puestos;
use OCA\Empleados\Db\configuraciones;
use OCA\Empleados\UploadException;
use OCP\IGroupManager;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;

use OCA\Empleados\Service\PermisosService;

require_once 'SimpleXLSXGen.php';
require_once 'SimpleXLSX.php';

/**
 * Controlador para la gestión de puestos de empleados en Nextcloud.
 */
class PuestosController extends BaseController {

    protected $userSession;
    protected $userManager;
    protected $empleadosMapper;
    protected $puestosMapper;
    protected $configuracionesMapper;
    protected $l10n;
    protected PermisosService $permisosService;

    public function __construct(
        IRequest $request,
        IUserSession $userSession,
        IUserManager $userManager,
        empleadosMapper $empleadosMapper,
        puestosMapper $puestosMapper,
        configuracionesMapper $configuracionesMapper,
        IL10N $l10n,
		IGroupManager $groupManager,
        PermisosService $permisosService
        
    ) {
		parent::__construct(Application::APP_ID, $request, $userSession, $groupManager, $empleadosMapper, $configuracionesMapper);

        $this->userSession = $userSession;
        $this->userManager = $userManager;
        $this->empleadosMapper = $empleadosMapper;
        $this->puestosMapper = $puestosMapper;
        $this->configuracionesMapper = $configuracionesMapper;
        $this->l10n = $l10n;
        $this->permisosService = $permisosService;
    }

    /**
     * Obtiene la lista de puestos en formato clave-valor.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GetPuestosFix(): DataResponse {
        $this->requireHumanResourcesAccess();
        $result = array_map(fn($puesto) => [
            'value' => $puesto['Id_puestos'],
            'label' => $puesto['Nombre'],
        ], $this->puestosMapper->GetPuestosList());

        return new Dataresponse($result, Http::STATUS_OK);
    }

    /**
     * Obtiene la lista de puestos.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GetPuestosList(): DataResponse {
        $this->requireHumanResourcesAccess();
        return new DataResponse($this->puestosMapper->GetPuestosList(), Http::STATUS_OK);
    }

    /**
     * Exporta la lista de puestos a un archivo XLSX.
     */
    public function ExportListPuestos(): DataResponse {
        $this->requireHumanResourcesAccess();
        $puestos = $this->puestosMapper->GetPuestosList();
        $books = [['Id_puesto', 'Nombre', 'Nivel', 'created_at', 'updated_at']];

        foreach ($puestos as $puesto) {
            $books[] = [
                $puesto['Id_puestos'],
                $puesto['Nombre'],
                $puesto['Nivel'],
                $puesto['created_at'],
                $puesto['updated_at'],
            ];
        }

        \Shuchkin\SimpleXLSXGen::fromArray($books)->downloadAs('puestos.xlsx');
        return new DataResponse($books, Http::STATUS_OK);
    }

    /**
     * Importa la lista de puestos desde un archivo XLSX.
     */
    public function ImportListPuestos(): DataResponse {
        $this->requireHumanResourcesAccess();
        $file = $this->getUploadedFile('puestofileXLSX');
        if ($xlsx = \Shuchkin\SimpleXLSX::parse($file['tmp_name'])) {
            foreach ($xlsx->rows() as $row) {
                $nivel = isset($row[2]) && $row[2] !== '' ? (int) $row[2] : null;

                if (!empty($row[0])) {
                    $this->puestosMapper->updatePuestos((string) $row[0], (string) $row[1], $nivel);
                } else {
                    $timestamp = date('Y-m-d');
                    $puesto = new puestos();
                    $puesto->setnombre((string) $row[1]);
                    $puesto->setnivel($nivel);
                    $puesto->setcreated_at($timestamp);
                    $puesto->setupdated_at($timestamp);
                    $this->puestosMapper->insert($puesto);
                }
            }
            return new DataResponse(Http::STATUS_INTERNAL_SERVER_ERROR);
        }
        return new DataResponse(Http::STATUS_OK);
    }

    /**
     * Elimina un puesto por ID.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function EliminarPuesto(int $id_puesto): DataResponse {
        $this->requireHumanResourcesAccess();
        try {
            $this->puestosMapper->EliminarPuesto((string) $id_puesto);
            return new DataResponse(Http::STATUS_OK);
        } catch (\Exception $e) {
            return new DataResponse($e->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Guarda cambios en los puestos.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GuardarCambioPuestos(int $id_puestos, string $nombre, ?int $nivel = null): DataResponse {
        $this->requireHumanResourcesAccess();
        $this->puestosMapper->updatePuestos((string) $id_puestos, $nombre, $nivel);
        return new DataResponse(Http::STATUS_OK);
    }

    /**
     * Crea un nuevo puesto.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function crearPuesto(string $nombre, ?int $nivel = null): DataResponse {
        $this->requireHumanResourcesAccess();
        $timestamp = date('Y-m-d');
        $puesto = new puestos();
        $puesto->setnombre($nombre);
        $puesto->setnivel($nivel);
        $puesto->setcreated_at($timestamp);
        $puesto->setupdated_at($timestamp);
        $this->puestosMapper->insert($puesto);
        return new DataResponse(Http::STATUS_OK);
    }

    /**
     * Obtiene un archivo subido y maneja posibles errores.
     */
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
}