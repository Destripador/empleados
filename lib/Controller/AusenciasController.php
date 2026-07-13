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
        historialvacacionesMapper $historialvacacionesMapper,
        empleadosMapper $empleadosMapper,
        IRootFolder $rootFolder,
        IUserManager $userManager,
        IManager $activityManager,
		IURLGenerator $urlGenerator,
        MailHelper $mailHelper,
        reportetiempoMapper $reportetiempoMapper,
        aniversarioMapper $aniversarioMapper,
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

        $event->setSubject(
            'ausencia_registrada',
            [
                'nombre' => (string) $user,
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
            
            $dependent = $this->empleadosMapper->GetMyEmployeeInfo($user);

            $event = $this->activityManager->generateEvent();
            $event->setApp('empleados');
            $event->setType('empleados');
            $event->setObject('empleados', (int) $idHistorialAusencia, 'Solicitud de ausencia');
            $event->setAffectedUser($usuario);

            $event->setSubject(
                'ausencia_registrada',
                [
                    'nombre' => (string) $user,
                    'tipo_ausencia' => (string) $tipoAusencia
                ]
            );

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
        $finAnterior = $periodoInicioStr;

        $disfrutadoAnterior = 0.0;
        $historialAnterior = $this->historialausenciasMapper->GetAusenciasEnRango($inicioAnterior, $finAnterior, $id_ausencias);
        foreach ($historialAnterior as $item) {
            if ((int) $item['a_gerente'] === 3 || (int) $item['a_socio'] === 3) continue;
            if ((int) $item['a_gerente'] === 2 || (int) $item['a_socio'] === 2) continue;
            if ((int) ($item['solicitar_prima_vacacional'] ?? 0) !== 1) continue;
            $disfrutadoAnterior += (float) $item['dias_solicitados'] - (float) ($item['dias_de_acumulado'] ?? 0);
        }

        $sobrante = ((float) $anterior['dias_derecho']) - $disfrutadoAnterior;

        if ($sobrante <= 0) {
            return [0.0, null];
        }

        $fechaExpiracion = (clone $periodoInicio)->modify('+6 months')->format('Y-m-d');
        return [$sobrante, $fechaExpiracion];
    }

	/**
	 * Calcula el periodo/aniversario actual del empleado a partir de su Ingreso
	 */
	private function getPeriodoActualEmpleado(int $id_empleado, int $id_ausencias): ?array {
		$empleado = $this->empleadosMapper->GetMyEmployeeInfoByIdEmpleado((string) $id_empleado);
		if (empty($empleado) || empty($empleado[0]['Ingreso'])) {
			return null;
		}

		$fechaIngreso = new DateTime($empleado[0]['Ingreso']);
		$hoy = new DateTime();
		$numeroAniversario = $hoy->diff($fechaIngreso)->y;

		$periodoInicio = (clone $fechaIngreso)->modify('+' . $numeroAniversario . ' years');
		$periodoFin = (clone $fechaIngreso)->modify('+' . ($numeroAniversario + 1) . ' years');
		$periodoInicioStr = $periodoInicio->format('Y-m-d');
		$periodoFinStr = $periodoFin->format('Y-m-d');

		$existente = $this->historialvacacionesMapper->getByEmpleadoYAniversario($id_empleado, $numeroAniversario);

		if ($existente) {
            $esManual = (int) ($existente['asignado_manualmente'] ?? 0) === 1;

            if ($esManual) {
                // RH ya confirmó este periodo manualmente
                $diasDerecho = (float) $existente['dias_derecho'];
                $yaCalculado = (int) ($existente['acumulado_calculado'] ?? 0) === 1;

                if ($yaCalculado) {
                    $diasAcumuladosRestantes = (float) ($existente['dias_acumulados_restantes'] ?? 0);
                    $fechaExpiracionAcum = $existente['fecha_expiracion_acumulados'] ?? null;
                } else {
                    [$diasAcumulados, $fechaExpiracionAcum] = $this->calcularAcumuladoPeriodo(
                        $id_empleado, $id_ausencias, $numeroAniversario, $fechaIngreso, $periodoInicio, $periodoInicioStr
                    );
                    $this->historialvacacionesMapper->actualizarAcumulado(
                        $id_empleado, $numeroAniversario, $diasAcumulados, $fechaExpiracionAcum
                    );
                    $diasAcumuladosRestantes = $diasAcumulados;
                }
            } else {
                $yaCalculado = (int) ($existente['acumulado_calculado'] ?? 0) === 1;

                if ($yaCalculado) {
                    // Ya se calculó antes: no recalcular ni pisar lo que ya se gastó del acumulado.
                    $diasDerecho = (float) $existente['dias_derecho'];
                    $diasAcumuladosRestantes = (float) ($existente['dias_acumulados_restantes'] ?? 0);
                    $fechaExpiracionAcum = $existente['fecha_expiracion_acumulados'] ?? null;
                } else {
                    $tieneAsignacionManual = $this->historialvacacionesMapper->tieneAsignacionManual($id_empleado);
                    $tieneAniversarioCero = $this->historialvacacionesMapper->tieneAniversarioCero($id_empleado);

                    if ($tieneAsignacionManual || $tieneAniversarioCero || $numeroAniversario === 0) {
                        $tablaAniversario = $this->aniversarioMapper->GetAniversarioByDate($numeroAniversario);
                        $diasDerecho = !empty($tablaAniversario) ? (float) ($tablaAniversario[0]['dias'] ?? 0) : 0.0;
                    } else {
                        $diasDerecho = 0.0;
                    }

                    [$diasAcumulados, $fechaExpiracionAcum] = $this->calcularAcumuladoPeriodo(
                        $id_empleado, $id_ausencias, $numeroAniversario, $fechaIngreso, $periodoInicio, $periodoInicioStr
                    );

                    $this->historialvacacionesMapper->actualizarDerechoAutomatico(
                        $id_empleado, $numeroAniversario, $diasDerecho, $diasAcumulados, $fechaExpiracionAcum
                    );

                    $diasAcumuladosRestantes = $diasAcumulados;
                }
            }
        } else {
            $tieneAsignacionManual = $this->historialvacacionesMapper->tieneAsignacionManual($id_empleado);
            $tieneAniversarioCero = $this->historialvacacionesMapper->tieneAniversarioCero($id_empleado);

            if ($tieneAsignacionManual || $tieneAniversarioCero || $numeroAniversario === 0) {
                $tablaAniversario = $this->aniversarioMapper->GetAniversarioByDate($numeroAniversario);
                $diasDerecho = !empty($tablaAniversario) ? (float) ($tablaAniversario[0]['dias'] ?? 0) : 0.0;
            } else {
                $diasDerecho = 0.0;
            }

            [$diasAcumulados, $fechaExpiracionAcum] = $this->calcularAcumuladoPeriodo(
                $id_empleado, $id_ausencias, $numeroAniversario, $fechaIngreso, $periodoInicio, $periodoInicioStr
            );

            $this->historialvacacionesMapper->guardarConAcumulado(
                $id_empleado, $numeroAniversario, $periodoInicioStr, $periodoFinStr,
                $diasDerecho, $diasAcumulados, $fechaExpiracionAcum
            );

            $diasAcumuladosRestantes = $diasAcumulados;
        }

		$acumuladoVigente = $diasAcumuladosRestantes > 0
			&& $fechaExpiracionAcum !== null
			&& $hoy <= new DateTime($fechaExpiracionAcum);

		$diasDisfrutados = 0.0;
		$historial = $this->historialausenciasMapper->GetAusenciasEnRango($periodoInicioStr, $periodoFinStr, $id_ausencias);
		foreach ($historial as $item) {
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

            $ausenciaAbarcaPresenteOFuturo = ($fechaDeObj >= $hoy || $fechaHastaObj >= $hoy);

            $puedeDescontarDias = $ausenciaAbarcaPresenteOFuturo;

            error_log('TIPO AUSENCIA: ' . print_r($tipo_ausencia, true));
            error_log('PUEDE DESCONTAR: ' . ($puedeDescontarDias ? 'SI' : 'NO'));

            $diasDeAcumulado = 0.0;

            if ($puedeDescontarDias && !empty($tipo_ausencia) && $tipo_ausencia[0]['solicitar_prima_vacacional'] == 1) {
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

                // Lo que no alcanzó a cubrir el acumulado
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
                    // No debería pasar, pero por seguridad caemos al caso simple.
                    $esPartida = false;
                }
            }

            if ($esPartida) {
                $fechaCorteStr = $fechaCorte->format('Y-m-d');
                $fechaSiguienteStr = (clone $fechaCorte)->modify('+1 day')->format('Y-m-d');

                // Bloque 1: días cubiertos con el colchón acumulado.
                $idHistorialAusencia = $this->historialausenciasMapper->EnviarAusencia(
                    (int) $id_tipo_ausencia,
                    $empleado_ausencias[0]['id_ausencias'],
                    $fecha_de,
                    $fechaCorteStr,
                    (int) $prima_vacacional,
                    $notas,
                    $numeroAniversarioActual,
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
                // Caso simple: todo salió de un solo periodo
                $idHistorialAusencia = $this->historialausenciasMapper->EnviarAusencia(
                    (int) $id_tipo_ausencia,
                    $empleado_ausencias[0]['id_ausencias'],
                    $fecha_de,
                    $fecha_hasta,
                    (int) $prima_vacacional,
                    $notas,
                    $numeroAniversarioActual,
                    (int) $dias_solicitados,
                    $diasDeAcumulado
                );
            }

            if ($puedeDescontarDias && !empty($tipo_ausencia) && (int) $tipo_ausencia[0]['cargable'] === 1) {
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

        if (empty($id_empleado)) {
            return new DataResponse(
                ['error' => 'No se encontró el empleado'],
                Http::STATUS_BAD_REQUEST
            );
        }

        $empleado_ausencias = $this->ausenciasMapper->GetAusenciasByUser(
            (int)$id_empleado[0]['Id_empleados']
        );

        if (empty($empleado_ausencias)) {
            return new DataResponse(
                ['error' => 'El empleado no tiene registro en la tabla ausencias'],
                Http::STATUS_BAD_REQUEST
            );
        }

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

            $reg = $this->ausenciasMapper->GetAusenciasById((int) $ausencia['id_ausencias']);
            $empleadoInfo = !empty($reg) ? $this->empleadosMapper->GetMyEmployeeInfoByIdEmpleado((string) $reg[0]['id_empleado']) : [];

            $user = $this->userSession->getUser();
            $uid = $user->getUID();
            $isPrivileged = $this->groupManager->isInGroup($uid, 'admin') || $this->groupManager->isInGroup($uid, 'recursos_humanos');

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

            // Sólo ajustar días si el tipo descuenta vacaciones
            if (!empty($tipo_ausencia) && $tipo_ausencia[0]['solicitar_prima_vacacional'] == 1) {
                $dias_originales = (int) ($registro[0]['dias_solicitados'] ?? 0);
                $dias_disponibles = (float) $empleado_ausencias[0]['dias_disponibles'];
                // Devolver los días originales y descontar los nuevos
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

        // Rango del aniversario siguiente (N+1), de donde pueden venir las
        // ausencias "atrasadas" que gastaron el colchón vencido de N.
        $inicioN1 = $finN;
        $finN1 = (clone $fechaIngreso)->modify('+' . ($numero_aniversario + 2) . ' years')->format('Y-m-d');

        $propias = array_values(array_filter(
            $this->historialausenciasMapper->GetAusenciasEnRango($inicioN, $finN, $idAusencias),
            fn($item) => (float) ($item['dias_de_acumulado'] ?? 0) <= 0
        ));

        $atrasadas = array_values(array_filter(
            $this->historialausenciasMapper->GetAusenciasEnRango($inicioN1, $finN1, $idAusencias),
            fn($item) => (float) ($item['dias_de_acumulado'] ?? 0) > 0
        ));

        $todas = array_merge($propias, $atrasadas);

        $uidEmpleado = $empleadoInfo[0]['Id_user'] ?? null;
        foreach ($todas as &$row) {
            $row['nombre_empleado'] = $uidEmpleado;
            $row['id_empleado'] = $id_empleado;
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
                $historial = $this->historialausenciasMapper->GetAusenciasEnRango($periodoInicioStr, $periodoFinStr, $idAusencias);
                foreach ($historial as $item) {
                    if ((int) $item['a_gerente'] === 3 || (int) $item['a_socio'] === 3) {
                        continue;
                    }
                    if ((int) $item['a_gerente'] === 2 || (int) $item['a_socio'] === 2) {
                        continue;
                    }
                    if ((int) ($item['solicitar_prima_vacacional'] ?? 0) !== 1) {
                        continue;
                    }
                    $diasDisfrutados += (float) $item['dias_solicitados'] - (float) ($item['dias_de_acumulado'] ?? 0);
                }
                $periodoSiguienteInicio = $periodoFinStr;
                $periodoSiguienteFin = (clone $fechaIngreso)->modify('+' . ($n + 2) . ' years')->format('Y-m-d');
                $historialAtrasado = $this->historialausenciasMapper->GetAusenciasEnRango($periodoSiguienteInicio, $periodoSiguienteFin, $idAusencias);
                foreach ($historialAtrasado as $item) {
                    if ((int) $item['a_gerente'] === 3 || (int) $item['a_socio'] === 3) {
                        continue;
                    }
                    if ((int) $item['a_gerente'] === 2 || (int) $item['a_socio'] === 2) {
                        continue;
                    }
                    if ((int) ($item['solicitar_prima_vacacional'] ?? 0) !== 1) {
                        continue;
                    }
                    $diasDisfrutados += (float) ($item['dias_de_acumulado'] ?? 0);
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
                'capital_humano', 'capital_humano_como_socio' => $isPrivileged,
                default => false,
            };

            if (!$autorizado) {
                return new DataResponse(['success' => false, 'message' => 'Sin permiso para rechazar'], Http::STATUS_FORBIDDEN);
            }

            $this->historialausenciasMapper->RechazarTodo($id);
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
     * Revierte los efectos de una ausencia (devuelve días, quita prima, borra reporte de tiempo).
     * Usado tanto por CancelarAusencia como por RechazarAusencia.
     */
    private function revertirEfectosAusencia(array $ausencia): void {
        $tipo = $this->tipoausenciaMapper->getTipoById($ausencia['id_tipo_ausencia']);

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
                        $registroAcumulado = $this->historialvacacionesMapper->getByEmpleadoYAniversario(
                            (int) $reg[0]['id_empleado'],
                            (int) $ausencia['id_aniversario']
                        );
                        $restanteActual = (float) ($registroAcumulado['dias_acumulados_restantes'] ?? 0);
                        $this->historialvacacionesMapper->descontarAcumulado(
                            (int) $reg[0]['id_empleado'],
                            (int) $ausencia['id_aniversario'],
                            $restanteActual + $diasDeAcumulado
                        );
                    }

                    if ($diasDelPeriodo > 0) {
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
}