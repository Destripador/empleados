<?php
declare(strict_types=1);

namespace OCA\Empleados\Controller;

use OCA\Empleados\AppInfo\Application;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\IGroupManager;

use OCA\Empleados\Db\movimientosMapper;
use OCA\Empleados\Db\empleadosMapper;
use OCA\Empleados\Db\configuracionesMapper;

/**
 * Controlador para la bitácora general de movimientos (ya no es exclusivo
 * de ausencias/vacaciones, cualquier módulo puede escribir aquí).
 */
class MovimientosController extends BaseController {

    protected $userSession;
    private movimientosMapper $movimientosMapper;

    /**
     * Módulos disponibles para el filtro.
     */
    private const MODULOS_DISPONIBLES = [
        ['id' => 'vacaciones', 'nombre' => 'Vacaciones'],
        ['id' => 'actividades', 'nombre' => 'Actividades'],
        ['id' => 'equipos', 'nombre' => 'Equipos'],
        ['id' => 'puestos', 'nombre' => 'Puestos'],
        ['id' => 'areas', 'nombre' => 'Áreas'],
        ['id' => 'clientes', 'nombre' => 'Clientes'],
        ['id' => 'honorarios', 'nombre' => 'Honorarios'],
        ['id' => 'honorarios_parcialidades', 'nombre' => 'Parcialidades de honorarios'],
    ];

    public function __construct(
        IRequest $request,
        IUserSession $userSession,
        IGroupManager $groupManager,
        empleadosMapper $empleadosMapper,
        configuracionesMapper $configuracionesMapper,
        movimientosMapper $movimientosMapper
    ) {
        parent::__construct(Application::APP_ID, $request, $userSession, $groupManager, $empleadosMapper, $configuracionesMapper);
        $this->userSession = $userSession;
        $this->movimientosMapper = $movimientosMapper;
    }

    /**
     * Devuelve la bitácora de movimientos (todos para admin/RH, solo los
     * propios/relacionados para el resto). Filtros opcionales por query params:
     * modulo, tipo, id_empleado.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GetMovimientos(): DataResponse {
        $this->checkAccess(['admin', 'empleados']);

        $modulo = $this->request->getParam('modulo') ?: null;
        $tipo = $this->request->getParam('tipo') ?: null;
        $desde = $this->request->getParam('desde') ?: null;
        $hasta = $this->request->getParam('hasta') ?: null;
        $idEmpleadoParam = $this->request->getParam('id_empleado');
        $idEmpleadoFiltro = ($idEmpleadoParam !== null && $idEmpleadoParam !== '') ? (int) $idEmpleadoParam : null;

        $user = $this->userSession->getUser();
        $uid = $user->getUID();
        $isPrivileged = $this->groupManager->isInGroup($uid, 'admin')
                    || $this->groupManager->isInGroup($uid, 'recursos_humanos');

        if ($isPrivileged) {
            $movimientos = $this->movimientosMapper->GetMovimientos(300, $modulo, $tipo, $idEmpleadoFiltro, $desde, $hasta);
        } else {
            $id_empleado = $this->empleadosMapper->GetMyEmployeeInfo($uid);
            $movimientos = empty($id_empleado)
                ? []
                : $this->movimientosMapper->GetMovimientosDeEmpleado((int) $id_empleado[0]['Id_empleados'], 300, $modulo);
        }

        return new DataResponse($movimientos, Http::STATUS_OK);
    }

    /**
     * Opciones para armar los selects del panel de filtros:
     * módulos disponibles + empleados que han tenido algún movimiento.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GetOpcionesFiltro(): DataResponse {
        $this->checkAccess(['admin', 'empleados']);

        return new DataResponse([
            'modulos' => self::MODULOS_DISPONIBLES,
            'empleados' => $this->movimientosMapper->GetEmpleadosConMovimientos(),
        ], Http::STATUS_OK);
    }
}