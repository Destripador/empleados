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

require_once __DIR__ . '/SimpleXLSXGen.php';
require_once __DIR__ . '/SimpleXLSX.php';

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

		$denied = $this->denyIfNoAdminReportsAccess();

		if ($denied !== null) {
			return $denied;
		}

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
			$denied = $this->denyIfNoAdminReportsAccess();

			if ($denied !== null) {
				return $denied;
			}

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

		$denied = $this->denyIfNoAdminReportsAccess();

		if ($denied !== null) {
			return $denied;
		}

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

		$denied = $this->denyIfNoAdminReportsAccess();

		if ($denied !== null) {
			return $denied;
		}

		$empleadosData = $this->getEmpleadosReportsData(
			$periodo_inicio,
			$periodo_fin,
			$anio
		);

		$idEmpleadosVisibles = array_values(array_unique(array_filter(array_map(
			static function ($empleado) {
				return (int)($empleado['id_empleados'] ?? $empleado['Id_empleados'] ?? 0);
			},
			$empleadosData
		))));

		$resumen = $this->reportetiempoMapper->getResumenGeneral(
			$periodo_inicio,
			$periodo_fin,
			$anio,
			$idEmpleadosVisibles
		);

		$costoTotal = 0.0;

		foreach ($empleadosData as $empleado) {
			$totalMinutos = (float)($empleado['total_tiempo_registrado'] ?? 0);
			$sueldoHora = (float)($empleado['Sueldo'] ?? $empleado['sueldo'] ?? 0);

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
					$anio,
					$idEmpleadosVisibles
				),
				'horas_por_proyecto' => $this->reportetiempoMapper->getHorasPorProyecto(
					$periodo_inicio,
					$periodo_fin,
					$anio,
					$idEmpleadosVisibles
				),
				'horas_por_actividad' => $this->reportetiempoMapper->getHorasPorActividad(
					$periodo_inicio,
					$periodo_fin,
					$anio,
					$idEmpleadosVisibles
				),
				'horas_por_dia' => $this->reportetiempoMapper->getHorasPorDia(
					$periodo_inicio,
					$periodo_fin,
					$anio,
					$idEmpleadosVisibles
				),
				'reportes_por_dia' => $this->reportetiempoMapper->getReportesPorDia(
					$periodo_inicio,
					$periodo_fin,
					$anio,
					$idEmpleadosVisibles
				),
				'proyecto_vs_actividad' => $this->reportetiempoMapper->getProyectoVsActividad(
					$periodo_inicio,
					$periodo_fin,
					$anio,
					$idEmpleadosVisibles
				),
			],
		], Http::STATUS_OK);
	}

	/**
	 * Exporta reportes de tiempo a XLSX con diseño usando SimpleXLSXGen.
	 */
	#[UseSession]
	#[NoAdminRequired]
	public function ExportarReportes($periodo_inicio = null, $periodo_fin = null, $anio = null) {
		$this->checkAccess(['admin', 'recursos_humanos', 'empleados']);

		$denied = $this->denyIfNoAdminReportsAccess();

		if ($denied !== null) {
			return $denied;
		}

		$empleadosData = $this->getEmpleadosReportsData(
			$periodo_inicio,
			$periodo_fin,
			$anio
		);

		$idEmpleadosVisibles = array_values(array_unique(array_filter(array_map(
			static function ($empleado) {
				return (int)($empleado['id_empleados'] ?? $empleado['Id_empleados'] ?? $empleado['id'] ?? 0);
			},
			$empleadosData
		))));

		$resumen = $this->reportetiempoMapper->getResumenGeneral(
			$periodo_inicio,
			$periodo_fin,
			$anio,
			$idEmpleadosVisibles
		);

		$costoTotal = 0.0;

		foreach ($empleadosData as $empleado) {
			$totalMinutos = (float)($empleado['total_tiempo_registrado'] ?? 0);
			$sueldoHora = (float)($empleado['Sueldo'] ?? $empleado['sueldo'] ?? 0);

			$costoTotal += ($totalMinutos / 60) * $sueldoHora;
		}

		$resumen['costo_total'] = $costoTotal;

		$resumenSheet = $this->buildResumenReportesXlsx(
			$resumen,
			$empleadosData,
			$periodo_inicio,
			$periodo_fin,
			$anio
		);

		$detalleSheet = $this->buildDetalleReportesXlsx(
			$empleadosData,
			$periodo_inicio,
			$periodo_fin,
			$anio
		);

		$xlsx = \Shuchkin\SimpleXLSXGen::fromArray($resumenSheet, 'Resumen')
			->addSheet($detalleSheet, 'Detalle')
			->setDefaultFont('Arial')
			->setDefaultFontSize(10)
			->setColWidth(1, 30)
			->setColWidth(2, 22)
			->setColWidth(3, 16)
			->setColWidth(4, 16)
			->setColWidth(5, 18)
			->setColWidth(6, 18)
			->setColWidth(7, 18)
			->setColWidth(8, 35)
			->mergeCells('A1:H1')
			->mergeCells('A2:H2')
			->autoFilter('A6:H2000')
			->freezePanes('A7');

		$tmpFile = tempnam(sys_get_temp_dir(), 'reportetiempo_') . '.xlsx';

		$xlsx->saveAs($tmpFile);

		$content = file_get_contents($tmpFile);
		@unlink($tmpFile);

		$filename = 'reporte_tiempos_' . date('Ymd_His') . '.xlsx';

		return new DataDownloadResponse(
			$content ?: '',
			$filename,
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

		$denied = $this->denyIfNoAdminReportsAccess();

		if ($denied !== null) {
			return $denied;
		}

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

		$denied = $this->denyIfNoAdminReportsAccess();

		if ($denied !== null) {
			return $denied;
		}

		$user = $this->userSession->getUser();

		if ($user === null) {
			return new DataResponse([
				'error' => 'Usuario no autenticado',
			], Http::STATUS_UNAUTHORIZED);
		}

		// resto igual...
	}

	private function clearReporteTiempoNotification(string $uid, string $fecha): void {
			$notification = $this->notificationManager->createNotification();

			$notification
				->setApp(Application::APP_ID)
				->setUser($uid)
				->setObject('reporte_tiempo', $fecha);

			$this->notificationManager->markProcessed($notification);
		}
		private function canAccessAdminReports(): bool {
		$user = $this->userSession->getUser();

		if ($user === null) {
			return false;
		}

		$uid = $user->getUID();

		if ($this->groupManager->isAdmin($uid)) {
			return true;
		}

		$groupId = trim($this->config->getAppValue(
			Application::APP_ID,
			'reportes_admin_reports_group',
			'recursos_humanos'
		));

		if ($groupId === '') {
			return false;
		}

		$group = $this->groupManager->get($groupId);

		if ($group === null) {
			return false;
		}

		return $group->inGroup($user);
	}
	
	private function denyIfNoAdminReportsAccess(): ?DataResponse {
		if (!$this->canAccessAdminReports()) {
			return new DataResponse([
				'error' => 'No tienes permisos para acceder a reportes administrativos.',
			], Http::STATUS_FORBIDDEN);
		}

		return null;
	}
	

	private function buildResumenReportesXlsx(
		array $resumen,
		array $empleadosData,
		$periodoInicio,
		$periodoFin,
		$anio
	): array {
		$rows = [];

		$rows[] = [
			'<style bgcolor="#1F2937" color="#FFFFFF" font-size="18"><center><b>Reporte administrativo de tiempos</b></center></style>',
			null,
			null,
			null,
			null,
			null,
			null,
			null,
		];

		$rows[] = [
			'<style bgcolor="#E5E7EB" color="#374151"><center>'
			. $this->escapeXlsxText($this->getPeriodoLabel($periodoInicio, $periodoFin, $anio))
			. '</center></style>',
			null,
			null,
			null,
			null,
			null,
			null,
			null,
		];

		$rows[] = ['', '', '', '', '', '', '', ''];

		$rows[] = [
			$this->kpiLabel('Horas reportadas'),
			$this->kpiValue(number_format((float)($resumen['horas_reportadas'] ?? 0), 2)),
			$this->kpiLabel('Costo total'),
			$this->moneyCell((float)($resumen['costo_total'] ?? 0)),
			$this->kpiLabel('Total reportes'),
			$this->kpiValue((string)((int)($resumen['total_reportes'] ?? 0))),
			$this->kpiLabel('Empleados con reportes'),
			$this->kpiValue((string)((int)($resumen['empleados_con_reportes'] ?? 0))),
		];

		$rows[] = ['', '', '', '', '', '', '', ''];

		$rows[] = [
			$this->headerCell('Empleado'),
			$this->headerCell('Usuario'),
			$this->headerCell('Minutos'),
			$this->headerCell('Horas'),
			$this->headerCell('Sueldo/hora'),
			$this->headerCell('Costo'),
			$this->headerCell('Estado'),
			$this->headerCell('Observaciones'),
		];

		foreach ($empleadosData as $empleado) {
			$totalMinutos = (float)($empleado['total_tiempo_registrado'] ?? 0);
			$horas = $totalMinutos / 60;
			$sueldo = (float)($empleado['Sueldo'] ?? $empleado['sueldo'] ?? 0);
			$costo = $horas * $sueldo;

			$rows[] = [
				$this->bodyCell((string)($empleado['displayname'] ?? $empleado['name'] ?? 'Empleado')),
				$this->bodyCell((string)($empleado['Id_user'] ?? $empleado['id_user'] ?? '')),
				$this->numberCell($totalMinutos),
				$this->numberCell($horas),
				$this->moneyCell($sueldo),
				$this->moneyCell($costo),
				$totalMinutos > 0
					? '<style bgcolor="#DCFCE7" color="#166534" border="#BBF7D0"><center><b>Con reportes</b></center></style>'
					: '<style bgcolor="#FEE2E2" color="#991B1B" border="#FECACA"><center><b>Sin reportes</b></center></style>',
				$this->bodyCell(''),
			];
		}

		return $rows;
	}

	private function buildDetalleReportesXlsx(
		array $empleadosData,
		$periodoInicio,
		$periodoFin,
		$anio
	): array {
		$rows = [];

		$clientesMap = $this->getClientesMap();
		$actividadesMap = $this->getActividadesMap();

		$rows[] = [
			'<style bgcolor="#1F2937" color="#FFFFFF" font-size="18"><center><b>Detalle de reportes de tiempo</b></center></style>',
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
		];

	// resto igual...

		$rows[] = [
			'<style bgcolor="#E5E7EB" color="#374151"><center>'
			. $this->escapeXlsxText($this->getPeriodoLabel($periodoInicio, $periodoFin, $anio))
			. '</center></style>',
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
		];

		$rows[] = ['', '', '', '', '', '', '', '', '', ''];

		$rows[] = [
			$this->headerCell('Empleado'),
			$this->headerCell('Cliente / proyecto'),
			$this->headerCell('Actividad'),
			$this->headerCell('Descripción'),
			$this->headerCell('Minutos'),
			$this->headerCell('Horas'),
			$this->headerCell('Costo'),
			$this->headerCell('Fecha'),
			$this->headerCell('Creado'),
		];

		foreach ($empleadosData as $empleado) {
			$idEmpleado = (int)($empleado['id_empleados'] ?? $empleado['Id_empleados'] ?? $empleado['id'] ?? 0);

			if ($idEmpleado <= 0) {
				continue;
			}

			$nombreEmpleado = $empleado['displayname']
				?? $empleado['name']
				?? $empleado['Id_user']
				?? $empleado['id_user']
				?? 'Empleado';

			$sueldoHora = (float)($empleado['Sueldo'] ?? $empleado['sueldo'] ?? 0);

			$reportes = $this->reportetiempoMapper->findById(
				$idEmpleado,
				0,
				0,
				$periodoInicio,
				$periodoFin,
				$anio
			);

			foreach ($reportes as $reporte) {
				$minutos = (float)($reporte['tiempo_registrado'] ?? 0);
				$horas = $minutos / 60;
				$costo = $horas * $sueldoHora;

				$idCliente = (int)($reporte['id_cliente'] ?? 0);
				$idActividad = (int)($reporte['id_actividad'] ?? 0);

				$nombreCliente = $reporte['cliente']
					?? $reporte['nombre_cliente']
					?? $reporte['cliente_nombre']
					?? $clientesMap[$idCliente]
					?? ('Cliente #' . $idCliente);

				$nombreActividad = $reporte['actividad']
					?? $reporte['nombre_actividad']
					?? $reporte['actividad_nombre']
					?? $actividadesMap[$idActividad]
					?? ('Actividad #' . $idActividad);

				$rows[] = [
					$this->bodyCell((string)$nombreEmpleado),
					$this->bodyCell((string)$nombreCliente),
					$this->bodyCell((string)$nombreActividad),
					$this->wrapCell((string)($reporte['descripcion'] ?? '')),
					$this->numberCell($minutos),
					$this->numberCell($horas),
					$this->moneyCell($costo),
					$this->bodyCell((string)($reporte['fecha_registro'] ?? '')),
					$this->bodyCell((string)($reporte['created_at'] ?? '')),
				];
			}
		}

		return $rows;
	}

	private function headerCell(string $text): string {
		return '<style bgcolor="#334155" color="#FFFFFF" border="#CBD5E1"><center><b>'
			. $this->escapeXlsxText($text)
			. '</b></center></style>';
	}

	private function kpiLabel(string $text): string {
		return '<style bgcolor="#EEF2FF" color="#374151" border="#CBD5E1"><center><b>'
			. $this->escapeXlsxText($text)
			. '</b></center></style>';
	}

	private function kpiValue(string $text): string {
		return '<style bgcolor="#FFFFFF" color="#111827" border="#CBD5E1"><center><b>'
			. $this->escapeXlsxText($text)
			. '</b></center></style>';
	}

	private function bodyCell(string $text): string {
		return '<style border="#E5E7EB">'
			. $this->escapeXlsxText($text)
			. '</style>';
	}

	private function wrapCell(string $text): string {
		return '<style border="#E5E7EB"><wraptext>'
			. $this->escapeXlsxText($text)
			. '</wraptext></style>';
	}

	private function numberCell(float $value): string {
		return '<style border="#E5E7EB" nf="#,##0.00"><right>'
			. $value
			. '</right></style>';
	}

	private function moneyCell(float $value): string {
		return '<style border="#E5E7EB" nf="$#,##0.00"><right>'
			. $value
			. '</right></style>';
	}

	private function escapeXlsxText(string $text): string {
		return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}

	private function getPeriodoLabel($periodoInicio, $periodoFin, $anio): string {
		$meses = [
			1 => 'Enero',
			2 => 'Febrero',
			3 => 'Marzo',
			4 => 'Abril',
			5 => 'Mayo',
			6 => 'Junio',
			7 => 'Julio',
			8 => 'Agosto',
			9 => 'Septiembre',
			10 => 'Octubre',
			11 => 'Noviembre',
			12 => 'Diciembre',
		];

		if (empty($periodoInicio) && empty($periodoFin) && empty($anio)) {
			return 'Periodo: todos los registros';
		}

		$inicio = $meses[(int)$periodoInicio] ?? 'Sin inicio';
		$fin = $meses[(int)$periodoFin] ?? 'Sin fin';
		$year = $anio ?: date('Y');

		return 'Periodo: ' . $inicio . ' - ' . $fin . ' (' . $year . ')';
	}
	

	private function getClientesMap(): array {
		$map = [];

		try {
			$clientes = $this->clientesMapper->findAll();
		} catch (\Throwable $e) {
			return $map;
		}

		foreach ($clientes as $cliente) {
			if (is_object($cliente) && method_exists($cliente, 'read')) {
				$cliente = $cliente->read();
			}

			if (!is_array($cliente)) {
				continue;
			}

			$id = (int)($cliente['id_cliente'] ?? $cliente['id'] ?? 0);
			$nombre = (string)($cliente['nombre'] ?? $cliente['name'] ?? '');

			if ($id > 0 && $nombre !== '') {
				$map[$id] = $nombre;
			}
		}

		return $map;
	}

	private function getActividadesMap(): array {
		$map = [];

		try {
			$actividades = $this->actividadMapper->findAll();
		} catch (\Throwable $e) {
			return $map;
		}

		foreach ($actividades as $actividad) {
			if (is_object($actividad) && method_exists($actividad, 'read')) {
				$actividad = $actividad->read();
			}

			if (!is_array($actividad)) {
				continue;
			}

			$id = (int)($actividad['id_actividad'] ?? $actividad['id'] ?? 0);
			$nombre = (string)($actividad['nombre'] ?? $actividad['name'] ?? '');

			if ($id > 0 && $nombre !== '') {
				$map[$id] = $nombre;
			}
		}

		return $map;
	}
}