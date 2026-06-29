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
use OCA\Empleados\UploadException;
use OCP\AppFramework\Http\DataResponse;

use OCP\IUserSession;
use OCP\IUserManager;
use OCP\IGroupManager;

use OCA\Empleados\Db\configuracionesMapper;
use OCP\Files\IRootFolder;

use DateTime;

use OCA\Empleados\Db\reportetiempoMapper;
use OCA\Empleados\Db\reportetiempo;
use OCA\Empleados\Db\equiposMapper;
use OCA\Empleados\Db\empleadosMapper;
use OCA\Empleados\Db\ausenciasMapper;
use OCA\Empleados\Db\tipoausenciaMapper;
use OCA\Empleados\Db\historialausenciasMapper;
use OCA\Empleados\Db\ausencias;

use OCP\AppFramework\Http;
use OCP\IURLGenerator;
use OCP\Activity\IManager;

use OCA\Empleados\Helper\MailHelper;

require_once 'SimpleXLSXGen.php';
require_once 'SimpleXLSX.php';

/**
 * Controlador para la gestión de áreas en Nextcloud.
 */
class AusenciasController extends BaseController {

    protected $userSession;
    protected $configuracionesMapper;
    protected $l10n;
    protected $equiposMapper;
    protected $empleadosMapper;
    protected $ausenciasMapper;
    protected $tipoausenciaMapper;
    protected $historialausenciasMapper;
    protected $reportetiempoMapper;

    protected $userManager;

    protected IRootFolder $rootFolder;

    private IManager $activityManager;
	private IURLGenerator $urlGenerator;
    private MailHelper $mailHelper;

