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
use OCA\Empleados\Db\historialvacacionesMapper;
use OCA\Empleados\Db\aniversarioMapper;
use OCA\Empleados\Db\ausencias;
use OCA\Empleados\Db\actividadesMapper;
use OCA\Empleados\Db\primavacacionalpagoMapper;
use OCA\Empleados\Service\AniversarioSyncService;

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
    protected $aniversarioMapper;
    protected $historialvacacionesMapper;
    protected $primavacacionalpagoMapper;

    protected $userManager;

    protected IRootFolder $rootFolder;

    private IManager $activityManager;
	private IURLGenerator $urlGenerator;
    private MailHelper $mailHelper;
    private actividadesMapper $actividadesMapper;
    private AniversarioSyncService $aniversarioSyncService;

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
        historialvacacionesMapper $historialvacacionesMapper,
        primavacacionalpagoMapper $primavacacionalpagoMapper,
        empleadosMapper $empleadosMapper,
        IRootFolder $rootFolder,
        IUserManager $userManager,
        IManager $activityManager,
		IURLGenerator $urlGenerator,
        MailHelper $mailHelper,
        reportetiempoMapper $reportetiempoMapper,
        aniversarioMapper $aniversarioMapper,
        actividadesMapper $actividadesMapper,
        AniversarioSyncService $aniversarioSyncService
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
        $this->historialvacacionesMapper = $historialvacacionesMapper;
        $this->rootFolder = $rootFolder;
        $this->userManager = $userManager;
        $this->activityManager = $activityManager;
		$this->urlGenerator = $urlGenerator;
        $this->mailHelper = $mailHelper;
        $this->reportetiempoMapper = $reportetiempoMapper; 
        $this->aniversarioMapper = $aniversarioMapper;
        $this->actividadesMapper = $actividadesMapper;
        $this->primavacacionalpagoMapper = $primavacacionalpagoMapper;
        $this->aniversarioSyncService = $aniversarioSyncService;
    }
    /**
     * Obtiene la lista de ausencias.
     */
    #[UseSession]
    #[NoAdminRequired]
    private function registrarActividadAusencia(
        string $tipoAusencia,
        string $fechaInicio,
        string $fechaFin,
        ?int $idHistorialAusencia,
        string $uidEmpleado,
        string $nombreEmpleado
    ): void {
        $employe_info = $this->empleadosMapper->GetMyEmployeeInfo($uidEmpleado);

        $event = $this->activityManager->generateEvent();
        $event->setApp('empleados');
        $event->setType('empleados');
        $event->setObject('empleados', (int) $idHistorialAusencia, 'Solicitud de ausencia');
        $event->setAffectedUser($uidEmpleado);

        $event->setSubject(
            'ausencia_registrada',
            [
                'nombre' => (string) $uidEmpleado,
                'tipo_ausencia' => (string) $tipoAusencia
            ]
        );

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

            $event = $this->activityManager->generateEvent();
            $event->setApp('empleados');
            $event->setType('empleados');
            $event->setObject('empleados', (int) $idHistorialAusencia, 'Solicitud de ausencia');
            $event->setAffectedUser($usuario);

            $event->setSubject(
                'ausencia_registrada',
                [
                    'nombre' => (string) $uidEmpleado,
                    'tipo_ausencia' => (string) $tipoAusencia
                ]
            );

            $event->setMessage('Desde "' . $fechaInicio . '" hasta "' . $fechaFin . '"');
            $this->activityManager->publish($event);

            // FIX: el correo mostraba $fechaFin dos veces (también como "fecha de inicio").
            $this->mailHelper->enviarCorreo(
                $mail,
                'Nueva solicitud',
                [
                    'Hola ' . $userM->getDisplayName() . '',
                    'El usuario ' . $nombreEmpleado . ' ha realizado una solicitud de "' . $tipoAusencia . '".',
                    'Fecha de inicio: ' . $fechaInicio . '  - Fecha de finalización: ' . $fechaFin . '',
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
        $uid = $user->getUID();
        $isPrivileged = $this->groupManager->isInGroup($uid, 'admin')
                    || $this->groupManager->isInGroup($uid, 'recursos_humanos');

        $empleados_data = [];
        $ids_vistos = [];

        // 1) Jerarquía: gerente / socio
        foreach ($this->empleadosMapper->GetSubordinates($uid) as $empleado) {
            $id_empleado = $this->empleadosMapper->GetMyEmployeeInfo($empleado['Id_user']);
            $ausencias = $this->ausenciasMapper->GetAusenciasByUser($id_empleado[0]['Id_empleados']);
            if (empty($ausencias)) continue;

            $historial = [];
            if ($id_empleado[0]['Id_gerente'] === $uid) {
                $historial = array_merge($historial, $this->historialausenciasMapper->GetAusenciasHistorialGerente($ausencias[0]['id_ausencias']));
            }
            if ($id_empleado[0]['Id_socio'] === $uid) {
                $historial = array_merge($historial, $this->historialausenciasMapper->GetAusenciasHistorialSocio($ausencias[0]['id_ausencias']));
            }

            foreach ($historial as $item) {
                if (isset($ids_vistos[$item['id_historial_ausencias']])) continue;
                $ids_vistos[$item['id_historial_ausencias']] = true;
                $empleados_data[] = array_merge($empleado, $item);
            }
        }

        // 2) Capital humano
        if ($isPrivileged) {
            foreach ($this->historialausenciasMapper->GetAusenciasHistorialCapitalHumano() as $item) {
                if (isset($ids_vistos[$item['id_historial_ausencias']])) continue;
                $empleado_info = $this->empleadosMapper->GetMyEmployeeInfo($item['nombre_empleado']);
                $empleados_data[] = array_merge($empleado_info[0] ?? [], $item);
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
     * Cuenta cuántos días hábiles del rango  caen ANTES O EN la fecha límite
     */
    private function contarDiasHabilesHastaFecha(\DateTime $inicio, \DateTime $fin, \DateTime $limite): int {
        $cursor = clone $inicio;
        $count = 0;
        while ($cursor <= $fin) {
            if ($cursor > $limite) {
                break;
            }
            if ((int) $cursor->format('N') <= 5) {
                $count++;
            }
            $cursor->modify('+1 day');
        }
        return $count;
    }

    /**
	 * Calcula el colchón acumulado de un aniversario
	 */
	private function calcularAcumuladoPeriodo(
        int $id_empleado,
        int $id_ausencias,
        int $numeroAniversario,
        DateTime $fechaIngreso,
        DateTime $periodoInicio,
        string $periodoInicioStr
    ): array {
        if ($numeroAniversario <= 0) {
            return [0.0, null];
        }

        $anterior = $this->historialvacacionesMapper->getByEmpleadoYAniversario($id_empleado, $numeroAniversario - 1);
        if (!$anterior) {
            return [0.0, null];
        }

        $inicioAnterior = (clone $fechaIngreso)->modify('+' . ($numeroAniversario - 1) . ' years')->format('Y-m-d');
        $finAnteriorConGracia = (clone $periodoInicio)->modify('+6 months')->format('Y-m-d');

        $totalSolicitado = 0.0;
        $historialAnterior = $this->historialausenciasMapper->GetAusenciasEnRango($inicioAnterior, $finAnteriorConGracia, $id_ausencias);
        foreach ($historialAnterior as $item) {
            if ((int) ($item['id_aniversario'] ?? -1) !== ($numeroAniversario - 1)) continue;
            if ((int) $item['a_gerente'] === 3 || (int) $item['a_socio'] === 3) continue;
            if ((int) $item['a_gerente'] === 2 || (int) $item['a_socio'] === 2) continue;
            if ((int) ($item['solicitar_prima_vacacional'] ?? 0) !== 1) continue;
            $totalSolicitado += (float) $item['dias_solicitados'];
        }

        $sobrante = ((float) $anterior['dias_derecho']) - $totalSolicitado;

        if ($sobrante <= 0) {
            return [0.0, null];
        }

        $fechaExpiracion = (clone $periodoInicio)->modify('+6 months');
        $hoy = new DateTime();

        // Ya venció: no se muestra aunque matemáticamente sobre algo.
        if ($hoy > $fechaExpiracion) {
            return [0.0, null];
        }

        return [$sobrante, $fechaExpiracion->format('Y-m-d')];
    }

	/**
	 * Calcula el periodo/aniversario actual del empleado a partir de su Ingreso
	 */
	private function getPeriodoActualEmpleado(int $id_empleado, int $id_ausencias): ?array {
        $empleado = $this->empleadosMapper->GetMyEmployeeInfoByIdEmpleado((string) $id_empleado);
        if (empty($empleado) || empty($empleado[0]['Ingreso'])) {
            return null;
        }

        $this->aniversarioSyncService->sincronizarPeriodos($id_empleado, $empleado[0]['Ingreso']);

        $fechaIngreso = new DateTime($empleado[0]['Ingreso']);
        $hoy = new DateTime();
        $numeroAniversario = $hoy->diff($fechaIngreso)->y;

        $periodoInicio = (clone $fechaIngreso)->modify('+' . $numeroAniversario . ' years');
        $periodoFin = (clone $fechaIngreso)->modify('+' . ($numeroAniversario + 1) . ' years');
        $periodoInicioStr = $periodoInicio->format('Y-m-d');
        $periodoFinStr = $periodoFin->format('Y-m-d');

        $existente = $this->historialvacacionesMapper->getByEmpleadoYAniversario($id_empleado, $numeroAniversario);

        // --- dias_derecho: se respeta si RH lo asignó manualmente ---
        if ($existente && (int) ($existente['asignado_manualmente'] ?? 0) === 1) {
            $diasDerecho = (float) $existente['dias_derecho'];
        } else {
            $tieneAsignacionManual = $this->historialvacacionesMapper->tieneAsignacionManual($id_empleado);
            $tieneAniversarioCero = $this->historialvacacionesMapper->tieneAniversarioCero($id_empleado);

            if ($tieneAsignacionManual || $tieneAniversarioCero || $numeroAniversario === 0) {
                $tablaAniversario = $this->aniversarioMapper->GetAniversarioByDate($numeroAniversario);
                $diasDerecho = !empty($tablaAniversario) ? (float) ($tablaAniversario[0]['dias'] ?? 0) : 0.0;
            } else {
                $diasDerecho = 0.0;
            }
        }

        if (!$existente) {
            $this->historialvacacionesMapper->guardar($id_empleado, $numeroAniversario, $periodoInicioStr, $periodoFinStr, $diasDerecho);
        }

        [$diasAcumuladosRestantes, $fechaExpiracionAcum] = $this->calcularAcumuladoPeriodo(
            $id_empleado, $id_ausencias, $numeroAniversario, $fechaIngreso, $periodoInicio, $periodoInicioStr
        );

        $this->historialvacacionesMapper->actualizarAcumulado(
            $id_empleado, $numeroAniversario, $diasAcumuladosRestantes, $fechaExpiracionAcum
        );

        $acumuladoVigente = $diasAcumuladosRestantes > 0 && $fechaExpiracionAcum !== null;

        // --- Días disfrutados del periodo actual ---
        $limiteConGraciaStr = (clone $periodoFin)->modify('+6 months')->format('Y-m-d');
        $diasDisfrutados = 0.0;
        $historial = $this->historialausenciasMapper->GetAusenciasEnRango($fechaIngreso->format('Y-m-d'), $limiteConGraciaStr, $id_ausencias);
        foreach ($historial as $item) {
            if ((int) ($item['id_aniversario'] ?? -1) !== $numeroAniversario) continue;
            if ((int) $item['a_gerente'] === 3 || (int) $item['a_socio'] === 3) continue;
            if ((int) $item['a_gerente'] === 2 || (int) $item['a_socio'] === 2) continue;
            if ((int) ($item['solicitar_prima_vacacional'] ?? 0) !== 1) continue;
            $diasDisfrutados += (float) $item['dias_solicitados'] - (float) ($item['dias_de_acumulado'] ?? 0);
        }

        return [
            'numero_aniversario' => $numeroAniversario,
            'periodo_inicio' => $periodoInicioStr,
            'periodo_fin' => $periodoFinStr,
            'dias_derecho' => $diasDerecho,
            'dias_disfrutados' => $diasDisfrutados,
            'dias_restantes' => $diasDerecho - $diasDisfrutados,
            'dias_acumulados_restantes' => $acumuladoVigente ? $diasAcumuladosRestantes : 0,
            'fecha_expiracion_acumulados' => $acumuladoVigente ? $fechaExpiracionAcum : null,
            'fecha_limite_periodo_actual' => (clone $periodoFin)->modify('+6 months')->format('Y-m-d'),
        ];
    }

    /**
     * Obtiene la lista de ausencias.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GetAusenciasByUser($id): DataResponse {
        $this->checkAccess(['admin', 'empleados']);

        $rows = $this->ausenciasMapper->GetAusenciasByUser($id);

        if (!empty($rows)) {
            $periodo = $this->getPeriodoActualEmpleado((int) $id, (int) $rows[0]['id_ausencias']);
            if ($periodo) {
                $rows[0]['id_aniversario'] = $periodo['numero_aniversario'];
                $rows[0]['dias_derecho'] = $periodo['dias_derecho'];
                $rows[0]['dias_disponibles'] = $periodo['dias_restantes'];
                $rows[0]['dias_acumulados'] = $periodo['dias_acumulados_restantes'];
                $rows[0]['fecha_expiracion_acumulados'] = $periodo['fecha_expiracion_acumulados'];
                $rows[0]['fecha_limite_periodo_actual'] = $periodo['fecha_limite_periodo_actual'];
                $rows[0]['prima_vacacional'] = $this->historialausenciasMapper->PrimaVacacionalUsadaEsteAnio(
                    (int) $rows[0]['id_ausencias'],
                    (int) date('Y')
                ) ? 1 : 0;
            }
        }

        return new DataResponse($rows, Http::STATUS_OK);
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
        
                    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
                    $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        
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

            if ($prima_vacacional === 1 && (float) $dias_solicitados < 2) {
                return new DataResponse(
                    ['success' => false, 'message' => 'La prima vacacional requiere al menos 2 días solicitados.'],
                    Http::STATUS_BAD_REQUEST
                );
            }

            // aqui se disminuyen los dias de la ausencia
            $tipo_ausencia = $this->tipoausenciaMapper->getTipoById($id_tipo_ausencia);
            $esAnticipada = !empty($tipo_ausencia) && (int) ($tipo_ausencia[0]['privado'] ?? 0) > 0;

            if ($esAnticipada && !$isPrivileged) {
                return new DataResponse([
                    'success' => false,
                    'message' => 'Solo un administrador o RH puede registrar vacaciones anticipadas.'
                ], Http::STATUS_FORBIDDEN);
            }
            $id_empleado = $this->empleadosMapper->GetMyEmployeeInfo($user->getUID());
            error_log('Empleado: ' . print_r($id_empleado, true));

            $empleado_ausencias = $this->ausenciasMapper->GetAusenciasByUser(
                (int)$id_empleado[0]['Id_empleados']
            );

            error_log('Ausencias: ' . print_r($empleado_ausencias, true));

            // Periodo/aniversario actual calculado desde el Ingreso
            $periodoActual = $this->getPeriodoActualEmpleado(
                (int) $id_empleado[0]['Id_empleados'],
                (int) $empleado_ausencias[0]['id_ausencias']
            );
            $numeroAniversarioActual = $periodoActual['numero_aniversario'] ?? $empleado_ausencias[0]['id_aniversario'];

            $fechaDeObj = DateTime::createFromFormat('d/m/Y', $this->request->getParam('fecha_de'));
            $fechaHastaObj = DateTime::createFromFormat('d/m/Y', $this->request->getParam('fecha_hasta'));
            $hoy = new \DateTime();

            // Normalizamos horas
            $fechaDeObj->setTime(0, 0);
            $fechaHastaObj->setTime(0, 0);
            $hoy->setTime(0, 0);

            // El empleado solo puede solicitar la prima vacacional 1 vez por año calendario
            // (ene-nov). Se valida contra el historial real, no solo contra el "flag" general
            // de la tabla ausencias, para que el conteo sea por año de la fecha solicitada.
            if ($prima_vacacional === 1) {
                $anioSolicitud = (int) $fechaDeObj->format('Y');
                $primaUsadaEsteAnio = $this->historialausenciasMapper->PrimaVacacionalUsadaEsteAnio(
                    (int) $empleado_ausencias[0]['id_ausencias'],
                    $anioSolicitud
                );

                if ($primaUsadaEsteAnio) {
                    return new DataResponse([
                        'success' => false,
                        'message' => 'Ya se solicitó la prima vacacional para el año ' . $anioSolicitud . '.'
                    ], Http::STATUS_BAD_REQUEST);
                }
            }

            // Tope de 1 año y medio (fin del periodo actual + 6 meses de gracia del acumulado)
            if ($periodoActual) {
                $fechaLimite = (new \DateTime($periodoActual['periodo_fin']))->modify('+6 months');
                $fechaLimite->setTime(0, 0);

                if ($fechaDeObj > $fechaLimite || $fechaHastaObj > $fechaLimite) {
                    return new DataResponse([
                        'success' => false,
                        'message' => 'No puedes solicitar ausencias más allá del ' . $fechaLimite->format('d/m/Y') . ', fecha límite para usar los días de tu periodo actual.'
                    ], Http::STATUS_BAD_REQUEST);
                }
            }

            $ausenciaAbarcaPresenteOFuturo = ($fechaDeObj >= $hoy || $fechaHastaObj >= $hoy);

            $puedeDescontarDias = $ausenciaAbarcaPresenteOFuturo;

            error_log('TIPO AUSENCIA: ' . print_r($tipo_ausencia, true));
            error_log('PUEDE DESCONTAR: ' . ($puedeDescontarDias ? 'SI' : 'NO'));

            $diasDeAcumulado = 0.0;

            if ($esAnticipada) {
                    $diasDeAcumulado = 0.0;
                } elseif ($puedeDescontarDias && !empty($tipo_ausencia) && $tipo_ausencia[0]['solicitar_prima_vacacional'] == 1) {

                // No permitir agendar más allá del límite del periodo actual (1 año + 6 meses)
                if (!empty($periodoActual['fecha_limite_periodo_actual'])) {
                    $fechaLimite = (new \DateTime($periodoActual['fecha_limite_periodo_actual']));
                    $fechaLimite->setTime(0, 0);

                    if ($fechaDeObj > $fechaLimite || $fechaHastaObj > $fechaLimite) {
                        return new DataResponse([
                            'success' => false,
                            'message' => 'No puedes solicitar vacaciones más allá del ' . $fechaLimite->format('d/m/Y') . ', fecha límite de tu periodo actual.'
                        ], Http::STATUS_BAD_REQUEST);
                    }
                }

                if ($prima_vacacional === 1) {
                    $diasDeAcumulado = 0.0;
                } else {
                    $diasAcumuladosDisponibles = (float) ($periodoActual['dias_acumulados_restantes'] ?? 0);
                    $fechaExpiracionAcum = $periodoActual['fecha_expiracion_acumulados'] ?? null;

                    if ($diasAcumuladosDisponibles > 0 && $fechaExpiracionAcum !== null) {
                        $fechaExpiracionObj = new \DateTime($fechaExpiracionAcum);
                        $fechaExpiracionObj->setTime(0, 0);

                        $diasDentroDeVigencia = $this->contarDiasHabilesHastaFecha($fechaDeObj, $fechaHastaObj, $fechaExpiracionObj);

                        $diasDeAcumulado = min($diasAcumuladosDisponibles, (float) $dias_solicitados, $diasDentroDeVigencia);

                        if ($diasDeAcumulado > 0) {
                            $this->historialvacacionesMapper->descontarAcumulado(
                                (int) $id_empleado[0]['Id_empleados'],
                                (int) $numeroAniversarioActual,
                                $diasAcumuladosDisponibles - $diasDeAcumulado
                            );
                        }
                    }
                }

                $diasDelPeriodoActual = $dias_solicitados - $diasDeAcumulado;
                if ($diasDelPeriodoActual > 0) {
                    $dias_disponibles = $empleado_ausencias[0]['dias_disponibles'] - $diasDelPeriodoActual;
                    $this->ausenciasMapper->updateAusenciasEmpleado($empleado_ausencias[0]['id_ausencias'], $dias_disponibles);
                }
            }
            
            $fecha_de = DateTime::createFromFormat('d/m/Y', $this->request->getParam('fecha_de'))->format('Y-m-d');
            $fecha_hasta = DateTime::createFromFormat('d/m/Y', $this->request->getParam('fecha_hasta'))->format('Y-m-d');

            // Si la solicitud queda "partida" entre el colchón acumulado y el periodo
            // actual, se registran DOS filas en el historial con sus fechas reales, para que
            // el reporte no mezcle días de dos aniversarios en un solo registro.
            $esPartida = $diasDeAcumulado > 0
                && $diasDeAcumulado < (float) $dias_solicitados
                && floor($diasDeAcumulado) == $diasDeAcumulado;

            if ($esPartida) {
                $fechaCorte = null;
                $cursor = new \DateTime($fecha_de);
                $fin = new \DateTime($fecha_hasta);
                $contador = 0;
                while ($cursor <= $fin) {
                    $diaSemana = (int) $cursor->format('N'); // 1=lunes, 7=domingo
                    if ($diaSemana <= 5) {
                        $contador++;
                        if ($contador >= (int) $diasDeAcumulado) {
                            $fechaCorte = clone $cursor;
                            break;
                        }
                    }
                    $cursor->modify('+1 day');
                }

                if ($fechaCorte === null) {
                    $esPartida = false;
                }
            }

            if ($esPartida) {
                $fechaCorteStr = $fechaCorte->format('Y-m-d');
                $fechaSiguienteStr = (clone $fechaCorte)->modify('+1 day')->format('Y-m-d');

                // Bloque 1: días cubiertos con el colchón acumulado → pertenecen al periodo ANTERIOR
                $idHistorialAusencia = $this->historialausenciasMapper->EnviarAusencia(
                    (int) $id_tipo_ausencia,
                    $empleado_ausencias[0]['id_ausencias'],
                    $fecha_de,
                    $fechaCorteStr,
                    (int) $prima_vacacional,
                    $notas,
                    $numeroAniversarioActual - 1,
                    (int) $diasDeAcumulado,
                    $diasDeAcumulado
                );

                // Bloque 2: el resto de días, del periodo actual.
                $this->historialausenciasMapper->EnviarAusencia(
                    (int) $id_tipo_ausencia,
                    $empleado_ausencias[0]['id_ausencias'],
                    $fechaSiguienteStr,
                    $fecha_hasta,
                    (int) $prima_vacacional,
                    $notas,
                    $numeroAniversarioActual,
                    (int) ($dias_solicitados - $diasDeAcumulado),
                    0.0
                );
            } else {
                // Caso simple: todo salió de un solo periodo.
                $idAniversarioRegistro = $esAnticipada
                    ? $numeroAniversarioActual + 1
                    : (($diasDeAcumulado > 0 && $diasDeAcumulado >= (float) $dias_solicitados)
                        ? $numeroAniversarioActual - 1
                        : $numeroAniversarioActual);

                $idHistorialAusencia = $this->historialausenciasMapper->EnviarAusencia(
                    (int) $id_tipo_ausencia,
                    $empleado_ausencias[0]['id_ausencias'],
                    $fecha_de,
                    $fecha_hasta,
                    (int) $prima_vacacional,
                    $notas,
                    $idAniversarioRegistro,
                    (int) $dias_solicitados,
                    $diasDeAcumulado
                );
            }

            if ($puedeDescontarDias && !empty($tipo_ausencia)) {
                $this->actividadesMapper->ensureActividadAusencia();

                $cursor = new \DateTime($fecha_de);
                $fin = new \DateTime($fecha_hasta);

                while ($cursor <= $fin) {
                    $diaSemana = (int) $cursor->format('N');
                    if ($diaSemana <= 5) {
                        $reporte = new \OCA\Empleados\Db\reportetiempo();
                        $reporte->setidEmpleado((int) $id_empleado[0]['Id_empleados']);
                        $reporte->setidCliente(99999);
                        $reporte->setidActividad(99999);
                        $reporte->settiempoRegistrado(480);
                        $reporte->setfechaRegistro($cursor->format('Y-m-d'));
                        $reporte->setdescripcion((string) ($tipo_ausencia[0]['nombre'] ?? ''));
						$reporte->setTipoTrabajo(\OCA\Empleados\Db\reportetiempo::TIPO_AUSENCIA);
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

            $this->registrarActividadAusencia(
                $tipo_ausencia[0]['nombre'],
                $fecha_de,
                $fecha_hasta,
                $idHistorialAusencia,
                $user->getUID(),
                $user->getDisplayName()
            );

            return new DataResponse(['success' => true, 'message' => 'Ausencia registrada correctamente']);
        } catch (\Exception $e) {
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

        $uid = $this->userSession->getUser()->getUID();
        $id_empleado = $this->empleadosMapper->GetMyEmployeeInfo($uid);

        if (empty($id_empleado)) {
            return new DataResponse(['error' => 'No se encontró el empleado'], Http::STATUS_BAD_REQUEST);
        }

        $empleado_ausencias = $this->ausenciasMapper->GetAusenciasByUser((int)$id_empleado[0]['Id_empleados']);

        if (empty($empleado_ausencias)) {
            return new DataResponse(['error' => 'El empleado no tiene registro en la tabla ausencias'], Http::STATUS_BAD_REQUEST);
        }

        $historial = $this->historialausenciasMapper->GetAusenciasEnRango(
            $desde, $hasta, $empleado_ausencias[0]['id_ausencias']
        );

        $this->marcarEsTemprana($historial, (int) $id_empleado[0]['Id_empleados']);

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

            $this->marcarEsTemprana($ausencias, (int) $empleado['Id_empleados']);

            // opcional: agrega nombre del empleado a cada evento
            foreach ($ausencias as &$a) {
                $a['nombre_empleado'] = $empleado['Id_user'];
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
        $isPrivileged = $this->groupManager->isInGroup($user->getUID(), 'admin')
                    || $this->groupManager->isInGroup($user->getUID(), 'recursos_humanos');

        $equipo_empleado = $this->empleadosMapper->GetSubordinates($user->getUID());

        $response = [];

        foreach ($equipo_empleado as $empleado) {

            $empleado_inf = $this->ausenciasMapper->GetAusenciasByUser(
                (int)$empleado['Id_empleados']
            );

            if (empty($empleado_inf)) {
                continue;
            }

            $ausencias = $this->historialausenciasMapper->GetAusenciasEnRango(
                $desde,
                $hasta,
                (int)$empleado_inf[0]['id_ausencias']
            );

            $this->marcarEsTemprana($ausencias, (int) $empleado['Id_empleados']);

            foreach ($ausencias as &$a) {
                $a['nombre_empleado'] = $empleado['Id_user'];
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

                $this->marcarEsTemprana($ausencias, $id_emp);

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
            return new DataResponse(['success' => false, 'message' => 'ID inválido'], Http::STATUS_BAD_REQUEST);
        }

        try {
            $detalle = $this->historialausenciasMapper->GetDetalleById($id);
            if (empty($detalle)) {
                return new DataResponse(['success' => false, 'message' => 'Ausencia no encontrada'], Http::STATUS_NOT_FOUND);
            }
            $ausencia = $detalle[0];

            $user = $this->userSession->getUser();
            $uid = $user->getUID();
            $isPrivileged = $this->groupManager->isInGroup($uid, 'admin') || $this->groupManager->isInGroup($uid, 'recursos_humanos');

            $reg = $this->ausenciasMapper->GetAusenciasById((int) $ausencia['id_ausencias']);
            $empleadoInfo = !empty($reg) ? $this->empleadosMapper->GetMyEmployeeInfoByIdEmpleado((string) $reg[0]['id_empleado']) : [];

            $ausencia['es_gerente'] = !empty($empleadoInfo) && $empleadoInfo[0]['Id_gerente'] === $uid;
            $ausencia['es_socio'] = !empty($empleadoInfo) && $empleadoInfo[0]['Id_socio'] === $uid;
            $ausencia['es_privilegiado'] = $isPrivileged;
            $ausencia['gerente_es_socio'] = !empty($empleadoInfo) && $this->gerenteEsSocio($empleadoInfo);

            return new DataResponse($ausencia, Http::STATUS_OK);
        } catch (\Exception $e) {
            return new DataResponse(['success' => false, 'message' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Cancela una ausencia del historial.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function CancelarAusencia(): DataResponse {
        $this->checkAccess(['admin', 'empleados']);

        $id = (int) $this->request->getParam('id');
        $user = $this->userSession->getUser();
        $uid = $user->getUID();

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
                $id_empleado = $this->empleadosMapper->GetMyEmployeeInfo($uid);
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

            if ((int) $ausencia['a_gerente'] === 2 || (int) $ausencia['a_socio'] === 2 || (int) ($ausencia['a_capital_humano'] ?? 0) === 2) {
                return new DataResponse(
                    ['success' => false, 'message' => 'La ausencia ya fue rechazada'],
                    Http::STATUS_BAD_REQUEST
                );
            }

            $this->historialausenciasMapper->CancelarAusencia($id);
            $this->revertirEfectosAusencia($ausencia);

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
            $fecha_de_raw = $this->request->getParam('fecha_de');   // yyyy-mm-dd
            $fecha_hasta_raw = $this->request->getParam('fecha_hasta'); // yyyy-mm-dd
            $dias = (int) $this->request->getParam('dias_solicitados');
            $prima = (int) $this->request->getParam('prima_vacacional');
            $notas = $this->request->getParam('notas') ?? '';

            if (!$id || !$id_tipo || !$fecha_de_raw || !$fecha_hasta_raw) {
                return new DataResponse(['success' => false, 'message' => 'Faltan parámetros requeridos'], Http::STATUS_BAD_REQUEST);
            }

            if ($prima === 1 && $dias < 2) {
                return new DataResponse(
                    ['success' => false, 'message' => 'La prima vacacional requiere al menos 2 días solicitados.'],
                    Http::STATUS_BAD_REQUEST
                );
            }

            $user = $this->userSession->getUser();
            $uid = $user->getUID();

            $isPrivileged = $this->groupManager->isInGroup($uid, 'admin') ||
                            $this->groupManager->isInGroup($uid, 'recursos_humanos');

            // Obtener el registro actual para validar días y devolver los que ya se descontaron
            $registro = $this->historialausenciasMapper->GetById($id);
            if (empty($registro)) {
                return new DataResponse(['success' => false, 'message' => 'Ausencia no encontrada'], Http::STATUS_BAD_REQUEST);
            }

            $id_empleado = $this->empleadosMapper->GetMyEmployeeInfo($uid);
            $empleado_ausencias = $this->ausenciasMapper->GetAusenciasByUser($id_empleado[0]['Id_empleados']);
            $tipo_ausencia = $this->tipoausenciaMapper->getTipoById($id_tipo);

            // Ni la ausencia original ni el tipo nuevo pueden ser "anticipada" (privado=1)
            // si quien edita no es admin/RH.
            $tipoOriginal = $this->tipoausenciaMapper->getTipoById($registro[0]['id_tipo_ausencia']);
            $eraAnticipada = !empty($tipoOriginal) && (int) ($tipoOriginal[0]['privado'] ?? 0) > 0;
            $esAnticipadaNueva = !empty($tipo_ausencia) && (int) ($tipo_ausencia[0]['privado'] ?? 0) > 0;

            if (!$isPrivileged && ($eraAnticipada || $esAnticipadaNueva)) {
                return new DataResponse(['success' => false, 'message' => 'Sin permiso para editar esta ausencia'], Http::STATUS_FORBIDDEN);
            }

            if ($prima === 1) {
                $fechaDeCheck = new DateTime($fecha_de_raw);
                $fechaHastaCheck = new DateTime($fecha_hasta_raw);

                // El empleado solo puede tener 1 prima vacacional por año calendario.
                // Se excluye este mismo registro ($id) para permitir editar sin marcarse a sí mismo como duplicado.
                $anioSolicitud = (int) $fechaDeCheck->format('Y');
                $primaUsadaEsteAnio = $this->historialausenciasMapper->PrimaVacacionalUsadaEsteAnio(
                    (int) $empleado_ausencias[0]['id_ausencias'],
                    $anioSolicitud,
                    $id
                );

                if ($primaUsadaEsteAnio) {
                    return new DataResponse([
                        'success' => false,
                        'message' => 'Ya se solicitó la prima vacacional para el año ' . $anioSolicitud . '.'
                    ], Http::STATUS_BAD_REQUEST);
                }
            }

            // Sólo ajustar días si el tipo descuenta vacaciones
            if (!empty($tipo_ausencia) && $tipo_ausencia[0]['solicitar_prima_vacacional'] == 1 && (int) ($tipo_ausencia[0]['privado'] ?? 0) === 0) {
                $dias_originales = (int) ($registro[0]['dias_solicitados'] ?? 0);
                $dias_disponibles = (float) $empleado_ausencias[0]['dias_disponibles'];
                $nuevos_disponibles = ($dias_disponibles + $dias_originales) - $dias;
                $this->ausenciasMapper->updateAusenciasEmpleado(
                    $empleado_ausencias[0]['id_ausencias'],
                    $nuevos_disponibles
                );
            }

            // Fecha en formato Y-m-d
            $fecha_de = (new \DateTime($fecha_de_raw))->format('Y-m-d');
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
                $era_cargable = !empty($tipo_original) && (int) $tipo_original[0]['cargable'] === 1;
                $es_cargable = (int) $tipo_nuevo[0]['cargable'] === 1;

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
                    $fin = new \DateTime($fecha_hasta);

                    while ($cursor <= $fin) {
                        if ((int) $cursor->format('N') <= 5) {
                            $reporte = new \OCA\Empleados\Db\reportetiempo();
                            $reporte->setidEmpleado($id_empleado);
                            $reporte->setidCliente(99999);
                            $reporte->setidActividad(99999);
                            $reporte->settiempoRegistrado(480);
                            $reporte->setfechaRegistro($cursor->format('Y-m-d'));
                            $reporte->setdescripcion((string) ($tipo_nuevo[0]['nombre'] ?? ''));
							$reporte->setTipoTrabajo(\OCA\Empleados\Db\reportetiempo::TIPO_AUSENCIA);
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

        $exclude_id = (int) $this->request->getParam('exclude_id');
        $fechaParam = $this->request->getParam('fecha_de');

        $anio = (int) date('Y');
        if (!empty($fechaParam)) {
            $fechaObj = DateTime::createFromFormat('d/m/Y', $fechaParam) ?: (new DateTime($fechaParam));
            if ($fechaObj) {
                $anio = (int) $fechaObj->format('Y');
            }
        }

        $user = $this->userSession->getUser();
        $uid = $user->getUID();

        $isPrivileged = $this->groupManager->isInGroup($uid, 'admin') ||
                        $this->groupManager->isInGroup($uid, 'recursos_humanos');

        if ($isPrivileged && $this->request->getParam('id_usuario')) {
            $target = $this->userManager->get($this->request->getParam('id_usuario'));
            $uid = $target->getUID();
        }

        $id_empleado = $this->empleadosMapper->GetMyEmployeeInfo($uid);
        $empleado_ausencias = $this->ausenciasMapper->GetAusenciasByUser($id_empleado[0]['Id_empleados']);

        if (empty($empleado_ausencias)) {
            return new DataResponse(['used' => false], Http::STATUS_OK);
        }

        $used = $this->historialausenciasMapper->PrimaVacacionalUsadaEsteAnio(
            (int) $empleado_ausencias[0]['id_ausencias'],
            $anio,
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
        $uid = $user->getUID();

        $isPrivileged = $this->groupManager->isInGroup($uid, 'admin') ||
                        $this->groupManager->isInGroup($uid, 'recursos_humanos');

        $desde = $this->request->getParam('desde');
        $hasta = $this->request->getParam('hasta');

        if (!$isPrivileged) {
            return new DataResponse(['success' => false, 'message' => []], Http::STATUS_FORBIDDEN);
        }

        $response = $this->historialausenciasMapper->GetHistorialReporteCompleto($desde, $hasta);

        foreach ($response as &$row) {
            $row['es_temprana'] = false;

            if (empty($row['ingreso_empleado']) || !isset($row['id_aniversario']) || empty($row['fecha_de'])) {
                continue;
            }

            try {
                $fechaIngreso = new DateTime($row['ingreso_empleado']);
                $periodoInicio = (clone $fechaIngreso)->modify('+' . (int) $row['id_aniversario'] . ' years');
                $fechaDeItem = new DateTime($row['fecha_de']);
                $row['es_temprana'] = $fechaDeItem < $periodoInicio;
            } catch (\Exception $e) {
            }
        }
        unset($row);

        return new DataResponse(['success' => true, 'message' => $response], Http::STATUS_OK);
    }

    /**
     * Obtiene el historial de un empleado para el "Resumen por empleado",
     * agrupado por el aniversario al que pertenece cada ausencia.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GetHistorialReporteAniversario(int $id_empleado, int $numero_aniversario): DataResponse {
        $this->checkAccess(['admin', 'empleados']);

        $user = $this->userSession->getUser();
        $uid = $user->getUID();

        $isPrivileged = $this->groupManager->isInGroup($uid, 'admin') ||
                        $this->groupManager->isInGroup($uid, 'recursos_humanos');

        if (!$isPrivileged) {
            return new DataResponse(['success' => false, 'message' => []], Http::STATUS_FORBIDDEN);
        }

        $empleado_ausencias = $this->ausenciasMapper->GetAusenciasByUser($id_empleado);
        if (empty($empleado_ausencias)) {
            return new DataResponse(['success' => true, 'message' => []], Http::STATUS_OK);
        }
        $idAusencias = (int) $empleado_ausencias[0]['id_ausencias'];

        $empleadoInfo = $this->empleadosMapper->GetMyEmployeeInfoByIdEmpleado((string) $id_empleado);
        if (empty($empleadoInfo) || empty($empleadoInfo[0]['Ingreso'])) {
            return new DataResponse(['success' => false, 'message' => 'Empleado sin fecha de ingreso'], Http::STATUS_BAD_REQUEST);
        }

        $fechaIngreso = new DateTime($empleadoInfo[0]['Ingreso']);

        // Rango de calendario propio del aniversario N
        $inicioN = (clone $fechaIngreso)->modify('+' . $numero_aniversario . ' years')->format('Y-m-d');
        $finN = (clone $fechaIngreso)->modify('+' . ($numero_aniversario + 1) . ' years')->format('Y-m-d');
        $finNConGracia = (clone $fechaIngreso)->modify('+' . ($numero_aniversario + 1) . ' years')->modify('+6 months')->format('Y-m-d');

        // Rango del aniversario siguiente (N+1), de donde pueden venir las
        // ausencias "atrasadas" que gastaron el colchón vencido de N.
        $inicioN1 = $finN;
        $finN1 = (clone $fechaIngreso)->modify('+' . ($numero_aniversario + 2) . ' years')->format('Y-m-d');

        $historialAmplio = $this->historialausenciasMapper->GetAusenciasEnRango($fechaIngreso->format('Y-m-d'), $finN1, $idAusencias);

        $todas = array_values(array_filter(
            $historialAmplio,
            fn($item) => (int) ($item['id_aniversario'] ?? -1) === $numero_aniversario
        ));

        $inicioNDate = new DateTime($inicioN);

        $uidEmpleado = $empleadoInfo[0]['Id_user'] ?? null;
        foreach ($todas as &$row) {
            $row['nombre_empleado'] = $uidEmpleado;
            $row['id_empleado'] = $id_empleado;
            $fechaDeItem = new DateTime($row['fecha_de']);
            $fechaHastaItem = new DateTime($row['fecha_hasta']);
            $finNormal = new DateTime($finN);
            $row['es_tardia'] = ($fechaDeItem > $finNormal || $fechaHastaItem > $finNormal);
            $row['es_temprana'] = $fechaDeItem < $inicioNDate;
        }
        unset($row);

        usort($todas, fn($a, $b) => strcmp($b['timestamp'] ?? '', $a['timestamp'] ?? ''));

        return new DataResponse(['success' => true, 'message' => $todas], Http::STATUS_OK);
    }

    /**
     * Devuelve todos los periodos de vacaciones de un empleado.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GetPeriodosVacaciones(int $id_empleado): DataResponse {
        $this->checkAccess(['admin', 'empleados']);

        $empleado = $this->empleadosMapper->GetMyEmployeeInfoByIdEmpleado((string) $id_empleado);
        if (empty($empleado) || empty($empleado[0]['Ingreso'])) {
            return new DataResponse(['success' => false, 'message' => 'Empleado sin fecha de ingreso'], Http::STATUS_BAD_REQUEST);
        }

        $fechaIngreso = new DateTime($empleado[0]['Ingreso']);
        $hoy = new DateTime();
        $numeroAniversarioActual = $hoy->diff($fechaIngreso)->y;

        $empleadoAusencias = $this->ausenciasMapper->GetAusenciasByUser($id_empleado);
        $idAusencias = $empleadoAusencias[0]['id_ausencias'] ?? null;

        $ultimosDiasConocidos = 0;
        $response = [];

        $tieneHistorialPrevio = $this->historialvacacionesMapper->tieneAsignacionManual($id_empleado);

        for ($n = 0; $n <= $numeroAniversarioActual; $n++) {
            $periodoInicio = (clone $fechaIngreso)->modify('+' . $n . ' years');
            $periodoFin = (clone $fechaIngreso)->modify('+' . ($n + 1) . ' years');
            $periodoInicioStr = $periodoInicio->format('Y-m-d');
            $periodoFinStr = $periodoFin->format('Y-m-d');
            $periodoFinConGraciaStr = (clone $periodoFin)->modify('+6 months')->format('Y-m-d');

            $existente = $this->historialvacacionesMapper->getByEmpleadoYAniversario($id_empleado, $n);

            if ($existente) {
                $diasDerecho = (float) $existente['dias_derecho'];

                if ($existente['periodo_inicio'] !== $periodoInicioStr || $existente['periodo_fin'] !== $periodoFinStr) {
                    error_log(sprintf(
                        'GetPeriodosVacaciones: corrigiendo fechas obsoletas del aniversario %d (empleado %d): %s→%s pasa a %s→%s',
                        $n, $id_empleado, $existente['periodo_inicio'], $existente['periodo_fin'], $periodoInicioStr, $periodoFinStr
                    ));
                    $this->historialvacacionesMapper->actualizarFechas($id_empleado, $n, $periodoInicioStr, $periodoFinStr);
                }
            } else {
                $tieneAniversarioCero = $this->historialvacacionesMapper->tieneAniversarioCero($id_empleado);

                if (!$tieneHistorialPrevio && !$tieneAniversarioCero && $n !== 0) {
                    $diasDerecho = 0.0;
                } else {
                    $tablaAniversario = $this->aniversarioMapper->GetAniversarioByDate($n);
                    if (empty($tablaAniversario)) {
                        $diasDerecho = $ultimosDiasConocidos;
                    } else {
                        $diasDerecho = (float) ($tablaAniversario[0]['dias'] ?? 0);
                    }
                }
                $this->historialvacacionesMapper->guardar($id_empleado, $n, $periodoInicioStr, $periodoFinStr, $diasDerecho);
            }

            $ultimosDiasConocidos = $diasDerecho;

            $diasDisfrutados = 0;
            if ($idAusencias) {
                $historial = $this->historialausenciasMapper->GetAusenciasEnRango($fechaIngreso->format('Y-m-d'), $periodoFinConGraciaStr, $idAusencias);
                foreach ($historial as $item) {
                    if ((int) ($item['id_aniversario'] ?? -1) !== $n) continue;
                    if ((int) $item['a_gerente'] === 3 || (int) $item['a_socio'] === 3) continue;
                    if ((int) $item['a_gerente'] === 2 || (int) $item['a_socio'] === 2) continue;
                    if ((int) ($item['solicitar_prima_vacacional'] ?? 0) !== 1) continue;
                    $diasDisfrutados += (float) $item['dias_solicitados'] - (float) ($item['dias_de_acumulado'] ?? 0);
                }

                $periodoSiguienteInicio = $periodoFinStr;
                $periodoSiguienteFin = (clone $fechaIngreso)->modify('+' . ($n + 2) . ' years')->format('Y-m-d');
                $historialAtrasado = $this->historialausenciasMapper->GetAusenciasEnRango($periodoSiguienteInicio, $periodoSiguienteFin, $idAusencias);
                foreach ($historialAtrasado as $item) {
                    if ((int) ($item['id_aniversario'] ?? -1) !== $n) continue;
                    if ((int) $item['a_gerente'] === 3 || (int) $item['a_socio'] === 3) continue;
                    if ((int) $item['a_gerente'] === 2 || (int) $item['a_socio'] === 2) continue;
                    if ((int) ($item['solicitar_prima_vacacional'] ?? 0) !== 1) continue;
                    $diasDisfrutados += (float) ($item['dias_de_acumulado'] ?? 0); // ← solo la porción del colchón
                }
            }

            $response[] = [
                'id_empleado' => $id_empleado,
                'numero_aniversario' => $n,
                'periodo_inicio' => $periodoInicioStr,
                'periodo_fin' => $periodoFinStr,
                'dias_derecho' => $diasDerecho,
                'dias_disfrutados' => $diasDisfrutados,
                'dias_restantes' => $diasDerecho - $diasDisfrutados,
                'es_actual' => $n === $numeroAniversarioActual,
            ];
        }

        usort($response, fn($a, $b) => $b['numero_aniversario'] <=> $a['numero_aniversario']);

        return new DataResponse(['success' => true, 'message' => $response], Http::STATUS_OK);
    }

    /**
     * Permite a RH ingresar manualmente el colchón acumulado de un empleado
     */
    #[UseSession]
    #[NoAdminRequired]
    public function EditarAcumuladoManual(): DataResponse {
        $this->checkAccess(['admin', 'empleados']);

        $id_empleado = (int) $this->request->getParam('id_empleado');
        $dias_acumulados = (float) $this->request->getParam('dias_acumulados');

        if ($id_empleado <= 0) {
            return new DataResponse(['success' => false, 'message' => 'ID inválido'], Http::STATUS_BAD_REQUEST);
        }

        $empleado_ausencias = $this->ausenciasMapper->GetAusenciasByUser($id_empleado);
        if (empty($empleado_ausencias)) {
            return new DataResponse(['success' => false, 'message' => 'Empleado sin registro de ausencias'], Http::STATUS_BAD_REQUEST);
        }

        // Esto crea la fila del periodo actual si no existe
        $periodo = $this->getPeriodoActualEmpleado($id_empleado, (int) $empleado_ausencias[0]['id_ausencias']);
        if (!$periodo) {
            return new DataResponse(['success' => false, 'message' => 'No se pudo calcular el periodo actual'], Http::STATUS_BAD_REQUEST);
        }

        $fechaExpiracion = $dias_acumulados > 0
            ? (new DateTime($periodo['periodo_inicio']))->modify('+6 months')->format('Y-m-d')
            : null;

        $this->historialvacacionesMapper->actualizarAcumulado(
            $id_empleado,
            $periodo['numero_aniversario'],
            $dias_acumulados,
            $fechaExpiracion
        );

        return new DataResponse(['success' => true], Http::STATUS_OK);
    }

    /**
     * Permite a RH asignar manualmente los días de vacaciones del periodo actual de un empleado.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function AsignarDiasDerecho(int $id_empleado, float $dias_disponibles): DataResponse {
        $this->checkAccess(['admin', 'empleados']);

        $empleado_ausencias = $this->ausenciasMapper->GetAusenciasByUser($id_empleado);
        if (empty($empleado_ausencias)) {
            return new DataResponse(['success' => false, 'message' => 'Empleado sin registro de ausencias'], Http::STATUS_BAD_REQUEST);
        }

        $periodo = $this->getPeriodoActualEmpleado($id_empleado, (int) $empleado_ausencias[0]['id_ausencias']);
        if (!$periodo) {
            return new DataResponse(['success' => false, 'message' => 'No se pudo calcular el periodo actual'], Http::STATUS_BAD_REQUEST);
        }

        $nuevoDerecho = $dias_disponibles + $periodo['dias_disfrutados'];

        $this->historialvacacionesMapper->actualizarDerecho(
            $id_empleado,
            $periodo['numero_aniversario'],
            $nuevoDerecho
        );

        $this->historialvacacionesMapper->invalidarAcumulado(
            $id_empleado,
            $periodo['numero_aniversario'] + 1
        );

        return new DataResponse(['success' => true], Http::STATUS_OK);
    }

    /**
     * Determina si el empleado tiene la misma persona como gerente y socio.
     */
    private function gerenteEsSocio(array $empleadoInfo): bool {
        $gerente = $empleadoInfo[0]['Id_gerente'] ?? null;
        $socio = $empleadoInfo[0]['Id_socio'] ?? null;
        return $gerente !== null && $socio !== null && $gerente === $socio;
    }

    /**
     * Marca cada fila del historial con 'es_temprana'.
     */
    private function marcarEsTemprana(array &$historial, int $id_empleado): void {
        if (empty($historial)) {
            return;
        }

        $empleado = $this->empleadosMapper->GetMyEmployeeInfoByIdEmpleado((string) $id_empleado);
        if (empty($empleado) || empty($empleado[0]['Ingreso'])) {
            foreach ($historial as &$row) {
                $row['es_temprana'] = false;
            }
            unset($row);
            return;
        }

        $fechaIngreso = new DateTime($empleado[0]['Ingreso']);

        foreach ($historial as &$row) {
            $row['es_temprana'] = false;

            if (!isset($row['id_aniversario']) || empty($row['fecha_de'])) {
                continue;
            }

            try {
                $periodoInicio = (clone $fechaIngreso)->modify('+' . (int) $row['id_aniversario'] . ' years');
                $fechaDeItem = new DateTime($row['fecha_de']);
                $row['es_temprana'] = $fechaDeItem < $periodoInicio;
            } catch (\Exception $e) {
            }
        }
        unset($row);
    }

    /**
     * Determina si un registro del historial corresponde a un tipo de ausencia
     * "anticipada" (privado = 1), es decir, solo visible/gestionable por admin/RH.
     */
    private function esRegistroAnticipado(array $item): bool {
        if (!isset($item['id_tipo_ausencia'])) {
            return false;
        }
        $tipo = $this->tipoausenciaMapper->getTipoById($item['id_tipo_ausencia']);
        return !empty($tipo) && (int) ($tipo[0]['privado'] ?? 0) > 0;
    }

    /**
     * Quita del historial las ausencias anticipadas (privado=1) si quien consulta
     * no es admin/RH. Deja el arreglo intacto (reindexado) si sí lo es.
     */
    private function filtrarAnticipadasSiNoPrivilegiado(array $historial, bool $isPrivileged): array {
        if ($isPrivileged) {
            return $historial;
        }
        return array_values(array_filter($historial, fn($item) => !$this->esRegistroAnticipado($item)));
    }

    /**
     * Aprobar una ausencia según el rol de quien aprueba.
     * $rol puede ser: 'gerente' | 'socio' | 'capital_humano' | 'capital_humano_como_socio'
     */
    #[UseSession]
    #[NoAdminRequired]
    public function AprobarAusencia(): DataResponse {
        $id = (int) $this->request->getParam('id');
        $rol = (string) $this->request->getParam('rol');

        if ($id <= 0 || empty($rol)) {
            return new DataResponse([
                'success' => false,
                'message' => 'Parámetros inválidos'
            ], Http::STATUS_BAD_REQUEST);
        }

        $detalle = $this->historialausenciasMapper->GetDetalleById($id);
        if (empty($detalle)) {
            return new DataResponse([
                'success' => false,
                'message' => 'Ausencia no encontrada'
            ], Http::STATUS_NOT_FOUND);
        }

        $ausencia = $detalle[0];

        // Ya fue rechazada o cancelada
        if ((int)$ausencia['a_gerente'] === 2 || (int)$ausencia['a_gerente'] === 3) {
            return new DataResponse([
                'success' => false,
                'message' => 'Esta solicitud ya fue rechazada o cancelada'
            ], Http::STATUS_BAD_REQUEST);
        }

        $reg = $this->ausenciasMapper->GetAusenciasById((int)$ausencia['id_ausencias']);
        if (empty($reg)) {
            return new DataResponse([
                'success' => false,
                'message' => 'No se encontró el empleado dueño de la solicitud'
            ], Http::STATUS_BAD_REQUEST);
        }

        $empleadoInfo = $this->empleadosMapper
            ->GetMyEmployeeInfoByIdEmpleado((string)$reg[0]['id_empleado']);

        $user = $this->userSession->getUser();
        $uid = $user->getUID();

        $isPrivileged =
            $this->groupManager->isInGroup($uid, 'admin') ||
            $this->groupManager->isInGroup($uid, 'recursos_humanos');

        $esGerente = !empty($empleadoInfo)
            && $empleadoInfo[0]['Id_gerente'] === $uid;

        $esSocio = !empty($empleadoInfo)
            && $empleadoInfo[0]['Id_socio'] === $uid;

        $autorizado = match ($rol) {
            'gerente' => $esGerente,
            'socio' => $esSocio,
            'capital_humano',
            'capital_humano_como_socio' => $isPrivileged,
            default => false,
        };

        if (!$autorizado) {
            return new DataResponse([
                'success' => false,
                'message' => 'No tienes permiso para aprobar con este rol'
            ], Http::STATUS_FORBIDDEN);
        }

        // RH "puro" (no gerente ni socio) solo puede aprobar como capital_humano
        // después de que gerente y socio ya hayan aprobado.
        if ($rol === 'capital_humano' && !$esGerente && !$esSocio) {
            if ((int) $ausencia['a_gerente'] !== 1 || (int) $ausencia['a_socio'] !== 1) {
                return new DataResponse([
                    'success' => false,
                    'message' => 'RH solo puede aprobar una vez que gerente y socio hayan aprobado'
                ], Http::STATUS_BAD_REQUEST);
            }
        }

        if (
            $rol === 'capital_humano_como_socio' &&
            (int)$ausencia['a_socio'] === 1
        ) {
            return new DataResponse([
                'success' => false,
                'message' => 'El socio ya aprobó esta solicitud'
            ], Http::STATUS_BAD_REQUEST);
        }

        if ($esGerente && (int)$ausencia['a_gerente'] !== 1) {
            $this->historialausenciasMapper->SetEstadoGerente($id, 1);
        }

        if ($esSocio && (int)$ausencia['a_socio'] !== 1) {
            $this->historialausenciasMapper->SetEstadoSocio($id, 1);
        }

        if ($isPrivileged && (int)$ausencia['a_capital_humano'] !== 1) {
            $this->historialausenciasMapper->SetEstadoCapitalHumano($id, 1);
        }

        if (
            $rol === 'capital_humano_como_socio' &&
            (int)$ausencia['a_socio'] !== 1
        ) {
            $this->historialausenciasMapper->SetEstadoSocio($id, 1);
        }

        $gerenteFinal = $esGerente ? 1 : (int) $ausencia['a_gerente'];
        $socioFinal = ($esSocio || $rol === 'capital_humano_como_socio') ? 1 : (int) $ausencia['a_socio'];
        $capitalHumanoFinal = $isPrivileged ? 1 : (int) ($ausencia['a_capital_humano'] ?? 0);

        if ($gerenteFinal === 1 && $socioFinal === 1 && $capitalHumanoFinal === 1) {
            $this->notificarAusenciaAprobada($ausencia);
        }

        return new DataResponse([
            'success' => true
        ], Http::STATUS_OK);
    }

    /**
     * Rechazar una ausencia. Cualquier rol que rechace tumba toda la solicitud
     * y devuelve los días descontados (igual que CancelarAusencia).
     */
    #[UseSession]
    #[NoAdminRequired]
    public function RechazarAusencia(): DataResponse {
        $id = (int) $this->request->getParam('id');
        $rol = (string) $this->request->getParam('rol');
        $motivo = trim((string) $this->request->getParam('motivo', ''));
 
        if ($id <= 0 || empty($rol)) {
            return new DataResponse(['success' => false, 'message' => 'Parámetros inválidos'], Http::STATUS_BAD_REQUEST);
        }
 
        try {
            $detalle = $this->historialausenciasMapper->GetDetalleById($id);
            if (empty($detalle)) {
                return new DataResponse(['success' => false, 'message' => 'Ausencia no encontrada'], Http::STATUS_NOT_FOUND);
            }
            $ausencia = $detalle[0];
 
            if ((int) $ausencia['a_gerente'] === 2 || (int) $ausencia['a_gerente'] === 3) {
                return new DataResponse(['success' => false, 'message' => 'Esta solicitud ya estaba cerrada'], Http::STATUS_BAD_REQUEST);
            }
 
            $reg = $this->ausenciasMapper->GetAusenciasById((int) $ausencia['id_ausencias']);
            $empleadoInfo = !empty($reg) ? $this->empleadosMapper->GetMyEmployeeInfoByIdEmpleado((string) $reg[0]['id_empleado']) : [];
 
            $user = $this->userSession->getUser();
            $uid = $user->getUID();
            $isPrivileged = $this->groupManager->isInGroup($uid, 'admin') || $this->groupManager->isInGroup($uid, 'recursos_humanos');
 
           $autorizado = match ($rol) {
                'gerente' => !empty($empleadoInfo) && $empleadoInfo[0]['Id_gerente'] === $uid,
                'socio' => !empty($empleadoInfo) && $empleadoInfo[0]['Id_socio'] === $uid,
                default => false,
            };
 
            if (!$autorizado) {
                return new DataResponse(['success' => false, 'message' => 'Sin permiso para rechazar'], Http::STATUS_FORBIDDEN);
            }
 
            $this->historialausenciasMapper->RechazarTodo($id, $motivo !== '' ? $motivo : null);
            $this->revertirEfectosAusencia($ausencia);
            $this->notificarAusenciaRechazada($ausencia, $motivo);
 
            return new DataResponse(['success' => true], Http::STATUS_OK);
        } catch (\Exception $e) {
            return new DataResponse(
                ['success' => false, 'message' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Revierte los efectos de una ausencia (devuelve días, quita prima, borra reporte de tiempo).
     * Usado tanto por CancelarAusencia como por RechazarAusencia.
     */
    private function revertirEfectosAusencia(array $ausencia): void {
        $tipo = $this->tipoausenciaMapper->getTipoById($ausencia['id_tipo_ausencia']);
        $esAnticipada = !empty($tipo) && (int) ($tipo[0]['privado'] ?? 0) > 0;

        if (!empty($tipo) && (int) $tipo[0]['solicitar_prima_vacacional'] === 1) {
            $fechaDe = new \DateTime($ausencia['fecha_de']);
            $fechaHasta = new \DateTime($ausencia['fecha_hasta']);
            $hoy = new \DateTime();
            $hoy->setTime(0, 0);
            $fechaDe->setTime(0, 0);
            $fechaHasta->setTime(0, 0);

            if ($fechaDe >= $hoy || $fechaHasta >= $hoy) {
                $reg = $this->ausenciasMapper->GetAusenciasById((int) $ausencia['id_ausencias']);

                if (!empty($reg)) {
                    $diasDevolver = (float) $ausencia['dias_solicitados'];
                    $diasDeAcumulado = (float) ($ausencia['dias_de_acumulado'] ?? 0);
                    $diasDelPeriodo = $diasDevolver - $diasDeAcumulado;

                    if ($diasDeAcumulado > 0) {
                        // ... sin cambios ...
                    }

                    if ($diasDelPeriodo > 0 && !$esAnticipada) {
                        $diasActuales = (float) $reg[0]['dias_disponibles'];
                        $nuevosDias = $diasActuales + $diasDelPeriodo;
                        $this->ausenciasMapper->updateAusenciasEmpleado(
                            (int) $ausencia['id_ausencias'],
                            $nuevosDias
                        );
                    }
                }

                if ((int) $ausencia['prima_vacacional'] === 1) {
                    $this->ausenciasMapper->updatePrimaVacacional((int) $ausencia['id_ausencias'], 0);
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
    }

    /**
     * Envía un correo al empleado informando que su ausencia fue aprobada.
     */
    private function notificarAusenciaAprobada(array $ausencia): void {
        $reg = $this->ausenciasMapper->GetAusenciasById((int) $ausencia['id_ausencias']);
        if (empty($reg)) {
            return;
        }

        $idEmpleado = (int) $reg[0]['id_empleado'];
        $empleadoInfo = $this->empleadosMapper->GetMyEmployeeInfoByIdEmpleado((string) $idEmpleado);
        if (empty($empleadoInfo) || empty($empleadoInfo[0]['Id_user'])) {
            return;
        }

        $uidEmpleado = $empleadoInfo[0]['Id_user'];
        $userEmpleado = $this->userManager->get($uidEmpleado);
        if (!$userEmpleado) {
            return;
        }

        $mail = $userEmpleado->getEMailAddress();
        if (!$mail) {
            return;
        }

        $tipo = $this->tipoausenciaMapper->getTipoById($ausencia['id_tipo_ausencia']);
        $nombreTipo = $tipo[0]['nombre'] ?? 'Ausencia';

        $this->mailHelper->enviarCorreo(
            $mail,
            'Solicitud aprobada',
            [
                'Hola ' . $userEmpleado->getDisplayName() . '',
                'Tu solicitud de "' . $nombreTipo . '" ha sido aprobada por completo.',
                'Fecha de inicio: ' . $ausencia['fecha_de'] . '  - Fecha de finalización: ' . $ausencia['fecha_hasta'] . '',
                '',
            ]
        );

        $event = $this->activityManager->generateEvent();
        $event->setApp('empleados');
        $event->setType('empleados');
        $event->setObject('empleados', (int) $ausencia['id_historial_ausencias'] ?? 0, 'Ausencia aprobada');
        $event->setAffectedUser($uidEmpleado);
        $event->setSubject(
            'ausencia_aprobada',
            [
                'nombre' => (string) $uidEmpleado,
                'tipo_ausencia' => (string) $nombreTipo
            ]
        );
        $this->activityManager->publish($event);
    }

    /**
     * Envía un correo al empleado informando que su ausencia fue rechazada.
     */
    private function notificarAusenciaRechazada(array $ausencia, string $motivo = ''): void {
        $reg = $this->ausenciasMapper->GetAusenciasById((int) $ausencia['id_ausencias']);
        if (empty($reg)) {
            return;
        }

        $idEmpleado = (int) $reg[0]['id_empleado'];
        $empleadoInfo = $this->empleadosMapper->GetMyEmployeeInfoByIdEmpleado((string) $idEmpleado);
        if (empty($empleadoInfo) || empty($empleadoInfo[0]['Id_user'])) {
            return;
        }

        $uidEmpleado = $empleadoInfo[0]['Id_user'];
        $userEmpleado = $this->userManager->get($uidEmpleado);
        if (!$userEmpleado) {
            return;
        }

        $mail = $userEmpleado->getEMailAddress();
        if (!$mail) {
            return;
        }

        $tipo = $this->tipoausenciaMapper->getTipoById($ausencia['id_tipo_ausencia']);
        $nombreTipo = $tipo[0]['nombre'] ?? 'Ausencia';

        $cuerpo = [
            'Hola ' . $userEmpleado->getDisplayName() . '',
            'Tu solicitud de "' . $nombreTipo . '" ha sido rechazada.',
            'Fecha de inicio: ' . $ausencia['fecha_de'] . '  - Fecha de finalización: ' . $ausencia['fecha_hasta'] . '',
        ];

        if (trim($motivo) !== '') {
            $cuerpo[] = 'Motivo: ' . $motivo;
        }

        $cuerpo[] = '';

        $this->mailHelper->enviarCorreo(
            $mail,
            'Solicitud rechazada',
            $cuerpo
        );

        $event = $this->activityManager->generateEvent();
        $event->setApp('empleados');
        $event->setType('empleados');
        $event->setObject('empleados', (int) $ausencia['id_historial_ausencias'] ?? 0, 'Ausencia rechazada');
        $event->setAffectedUser($uidEmpleado);
        $event->setSubject(
            'ausencia_rechazada',
            [
                'nombre' => (string) $uidEmpleado,
                'tipo_ausencia' => (string) $nombreTipo
            ]
        );
        $this->activityManager->publish($event);
    }

    /**
     * Exportar Reporte.
     */
    #[UseSession]
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function DescargarReportePeriodosExcel(int $id_empleado) {
        $this->checkAccess(['admin', 'empleados']);
    
        $user = $this->userSession->getUser();
        $uid = $user->getUID();
        $isPrivileged = $this->groupManager->isInGroup($uid, 'admin')
            || $this->groupManager->isInGroup($uid, 'recursos_humanos');
    
        if (!$isPrivileged) {
            return new DataResponse(['success' => false, 'message' => 'Sin permiso'], Http::STATUS_FORBIDDEN);
        }
    
        $empleado = $this->empleadosMapper->GetMyEmployeeInfoByIdEmpleado((string) $id_empleado);
        if (empty($empleado) || empty($empleado[0]['Ingreso'])) {
            return new DataResponse(['success' => false, 'message' => 'Empleado sin fecha de ingreso'], Http::STATUS_BAD_REQUEST);
        }
    
        $nombreEmpleado = $empleado[0]['Nombre'] ?? $empleado[0]['Id_user'];
        $fechaIngreso = new DateTime($empleado[0]['Ingreso']);
    
        $empleadoAusencias = $this->ausenciasMapper->GetAusenciasByUser($id_empleado);
        $idAusencias = $empleadoAusencias[0]['id_ausencias'] ?? null;
    
        $hoy = new DateTime();
        $numeroAniversarioActual = $hoy->diff($fechaIngreso)->y;
    
        $pagos = $this->primavacacionalpagoMapper->getByEmpleado($id_empleado);
        $pagosPorAniversario = [];
        foreach ($pagos as $p) {
            $pagosPorAniversario[(int) $p['numero_aniversario']] = $p;
        }
    
        $meses = ['', 'ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO',
            'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE'];
    
        $filas = [];
    
        $filas[] = [
            'esIngreso' => true,
            'fecha' => $fechaIngreso->format('d/m/Y'),
            'evento' => 'Ingreso',
            'derecho' => '0',
        ];
    
        for ($n = 0; $n <= $numeroAniversarioActual; $n++) {
            $periodoInicio = (clone $fechaIngreso)->modify('+' . $n . ' years');
            $periodoFin = (clone $fechaIngreso)->modify('+' . ($n + 1) . ' years');
            $periodoInicioStr = $periodoInicio->format('Y-m-d');
            $periodoFinConGraciaStr = (clone $periodoFin)->modify('+6 months')->format('Y-m-d');
    
            $existente = $this->historialvacacionesMapper->getByEmpleadoYAniversario($id_empleado, $n);
            $diasDerecho = $existente ? (float) $existente['dias_derecho'] : 0.0;
    
            $diasDisfrutados = 0.0;
            $eventosTexto = [];
            $registroPrima = null;
    
            if ($idAusencias) {
                $historial = $this->historialausenciasMapper->GetAusenciasEnRango($fechaIngreso->format('Y-m-d'), $periodoFinConGraciaStr, $idAusencias);
    
                foreach ($historial as $item) {
                    if ((int) ($item['id_aniversario'] ?? -1) !== $n) {
                        continue;
                    }
                    // cancelada o rechazada
                    if ((int) $item['a_gerente'] === 3 || (int) $item['a_socio'] === 3) continue;
                    if ((int) $item['a_gerente'] === 2 || (int) $item['a_socio'] === 2) continue;
    
                    if ((int) ($item['prima_vacacional'] ?? 0) === 1 && $registroPrima === null) {
                        $registroPrima = $item;
                    }
    
                    if ((int) ($item['solicitar_prima_vacacional'] ?? 0) !== 1) {
                        continue;
                    }
    
                    $dias = (float) $item['dias_solicitados'] - (float) ($item['dias_de_acumulado'] ?? 0);
                    if ($dias <= 0) {
                        continue;
                    }
    
                    $diasDisfrutados += $dias;
                    $eventosTexto[] = $this->formatearRangoFechas($item['fecha_de'], $item['fecha_hasta'], $dias, $meses);
                }
            }
    
            $diasRestantes = $diasDerecho - $diasDisfrutados;
            $pago = $pagosPorAniversario[$n] ?? null;
    
            $filas[] = [
                'esIngreso' => false,
                'fecha' => $periodoInicio->format('d/m/Y'),
                'evento' => $n . '° Aniversario',
                'derecho' => $this->formatNumeroReporte($diasDerecho),
                'disfrutados' => $this->formatNumeroReporte($diasDisfrutados),
                'fechas' => implode(', ', $eventosTexto),
                'disponibles' => $this->formatNumeroReporte($diasRestantes),
                'prescripcion' => $periodoFin->format('d/m/Y'),
                'pv' => $registroPrima ? (new DateTime($registroPrima['fecha_de']))->format('d/m/Y') : '',
                'nota' => $pago
                    ? ('Pagado en ' . (new DateTime($pago['fecha_pago']))->format('d/m/Y')
                        . ' sobre ' . $this->formatNumeroReporte((float) $pago['dias_pagados']) . ' días.')
                    : '',
            ];
        }
    
        $xlsx = $this->construirExcelReportePeriodos($nombreEmpleado, $empleado[0]['Ingreso'] ?? '', $filas);
    
        $nombreArchivo = 'Detalle_Periodos_Vacacionales_'
            . preg_replace('/[^A-Za-z0-9_]+/', '_', $nombreEmpleado)
            . '.xlsx';
    
        $xlsx->downloadAs($nombreArchivo);
        exit;
    }
    
    /**
     * Arma el objeto SimpleXLSXGen con el layout/colores del reporte.
     */
    private function construirExcelReportePeriodos(string $nombreEmpleado, string $fechaIngresoRaw, array $filas) {
        $azul = '#1F4E79';
        $amarillo = '#FFC000';
        $cyan = '#29ABE2';
        $verde = '#C6E0B4';
        $blanco = '#FFFFFF';
    
        $ingresoFmt = $fechaIngresoRaw ? (new DateTime($fechaIngresoRaw))->format('d/m/Y') : '';
    
        $mesesL = ['', 'ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO',
            'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE'];
        $hoy = new DateTime();
        $hoyTxt = $hoy->format('d') . ' DE ' . $mesesL[(int) $hoy->format('n')] . ' ' . $hoy->format('Y');
    
        $cols = 9;
        $blank = array_fill(0, $cols, '');
        $rows = [];
    
        // Fila 1: título empresa + ingreso a nómina
        $r1 = $blank;
        $r1[0] = '<b><style font-size="14" color="' . $azul . '">GOSSLER, S.C. Oficina Torreón</style></b>';
        $r1[7] = 'Ingreso a nómina Gossler: ' . $ingresoFmt;
        $rows[] = $r1;
    
        // Fila 2: subtítulo
        $r2 = $blank;
        $r2[0] = '<b><i><style color="' . $azul . '">Detalle Periodos Vacacionales</style></i></b>';
        $rows[] = $r2;
    
        // Fila 3: fecha de generación
        $r3 = $blank;
        $r3[0] = $hoyTxt;
        $rows[] = $r3;
    
        $rows[] = $blank; // espacio
    
        // Fila 5: barra amarilla con nombre
        $r5 = [];
        for ($i = 0; $i < $cols; $i++) {
            $texto = $i === 0 ? ('Nombre: ' . mb_strtoupper($nombreEmpleado)) : '';
            $r5[] = '<b><style bgcolor="' . $amarillo . '">' . $texto . '</style></b>';
        }
        $rows[] = $r5;
    
        $rows[] = $blank; // espacio
    
        // Fila 7: encabezados de tabla
        $headers = ['Fecha', 'Evento', 'Dias con derecho', 'Dias disfrutados', 'Fechas',
            'Dias Disponibles', 'Prescripción', 'PV', ''];
        $r7 = [];
        foreach ($headers as $h) {
            $r7[] = '<b><style bgcolor="' . $azul . '" color="' . $blanco . '">' . $h . '</style></b>';
        }
        $rows[] = $r7;
    
        // Filas de datos
        foreach ($filas as $fila) {
            if (!empty($fila['esIngreso'])) {
                $valores = [$fila['fecha'], $fila['evento'], $fila['derecho'], '', '', '', '', '', ''];
                $row = [];
                foreach ($valores as $v) {
                    $row[] = '<b><style bgcolor="' . $cyan . '">' . $v . '</style></b>';
                }
                $rows[] = $row;
                continue;
            }
    
            $rows[] = [
                $fila['fecha'],
                '<b><style color="' . $azul . '">' . $fila['evento'] . '</style></b>',
                $fila['derecho'],
                $fila['disfrutados'],
                $fila['fechas'],
                $fila['disponibles'],
                $fila['prescripcion'],
                $fila['pv'],
                $fila['nota'] !== '' ? ('<b><style bgcolor="' . $verde . '">' . $fila['nota'] . '</style></b>') : '',
            ];
        }
    
        $xlsx = \Shuchkin\SimpleXLSXGen::fromArray($rows);
        $xlsx->mergeCells('A1:D1');
        $xlsx->mergeCells('H1:I1');
        $xlsx->mergeCells('A2:D2');
        $xlsx->mergeCells('A3:D3');
        $xlsx->mergeCells('A5:I5');
    
        return $xlsx;
    }
    
    private function formatearRangoFechas(string $fechaDeStr, string $fechaHastaStr, float $dias, array $meses): string {
        $de = new DateTime($fechaDeStr);
        $hasta = new DateTime($fechaHastaStr);
        $diasTxt = $this->formatNumeroReporte($dias) . ' ' . ($dias == 1 ? 'DÍA' : 'DÍAS');
    
        if ($de->format('Y-m-d') === $hasta->format('Y-m-d')) {
            return $diasTxt . ' (' . $de->format('d') . ' DE ' . $meses[(int) $de->format('n')] . ' ' . $de->format('Y') . ')';
        }
    
        if ($de->format('Y-m') === $hasta->format('Y-m')) {
            return $diasTxt . ' (DEL ' . $de->format('d') . ' AL ' . $hasta->format('d')
                . ' DE ' . $meses[(int) $de->format('n')] . ' ' . $de->format('Y') . ')';
        }
    
        return $diasTxt . ' (DEL ' . $de->format('d') . ' DE ' . $meses[(int) $de->format('n')]
            . ' AL ' . $hasta->format('d') . ' DE ' . $meses[(int) $hasta->format('n')] . ' ' . $hasta->format('Y') . ')';
    }
    
    private function formatNumeroReporte(float $n): string {
        return floor($n) == $n ? (string) (int) $n : rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    }
}
