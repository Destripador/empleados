<?php

declare(strict_types=1);

namespace OCA\Empleados\Controller;

use OCA\Empleados\AppInfo\Application;
use OCA\Empleados\Db\actividadMapper;
use OCA\Empleados\Db\clientesMapper;
use OCA\Empleados\Db\configuracionesMapper;
use OCA\Empleados\Db\empleadosMapper;
use OCA\Empleados\Db\reportetiempo;
use OCA\Empleados\Db\reportetiempoMapper;
use OCA\Empleados\UploadException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\Group\ISubAdmin;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\Mail\IMailer;
use OCP\Notification\IManager as INotificationManager;

require_once 'SimpleXLSXGen.php';
require_once 'SimpleXLSX.php';

/**
 * Controlador para la gestión de reportes de tiempo de empleados.
 */
class reportetiempoController extends BaseController {

	protected $userManager;
    protected $reportetiempoMapper;
    protected $clientesMapper;
    protected $actividadMapper;
    protected $l10n;
    private $config;
    private $clientService;
    private $subAdmin;
    private $urlGenerator;
	private $mailer;
	private INotificationManager $notificationManager;

	public function __construct(
		IRequest $request,
		IUserSession $userSession,
		IUserManager $userManager,
		empleadosMapper $empleadosMapper,
		reportetiempoMapper $reportetiempoMapper,
		configuracionesMapper $configuracionesMapper,
		clientesMapper $clientesMapper,
		actividadMapper $actividadMapper,
		IL10N $l10n,
		IConfig $config,
		IGroupManager $groupManager,
		IURLGenerator $urlGenerator,
		IClientService $clientService,
		IMailer $mailer,
		ISubAdmin $subAdmin,
		INotificationManager $notificationManager,
	) {
		parent::__construct(
			Application::APP_ID,
			$request,
			$userSession,
			$groupManager,
			$empleadosMapper,
			$configuracionesMapper,
		);

		$this->userSession = $userSession;
		$this->userManager = $userManager;
		$this->empleadosMapper = $empleadosMapper;
		$this->reportetiempoMapper = $reportetiempoMapper;
		$this->configuracionesMapper = $configuracionesMapper;
		$this->clientesMapper = $clientesMapper;
		$this->actividadMapper = $actividadMapper;
		$this->l10n = $l10n;
		$this->groupManager = $groupManager;
		$this->config = $config;
		$this->urlGenerator = $urlGenerator;
		$this->clientService = $clientService;
		$this->subAdmin = $subAdmin;
		$this->mailer = $mailer;
		$this->notificationManager = $notificationManager;
	}

	/**
	 * Obtiene todos los reportes de tiempo.
	 */
	#[UseSession]
	#[NoAdminRequired]
	public function GetReportes(): DataResponse {
		$this->checkAccess(['admin', 'empleados', 'recursos_humanos']);

		return new DataResponse(
			$this->reportetiempoMapper->findAll(),
			Http::STATUS_OK
		);
	}

	/**
	 * Obtiene reportes de tiempo por empleado.
	 *
	 * Si no se manda id, obtiene reportes del empleado actual.
	 */
	#[UseSession]
	#[NoAdminRequired]
	public function findById($id = null, $periodo_inicio = null, $periodo_fin = null, $anio = null): DataResponse {
		$this->checkAccess(['admin', 'recursos_humanos', 'empleados']);

		if ($id !== null) {
			return new DataResponse(
				$this->reportetiempoMapper->findById(
					(int)$id,
					0,
					0,
					$periodo_inicio,
					$periodo_fin,
					$anio
				),
				Http::STATUS_OK
			);
		}

		$empleado = $this->empleadosMapper->GetMyEmployeeInfo(
			$this->userSession->getUser()->getUID()
		);

		return new DataResponse(
			$this->reportetiempoMapper->findById(
				(int)$empleado[0]['Id_empleados'],
				0,
				0,
				$periodo_inicio,
				$periodo_fin,
				$anio
			),
			Http::STATUS_OK
		);
	}

