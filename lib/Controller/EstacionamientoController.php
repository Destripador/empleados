<?php

declare(strict_types=1);
namespace OCA\Empleados\Controller;

use OCA\Empleados\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\ForbiddenException;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\AppFramework\Http;
use OCP\ISession;
use OCP\IUserSession;
use OCP\IUserManager;
use OCP\IGroupManager;
use OCP\IL10N;
use OCA\Empleados\Db\empleadosMapper;
use OCA\Empleados\Db\departamentosMapper;
use OCA\Empleados\Db\configuracionesMapper;
use OCA\Empleados\Db\espacioMapper;
use OCA\Empleados\Db\espacioEmpleadosMapper;
use OCA\Empleados\Db\espacioEmpleados;
use OCA\Empleados\Db\espacioObstruyeMapper;
use OCA\Empleados\Db\espacioObstruye;
use OCA\Empleados\Db\empleadosEspacioDisponibleMapper;
use OCA\Empleados\Db\empleadosEspacioDisponible;

use OCP\IAvatarManager;

use OCP\IDBConnection;

use OCP\Files\IRootFolder;

use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;

require_once 'SimpleXLSXGen.php';
require_once 'SimpleXLSX.php';
use DateTime;

/**
 * Controlador principal para la gestión de empleados en Nextcloud.
 */
class EstacionamientoController extends BaseController {

    protected $userSession;
    protected $userManager;
    protected $groupManager;
    protected $empleadosMapper;
    protected $configuracionesMapper;
    protected $session;
    protected $l10n;
    protected $espacioMapper;
    protected $espacioEmpleadosMapper;
    protected $espacioObstruyeMapper;
    protected $empleadosEspacioDisponibleMapper;

    protected IRootFolder $rootFolder;

    public function __construct(
        IRequest $request,
        ISession $session,
        IUserSession $userSession,
        IUserManager $userManager,
        empleadosMapper $empleadosMapper,
        configuracionesMapper $configuracionesMapper,
        IL10N $l10n,
        IGroupManager $groupManager,
        IRootFolder $rootFolder,
        espacioMapper $espacioMapper,
        espacioEmpleadosMapper $espacioEmpleadosMapper,
        espacioObstruyeMapper $espacioObstruyeMapper,
        empleadosEspacioDisponibleMapper $empleadosEspacioDisponibleMapper,
        
    ) {
		parent::__construct(Application::APP_ID, $request, $userSession, $groupManager, $empleadosMapper, $configuracionesMapper, $espacioMapper, $espacioEmpleadosMapper, $espacioObstruyeMapper, $empleadosEspacioDisponibleMapper);

        $this->session = $session;
        $this->userSession = $userSession;
        $this->userManager = $userManager;
        $this->groupManager = $groupManager;
        $this->empleadosMapper = $empleadosMapper;
        $this->configuracionesMapper = $configuracionesMapper;
        $this->l10n = $l10n;
        $this->espacioMapper = $espacioMapper;
        $this->espacioEmpleadosMapper = $espacioEmpleadosMapper;
        $this->espacioObstruyeMapper = $espacioObstruyeMapper;
        $this->empleadosEspacioDisponibleMapper = $empleadosEspacioDisponibleMapper;

        $this->rootFolder = $rootFolder;
    }

    /** Devuelve los IDs de los empleados asignados a un espacio */
    #[UseSession]
    #[NoAdminRequired]
    public function GetEmpleadosAsignados(int $id_espacio): DataResponse {
        return new DataResponse([
            'empleados' => $this->espacioEmpleadosMapper->findIdsByEspacio($id_espacio),
        ], Http::STATUS_OK);
    }

    /** Guarda la nueva selección */
    #[UseSession]
    #[NoAdminRequired]
    public function GuardarAsignacion(int $id_espacio, array $id_empleados): DataResponse {
        // Limpiar anteriores
        $this->espacioEmpleadosMapper->deleteByEspacio($id_espacio);

        foreach ($id_empleados as $id_empleado) {
            $entidad = new espacioEmpleados();
            $entidad->setidEspacio($id_espacio);
            $entidad->setidEmpleado((int)$id_empleado);
            $entidad->setcreatedAt(new DateTime());
            $entidad->setupdatedAt(new DateTime());
            $this->espacioEmpleadosMapper->insert($entidad);
        }

        return new DataResponse([
            'status' => 'success',
        ], Http::STATUS_OK);
    }

    /** Devuelve los IDs de los que obstruyen a un espacio */
    #[UseSession]
    #[NoAdminRequired]
    public function GetEspaciosObstruyen(int $id_espacio): DataResponse {
        return new DataResponse([
            'espacios' => $this->espacioObstruyeMapper->findIdsObstruyen($id_espacio),
        ], Http::STATUS_OK);
    }

