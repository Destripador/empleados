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
		ISubAdmin $subAdmin,
	) {
		parent::__construct(
			Application::APP_ID,
			$request,
			$userSession,
			$groupManager,
			$empleadosMapper,
			$configuracionesMapper
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
}