	/**
	 * Obtiene los reportes del empleado actual.
	 */
	#[UseSession]
	#[NoAdminRequired]
	public function GetReportesAll($periodo_inicio = null, $periodo_fin = null, $anio = null): DataResponse {
		$this->checkAccess(['admin', 'recursos_humanos', 'empleados']);

		$empleado = $this->empleadosMapper->GetMyEmployeeInfo(
			$this->userSession->getUser()->getUID()
		);

		return new DataResponse(
			$this->reportetiempoMapper->findById(
				(int)$empleado[0]['Id_empleados'],
				0,
				0,
				$periodo_inicio,
				$periodo_fin,
				$anio
			),
			Http::STATUS_OK
		);
	}

	/**
	 * Elimina un reporte de tiempo.
	 */
	#[UseSession]
	#[NoAdminRequired]
	public function deleteReport($id): DataResponse {
		$this->checkAccess(['admin', 'recursos_humanos', 'empleados']);

		$this->reportetiempoMapper->deleteById((int)$id);

		return new DataResponse('ok', Http::STATUS_OK);
	}

	/**
	 * Modifica un reporte de tiempo.
	 */
	#[UseSession]
	#[NoAdminRequired]
	public function modificarReporte(
		int $id_reporte,
		$id_actividad,
		$tiemporegistrado,
		$descripcion,
		string $tipo,
		$fecharegistrada
	): DataResponse {
		$this->checkAccess(['admin', 'recursos_humanos', 'empleados']);

		$empleado = $this->empleadosMapper->GetMyEmployeeInfo(
			$this->userSession->getUser()->getUID()
		);

		$tipo = strtolower(trim($tipo));

		if ($tipo === 'horas') {
			$tiemporegistrado *= 60;
		}

		$fecha = (new \DateTimeImmutable($fecharegistrada))->format('Y-m-d');

		$this->reportetiempoMapper->updateReporte(
			$id_reporte,
			$id_actividad,
			(int)$empleado[0]['Id_empleados'],
			$descripcion,
			$tiemporegistrado,
			$fecha
		);

		return new DataResponse('ok', Http::STATUS_OK);
	}

	/**
	 * Crea un nuevo reporte de tiempo.
	 */
	#[UseSession]
	#[NoAdminRequired]
	public function crearReporte(
		$id_cliente,
		$id_actividad,
		$tiemporegistrado,
		$descripcion,
		string $tipo,
		$time
	): DataResponse {
		$this->checkAccess(['admin', 'recursos_humanos', 'empleados']);

		$empleado = $this->empleadosMapper->GetMyEmployeeInfo(
			$this->userSession->getUser()->getUID()
		);

		$fecha = (new \DateTimeImmutable($time))->format('Y-m-d');

		$tipo = strtolower(trim($tipo));

		if ($tipo === 'horas') {
			$tiemporegistrado *= 60;
		}

		$reportetiempo = new reportetiempo();
		$reportetiempo->setidEmpleado((int)$empleado[0]['Id_empleados']);
		$reportetiempo->setidCliente((int)$id_cliente);
		$reportetiempo->setidActividad((int)$id_actividad);
		$reportetiempo->settiempoRegistrado((float)$tiemporegistrado);
		$reportetiempo->setfechaRegistro($fecha);
		$reportetiempo->setdescripcion((string)$descripcion);

		$this->reportetiempoMapper->insert($reportetiempo);
		$this->clearReporteTiempoNotification(
		$this->userSession->getUser()->getUID(),
			$fecha
		);
		return new DataResponse('ok', Http::STATUS_OK);
	}

	/**
	 * Obtiene empleados visibles con total de tiempo reportado en el periodo.
	 *
	 * Este endpoint sirve para la lista lateral de empleados.
	 */
	#[UseSession]
	#[NoAdminRequired]
	public function GetEmpleadosReports($periodo_inicio = null, $periodo_fin = null, $anio = null): DataResponse {
		$this->checkAccess(['admin', 'recursos_humanos', 'empleados']);

		return new DataResponse(
			$this->getEmpleadosReportsData($periodo_inicio, $periodo_fin, $anio),
			Http::STATUS_OK
		);
	}