    /** Guarda selección de id de espacios que obstryen al id espacio enviado */
    #[UseSession]
    #[NoAdminRequired]
    public function GuardarEspaciosObstruyen(int $id_espacio, array $id_espacios_obstruyen): DataResponse {
        // Limpiar anteriores
        $this->espacioObstruyeMapper->deleteByEspacio($id_espacio);

        foreach ($id_espacios_obstruyen as $id_obstruye) {
            $entidad = new espacioObstruye();
            $entidad->setid_espacio($id_espacio);
            $entidad->setid_obstruye((int)$id_obstruye);
            $this->espacioObstruyeMapper->insert($entidad);
        }

        return new DataResponse([
            'status' => 'success',
        ], Http::STATUS_OK);
    }

    /*Obtener empleados con espacio asignado*/
    #[UseSession]
    #[NoAdminRequired]
    public function GetEmpleadosConEspacio(): DataResponse {
        return new DataResponse([
            'empleados' => $this->espacioEmpleadosMapper->getEspacioEmpleado(),
        ], Http::STATUS_OK);
    }

    #[UseSession]
    #[NoAdminRequired]
    public function GetUser(): DataResponse {
        $currentUser = $this->userSession->getUser()->getUID();
        return new DataResponse([
            'uid' => $currentUser,
        ], Http::STATUS_OK);
    }

    #[UseSession]
    #[NoAdminRequired]
    public function GuardarDisponibilidadTemporal(
        int $id_espacio_empleado, 
        string $fecha_inicio, 
        string $fecha_fin, 
        bool $todo_dia, 
        ?string $hora_inicial, 
        ?string $hora_final
        ): DataResponse {
            try {
                $inicio = new DateTime($fecha_inicio);
                $fin = new DateTime($fecha_fin);

                // 1. Validar que la fecha final no sea menor a la inicial
                if ($fin < $inicio) {
                    throw new \Exception("La fecha final no puede ser anterior a la fecha inicial.");
                }

                // Normalizar las horas recibidas del frontend (asegurar formato HH:mm)
                $solicitudInicio = $hora_inicial ? (new DateTime($hora_inicial))->format('H:i') : null;
                $solicitudFinal = $hora_final ? (new DateTime($hora_final))->format('H:i') : null;

                // Iteramos por cada día en el rango seleccionado
                $intervalo = new \DateInterval('P1D');
                $periodo = new \DatePeriod($inicio, $intervalo, $fin->modify('+1 day')); // +1 day para incluir el día final

                foreach ($periodo as $dt) {
                    $fechaStr = $dt->format('Y-m-d');
                
                    // 2. Obtener registros existentes para este día
                    $existentes = $this->empleadosEspacioDisponibleMapper->findRecordsByDate($id_espacio_empleado, $fechaStr);

                    if ($todo_dia) {
                        // Si intenta marcar "Todo el día", no debe haber NADA registrado ese día
                        if (count($existentes) > 0) {
                            throw new \Exception("No se puede marcar 'Todo el día' para la fecha $fechaStr porque ya existen horarios registrados.");
                        }
                    } else {
                        // Si es por rango de horas, validar contra cada registro existente
                        $nuevaHoraInicio = new DateTime($fechaStr . ' ' . $hora_inicial);
                        $nuevaHoraFinal = new DateTime($fechaStr . ' ' . $hora_final);

                        foreach ($existentes as $reg) {
                            // Si ya hay uno que es "Todo el día", bloquea cualquier rango
                            if ($reg->getTodoDia()) {
                                throw new \Exception("La fecha $fechaStr ya está marcada como disponible para todo el día.");
                            }

                            // OBTENER HORAS DE LA DB Y NORMALIZAR
                            // MySQL TIME '14:00:00' -> PHP format 'H:i' -> '14:00'
                            $regInicio = $reg->getHoraInicial() instanceof \DateTime 
                                        ? $reg->getHoraInicial()->format('H:i') 
                                        : $reg->getHoraInicial(); 
                            
                            $regFinal = $reg->getHoraFinal() instanceof \DateTime 
                                        ? $reg->getHoraFinal()->format('H:i') 
                                        : $reg->getHoraFinal();

                            // LÓGICA DE TRASLAPE (Comparación de Strings)
                            // Un traslapa si: (NuevoInicio < ExistenteFin) AND (NuevoFin > ExistenteInicio)
                            if ($solicitudInicio < $regFinal && $solicitudFinal > $regInicio) {
                                throw new \Exception("Conflicto de horario el $fechaStr: El rango $solicitudInicio-$solicitudFinal se traslapa con el registro existente $regInicio-$regFinal.");
                            }
                        }
                    }
                    $entidad = new empleadosEspacioDisponible();
                    $entidad->setidEspacioEmpleado($id_espacio_empleado);
                    $entidad->setfecha($dt);
                    $entidad->settodoDia($todo_dia);

                    if (!$todo_dia) {
                        $entidad->setHoraInicial(new DateTime($fechaStr . ' ' . $hora_inicial));
                        $entidad->setHoraFinal(new DateTime($fechaStr . ' ' . $hora_final));
                    } else {
                        $entidad->setHoraInicial(null);
                        $entidad->setHoraFinal(null);
                    }
                    $entidad->setcreatedAt(new DateTime());
                    $entidad->setupdatedAt(new DateTime());

                    $this->empleadosEspacioDisponibleMapper->insert($entidad);
                }

                return new DataResponse(
                    ['status' => 'success'
                ], Http::STATUS_OK);
            }
            catch (\Exception $e) {
                return new DataResponse([
                    'status' => 'error',
                    'message' => 'No se pudo guardar la disponibilidad: ' . $e->getMessage()
                ], Http::STATUS_INTERNAL_SERVER_ERROR);
            }
    }