    public function __construct(
        IRequest $request,
        IUserSession $userSession,
        IGroupManager $groupManager,
        configuracionesMapper $configuracionesMapper,
        IL10N $l10n,
        ausenciasMapper $ausenciasMapper,
        equiposMapper $equiposMapper,
        historialausenciasMapper $historialausenciasMapper,
        tipoausenciaMapper $tipoausenciaMapper,
        empleadosMapper $empleadosMapper,
        IRootFolder $rootFolder,
        IUserManager $userManager,
        IManager $activityManager,
		IURLGenerator $urlGenerator,
        MailHelper $mailHelper,
        reportetiempoMapper $reportetiempoMapper,
    ) {
        parent::__construct(Application::APP_ID, $request, $userSession, $groupManager, $empleadosMapper, $configuracionesMapper);
        
        $this->l10n = $l10n;
        $this->empleadosMapper = $empleadosMapper;
        $this->groupManager = $groupManager;
        $this->configuracionesMapper = $configuracionesMapper;
        $this->userSession = $userSession;
        $this->ausenciasMapper = $ausenciasMapper;
        $this->equiposMapper = $equiposMapper;
        $this->tipoausenciaMapper = $tipoausenciaMapper;
        $this->historialausenciasMapper = $historialausenciasMapper;
        $this->rootFolder = $rootFolder;
        $this->userManager = $userManager;
        $this->activityManager = $activityManager;
		$this->urlGenerator = $urlGenerator;
        $this->mailHelper = $mailHelper;
        $this->reportetiempoMapper = $reportetiempoMapper; 
    }
    /**
     * Obtiene la lista de ausencias.
     */
    #[UseSession]
    #[NoAdminRequired]
    private function registrarActividadAusencia(string $tipoAusencia, string $fechaInicio, string $fechaFin, ?int $idHistorialAusencia): void {
        $user = $this->userSession->getUser()->getUID();
        $username = $this->userSession->getUser()->getDisplayName();
        $employe_info = $this->empleadosMapper->GetMyEmployeeInfo($user);

        $event = $this->activityManager->generateEvent();
        $event->setApp('empleados');
        $event->setType('empleados');
        $event->setObject('empleados', (int) $idHistorialAusencia, 'Solicitud de ausencia');
        $event->setAffectedUser($user);

        // ✅ Usa el subject ID y pasa los parámetros de forma estándar
        $event->setSubject(
            'ausencia_registrada',
            [
                'nombre' => (string) $user,
                'tipo_ausencia' => (string) $tipoAusencia
            ]
        );

        // ✅ Usa el message como resumen textual
        $event->setMessage('Desde "' . $fechaInicio . '" hasta "' . $fechaFin . '"');
        $this->activityManager->publish($event);
 
        foreach ([$employe_info[0]['Id_gerente'], $employe_info[0]['Id_socio'], $this->configuracionesMapper->GetGestor()[0]['Data']] as $usuario) {
            $userM = $this->userManager->get($usuario);

            if (!$userM) {
                continue;
            }

            $mail = $userM->getEMailAddress();

            if (!$mail) {
                continue;
            }
            
            $dependent = $this->empleadosMapper->GetMyEmployeeInfo($user);

            $event = $this->activityManager->generateEvent();
            $event->setApp('empleados');
            $event->setType('empleados');
            $event->setObject('empleados', (int) $idHistorialAusencia, 'Solicitud de ausencia');
            $event->setAffectedUser($usuario);

            // ✅ Usa el subject ID y pasa los parámetros de forma estándar
            $event->setSubject(
                'ausencia_registrada',
                [
                    'nombre' => (string) $user,
                    'tipo_ausencia' => (string) $tipoAusencia
                ]
            );

            // ✅ Usa el message como resumen textual
            $event->setMessage('Desde "' . $fechaInicio . '" hasta "' . $fechaFin . '"');
            $this->activityManager->publish($event);

            $this->mailHelper->enviarCorreo(
                $mail,
                'Nueva solicitud',
                [
                    'Hola ' . $userM->getDisplayName() . '',
                    'El usuario ' . $username . ' ha realizado una solicitud de "' . $tipoAusencia . '".',
                    'Fecha de inicio: ' . $fechaFin . '  - Fecha de finalización: ' . $fechaFin . '',
                    '',
                ]
            );
        }
    }
    
    
    #[UseSession]
    #[NoAdminRequired]
    public function GetNotificationsSubordinates(): DataResponse {
        $this->checkAccess(['admin', 'empleados']);
        $user = $this->userSession->getUser();
        $equipo_empleado = $this->empleadosMapper->GetSubordinates($user->getUID());

        $empleados_data = [];

        foreach ($equipo_empleado as $empleado) {
            $id_empleado = $this->empleadosMapper->GetMyEmployeeInfo($empleado['Id_user']);
            $ausencias = $this->ausenciasMapper->GetAusenciasByUser($id_empleado[0]['Id_empleados']);

            if (empty($ausencias)) {
                continue; // Si no hay ausencias, no seguimos con este empleado
            }

            if ($id_empleado[0]['Id_gerente'] == $user->getUID()) {
                $ausencias_historial = $this->historialausenciasMapper
                    ->GetAusenciasHistorialGerente($ausencias[0]['id_ausencias']);
            } elseif ($id_empleado[0]['Id_socio'] == $user->getUID()) {
                $ausencias_historial = $this->historialausenciasMapper
                    ->GetAusenciasHistorialSocio($ausencias[0]['id_ausencias']);
            } else {
                continue; // Si no es ni socio ni gerente, lo ignoramos
            }

            if (!empty($ausencias_historial)) {
                foreach ($ausencias_historial as $item) {
                    $empleados_data[] = array_merge($empleado, $item); // Fusiona empleado + historial
                }
            }
        }

        return new DataResponse($empleados_data, Http::STATUS_OK);
    }


    /**
     * Obtiene la lista de ausencias.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GetAusencias(): DataResponse {
        $this->checkAccess(['admin', 'empleados']);
        return new DataResponse($this->ausenciasMapper->Getausencias(), Http::STATUS_OK);
    }

    /**
     * Obtiene la lista de ausencias.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GetAusenciasByUser($id): DataResponse {
        $this->checkAccess(['admin', 'empleados']);
        return new DataResponse($this->ausenciasMapper->GetAusenciasByUser($id), Http::STATUS_OK);
    }

    /**
     * Exporta la lista de áreas a un archivo XLSX.
     */
    public function ExportListAusencias(): DataResponse {
        $this->checkAccess(['admin', 'empleados']);
        $ausencias = $this->ausenciasMapper->Getausencias();
        $books = [['id_universario', 'numero_ausencias', 'dias']];

        foreach ($ausencias as $area) {
            $books[] = [
                $area['numero_ausencias'],
                $area['dias'],
            ];
        }

        \Shuchkin\SimpleXLSXGen::fromArray($books)->downloadAs('ausencias.xlsx');
        return new DataResponse($books, Http::STATUS_OK);
    }