	/**
	 * Resumen general administrativo del periodo.
	 *
	 * Este endpoint sirve para dashboard general:
	 * KPIs + gráficas agregadas.
	 */
	#[UseSession]
	#[NoAdminRequired]
	public function GetAdminReportsSummary($periodo_inicio = null, $periodo_fin = null, $anio = null): DataResponse {
		$this->checkAccess(['admin', 'recursos_humanos', 'empleados']);

		$resumen = $this->reportetiempoMapper->getResumenGeneral(
			$periodo_inicio,
			$periodo_fin,
			$anio
		);

		$empleadosData = $this->getEmpleadosReportsData(
			$periodo_inicio,
			$periodo_fin,
			$anio
		);

		$costoTotal = 0.0;

		foreach ($empleadosData as $empleado) {
			$totalMinutos = (float)($empleado['total_tiempo_registrado'] ?? 0);
			$sueldoHora = (float)($empleado['Sueldo'] ?? 0);

			$costoTotal += ($totalMinutos / 60) * $sueldoHora;
		}

		$resumen['costo_total'] = $costoTotal;

		return new DataResponse([
			'kpis' => $resumen,
			'empleados' => $empleadosData,
			'graficas' => [
				'horas_por_empleado' => $this->reportetiempoMapper->getHorasPorEmpleado(
					$periodo_inicio,
					$periodo_fin,
					$anio
				),
				'horas_por_proyecto' => $this->reportetiempoMapper->getHorasPorProyecto(
					$periodo_inicio,
					$periodo_fin,
					$anio
				),
				'horas_por_actividad' => $this->reportetiempoMapper->getHorasPorActividad(
					$periodo_inicio,
					$periodo_fin,
					$anio
				),
				'horas_por_dia' => $this->reportetiempoMapper->getHorasPorDia(
					$periodo_inicio,
					$periodo_fin,
					$anio
				),
				'reportes_por_dia' => $this->reportetiempoMapper->getReportesPorDia(
					$periodo_inicio,
					$periodo_fin,
					$anio
				),
				'proyecto_vs_actividad' => $this->reportetiempoMapper->getProyectoVsActividad(
					$periodo_inicio,
					$periodo_fin,
					$anio
				),
			],
		], Http::STATUS_OK);
	}

	/**
	 * Exporta reportes de tiempo a XLSX.
	 */
	#[UseSession]
	#[NoAdminRequired]
	public function ExportarReportes($periodo_inicio = null, $periodo_fin = null, $anio = null): DataDownloadResponse {
		$this->checkAccess(['admin', 'recursos_humanos']);

		$reportes = $this->reportetiempoMapper->findAll(
			0,
			0,
			$periodo_inicio,
			$periodo_fin,
			$anio
		);

		$books = [[
			'id_reporte',
			'id_cliente',
			'id_actividad',
			'id_empleado',
			'descripcion',
			'tiempo_registrado',
			'fecha_registro',
			'created_at',
			'updated_at',
		]];

		foreach ($reportes as $reporte) {
			$row = $reporte->read();

			$books[] = [
				$row['id_reporte'] ?? '',
				$row['id_cliente'] ?? '',
				$row['id_actividad'] ?? '',
				$row['id_empleado'] ?? '',
				$row['descripcion'] ?? '',
				$row['tiempo_registrado'] ?? '',
				$row['fecha_registro'] ?? '',
				$row['created_at'] ?? '',
				$row['updated_at'] ?? '',
			];
		}

		$tmpFile = tempnam(sys_get_temp_dir(), 'reportetiempo_');

		\Shuchkin\SimpleXLSXGen::fromArray($books)->saveAs($tmpFile);

		$content = file_get_contents($tmpFile);
		@unlink($tmpFile);

		return new DataDownloadResponse(
			$content ?: '',
			'reportetiempo.xlsx',
			'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
		);
	}

