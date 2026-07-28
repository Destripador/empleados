<?php

declare(strict_types=1);

namespace OCA\Empleados\Controller;

use OCA\Empleados\AppInfo\Application;
use OCA\Empleados\Db\actividadesMapper;
use OCA\Empleados\Db\clientesMapper;
use OCA\Empleados\Db\configuracionesMapper;
use OCA\Empleados\Db\empleadosMapper;
use OCA\Empleados\Db\reportetiempo;
use OCA\Empleados\Db\reportetiempoMapper;
use OCA\Empleados\Db\historialausenciasMapper;
use OCA\Empleados\Service\VacacionesCalculoService;
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

	private const ID_CLIENTE_AUSENCIA   = 99999;
    private const ID_ACTIVIDAD_CARGABLE = 99999;
	private const MAX_HORAS_PLANIFICACION = 10000000.0;
	protected $userManager;
    protected $reportetiempoMapper;
    protected $clientesMapper;
    protected $actividaesdMapper;
	protected $historialausenciasMapper;
    protected $l10n;
    private $config;
    private $clientService;
    private $subAdmin;
    private $urlGenerator;
	private $mailer;
	private INotificationManager $notificationManager;
	private VacacionesCalculoService $vacacionesCalculoService;

	public function __construct(
		IRequest $request,
		IUserSession $userSession,
		IUserManager $userManager,
		empleadosMapper $empleadosMapper,
		reportetiempoMapper $reportetiempoMapper,
		configuracionesMapper $configuracionesMapper,
		clientesMapper $clientesMapper,
		actividadesMapper $actividadesMapper,
		historialausenciasMapper $historialausenciasMapper,
		IL10N $l10n,
		IConfig $config,
		IGroupManager $groupManager,
		IURLGenerator $urlGenerator,
		IClientService $clientService,
		IMailer $mailer,
		ISubAdmin $subAdmin,
		INotificationManager $notificationManager,
		VacacionesCalculoService $vacacionesCalculoService,
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
		$this->actividadesMapper = $actividadesMapper;
		$this->l10n = $l10n;
		$this->groupManager = $groupManager;
		$this->config = $config;
		$this->urlGenerator = $urlGenerator;
		$this->clientService = $clientService;
		$this->subAdmin = $subAdmin;
		$this->mailer = $mailer;
		$this->notificationManager = $notificationManager;
		$this->historialausenciasMapper = $historialausenciasMapper;
		$this->vacacionesCalculoService = $vacacionesCalculoService;
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
	 * Resumen de costos estimados y horas propias de los líderes de proyecto.
	 */
	#[UseSession]
	#[NoAdminRequired]
	public function GetCostosLideres(
		$periodo_inicio = null,
		$periodo_fin = null,
		$anio = null
	): DataResponse {
		$this->checkAccess(['admin', 'recursos_humanos', 'empleados']);

		$denied = $this->denyIfNoAdminReportsAccess();

		if ($denied !== null) {
			return $denied;
		}

		$empleadosVisibles = $this->getEmpleadosVisiblesBasico();
		$idEmpleadosVisibles = array_values(array_unique(array_filter(array_map(
			static function ($empleado) {
				return (int)(
					$empleado['Id_empleados']
					?? $empleado['id_empleados']
					?? 0
				);
			},
			$empleadosVisibles
		))));

		$costos = $this->reportetiempoMapper->getCostosPorLider(
			$periodo_inicio,
			$periodo_fin,
			$anio,
			$idEmpleadosVisibles
		);
		$periodo = $costos['periodo'];
		$fechaInicio = sprintf(
			'%04d-%02d-01',
			(int)$periodo['anio'],
			(int)$periodo['periodo_inicio']
		);
		$fechaFin = (new \DateTimeImmutable(sprintf(
			'%04d-%02d-01',
			(int)$periodo['anio'],
			(int)$periodo['periodo_fin']
		)))->modify('last day of this month')->format('Y-m-d');
		$horasDiarias = $this->getCostosHorasDiarias();
		$empleados = $this->construirCostosCandidatos(
			$idEmpleadosVisibles,
			$fechaInicio,
			$fechaFin,
			[],
			0,
			0.0,
			$horasDiarias
		);

		$costos['empleados_disponibilidad'] = array_map(
			static function (array $empleado): array {
				return [
					'id_empleado' => $empleado['id_empleado'],
					'uid' => $empleado['uid'],
					'displayname' => $empleado['displayname'],
					'capacidad_calculable' => $empleado['capacidad_calculable'],
					'horas_periodo' => $empleado['horas_periodo'],
					'horas_ausencia' => $empleado['horas_ausencia'],
					'horas_reportadas_periodo' => $empleado['horas_reportadas_periodo'],
					'disponibilidad_estimada' => $empleado['disponibilidad_estimada'],
					'ocupacion_estimada' => $empleado['ocupacion_estimada'],
					'calidad_datos' => $empleado['calidad_datos'],
				];
			},
			$empleados
		);
		$costos['kpis']['empleados_disponibilidad_baja'] = count(array_filter(
			$empleados,
			static function (array $empleado): bool {
				return $empleado['capacidad_calculable']
					&& (
						(float)$empleado['disponibilidad_estimada'] <= 0
						|| (float)$empleado['ocupacion_estimada'] >= 90
					);
			}
		));
		$costos['kpis']['empleados_informacion_insuficiente'] = count(array_filter(
			$empleados,
			static fn (array $empleado): bool => $empleado['calidad_datos'] === 'baja'
		));

		return new DataResponse($costos, Http::STATUS_OK);
	}

	/**
	 * Analiza candidatos visibles para un proyecto tentativo.
	 */
	#[UseSession]
	#[NoAdminRequired]
	public function GetCostosCandidatos(
		$id_cliente = null,
		$fecha_inicio = null,
		$fecha_fin = null,
		$actividades = []
	): DataResponse {
		$this->checkAccess(['admin', 'recursos_humanos', 'empleados']);

		$denied = $this->denyIfNoAdminReportsAccess();

		if ($denied !== null) {
			return $denied;
		}

		$inicio = $this->normalizarFechaCostos($fecha_inicio);
		$fin = $this->normalizarFechaCostos($fecha_fin);

		if ($inicio === null || $fin === null || $inicio > $fin) {
			return new DataResponse([
				'error' => 'El periodo de planificación no es válido.',
			], Http::STATUS_BAD_REQUEST);
		}

		$fechaInicio = $inicio->format('Y-m-d');
		$fechaFin = $fin->format('Y-m-d');
		$diasLaborales = $this->contarDiasLaboralesCostos($fechaInicio, $fechaFin);
		$horasDiarias = $this->getCostosHorasDiarias();
		$idEmpleadosVisibles = $this->getIdsEmpleadosVisiblesCostos();

		if (empty($idEmpleadosVisibles)) {
			return new DataResponse([
				'periodo' => [
					'fecha_inicio' => $fechaInicio,
					'fecha_fin' => $fechaFin,
					'dias_laborales' => $diasLaborales,
					'horas_diarias' => $horasDiarias,
				],
				'empresa' => null,
				'requerimiento' => [
					'horas_estimadas' => 0.0,
					'actividades' => [],
				],
				'candidatos' => [],
			], Http::STATUS_OK);
		}

		$idCliente = filter_var($id_cliente, FILTER_VALIDATE_INT);

		if ($idCliente === false || (int)$idCliente <= 0 || (int)$idCliente === self::ID_CLIENTE_AUSENCIA) {
			return new DataResponse([
				'error' => 'La empresa seleccionada no está disponible.',
			], Http::STATUS_BAD_REQUEST);
		}

		$empresa = $this->reportetiempoMapper->getCostosEmpresaVisible(
			(int)$idCliente,
			$idEmpleadosVisibles
		);

		if (empty($empresa)) {
			return new DataResponse([
				'error' => 'La empresa seleccionada no está disponible.',
			], Http::STATUS_NOT_FOUND);
		}

		if (is_string($actividades)) {
			$actividades = json_decode($actividades, true);
		}

		if (!is_array($actividades) || empty($actividades)) {
			return new DataResponse([
				'error' => 'Selecciona al menos una actividad válida.',
			], Http::STATUS_BAD_REQUEST);
		}

		$horasPorActividad = [];

		foreach ($actividades as $actividad) {
			if (!is_array($actividad)) {
				return new DataResponse([
					'error' => 'Las actividades seleccionadas no son válidas.',
				], Http::STATUS_BAD_REQUEST);
			}

			$idActividadRaw = $actividad['id_actividad'] ?? null;
			$horasRaw = $actividad['horas_estimadas'] ?? 0;

			if (
				!is_numeric($idActividadRaw)
				|| (float)$idActividadRaw !== floor((float)$idActividadRaw)
				|| (int)$idActividadRaw <= 0
				|| (int)$idActividadRaw === self::ID_ACTIVIDAD_CARGABLE
				|| !is_numeric($horasRaw)
				|| !is_finite((float)$horasRaw)
				|| (float)$horasRaw < 0
				|| (float)$horasRaw > self::MAX_HORAS_PLANIFICACION
			) {
				return new DataResponse([
					'error' => 'Las actividades y sus horas estimadas no son válidas.',
				], Http::STATUS_BAD_REQUEST);
			}

			$idActividad = (int)$idActividadRaw;
			$horasPorActividad[$idActividad] = ($horasPorActividad[$idActividad] ?? 0.0)
				+ (float)$horasRaw;

			if (
				!is_finite($horasPorActividad[$idActividad])
				|| $horasPorActividad[$idActividad] > self::MAX_HORAS_PLANIFICACION
				|| array_sum($horasPorActividad) > self::MAX_HORAS_PLANIFICACION
			) {
				return new DataResponse([
					'error' => 'El total de horas estimadas no es válido.',
				], Http::STATUS_BAD_REQUEST);
			}
		}

		$actividadesValidas = $this->reportetiempoMapper->getCostosActividades(
			array_keys($horasPorActividad)
		);

		if (count($actividadesValidas) !== count($horasPorActividad)) {
			return new DataResponse([
				'error' => 'Una o más actividades seleccionadas no están disponibles.',
			], Http::STATUS_BAD_REQUEST);
		}

		$actividadesNormalizadas = array_map(
			static function (array $actividad) use ($horasPorActividad): array {
				$id = (int)$actividad['id_actividad'];

				return [
					'id_actividad' => $id,
					'nombre' => $actividad['nombre'],
					'cargable' => (int)$actividad['cargable'],
					'horas_estimadas' => (float)$horasPorActividad[$id],
				];
			},
			$actividadesValidas
		);
		$horasEstimadas = array_sum($horasPorActividad);
		$candidatos = $this->construirCostosCandidatos(
			$idEmpleadosVisibles,
			$fechaInicio,
			$fechaFin,
			array_keys($horasPorActividad),
			(int)$idCliente,
			(float)$horasEstimadas,
			$horasDiarias
		);

		return new DataResponse([
			'periodo' => [
				'fecha_inicio' => $fechaInicio,
				'fecha_fin' => $fechaFin,
				'dias_laborales' => $diasLaborales,
				'horas_diarias' => $horasDiarias,
			],
			'empresa' => $empresa,
			'requerimiento' => [
				'horas_estimadas' => (float)$horasEstimadas,
				'actividades' => $actividadesNormalizadas,
			],
			'candidatos' => $candidatos,
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
	 * Extrae únicamente identificadores válidos del alcance calculado desde la sesión.
	 */
	private function getIdsEmpleadosVisiblesCostos(): array {
		$ids = array_map(
			static function (array $empleado): int {
				return (int)(
					$empleado['Id_empleados']
					?? $empleado['id_empleados']
					?? 0
				);
			},
			$this->getEmpleadosVisiblesBasico()
		);

		return array_values(array_unique(array_filter(
			$ids,
			static fn (int $id): bool => $id > 0
		)));
	}

	/**
	 * Acepta exclusivamente fechas ISO completas para evitar normalizaciones ambiguas.
	 */
	private function normalizarFechaCostos($valor): ?\DateTimeImmutable {
		if (!is_string($valor)) {
			return null;
		}

		$valor = trim($valor);
		$fecha = \DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
		$errores = \DateTimeImmutable::getLastErrors();

		if (
			$fecha === false
			|| $fecha->format('Y-m-d') !== $valor
			|| (
				is_array($errores)
				&& (
					(int)$errores['warning_count'] > 0
					|| (int)$errores['error_count'] > 0
				)
			)
		) {
			return null;
		}

		return $fecha;
	}

	/**
	 * Normaliza fechas provenientes de columnas DATE/DATETIME sin relajar la entrada pública.
	 */
	private function normalizarFechaPersistidaCostos($valor): ?\DateTimeImmutable {
		if ($valor instanceof \DateTimeInterface) {
			return new \DateTimeImmutable($valor->format('Y-m-d'));
		}

		if (!is_string($valor) || strlen($valor) < 10) {
			return null;
		}

		return $this->normalizarFechaCostos(substr($valor, 0, 10));
	}

	/**
	 * Usa la misma jornada de referencia configurada para los reportes.
	 */
	private function getCostosHorasDiarias(): float {
		$horas = (float)$this->config->getAppValue(
			Application::APP_ID,
			'reportes_horas_minimas',
			'0'
		);

		if (!is_finite($horas) || $horas <= 0) {
			return 0.0;
		}

		return $horas;
	}

	/**
	 * Reutiliza la utilidad de vacaciones para contar días de lunes a viernes.
	 */
	private function contarDiasLaboralesCostos(string $fechaInicio, string $fechaFin): int {
		$inicio = new \DateTime($fechaInicio);
		$fin = new \DateTime($fechaFin);

		return $this->vacacionesCalculoService->contarDiasHabilesHastaFecha(
			$inicio,
			$fin,
			clone $fin
		);
	}

	/**
	 * Construye el análisis completo con un número fijo de consultas agregadas.
	 */
	private function construirCostosCandidatos(
		array $idEmpleadosVisibles,
		string $fechaInicio,
		string $fechaFin,
		array $idActividades,
		int $idCliente,
		float $horasRequeridas,
		float $horasDiarias
	): array {
		$idEmpleadosVisibles = array_values(array_unique(array_filter(
			array_map('intval', $idEmpleadosVisibles),
			static fn (int $id): bool => $id > 0
		)));
		$idActividades = array_values(array_unique(array_filter(
			array_map('intval', $idActividades),
			static fn (int $id): bool => $id > 0 && $id !== self::ID_ACTIVIDAD_CARGABLE
		)));

		if (empty($idEmpleadosVisibles)) {
			return [];
		}

		$empleadosBase = $this->reportetiempoMapper->getCostosEmpleadosBase(
			$idEmpleadosVisibles
		);

		if (empty($empleadosBase)) {
			return [];
		}

		$horasPeriodoRows = $this->reportetiempoMapper->getCostosHorasPeriodo(
			$idEmpleadosVisibles,
			$fechaInicio,
			$fechaFin
		);
		$fechaReferenciaBase = (!empty($idActividades) || $idCliente > 0)
			? new \DateTimeImmutable($fechaInicio)
			: new \DateTimeImmutable($fechaFin);
		$hoy = new \DateTimeImmutable('today');
		$fechaReferenciaExperiencia = $fechaReferenciaBase > $hoy
			? $hoy
			: $fechaReferenciaBase;
		$fechaCorteDoceMeses = $fechaReferenciaExperiencia
			->modify('-12 months')
			->format('Y-m-d');
		$experienciaRows = $this->reportetiempoMapper->getCostosExperiencia(
			$idEmpleadosVisibles,
			$idActividades,
			$idCliente,
			$fechaCorteDoceMeses,
			$fechaReferenciaExperiencia->format('Y-m-d')
		);
		$experienciaActividadRows = $this->reportetiempoMapper
			->getCostosExperienciaPorActividad(
				$idEmpleadosVisibles,
				$idActividades,
				$fechaCorteDoceMeses,
				$fechaReferenciaExperiencia->format('Y-m-d')
			);
		$ausenciasRows = $this->reportetiempoMapper->getCostosAusenciasAprobadas(
			$idEmpleadosVisibles,
			$fechaInicio,
			$fechaFin
		);

		$horasPeriodoPorEmpleado = [];

		foreach ($horasPeriodoRows as $row) {
			$idEmpleado = (int)($row['id_empleado'] ?? 0);

			if ($idEmpleado > 0) {
				$horasPeriodoPorEmpleado[$idEmpleado] = $row;
			}
		}

		$experienciaPorEmpleado = [];

		foreach ($experienciaRows as $row) {
			$idEmpleado = (int)($row['id_empleado'] ?? 0);

			if ($idEmpleado > 0) {
				$experienciaPorEmpleado[$idEmpleado] = $row;
			}
		}

		$experienciaActividadesPorEmpleado = [];

		foreach ($experienciaActividadRows as $row) {
			$idEmpleado = (int)($row['id_empleado'] ?? 0);
			$idActividad = (int)($row['id_actividad'] ?? 0);

			if ($idEmpleado <= 0 || $idActividad <= 0) {
				continue;
			}

			$experienciaActividadesPorEmpleado[$idEmpleado][] = [
				'id_actividad' => $idActividad,
				'horas' => round(max(0.0, (float)($row['minutos_actividad'] ?? 0)) / 60, 2),
				'horas_12_meses' => round(
					max(0.0, (float)($row['minutos_actividad_12_meses'] ?? 0)) / 60,
					2
				),
				'registros' => (int)($row['registros_actividad'] ?? 0),
				'ultimo_reporte' => $row['ultimo_reporte_actividad'] ?? null,
			];
		}

		$diasLaborales = $this->contarDiasLaboralesCostos($fechaInicio, $fechaFin);
		$fraccionesAusenciaPorEmpleado = [];
		$inicioPeriodo = new \DateTimeImmutable($fechaInicio);
		$finPeriodo = new \DateTimeImmutable($fechaFin);

		foreach ($ausenciasRows as $ausencia) {
			$idEmpleado = (int)($ausencia['id_empleado'] ?? 0);
			$inicioAusencia = $this->normalizarFechaPersistidaCostos(
				$ausencia['fecha_de'] ?? null
			);
			$finAusencia = $this->normalizarFechaPersistidaCostos(
				$ausencia['fecha_hasta'] ?? null
			);

			if (
				$idEmpleado <= 0
				|| $inicioAusencia === null
				|| $finAusencia === null
				|| $inicioAusencia > $finAusencia
			) {
				continue;
			}

			$inicioSolapado = $inicioAusencia > $inicioPeriodo
				? $inicioAusencia
				: $inicioPeriodo;
			$finSolapado = $finAusencia < $finPeriodo
				? $finAusencia
				: $finPeriodo;

			if ($inicioSolapado > $finSolapado) {
				continue;
			}

			$diasCompletos = $this->contarDiasLaboralesCostos(
				$inicioAusencia->format('Y-m-d'),
				$finAusencia->format('Y-m-d')
			);
			$diasSolicitados = (float)($ausencia['dias_solicitados'] ?? 0);
			$fraccionDiaria = 1.0;

			/*
			 * Si la fuente contiene una fracción, se conserva proporcionalmente.
			 * El esquema actual declara dias_solicitados como entero, por lo que
			 * nuevas fracciones requerirían una migración independiente.
			 */
			if ($diasSolicitados > 0 && $diasCompletos > 0) {
				$fraccionDiaria = min(1.0, $diasSolicitados / $diasCompletos);
			}

			$cursor = $inicioSolapado;

			while ($cursor <= $finSolapado) {
				$fecha = $cursor->format('Y-m-d');

				if ($this->contarDiasLaboralesCostos($fecha, $fecha) === 1) {
					$fraccionExistente = $fraccionesAusenciaPorEmpleado[$idEmpleado][$fecha]
						?? 0.0;
					$fraccionesAusenciaPorEmpleado[$idEmpleado][$fecha] = min(
						1.0,
						max(0.0, (float)$fraccionExistente)
							+ max(0.0, $fraccionDiaria)
					);
				}

				$cursor = $cursor->modify('+1 day');
			}
		}

		$diasAusenciaPorEmpleado = [];

		foreach ($fraccionesAusenciaPorEmpleado as $idEmpleado => $fracciones) {
			$diasAusenciaPorEmpleado[(int)$idEmpleado] = array_sum($fracciones);
		}

		$candidatos = [];
		$capacidadCalculable = $horasDiarias > 0
			&& is_finite($horasDiarias)
			&& (
				$diasLaborales === 0
				|| $horasDiarias <= PHP_FLOAT_MAX / $diasLaborales
			);
		$horasPeriodo = $capacidadCalculable
			? $diasLaborales * $horasDiarias
			: null;

		foreach ($empleadosBase as $empleado) {
			$idEmpleado = (int)($empleado['id_empleado'] ?? 0);

			if ($idEmpleado <= 0) {
				continue;
			}

			$periodo = $horasPeriodoPorEmpleado[$idEmpleado] ?? [];
			$experiencia = $experienciaPorEmpleado[$idEmpleado] ?? [];
			$minutosReportados = max(0.0, (float)($periodo['minutos_reportados'] ?? 0));
			$horasReportadas = $minutosReportados / 60;
			$diasAusencia = min(
				(float)$diasLaborales,
				max(0.0, (float)($diasAusenciaPorEmpleado[$idEmpleado] ?? 0))
			);
			$horasAusencia = $capacidadCalculable
				? $diasAusencia * $horasDiarias
				: null;
			$capacidadEfectiva = $capacidadCalculable
				? max(0.0, (float)$horasPeriodo - (float)$horasAusencia)
				: null;
			$disponibilidad = $capacidadCalculable
				? max(0.0, (float)$capacidadEfectiva - $horasReportadas)
				: null;
			$ocupacion = $capacidadCalculable && (float)$capacidadEfectiva > 0
				? ($horasReportadas / (float)$capacidadEfectiva) * 100
				: null;
			$ocupacionResultante = $capacidadCalculable && (float)$capacidadEfectiva > 0
				? (($horasReportadas + max(0.0, $horasRequeridas)) / (float)$capacidadEfectiva) * 100
				: null;

			if ($ocupacion !== null && !is_finite($ocupacion)) {
				$ocupacion = null;
			}

			if ($ocupacionResultante !== null && !is_finite($ocupacionResultante)) {
				$ocupacionResultante = null;
			}

			$minutosHistoricos = max(0.0, (float)($experiencia['minutos_historicos'] ?? 0));
			$minutosCargablesHistoricos = max(
				0.0,
				(float)($experiencia['minutos_cargables_historicos'] ?? 0)
			);
			$minutosActividades = max(0.0, (float)($experiencia['minutos_actividades'] ?? 0));
			$minutosActividadesRecientes = max(
				0.0,
				(float)($experiencia['minutos_actividades_12_meses'] ?? 0)
			);
			$minutosEmpresa = max(0.0, (float)($experiencia['minutos_empresa'] ?? 0));
			$minutosCargablesEmpresa = max(
				0.0,
				(float)($experiencia['minutos_cargables_empresa'] ?? 0)
			);
			$porcentajeCargable = $minutosHistoricos > 0
				? min(100.0, max(0.0, ($minutosCargablesHistoricos / $minutosHistoricos) * 100))
				: null;
			$costoHora = $empleado['costo_hora'] ?? null;
			$costoHora = $costoHora === null || !is_numeric($costoHora)
				? null
				: round(max(0.0, (float)$costoHora), 2);

			$candidatos[] = [
				'id_empleado' => $idEmpleado,
				'uid' => (string)($empleado['uid'] ?? ''),
				'displayname' => (string)($empleado['displayname'] ?? ''),
				'area' => $empleado['area'] ?? null,
				'puesto' => $empleado['puesto'] ?? null,
				'nivel_puesto' => $empleado['nivel_puesto'] ?? null,
				'costo_hora' => $costoHora,
				'capacidad_calculable' => $capacidadCalculable,
				'horas_periodo' => $horasPeriodo === null ? null : round($horasPeriodo, 2),
				'horas_ausencia' => $horasAusencia === null ? null : round($horasAusencia, 2),
				'capacidad_efectiva' => $capacidadEfectiva === null ? null : round($capacidadEfectiva, 2),
				'horas_reportadas_periodo' => round($horasReportadas, 2),
				'disponibilidad_estimada' => $disponibilidad === null ? null : round($disponibilidad, 2),
				'ocupacion_estimada' => $ocupacion === null ? null : round($ocupacion, 2),
				'ocupacion_resultante_estimada' => $ocupacionResultante === null
					? null
					: round($ocupacionResultante, 2),
				'experiencia' => [
					'horas_actividades' => round($minutosActividades / 60, 2),
					'horas_actividades_12_meses' => round($minutosActividadesRecientes / 60, 2),
					'registros_actividades' => (int)($experiencia['registros_actividades'] ?? 0),
					'empresas_actividades' => (int)($experiencia['empresas_actividades'] ?? 0),
					'horas_empresa' => round($minutosEmpresa / 60, 2),
					'horas_cargables_empresa' => round($minutosCargablesEmpresa / 60, 2),
					'empresas_atendidas' => (int)($experiencia['empresas_atendidas'] ?? 0),
					'ultimo_reporte_empresa' => $experiencia['ultimo_reporte_empresa'] ?? null,
					'actividades_empresa' => (int)($experiencia['actividades_empresa'] ?? 0),
					'actividades' => $experienciaActividadesPorEmpleado[$idEmpleado] ?? [],
				],
				'porcentaje_cargable_historico' => $porcentajeCargable === null
					? null
					: round($porcentajeCargable, 2),
				'_analisis' => [
					'disponibilidad' => $disponibilidad,
					'minutos_actividades' => $minutosActividades,
					'minutos_actividades_recientes' => $minutosActividadesRecientes,
					'minutos_empresa' => $minutosEmpresa,
					'registros_historicos' => (int)($experiencia['registros_historicos'] ?? 0),
					'registros_recientes' => (int)($experiencia['registros_12_meses'] ?? 0),
					'porcentaje_cargable' => $porcentajeCargable,
					'costo_hora' => $costoHora,
					'ocupacion_resultante' => $ocupacionResultante,
				],
			];
		}

		$maxDisponibilidad = 0.0;
		$maxMinutosActividades = 0.0;
		$maxMinutosEmpresa = 0.0;
		$costosConfigurados = [];

		foreach ($candidatos as $candidato) {
			$analisis = $candidato['_analisis'];
			$maxDisponibilidad = max(
				$maxDisponibilidad,
				(float)($analisis['disponibilidad'] ?? 0)
			);
			$maxMinutosActividades = max(
				$maxMinutosActividades,
				(float)$analisis['minutos_actividades']
			);
			$maxMinutosEmpresa = max(
				$maxMinutosEmpresa,
				(float)$analisis['minutos_empresa']
			);

			if ($analisis['costo_hora'] !== null) {
				$costosConfigurados[] = (float)$analisis['costo_hora'];
			}
		}

		$costoMinimo = empty($costosConfigurados) ? null : min($costosConfigurados);
		$costoMaximo = empty($costosConfigurados) ? null : max($costosConfigurados);

		foreach ($candidatos as &$candidato) {
			$analisis = $candidato['_analisis'];
			$desglose = [
				'disponibilidad' => null,
				'experiencia_actividades' => null,
				'experiencia_empresa' => null,
				'cargabilidad' => null,
				'costo' => null,
			];
			$puntos = 0.0;
			$puntosPosibles = 0.0;

			if ($capacidadCalculable) {
				$desglose['disponibilidad'] = $maxDisponibilidad > 0
					? ((float)$analisis['disponibilidad'] / $maxDisponibilidad) * 35
					: 0.0;
				$puntos += $desglose['disponibilidad'];
				$puntosPosibles += 35;
			}

			$tieneHistorial = (int)$analisis['registros_historicos'] > 0;

			if (!empty($idActividades) && $tieneHistorial) {
				$desglose['experiencia_actividades'] = $maxMinutosActividades > 0
					? ((float)$analisis['minutos_actividades'] / $maxMinutosActividades) * 30
					: 0.0;
				$puntos += $desglose['experiencia_actividades'];
				$puntosPosibles += 30;
			}

			if ($idCliente > 0 && $tieneHistorial) {
				$desglose['experiencia_empresa'] = $maxMinutosEmpresa > 0
					? ((float)$analisis['minutos_empresa'] / $maxMinutosEmpresa) * 15
					: 0.0;
				$puntos += $desglose['experiencia_empresa'];
				$puntosPosibles += 15;
			}

			if ($analisis['porcentaje_cargable'] !== null) {
				$desglose['cargabilidad'] =
					min(100.0, max(0.0, (float)$analisis['porcentaje_cargable']))
					/ 100
					* 10;
				$puntos += $desglose['cargabilidad'];
				$puntosPosibles += 10;
			}

			if ($analisis['costo_hora'] !== null) {
				$desglose['costo'] = $costoMinimo !== null && $costoMaximo !== null
					&& $costoMaximo > $costoMinimo
					? (($costoMaximo - (float)$analisis['costo_hora'])
						/ ($costoMaximo - $costoMinimo)) * 10
					: 10.0;
				$puntos += $desglose['costo'];
				$puntosPosibles += 10;
			}

			foreach ($desglose as &$valor) {
				if ($valor !== null) {
					$valor = round(min(100.0, max(0.0, (float)$valor)), 2);
				}
			}
			unset($valor);

			$candidato['ajuste_estimado'] = $puntosPosibles > 0
				? round(min(100.0, max(0.0, ($puntos / $puntosPosibles) * 100)), 2)
				: null;
			$candidato['desglose_ajuste'] = $desglose;

			$fuentesFaltantes = 0;
			$fuentesFaltantes += $capacidadCalculable ? 0 : 1;
			$fuentesFaltantes += $analisis['costo_hora'] === null ? 1 : 0;
			$fuentesFaltantes += (int)$analisis['registros_recientes'] > 0 ? 0 : 1;
			$tieneExperienciaRelacionada = !empty($idActividades) || $idCliente > 0
				? (
					(float)$analisis['minutos_actividades'] > 0
					|| (float)$analisis['minutos_empresa'] > 0
				)
				: $tieneHistorial;
			$fuentesFaltantes += $tieneExperienciaRelacionada ? 0 : 1;
			$candidato['calidad_datos'] = $fuentesFaltantes === 0
				? 'alta'
				: ($fuentesFaltantes === 1 ? 'media' : 'baja');

			$fortalezas = [];
			$riesgos = [];

			if ((float)$analisis['minutos_actividades_recientes'] > 0) {
				$fortalezas[] = ['key' => 'experiencia_reciente_actividades'];
			} elseif ((float)$analisis['minutos_actividades'] > 0) {
				$fortalezas[] = ['key' => 'experiencia_actividades'];
			} elseif (!empty($idActividades)) {
				$riesgos[] = ['key' => 'sin_experiencia_actividades'];
			}

			if ((float)$analisis['minutos_empresa'] > 0) {
				$fortalezas[] = ['key' => 'experiencia_empresa'];
			} elseif ($idCliente > 0) {
				$riesgos[] = ['key' => 'sin_experiencia_empresa'];
			}

			if (
				$capacidadCalculable
				&& $horasRequeridas > 0
				&& (float)$analisis['disponibilidad'] >= $horasRequeridas
			) {
				$fortalezas[] = ['key' => 'disponibilidad_suficiente'];
			} elseif (
				$capacidadCalculable
				&& $horasRequeridas > (float)$analisis['disponibilidad']
			) {
				$riesgos[] = ['key' => 'horas_superan_disponibilidad'];
			}

			if ((float)($analisis['porcentaje_cargable'] ?? 0) >= 70) {
				$fortalezas[] = ['key' => 'cargabilidad_alta'];
			}

			if (
				$desglose['costo'] !== null
				&& (float)$desglose['costo'] >= 7.5
			) {
				$fortalezas[] = ['key' => 'costo_relativo_favorable'];
			}

			if ($analisis['costo_hora'] === null) {
				$riesgos[] = ['key' => 'sin_costo_hora'];
			}

			if (!$capacidadCalculable) {
				$riesgos[] = ['key' => 'capacidad_no_calculable'];
			} elseif ($horasRequeridas > 0 && $analisis['ocupacion_resultante'] !== null) {
				if ((float)$analisis['ocupacion_resultante'] > 100) {
					$riesgos[] = ['key' => 'ocupacion_supera_100'];
				} elseif ((float)$analisis['ocupacion_resultante'] > 90) {
					$riesgos[] = ['key' => 'ocupacion_supera_90'];
				}
			}

			if ($candidato['calidad_datos'] !== 'alta') {
				$riesgos[] = ['key' => 'datos_incompletos'];
			}

			$candidato['fortalezas'] = $fortalezas;
			$candidato['riesgos'] = $riesgos;
			unset($candidato['_analisis']);
		}
		unset($candidato);

		usort($candidatos, static function (array $a, array $b): int {
			$ajusteA = $a['ajuste_estimado'] ?? -1;
			$ajusteB = $b['ajuste_estimado'] ?? -1;

			if ((float)$ajusteA !== (float)$ajusteB) {
				return (float)$ajusteB <=> (float)$ajusteA;
			}

			$disponibilidadA = $a['disponibilidad_estimada'] ?? -1;
			$disponibilidadB = $b['disponibilidad_estimada'] ?? -1;

			if ((float)$disponibilidadA !== (float)$disponibilidadB) {
				return (float)$disponibilidadB <=> (float)$disponibilidadA;
			}

			return strcasecmp(
				(string)($a['displayname'] ?? ''),
				(string)($b['displayname'] ?? '')
			);
		});

		return $candidatos;
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
				'status' => 'error',
				'message' => 'Usuario no autenticado',
			], Http::STATUS_UNAUTHORIZED);
		}

		$zonaHoraria = $this->config->getAppValue(
			Application::APP_ID,
			'reportes_recordatorios_zona_horaria',
			'America/Mexico_City'
		);

		try {
			$tz = new \DateTimeZone($zonaHoraria);
		} catch (\Throwable $e) {
			$tz = new \DateTimeZone('America/Mexico_City');
		}

		if (empty($fecha)) {
			$fecha = (new \DateTimeImmutable('now', $tz))->format('Y-m-d');
		} else {
			try {
				$fecha = (new \DateTimeImmutable((string)$fecha, $tz))->format('Y-m-d');
			} catch (\Throwable $e) {
				return new DataResponse([
					'status' => 'error',
					'message' => 'Fecha inválida.',
				], Http::STATUS_BAD_REQUEST);
			}
		}

		$enviarEmail = filter_var(
			$this->config->getAppValue(
				Application::APP_ID,
				'reportes_recordatorios_email',
				'true'
			),
			FILTER_VALIDATE_BOOLEAN
		);

		$horasMinimas = (float)$this->config->getAppValue(
			Application::APP_ID,
			'reportes_horas_minimas',
			'0'
		);

		if ($horasMinimas < 0) {
			$horasMinimas = 0;
		}

		$minutosMinimos = $horasMinimas * 60;

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
					'nombre' => $displayname,
					'motivo' => 'Empleado inválido',
				];
				continue;
			}

			$resumen = $this->reportetiempoMapper->getResumenDiaByEmpleado(
				(int)$idEmpleado,
				$fecha
			);

			$registros = (int)($resumen['registros'] ?? 0);
			$minutosReportados = (float)($resumen['minutos_reportados'] ?? 0);
			$horasReportadas = $minutosReportados / 60;

			$cumple = $minutosMinimos > 0
				? $minutosReportados >= $minutosMinimos
				: $registros > 0;

			if ($cumple) {
				$omitidos[] = [
					'uid' => $uid,
					'nombre' => $displayname,
					'motivo' => 'Ya cumple con el reporte',
					'registros' => $registros,
					'minutos_reportados' => $minutosReportados,
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
					'nombre' => $displayname,
					'motivo' => 'Ya se envió recordatorio hoy',
				];
				continue;
			}

			$nextcloudUser = $this->userManager->get((string)$uid);

			if ($nextcloudUser === null) {
				$omitidos[] = [
					'uid' => $uid,
					'nombre' => $displayname,
					'motivo' => 'Usuario Nextcloud no encontrado',
				];
				continue;
			}

			$email = $nextcloudUser->getEMailAddress();
			$notificacionEnviada = false;
			$correoEnviado = false;
			$erroresEnvio = [];

			try {
				$notification = $this->notificationManager->createNotification();

				$notification
					->setApp(Application::APP_ID)
					->setUser((string)$uid)
					->setDateTime(new \DateTime())
					->setObject('reporte_tiempo', $fecha)
					->setSubject('tiempo_pendiente', [
						'fecha' => $fecha,
						'horas_reportadas' => round($horasReportadas, 2),
						'horas_minimas' => round($horasMinimas, 2),
						'minutos_reportados' => $minutosReportados,
						'minutos_minimos' => $minutosMinimos,
					])
					->setLink($quickReportUrl);

				$this->notificationManager->notify($notification);

				$notificacionEnviada = true;
			} catch (\Throwable $e) {
				$erroresEnvio[] = 'Error notificación interna: ' . $e->getMessage();
			}

			if ($enviarEmail) {
				if (empty($email)) {
					$erroresEnvio[] = 'Usuario sin correo';
				} else {
					try {
						$message = $this->mailer->createMessage();

						$message->setTo([
							$email => $nextcloudUser->getDisplayName() ?: (string)$uid,
						]);

						$message->setSubject('Recordatorio: registra tu tiempo');

						if ($minutosMinimos > 0) {
							$estadoTexto = 'Actualmente llevas ' . round($horasReportadas, 2) . ' horas reportadas. '
								. 'La meta mínima configurada es de ' . round($horasMinimas, 2) . ' horas.';
						} else {
							$estadoTexto = 'Aún no tienes reportes de tiempo registrados para la fecha ' . $fecha . '.';
						}

						$body = implode("\n", [
							'Hola ' . ($nextcloudUser->getDisplayName() ?: $displayname) . ',',
							'',
							$estadoTexto,
							'',
							'Puedes registrarlo aquí:',
							$quickReportUrl,
							'',
							'Este es un recordatorio enviado desde el reporte de cumplimiento.',
						]);

						$message->setPlainBody($body);

						$this->mailer->send($message);

						$correoEnviado = true;
					} catch (\Throwable $e) {
						$erroresEnvio[] = 'Error correo: ' . $e->getMessage();
					}
				}
			}

			if ($notificacionEnviada || $correoEnviado) {
				$this->config->setUserValue(
					(string)$uid,
					Application::APP_ID,
					'ultimo_recordatorio_reporte_tiempo',
					$fecha
				);

				$enviados[] = [
					'uid' => $uid,
					'nombre' => $displayname,
					'email' => $email,
					'notificacion_interna' => $notificacionEnviada,
					'correo' => $correoEnviado,
					'registros' => $registros,
					'minutos_reportados' => $minutosReportados,
					'horas_reportadas' => round($horasReportadas, 2),
				];

				continue;
			}

			$omitidos[] = [
				'uid' => $uid,
				'nombre' => $displayname,
				'motivo' => implode(' | ', $erroresEnvio) ?: 'No se pudo enviar recordatorio',
			];
		}

		return new DataResponse([
			'status' => 'ok',
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
			$actividades = $this->actividadesMapper->findAll();
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