    /**
     * Importa la lista de áreas desde un archivo XLSX.
     */
    public function ImportListAusencias(): DataResponse {
        $this->checkAccess(['admin', 'empleados']);
        $file = $this->getUploadedFile('fileXLSX');
        if ($xlsx = \Shuchkin\SimpleXLSX::parse($file['tmp_name'])) {
            foreach ($xlsx->rows() as $row) {
                $area = new ausencias();
                    $area->setnumero_ausencias($row[0]);
                    $area->setdias($row[1]);
                    $this->ausenciasMapper->insert($area);
            }
            return new DataResponse(['success' => true], Http::STATUS_OK);
        }
        return new DataResponse(Http::STATUS_BAD_REQUEST);
    }
        
    /**
     * Elimina un área por ID.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function VaciarAusencias(): DataResponse {
        $this->checkAccess(['admin', 'empleados']);
        try {
            $this->ausenciasMapper->VaciarAusencias();
            return new DataResponse(['success' => true], Http::STATUS_OK);
        } catch (\Exception $e) {
            return new DataResponse(['success' => false, 'message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        }
    }
        
    /**
     * Obtiene un archivo subido y maneja posibles errores.
     */
    #[UseSession]
    #[NoAdminRequired]
    private function getUploadedFile(string $key): array {
        $file = $this->request->getUploadedFile($key);
        if (empty($file) || ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new UploadException($this->l10n->t('Error en la subida del archivo.'));
        }
        return $file;
    }