	/**
	 * Importa reportes desde XLSX.
	 *
	 * Ojo: este método asume columnas:
	 * id_reporte, id_cliente, id_actividad, id_empleado, descripcion,
	 * tiempo_registrado, fecha_registro.
	 */
	#[UseSession]
	#[NoAdminRequired]
	public function ImportarReportes(): DataResponse {
		$this->checkAccess(['admin', 'recursos_humanos']);

		$file = $this->getUploadedFile('ReportesfileXLSX');

		$xlsx = \Shuchkin\SimpleXLSX::parse($file['tmp_name']);

		if (!$xlsx) {
			return new DataResponse([
				'status' => 'error',
				'message' => 'No se pudo leer el archivo XLSX.',
			], Http::STATUS_BAD_REQUEST);
		}

		$rows = $xlsx->rows();

		if (count($rows) <= 1) {
			return new DataResponse([
				'status' => 'error',
				'message' => 'El archivo no contiene reportes.',
			], Http::STATUS_BAD_REQUEST);
		}

		$insertados = 0;

		foreach ($rows as $index => $row) {
			if ($index === 0) {
				continue;
			}

			if (empty($row[1]) || empty($row[2]) || empty($row[3])) {
				continue;
			}

			$reportetiempo = new reportetiempo();
			$reportetiempo->setidCliente((int)$row[1]);
			$reportetiempo->setidActividad((int)$row[2]);
			$reportetiempo->setidEmpleado((int)$row[3]);
			$reportetiempo->setdescripcion((string)($row[4] ?? ''));
			$reportetiempo->settiempoRegistrado((float)($row[5] ?? 0));

			$fecha = !empty($row[6])
				? (new \DateTimeImmutable((string)$row[6]))->format('Y-m-d')
				: date('Y-m-d');

			$reportetiempo->setfechaRegistro($fecha);

			$this->reportetiempoMapper->insert($reportetiempo);

			$insertados++;
		}

		return new DataResponse([
			'status' => 'ok',
			'insertados' => $insertados,
		], Http::STATUS_OK);
	}

	/**
	 * Obtiene un archivo subido y maneja posibles errores.
	 */
	private function getUploadedFile(string $key): array {
		$file = $this->request->getUploadedFile($key);

		if (empty($file) || ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
			throw new UploadException(
				$this->l10n->t('Error en la subida del archivo.')
			);
		}

		return $file;
	}

	/**
	 * Construye la lista de empleados visibles con total de minutos reportados.
	 */
	private function getEmpleadosReportsData($periodo_inicio = null, $periodo_fin = null, $anio = null): array {
		$user = $this->userSession->getUser();

		if ($user === null) {
			return [];
		}

		$userId = $user->getUID();
		$boss = $this->empleadosMapper->GetMyEmployeeInfo($userId);
		$equipoEmpleado = $this->empleadosMapper->GetSubordinates($userId);

		if (!is_array($equipoEmpleado)) {
			$equipoEmpleado = [];
		}

		if (!empty($boss)) {
			// Si viene como lista, toma el primer registro
			$bossRow = isset($boss[0]) && is_array($boss[0])
				? $boss[0]
				: $boss;

			$bossFiltrado = [
				'Id_empleados' => $bossRow['Id_empleados'] ?? $bossRow['id_empleados'] ?? null,
				'Id_user'      => $bossRow['Id_user'] ?? $bossRow['id_user'] ?? null,
				'displayname'  => $bossRow['displayname'] ?? $bossRow['Id_user'] ?? '',
				'Sueldo'       => $bossRow['Sueldo'] ?? $bossRow['sueldo'] ?? 0,
			];

			if (!empty($bossFiltrado['Id_empleados'])) {
				array_unshift($equipoEmpleado, $bossFiltrado);
			}
		}

		$empleadosData = [];

		foreach ($equipoEmpleado as $empleado) {
			$idEmpleado = $empleado['id_empleados'] ?? $empleado['Id_empleados'] ?? null;

			if (empty($idEmpleado)) {
				continue;
			}

			$total = 0.0;

			$reportes = $this->reportetiempoMapper->findById(
				(int)$idEmpleado,
				0,
				0,
				$periodo_inicio,
				$periodo_fin,
				$anio
			);

			foreach ($reportes as $item) {
				$total += (float)($item['tiempo_registrado'] ?? 0);
			}

			$horasReportadas = $total / 60;
			$sueldo = (float)($empleado['Sueldo'] ?? $empleado['sueldo'] ?? 0);

			$empleado['total_tiempo_registrado'] = $total;
			$empleado['horas_reportadas'] = $horasReportadas;
			$empleado['costo_total'] = $horasReportadas * $sueldo;

			$empleadosData[] = $empleado;
		}

		return $empleadosData;
	}

