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
        MailHelper $mailHelper
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
    }
    /**
     * Obtiene la lista de ausencias.
     */
    #[UseSession]
    #[NoAdminRequired]
    private function registrarActividadAusencia(string $tipoAusencia, string $fechaInicio, string $fechaFin, ?int $idHistorialAusencia): DataResponse {
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
            $mail = $userM->getEMailAddress();
            
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
                $targetUserId = (string) $this->request->getParam('id_usuario');
                if ($targetUserId === '') {
                    return new DataResponse(['success' => false, 'message' => 'Debe seleccionar un empleado para registrar la ausencia.']);
                }

                $targetUser = $this->userManager->get($targetUserId);
                if ($targetUser === null) {
                    return new DataResponse(['success' => false, 'message' => 'No se encontró el usuario seleccionado para registrar la ausencia.']);
                }

                $user = $targetUser;
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
            $prima_vacacional = $this->request->getParam('prima_vacacional');
            $notas = $this->request->getParam('notas');

            // aqui se disminuyen los dias de la ausencia
            $tipo_ausencia = $this->tipoausenciaMapper->getTipoById($id_tipo_ausencia);
            $id_empleado = $this->empleadosMapper->GetMyEmployeeInfo($user->getUID());
            $empleado_ausencias = $this->ausenciasMapper->GetAusenciasByUser($id_empleado[0]['Id_empleados']);
            
            $fechaDeObj = DateTime::createFromFormat('d/m/Y', (string) $this->request->getParam('fecha_de'));
            $fechaHastaObj = DateTime::createFromFormat('d/m/Y', (string) $this->request->getParam('fecha_hasta'));

            if ($fechaDeObj === false || $fechaHastaObj === false) {
                return new DataResponse(['success' => false, 'message' => 'Formato de fecha inválido. Utiliza DD/MM/AAAA.']);
            }

            if ($fechaDeObj > $fechaHastaObj) {
                return new DataResponse(['success' => false, 'message' => 'La fecha inicial no puede ser posterior a la fecha final.']);
            }

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
            
            $fecha_de = $fechaDeObj->format('Y-m-d');
            $fecha_hasta = $fechaHastaObj->format('Y-m-d');

            // Registro en el historial de ausencias 
            $idHistorialAusencia = $this->historialausenciasMapper->EnviarAusencia((int) $id_tipo_ausencia, $empleado_ausencias[0]['id_ausencias'], $fecha_de, $fecha_hasta, (int) $prima_vacacional, $notas, $empleado_ausencias[0]['id_aniversario']);

            if (!$isPrivileged) {
                $this->registrarActividadAusencia($tipo_ausencia[0]['nombre'], $fecha_de, $fecha_hasta, $idHistorialAusencia);
            }

            return new DataResponse(['success' => true, 'message' => $tipo_ausencia[0]['solicitar_prima_vacacional']]);
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
        $user = $this->userSession->getUser();
        $id_empleado = $this->empleadosMapper->GetMyEmployeeInfo($user->getUID());
        $empleado_ausencias = $this->ausenciasMapper->GetAusenciasByUser($id_empleado[0]['Id_empleados']);

        $desde = $this->request->getParam('desde'); // formato ISO
        $hasta = $this->request->getParam('hasta');

        $response = $this->historialausenciasMapper->GetAusenciasEnRango(
            $desde,
            $hasta,
            $empleado_ausencias[0]['id_ausencias']
        );
        
        foreach ($response as &$a) {
                $a['nombre_empleado'] = $id_empleado[0]['Id_user']; // si existe
        }

        return new DataResponse($response, Http::STATUS_OK);
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

        // Validar privilegios
        $isPrivileged = $this->groupManager->isInGroup($uid, 'admin') ||
                        $this->groupManager->isInGroup($uid, 'recursos_humanos');

        // Obtener IDs del equipo del usuario
        $id_empleado = $this->empleadosMapper->GetMyEmployeeInfo($uid);
        $equipo_empleado = $this->empleadosMapper->GetEmpleadosEquipo($id_empleado[0]['Id_equipo']);
        $ids_equipo = array_map(fn($e) => (int) $e['Id_empleados'], $equipo_empleado);

        // 🛡️ Normalizar entrada
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
}