    /**
     * Obtiene la lista de ausencias.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GetAniversarioByDate(string $ingreso): DataResponse {
        $this->checkAccess(['admin', 'empleados']);
        $fechaInicio = new DateTime($ingreso);
        $hoy = new DateTime();
    
        $diferencia = $hoy->diff($fechaInicio);
    
        return new DataResponse($this->ausenciasMapper->GetAniversarioByDate($diferencia->y), Http::STATUS_OK);

    }

    /**
    * Generar solicitud de ausencia con sus respectivos archivos adjuntos.
    */
    #[UseSession]
    #[NoAdminRequired]
    public function EnviarAusencia(): DataResponse {
        try {
            $files = $_FILES['archivos'] ?? ['name' => [], 'tmp_name' => []];
            $fileCount = count((array)($files['name'] ?? []));
        
            $user = $this->userSession->getUser();

            $uid = $user->getUID();
            // Verificamos privilegios
            $isPrivileged = $this->groupManager->isInGroup($uid, 'admin') ||
                            $this->groupManager->isInGroup($uid, 'recursos_humanos');

            if ($isPrivileged) {
                $user =  $this->userManager->get($this->request->getParam('id_usuario'));
            }

            $gestor = $this->configuracionesMapper->GetGestor()[0]['Data'] ?? null;
        
            if (!$gestor) {
                throw new \Exception('No se encontró la carpeta del gestor de información.');
            }
        
            $userFolder = $this->rootFolder->getUserFolder($gestor);
            $folderPath = "EMPLEADOS/" . $user->getUID() . " - " . strtoupper($user->getDisplayName()) . "/JUSTIFICANTES";
        
            if (!$userFolder->nodeExists($folderPath)) {
                $userFolder->newFolder($folderPath);
            }
        
            $carpetaDestino = $userFolder->get($folderPath);
            $fechaActual = (new \DateTime())->format('Y-m-d');
        
            for ($i = 0; $i < $fileCount; $i++) {
                $tmpName = $files['tmp_name'][$i];
                $originalName = $files['name'][$i];
        
                if (is_uploaded_file($tmpName)) {
                    $content = file_get_contents($tmpName);
        
                    // Separar extensión
                    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
                    $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        
                    // Construir nuevo nombre
                    $newName = $fechaActual . '-' . $baseName . '.' . $extension;
        
                    if ($carpetaDestino->nodeExists($newName)) {
                        $carpetaDestino->get($newName)->putContent($content);
                    } else {
                        $carpetaDestino->newFile($newName)->putContent($content);
                    }
                }
            }
            
            $id_tipo_ausencia = $this->request->getParam('id_tipo_ausencia');
            $dias_solicitados = $this->request->getParam('dias_solicitados');
            $fecha_de = $this->request->getParam('fecha_de');
            $fecha_hasta = $this->request->getParam('fecha_hasta');
            $prima_vacacional = (int) $this->request->getParam('prima_vacacional');
            $notas = $this->request->getParam('notas');

            // aqui se disminuyen los dias de la ausencia
            $tipo_ausencia = $this->tipoausenciaMapper->getTipoById($id_tipo_ausencia);
            $id_empleado = $this->empleadosMapper->GetMyEmployeeInfo($user->getUID());
            $empleado_ausencias = $this->ausenciasMapper->GetAusenciasByUser($id_empleado[0]['Id_empleados']);
            
            $fechaDeObj = DateTime::createFromFormat('d/m/Y', $this->request->getParam('fecha_de'));
            $fechaHastaObj = DateTime::createFromFormat('d/m/Y', $this->request->getParam('fecha_hasta'));
            $hoy = new \DateTime();

            // Normalizamos horas
            $fechaDeObj->setTime(0, 0);
            $fechaHastaObj->setTime(0, 0);
            $hoy->setTime(0, 0);

            // Solo se descuentan días si al menos una de las fechas es hoy o futura
            $ausenciaAbarcaPresenteOFuturo = ($fechaDeObj >= $hoy || $fechaHastaObj >= $hoy);

            // Si es privilegiado, solo se descuentan días si la ausencia abarca hoy o el futuro
            // Si no es privilegiado, también solo si la ausencia abarca hoy o el futuro
            $puedeDescontarDias = $ausenciaAbarcaPresenteOFuturo;

            // Aplicamos solo si se debe y el tipo de ausencia lo requiere
            if ($puedeDescontarDias && !empty($tipo_ausencia) && $tipo_ausencia[0]['solicitar_prima_vacacional'] == 1) {
                $dias_disponibles = $empleado_ausencias[0]['dias_disponibles'] - $dias_solicitados;
                $this->ausenciasMapper->updateAusenciasEmpleado($empleado_ausencias[0]['id_ausencias'], $dias_disponibles);
            }
            
            $fecha_de = DateTime::createFromFormat('d/m/Y', $this->request->getParam('fecha_de'))->format('Y-m-d');
            $fecha_hasta = DateTime::createFromFormat('d/m/Y', $this->request->getParam('fecha_hasta'))->format('Y-m-d');

            // Registro en el historial de ausencias 
            $idHistorialAusencia = $this->historialausenciasMapper->EnviarAusencia(
                (int) $id_tipo_ausencia,
                $empleado_ausencias[0]['id_ausencias'],
                $fecha_de,
                $fecha_hasta,
                (int) $prima_vacacional,
                $notas,
                $empleado_ausencias[0]['id_aniversario'],
                (int) $dias_solicitados
            );

            if ($puedeDescontarDias && !empty($tipo_ausencia) && (int) $tipo_ausencia[0]['cargable'] === 1) {
                $cursor = new \DateTime($fecha_de);
                $fin    = new \DateTime($fecha_hasta);

                while ($cursor <= $fin) {
                    $diaSemana = (int) $cursor->format('N'); // 1=lunes, 7=domingo
                    if ($diaSemana <= 5) {
                        $reporte = new \OCA\Empleados\Db\reportetiempo();
                        $reporte->setidEmpleado((int) $id_empleado[0]['Id_empleados']);
                        $reporte->setidCliente(99999);
                        $reporte->setidActividad(99999);
                        $reporte->settiempoRegistrado(480); // 8h en minutos
                        $reporte->setfechaRegistro($cursor->format('Y-m-d'));
                        $reporte->setdescripcion('');
                        $this->reportetiempoMapper->insert($reporte);
                    }
                    $cursor->modify('+1 day');
                }
            }

            if ($prima_vacacional === 1) {
                $this->ausenciasMapper->updatePrimaVacacional(
                    $empleado_ausencias[0]['id_ausencias'],
                    1
                );
            }

            if (!$isPrivileged) {
                $this->registrarActividadAusencia($tipo_ausencia[0]['nombre'], $fecha_de, $fecha_hasta, $idHistorialAusencia);
            }

            return new DataResponse(['success' => true, 'message' => 'Ausencia registrada correctamente']);
        } catch (\Exception $e) {
            // Manejo de errores
            return new DataResponse(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
    * Obtener ausencias del historial de ausencias por mes y año.
    */
    #[UseSession]
    #[NoAdminRequired]
    public function GetAusenciasHistorial(): DataResponse {
        $this->checkAccess(['admin', 'empleados']);
        $desde = $this->request->getParam('desde');
        $hasta = $this->request->getParam('hasta');

        $user = $this->userSession->getUser()->getUID();
        $id_empleado = $this->empleadosMapper->GetMyEmployeeInfo($user);
        $empleado_ausencias = $this->ausenciasMapper->GetAusenciasByUser($id_empleado[0]['Id_empleados']);

        $historial = $this->historialausenciasMapper->GetAusenciasEnRango(
            $desde,
            $hasta,
            $empleado_ausencias[0]['id_ausencias']
        );

        return new DataResponse($historial, Http::STATUS_OK);
    }

    /**
    * Obtener ausencias del historial de ausencias por mes y año.
    */
    #[UseSession]
    #[NoAdminRequired]
    public function GetAusenciasHistorialAll(): DataResponse {
        $desde = $this->request->getParam('desde');
        $hasta = $this->request->getParam('hasta');

        $user = $this->userSession->getUser();
        $id_empleado = $this->empleadosMapper->GetMyEmployeeInfo($user->getUID());
        $equipo_empleado = $this->empleadosMapper->GetEmpleadosEquipo($id_empleado[0]['Id_equipo']);

        $response = [];

        foreach ($equipo_empleado as $empleado) {
            $empleado_inf = $this->ausenciasMapper->GetAusenciasByUser($empleado['Id_empleados']);
            $ausencias = $this->historialausenciasMapper->GetAusenciasEnRango(
                $desde,
                $hasta,
                $empleado_inf[0]['id_ausencias']
            );

            // opcional: agrega nombre del empleado a cada evento
            foreach ($ausencias as &$a) {
                $a['nombre_empleado'] = $empleado['Id_user']; // si existe
            }

            $response = array_merge($response, $ausencias);
        }

        return new DataResponse(['success' => true, 'message' => $response]);
    }

    /**
    * Obtener ausencias de mis empleados.
    */
    #[UseSession]
    #[NoAdminRequired]
    public function GetAusenciasMyWorkers(): DataResponse {
        $desde = $this->request->getParam('desde');
        $hasta = $this->request->getParam('hasta');

        $user = $this->userSession->getUser();
        $equipo_empleado = $this->empleadosMapper->GetSubordinates($user->getUID());

        $response = [];

        foreach ($equipo_empleado as $empleado) {
            $empleado_inf = $this->ausenciasMapper->GetAusenciasByUser($empleado['Id_empleados']);
            $ausencias = $this->historialausenciasMapper->GetAusenciasEnRango(
                $desde,
                $hasta,
                $empleado_inf[0]['id_ausencias']
            );

            // opcional: agrega nombre del empleado a cada evento
            foreach ($ausencias as &$a) {
                $a['nombre_empleado'] = $empleado['Id_user']; // si existe
            }

            $response = array_merge($response, $ausencias);
        }

        return new DataResponse(['success' => true, 'message' => $response]);
    }

    /**
    * Obtener ausencias del historial de ausencias por mes y año.
    */
    #[UseSession]
    #[NoAdminRequired]
    public function GetAusenciasEmployeeHistorial(): DataResponse {
        $usuariosInput = $this->request->getParam('id_employee');
        $desde = $this->request->getParam('desde');
        $hasta = $this->request->getParam('hasta');

        $user = $this->userSession->getUser();
        $uid = $user->getUID();

        $isPrivileged = $this->groupManager->isInGroup($uid, 'admin') ||
                        $this->groupManager->isInGroup($uid, 'recursos_humanos');

        // Solo obtener equipo si no es privilegiado
        $ids_equipo = [];
        if (!$isPrivileged) {
            $id_empleado = $this->empleadosMapper->GetMyEmployeeInfo($uid);
            if (!empty($id_empleado) && !empty($id_empleado[0]['Id_equipo'])) {
                $equipo_empleado = $this->empleadosMapper->GetEmpleadosEquipo($id_empleado[0]['Id_equipo']);
                $ids_equipo = array_map(fn($e) => (int) $e['Id_empleados'], $equipo_empleado);
            }
        }

        if (is_string($usuariosInput)) {
            $usuariosInput = json_decode($usuariosInput, true);
        }
        $usuarios = is_array($usuariosInput) ? (array_keys($usuariosInput) === range(0, count($usuariosInput) - 1) ? $usuariosInput : [$usuariosInput]) : [];

        $response = [];

        foreach ($usuarios as $item) {
            if (!is_array($item) || !isset($item['Id_empleados'])) {
                continue;
            }

            $id_emp = (int) $item['Id_empleados'];

            if ($isPrivileged || in_array($id_emp, $ids_equipo)) {
                $empleado_ausencias = $this->ausenciasMapper->GetAusenciasByUser($id_emp);
                if (empty($empleado_ausencias)) {
                    continue;
                }

                $ausencias = $this->historialausenciasMapper->GetAusenciasEnRango(
                    $desde,
                    $hasta,
                    $empleado_ausencias[0]['id_ausencias']
                );

                $nombre = $item['displayName'] ?? $item['Id_user'] ?? 'Empleado ' . $id_emp;

                foreach ($ausencias as &$a) {
                    $a['nombre_empleado'] = $nombre;
                }

                $response = array_merge($response, $ausencias);
            }
        }

        return new DataResponse(['success' => true, 'message' => $response]);
    }

    /**
     * Obtiene el detalle de una ausencia del historial por su ID.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GetDetalleAusencia(): DataResponse {
        $this->checkAccess(['admin', 'empleados']);

        $id = (int) $this->request->getParam('id');

        if ($id <= 0) {
            return new DataResponse(
                ['success' => false, 'message' => 'ID inválido'],
                Http::STATUS_BAD_REQUEST
            );
        }

        try {
            $detalle = $this->historialausenciasMapper->GetDetalleById($id);

            if (empty($detalle)) {
                return new DataResponse(
                    ['success' => false, 'message' => 'Ausencia no encontrada'],
                    Http::STATUS_NOT_FOUND
                );
            }

            return new DataResponse($detalle[0], Http::STATUS_OK);
        } catch (\Exception $e) {
            return new DataResponse(
                ['success' => false, 'message' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Cancela una ausencia del historial.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function CancelarAusencia(): DataResponse {
        $this->checkAccess(['admin', 'empleados']);

        $id    = (int) $this->request->getParam('id');
        $user  = $this->userSession->getUser();
        $uid   = $user->getUID();

        $isPrivileged = $this->groupManager->isInGroup($uid, 'admin')
                    || $this->groupManager->isInGroup($uid, 'recursos_humanos');

        if ($id <= 0) {
            return new DataResponse(
                ['success' => false, 'message' => 'ID inválido'],
                Http::STATUS_BAD_REQUEST
            );
        }

        try {
            $detalle = $this->historialausenciasMapper->GetDetalleById($id);

            if (empty($detalle)) {
                return new DataResponse(
                    ['success' => false, 'message' => 'Ausencia no encontrada'],
                    Http::STATUS_NOT_FOUND
                );
            }

            $ausencia = $detalle[0];

            if (!$isPrivileged) {
                $id_empleado       = $this->empleadosMapper->GetMyEmployeeInfo($uid);
                $empleado_ausencias = $this->ausenciasMapper->GetAusenciasByUser(
                    $id_empleado[0]['Id_empleados']
                );

                if ((int) $ausencia['id_ausencias'] !== (int) $empleado_ausencias[0]['id_ausencias']) {
                    return new DataResponse(
                        ['success' => false, 'message' => 'Sin permiso para cancelar esta ausencia'],
                        Http::STATUS_FORBIDDEN
                    );
                }
            }

            if ((int) $ausencia['a_gerente'] === 3 || (int) $ausencia['a_socio'] === 3) {
                return new DataResponse(
                    ['success' => false, 'message' => 'La ausencia ya está cancelada'],
                    Http::STATUS_BAD_REQUEST
                );
            }

            $this->historialausenciasMapper->CancelarAusencia($id);

            $tipo = $this->tipoausenciaMapper->getTipoById($ausencia['id_tipo_ausencia']);

            if (!empty($tipo) && (int) $tipo[0]['solicitar_prima_vacacional'] === 1) {
                $fechaDe = new \DateTime($ausencia['fecha_de']);
                $hoy     = new \DateTime();
                $hoy->setTime(0, 0);
                $fechaDe->setTime(0, 0);

                if ($fechaDe >= $hoy) {
                    $empleado_ausencias_raw = $this->ausenciasMapper->GetAusenciasByUser(
                        $ausencia['id_ausencias']
                    );

                    $reg = $this->ausenciasMapper->GetAusenciasById((int) $ausencia['id_ausencias']);

                    if (!empty($reg)) {
                        $diasDevolver    = (float) $ausencia['dias_solicitados'];
                        $diasActuales    = (float) $reg[0]['dias_disponibles'];
                        $nuevosDias      = $diasActuales + $diasDevolver;

                        $this->ausenciasMapper->updateAusenciasEmpleado(
                            (int) $ausencia['id_ausencias'],
                            $nuevosDias
                        );
                    }

                    if ((int) $ausencia['prima_vacacional'] === 1) {
                        $this->ausenciasMapper->updatePrimaVacacional(
                            (int) $ausencia['id_ausencias'],
                            0
                        );
                    }
                }
            }

            if (!empty($tipo) && (int) $tipo[0]['cargable'] === 1) {
                $reg = $this->ausenciasMapper->GetAusenciasById((int) $ausencia['id_ausencias']);
                if (!empty($reg)) {
                    $this->reportetiempoMapper->deleteByFechaRangoAusencia(
                        (int) $reg[0]['id_empleado'],
                        $ausencia['fecha_de'],
                        $ausencia['fecha_hasta']
                    );
                }
            }

            return new DataResponse(['success' => true], Http::STATUS_OK);
        } catch (\Exception $e) {
            return new DataResponse(
                ['success' => false, 'message' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Editar una ausencia existente del historial.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function EditarAusencia(): DataResponse {
        try {
            $id = (int) $this->request->getParam('id');
            $id_tipo = (int) $this->request->getParam('id_tipo_ausencia');
            $fecha_de_raw  = $this->request->getParam('fecha_de');   // yyyy-mm-dd
            $fecha_hasta_raw = $this->request->getParam('fecha_hasta'); // yyyy-mm-dd
            $dias = (int) $this->request->getParam('dias_solicitados');
            $prima = (int) $this->request->getParam('prima_vacacional');
            $notas = $this->request->getParam('notas') ?? '';

            if (!$id || !$id_tipo || !$fecha_de_raw || !$fecha_hasta_raw) {
                return new DataResponse(['success' => false, 'message' => 'Faltan parámetros requeridos'], Http::STATUS_BAD_REQUEST);
            }

            $user = $this->userSession->getUser();
            $uid  = $user->getUID();

            $isPrivileged = $this->groupManager->isInGroup($uid, 'admin') ||
                            $this->groupManager->isInGroup($uid, 'recursos_humanos');

            // Obtener el registro actual para validar días y devolver los que ya se descontaron
            $registro = $this->historialausenciasMapper->GetById($id);
            if (empty($registro)) {
                return new DataResponse(['success' => false, 'message' => 'Ausencia no encontrada'], Http::STATUS_BAD_REQUEST);
            }

            $id_empleado        = $this->empleadosMapper->GetMyEmployeeInfo($uid);
            $empleado_ausencias = $this->ausenciasMapper->GetAusenciasByUser($id_empleado[0]['Id_empleados']);
            $tipo_ausencia      = $this->tipoausenciaMapper->getTipoById($id_tipo);

            // Sólo ajustar días si el tipo descuenta vacaciones
            if (!empty($tipo_ausencia) && $tipo_ausencia[0]['solicitar_prima_vacacional'] == 1) {
                $dias_originales   = (int) ($registro[0]['dias_solicitados'] ?? 0);
                $dias_disponibles  = (float) $empleado_ausencias[0]['dias_disponibles'];
                // Devolver los días originales y descontar los nuevos
                $nuevos_disponibles = ($dias_disponibles + $dias_originales) - $dias;
                $this->ausenciasMapper->updateAusenciasEmpleado(
                    $empleado_ausencias[0]['id_ausencias'],
                    $nuevos_disponibles
                );
            }

            // Fecha en formato Y-m-d (el Vue ya manda yyyy-mm-dd)
            $fecha_de    = (new \DateTime($fecha_de_raw))->format('Y-m-d');
            $fecha_hasta = (new \DateTime($fecha_hasta_raw))->format('Y-m-d');

            $this->historialausenciasMapper->EditarAusencia(
                $id, $id_tipo, $fecha_de, $fecha_hasta, $prima, $notas, $dias
            );

            // Manejar reportes de tiempo si el tipo es cargable
            $tipo_nuevo = $this->tipoausenciaMapper->getTipoById($id_tipo);
            $reg = $this->ausenciasMapper->GetAusenciasById((int) $registro[0]['id_ausencias']);

            if (!empty($tipo_nuevo) && !empty($reg)) {
                $id_empleado = (int) $reg[0]['id_empleado'];

                // Obtener el tipo original de la ausencia antes de editar
                $tipo_original = $this->tipoausenciaMapper->getTipoById($registro[0]['id_tipo_ausencia']);
                $era_cargable  = !empty($tipo_original) && (int) $tipo_original[0]['cargable'] === 1;
                $es_cargable   = (int) $tipo_nuevo[0]['cargable'] === 1;

                // Siempre eliminar reportes anteriores si el tipo original era cargable
                if ($era_cargable) {
                    $this->reportetiempoMapper->deleteByFechaRangoAusencia(
                        $id_empleado,
                        $registro[0]['fecha_de'],
                        $registro[0]['fecha_hasta']
                    );
                }

                // Crear nuevos reportes si el tipo nuevo es cargable
                if ($es_cargable) {
                    $cursor = new \DateTime($fecha_de);
                    $fin    = new \DateTime($fecha_hasta);

                    while ($cursor <= $fin) {
                        if ((int) $cursor->format('N') <= 5) {
                            $reporte = new \OCA\Empleados\Db\reportetiempo();
                            $reporte->setidEmpleado($id_empleado);
                            $reporte->setidCliente(99999);
                            $reporte->setidActividad(99999);
                            $reporte->settiempoRegistrado(480);
                            $reporte->setfechaRegistro($cursor->format('Y-m-d'));
                            $reporte->setdescripcion('');
                            $this->reportetiempoMapper->insert($reporte);
                        }
                        $cursor->modify('+1 day');
                    }
                }
            }

            return new DataResponse(['success' => true, 'message' => 'Ausencia actualizada correctamente']);

        } catch (\Exception $e) {
            return new DataResponse(['success' => false, 'message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        }
    }

    /**
     * Verifica si el empleado ya usó la prima vacacional en el año actual.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function CheckPrimaVacacional(): DataResponse {
        $this->checkAccess(['admin', 'empleados']);

        $exclude_id = (int) $this->request->getParam('exclude_id'); // id_historial_ausencias a ignorar (para editar)

        $user = $this->userSession->getUser();
        $uid  = $user->getUID();

        $isPrivileged = $this->groupManager->isInGroup($uid, 'admin') ||
                        $this->groupManager->isInGroup($uid, 'recursos_humanos');

        if ($isPrivileged && $this->request->getParam('id_usuario')) {
            $target = $this->userManager->get($this->request->getParam('id_usuario'));
            $uid = $target->getUID();
        }

        $id_empleado        = $this->empleadosMapper->GetMyEmployeeInfo($uid);
        $empleado_ausencias = $this->ausenciasMapper->GetAusenciasByUser($id_empleado[0]['Id_empleados']);

        if (empty($empleado_ausencias)) {
            return new DataResponse(['used' => false], Http::STATUS_OK);
        }

        $used = $this->historialausenciasMapper->PrimaVacacionalUsadaEsteAnio(
            (int) $empleado_ausencias[0]['id_ausencias'],
            $exclude_id
        );

        return new DataResponse(['used' => $used], Http::STATUS_OK);
    }

    /**
     * Obtiene el historial completo de ausencias para el reporte (solo admin)
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GetHistorialReporte(): DataResponse {
        $this->checkAccess(['admin', 'empleados']);

        $user = $this->userSession->getUser();
        $uid  = $user->getUID();

        $isPrivileged = $this->groupManager->isInGroup($uid, 'admin') ||
                        $this->groupManager->isInGroup($uid, 'recursos_humanos');

        $desde = $this->request->getParam('desde');
        $hasta = $this->request->getParam('hasta');

        if (!$isPrivileged) {
            return new DataResponse(['success' => false, 'message' => []], Http::STATUS_FORBIDDEN);
        }

        $response = $this->historialausenciasMapper->GetHistorialReporteCompleto($desde, $hasta);

        return new DataResponse(['success' => true, 'message' => $response], Http::STATUS_OK);
    }
}