	#[UseSession]
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function estadoReporteHoy(): DataResponse {
		$this->checkAccess(['admin', 'recursos_humanos', 'empleados']);

		$user = $this->userSession->getUser();

		if ($user === null) {
			return new DataResponse([
				'error' => 'Usuario no autenticado',
			], Http::STATUS_UNAUTHORIZED);
		}

		$userId = $user->getUID();
		$fecha = date('Y-m-d');

		$empleado = $this->empleadosMapper->GetMyEmployeeInfo($userId);

		if (empty($empleado)) {
			return new DataResponse([
				'fecha' => $fecha,
				'registros' => 0,
				'minutos_reportados' => 0,
				'horas_reportadas' => 0,
				'estado' => 'sin_empleado',
			], Http::STATUS_OK);
		}

		$empleadoRow = isset($empleado[0]) && is_array($empleado[0])
			? $empleado[0]
			: $empleado;

		$idEmpleado = (int)($empleadoRow['Id_empleados'] ?? $empleadoRow['id_empleados'] ?? 0);

		if ($idEmpleado <= 0) {
			return new DataResponse([
				'fecha' => $fecha,
				'registros' => 0,
				'minutos_reportados' => 0,
				'horas_reportadas' => 0,
				'estado' => 'sin_empleado',
			], Http::STATUS_OK);
		}

		$resumen = $this->reportetiempoMapper->getResumenDiaByEmpleado($idEmpleado, $fecha);

		$registros = (int)($resumen['registros'] ?? 0);
		$minutos = (float)($resumen['minutos_reportados'] ?? 0);
		$horas = $minutos / 60;

		$estado = $registros > 0 ? 'reportado' : 'pendiente';

		return new DataResponse([
			'fecha' => $fecha,
			'registros' => $registros,
			'minutos_reportados' => $minutos,
			'horas_reportadas' => round($horas, 2),
			'estado' => $estado,
		], Http::STATUS_OK);
	}
	