    #[UseSession]
    #[NoAdminRequired]
    public function GetEspaciosLiberadosHoy(): DataResponse {
        return new DataResponse([
            'espacios' => $this->empleadosEspacioDisponibleMapper->findCurrentLiberados(),
        ], Http::STATUS_OK);
    }

    #[UseSession]
    #[NoAdminRequired]
    public function GetHistorial(int $id_espacio_empleado): DataResponse {
        $historial = $this->empleadosEspacioDisponibleMapper->findByAsignacion($id_espacio_empleado);
        
        // Formateamos las fechas para que el frontend las lea más fácil
        $resultado = array_map(function($entidad) {
            $data = $entidad->read();
            // Aseguramos que la fecha sea string ISO
            // Formatear fechas para que Vue las entienda
            if ($data['fecha'] instanceof \DateTime) {
                $data['fecha'] = $data['fecha']->format('Y-m-d');
            }
            if ($data['hora_inicial'] instanceof \DateTime) {
                $data['hora_inicial'] = $data['hora_inicial']->format('H:i');
            }
            if ($data['hora_final'] instanceof \DateTime) {
                $data['hora_final'] = $data['hora_final']->format('H:i');
            }
            return $data;
        }, $historial);

        return new DataResponse([
            'historial' => $resultado
        ], Http::STATUS_OK);
    }

    #[UseSession]
    #[NoAdminRequired]
    public function EliminarDisponibilidad(int $id): DataResponse {
        try {
            $entidad = $this->empleadosEspacioDisponibleMapper->findById($id);
            $uidLogueado = $this->userSession->getUser()->getUID();
            $asignacion = $this->espacioEmpleadosMapper->findById($entidad->getIdEspacioEmpleado());
            $empleado = $this->empleadosMapper->findByIdAsArray($asignacion->getidEmpleado());

            if ($empleado['Id_user'] !== $uidLogueado) {
                return new DataResponse(['message' => 'No autorizado'], Http::STATUS_FORBIDDEN);
            }
            $this->empleadosEspacioDisponibleMapper->deleteById($id);

            return new DataResponse(['status' => 'success'], Http::STATUS_OK);
        } catch (\Exception $e) {
            return new DataResponse([
                'status' => 'error',
                'message' => 'Error al eliminar la disponibilidad: ' . $e->getMessage()
            ], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Obtiene disponibilidad futura para consulta pública
     * @NoAdminRequired
     */
    public function GetHistorialPublico(int $id_espacio): DataResponse {
        try {
            $data = $this->empleadosEspacioDisponibleMapper->findPublicHistory($id_espacio);

            // Formatear horas para el display
            $resultado = array_map(function($row) {
                if (!empty($row['hora_inicial'])) {
                    $row['hora_inicial'] = (new \DateTime($row['hora_inicial']))->format('H:i');
                }
                if (!empty($row['hora_final'])) {
                    $row['hora_final'] = (new \DateTime($row['hora_final']))->format('H:i');
                }
                return $row;
            }, $data);

            return new DataResponse([
                'historial' => $resultado
            ], Http::STATUS_OK);

        } catch (\Exception $e) {
            return new DataResponse([
                'status' => 'error',
                'message' => $e->getMessage()
            ], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }
}
