<?php

declare(strict_types=1);
namespace OCA\Empleados\Controller;

use OCA\Empleados\AppInfo\Application;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\IGroupManager;
use OCA\Empleados\Db\empleadosMapper;
use OCA\Empleados\Db\configuracionesMapper;
use OCA\Empleados\Db\empleadosorganigramaMapper;
use OCA\Empleados\Db\empleadosorganigramaposMapper;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;

use OCA\Empleados\Service\PermisosService;

/**
 * Controlador para el organigrama en red de empleados.
 */
class OrganigramaController extends BaseController {

    protected $empleadosMapper;
    protected $organigramaMapper;
    protected $organigramaposMapper;
    protected PermisosService $permisosService;

    public function __construct(
        IRequest $request,
        IUserSession $userSession,
        IGroupManager $groupManager,
        empleadosMapper $empleadosMapper,
        configuracionesMapper $configuracionesMapper,
        empleadosorganigramaMapper $organigramaMapper,
        empleadosorganigramaposMapper $organigramaposMapper,
        PermisosService $permisosService
    ) {
        parent::__construct(Application::APP_ID, $request, $userSession, $groupManager, $empleadosMapper, $configuracionesMapper);

        $this->empleadosMapper = $empleadosMapper;
        $this->organigramaMapper = $organigramaMapper;
        $this->organigramaposMapper = $organigramaposMapper;
        $this->permisosService = $permisosService;
    }

    /**
     * Devuelve los empleados (nodos) y las relaciones (aristas) del organigrama.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GetOrganigrama(): DataResponse {
        $this->requireHumanResourcesAccess();

        return new DataResponse([
            'empleados' => $this->empleadosMapper->GetUserLists(),
            'relaciones' => $this->organigramaMapper->GetOrganigrama(),
            'posiciones' => $this->organigramaposMapper->GetAll(),
        ], Http::STATUS_OK);
    }
    /**
     * Crea una relación jefe -> dependiente.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function CrearRelacionOrganigrama(int $id_empleado, int $id_dependiente): DataResponse {
        $this->requireHumanResourcesAccess();

        if ($id_empleado === $id_dependiente) {
            return new DataResponse('Un empleado no puede depender de sí mismo', Http::STATUS_BAD_REQUEST);
        }

        if ($this->organigramaMapper->ExisteRelacion($id_empleado, $id_dependiente)) {
            return new DataResponse('La relación ya existe', Http::STATUS_OK);
        }

        try {
            $this->organigramaMapper->CrearRelacion($id_empleado, $id_dependiente);
            return new DataResponse(Http::STATUS_OK);
        } catch (\Exception $e) {
            return new DataResponse($e->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Elimina una relación jefe -> dependiente.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function EliminarRelacionOrganigrama(int $id_empleado, int $id_dependiente): DataResponse {
        $this->requireHumanResourcesAccess();

        try {
            $this->organigramaMapper->EliminarRelacion($id_empleado, $id_dependiente);
            return new DataResponse(Http::STATUS_OK);
        } catch (\Exception $e) {
            return new DataResponse($e->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    private function requireHumanResourcesAccess(): void {
        $this->permisosService->requireCanSeeAny([
            'empleados.hr',
            'empleados.admin',
        ]);
    }

    /**
     * Guarda la posición de un solo nodo (al terminar de arrastrarlo).
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GuardarPosicionOrganigrama(int $id_empleado, float $x, float $y): DataResponse {
        $this->requireHumanResourcesAccess();
        try {
            $this->organigramaposMapper->GuardarPosicion($id_empleado, $x, $y);
            return new DataResponse(Http::STATUS_OK);
        } catch (\Exception $e) {
            return new DataResponse($e->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Guarda varias posiciones de golpe (tras la estabilización inicial de física).
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GuardarPosicionesOrganigrama(array $posiciones): DataResponse {
        $this->requireHumanResourcesAccess();
        try {
            $this->organigramaposMapper->GuardarPosicionesMasivas($posiciones);
            return new DataResponse(Http::STATUS_OK);
        } catch (\Exception $e) {
            return new DataResponse($e->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }
}