	/**
	 * Reporte de cumplimiento diario de reportes de tiempo.
	 *
	 * Muestra el estado del jefe actual y sus subordinados.
	 */
	#[UseSession]
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function GetCumplimientoReportesHoy($fecha = null): DataResponse {
		$this->checkAccess(['admin', 'recursos_humanos', 'empleados']);

		$user = $this->userSession->getUser();

		if ($user === null) {
			return new DataResponse([
				'error' => 'Usuario no autenticado',
			], Http::STATUS_UNAUTHORIZED);
		}

		$tz = new \DateTimeZone('America/Mexico_City');

		if (empty($fecha)) {
			$fecha = (new \DateTimeImmutable('now', $tz))->format('Y-m-d');
		} else {
			$fecha = (new \DateTimeImmutable((string)$fecha, $tz))->format('Y-m-d');
		}

		$empleados = $this->getEmpleadosVisiblesBasico();

		$data = [];

		$totalEmpleados = 0;
		$totalReportados = 0;
		$totalPendientes = 0;
		$totalMinutos = 0.0;
		$totalRegistros = 0;

		foreach ($empleados as $empleado) {
			$idEmpleado = $empleado['id_empleados'] ?? $empleado['Id_empleados'] ?? null;

			if (empty($idEmpleado)) {
				continue;
			}

			$resumen = $this->reportetiempoMapper->getResumenDiaByEmpleado(
				(int)$idEmpleado,
				$fecha
			);

			$registros = (int)($resumen['registros'] ?? 0);
			$minutos = (float)($resumen['minutos_reportados'] ?? 0);
			$horas = $minutos / 60;

			$estado = $registros > 0 ? 'reportado' : 'pendiente';

			$totalEmpleados++;
			$totalRegistros += $registros;
			$totalMinutos += $minutos;

			if ($estado === 'reportado') {
				$totalReportados++;
			} else {
				$totalPendientes++;
			}

			$data[] = [
				'id_empleado' => (int)$idEmpleado,
				'id_user' => $empleado['id_user'] ?? $empleado['Id_user'] ?? null,
				'displayname' => $empleado['displayname']
					?? $empleado['DisplayName']
					?? $empleado['Id_user']
					?? $empleado['id_user']
					?? 'Empleado',
				'registros' => $registros,
				'minutos_reportados' => $minutos,
				'horas_reportadas' => round($horas, 2),
				'estado' => $estado,
			];
		}

		return new DataResponse([
			'fecha' => $fecha,
			'kpis' => [
				'total_empleados' => $totalEmpleados,
				'reportados' => $totalReportados,
				'pendientes' => $totalPendientes,
				'total_registros' => $totalRegistros,
				'total_minutos' => $totalMinutos,
				'total_horas' => round($totalMinutos / 60, 2),
				'porcentaje_cumplimiento' => $totalEmpleados > 0
					? round(($totalReportados / $totalEmpleados) * 100, 2)
					: 0,
			],
			'empleados' => $data,
		], Http::STATUS_OK);
	}

	/**
	 * Obtiene el jefe actual y sus subordinados sin calcular tiempos.
	 */
	private function getEmpleadosVisiblesBasico(): array {
		$user = $this->userSession->getUser();

		if ($user === null) {
			return [];
		}

		$userId = $user->getUID();

		$boss = $this->empleadosMapper->GetMyEmployeeInfo($userId);
		$equipoEmpleado = $this->empleadosMapper->GetSubordinates($userId);

		if (!is_array($equipoEmpleado)) {
			$equipoEmpleado = [];
		}

		if (!empty($boss)) {
			$bossRow = isset($boss[0]) && is_array($boss[0])
				? $boss[0]
				: $boss;

			$bossFiltrado = [
				'Id_empleados' => $bossRow['Id_empleados'] ?? $bossRow['id_empleados'] ?? null,
				'Id_user' => $bossRow['Id_user'] ?? $bossRow['id_user'] ?? null,
				'displayname' => $bossRow['displayname']
					?? $bossRow['DisplayName']
					?? $bossRow['Id_user']
					?? $bossRow['id_user']
					?? '',
				'Sueldo' => $bossRow['Sueldo'] ?? $bossRow['sueldo'] ?? 0,
			];

			if (!empty($bossFiltrado['Id_empleados'])) {
				array_unshift($equipoEmpleado, $bossFiltrado);
			}
		}

		return $equipoEmpleado;
	}

	/**
	 * Envía recordatorio manual a empleados pendientes de reportar en la fecha indicada.
	 */
	#[UseSession]
	#[NoAdminRequired]
	public function EnviarRecordatoriosPendientesHoy($fecha = null): DataResponse {
		$this->checkAccess(['admin', 'recursos_humanos', 'empleados']);

		$user = $this->userSession->getUser();

		if ($user === null) {
			return new DataResponse([
				'error' => 'Usuario no autenticado',
			], Http::STATUS_UNAUTHORIZED);
		}

		$tz = new \DateTimeZone('America/Mexico_City');

		if (empty($fecha)) {
			$fecha = (new \DateTimeImmutable('now', $tz))->format('Y-m-d');
		} else {
			$fecha = (new \DateTimeImmutable((string)$fecha, $tz))->format('Y-m-d');
		}

		$empleados = $this->getEmpleadosVisiblesBasico();
		$quickReportUrl = $this->urlGenerator->linkToRouteAbsolute('empleados.page.index') . '#/quick-report';

		$enviados = [];
		$omitidos = [];

		foreach ($empleados as $empleado) {
			$idEmpleado = $empleado['id_empleados'] ?? $empleado['Id_empleados'] ?? null;
			$uid = $empleado['id_user'] ?? $empleado['Id_user'] ?? null;
			$displayname = $empleado['displayname']
				?? $empleado['DisplayName']
				?? $uid
				?? 'Empleado';

			if (empty($idEmpleado) || empty($uid)) {
				$omitidos[] = [
					'uid' => $uid,
					'motivo' => 'Empleado inválido',
				];
				continue;
			}

			$resumen = $this->reportetiempoMapper->getResumenDiaByEmpleado(
				(int)$idEmpleado,
				$fecha
			);

			$registros = (int)($resumen['registros'] ?? 0);

			if ($registros > 0) {
				$omitidos[] = [
					'uid' => $uid,
					'motivo' => 'Ya tiene reportes',
				];
				continue;
			}

			$ultimoRecordatorio = $this->config->getUserValue(
				(string)$uid,
				Application::APP_ID,
				'ultimo_recordatorio_reporte_tiempo',
				''
			);

			if ($ultimoRecordatorio === $fecha) {
				$omitidos[] = [
					'uid' => $uid,
					'motivo' => 'Ya se envió recordatorio hoy',
				];
				continue;
			}

			$nextcloudUser = $this->userManager->get((string)$uid);

			if ($nextcloudUser === null) {
				$omitidos[] = [
					'uid' => $uid,
					'motivo' => 'Usuario Nextcloud no encontrado',
				];
				continue;
			}

			$email = $nextcloudUser->getEMailAddress();

			if (empty($email)) {
				$omitidos[] = [
					'uid' => $uid,
					'motivo' => 'Sin correo',
				];
				continue;
			}

			try {
				$message = $this->mailer->createMessage();

				$message->setTo([
					$email => $nextcloudUser->getDisplayName() ?: (string)$uid,
				]);

				$message->setSubject('Recordatorio: registra tu tiempo');

				$body = implode("\n", [
					'Hola ' . ($nextcloudUser->getDisplayName() ?: $displayname) . ',',
					'',
					'Aún no tienes reportes de tiempo registrados para la fecha ' . $fecha . '.',
					'',
					'Puedes registrarlo aquí:',
					$quickReportUrl,
					'',
					'Este es un recordatorio enviado desde el reporte de cumplimiento.',
				]);

				$message->setPlainBody($body);

				$this->mailer->send($message);

				$this->config->setUserValue(
					(string)$uid,
					Application::APP_ID,
					'ultimo_recordatorio_reporte_tiempo',
					$fecha
				);

				$enviados[] = [
					'uid' => $uid,
					'email' => $email,
				];
			} catch (\Throwable $e) {
				$omitidos[] = [
					'uid' => $uid,
					'motivo' => 'Error enviando correo: ' . $e->getMessage(),
				];
			}
		}

		return new DataResponse([
			'fecha' => $fecha,
			'enviados' => count($enviados),
			'omitidos' => count($omitidos),
			'detalle_enviados' => $enviados,
			'detalle_omitidos' => $omitidos,
		], Http::STATUS_OK);
	}
	private function clearReporteTiempoNotification(string $uid, string $fecha): void {
		$notification = $this->notificationManager->createNotification();

		$notification
			->setApp(Application::APP_ID)
			->setUser($uid)
			->setObject('reporte_tiempo', $fecha);

		$this->notificationManager->markProcessed($notification);
	}
}