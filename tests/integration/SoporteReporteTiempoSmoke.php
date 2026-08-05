<?php

declare(strict_types=1);

use OCA\Empleados\Db\actividadesMapper;
use OCA\Empleados\Db\empleadosMapper;
use OCA\Empleados\Db\InventarioComputoMapper;
use OCA\Empleados\Db\InventarioMovimientoMapper;
use OCA\Empleados\Db\reportetiempoMapper;
use OCA\Empleados\Db\reportetiempo;
use OCA\Empleados\Db\SoporteHistorialMapper;
use OCA\Empleados\Controller\reportetiempoController;
use OCA\Empleados\Controller\InventarioController;
use OCA\Empleados\Service\PermisosService;
use OCA\Empleados\Service\InventarioMovimientoService;
use OCA\Empleados\Service\SoporteReporteTiempoService;
use OCP\IDBConnection;
use OCP\IUserManager;
use OCP\IUserSession;

require '/var/www/html/lib/base.php';
require_once '/var/www/html/custom_apps/empleados/lib/Controller/ReportetiempoController.php';

class FailingSupportReportIntegration extends SoporteReporteTiempoService {
	public function __construct() {
	}

	public function validarDuracion(mixed $value): int {
		return (int)$value;
	}

	public function normalizarFecha(?string $value, string $userId): string {
		return (string)$value;
	}

	public function crearDesdeSoporte(array $soporte, array $equipo): int {
		throw new RuntimeException('Fallo de reporte simulado.');
	}
}

class DenySupportPermissions extends PermisosService {
	public function __construct() {
	}

	public function canSee(string $permissionKey, ?string $uid = null): bool {
		return false;
	}
}

function assertSupport(bool $condition, string $name): void {
	if (!$condition) throw new RuntimeException('Falló: ' . $name);
	echo 'ok - ', $name, PHP_EOL;
}

$server = OC::$server;
$db = $server->get(IDBConnection::class);
$users = $server->get(IUserManager::class);
$session = $server->get(IUserSession::class);
$employees = $server->get(empleadosMapper::class);
$devices = $server->get(InventarioComputoMapper::class);
$supports = $server->get(SoporteHistorialMapper::class);
$movements = $server->get(InventarioMovimientoMapper::class);
$reports = $server->get(reportetiempoMapper::class);
$activities = $server->get(actividadesMapper::class);
$integration = $server->get(SoporteReporteTiempoService::class);
$service = $server->get(InventarioMovimientoService::class);

$controllerReflection = new ReflectionClass(InventarioController::class);
$controller = $controllerReflection->newInstanceWithoutConstructor();
$controllerReflection->getProperty('permisosService')->setValue($controller, new DenySupportPermissions());
$controllerReflection->getProperty('movimientoService')->setValue($controller, $service);
assertSupport(
	$controller->CrearSoporteEquipo(1, null, 'Sin permiso', null, null, null, 'diagnostico', 'media', 30)->getStatus() === 403,
	'usuario sin permiso recibe 403 y no crea soporte'
);

$deviceQuery = $db->getQueryBuilder();
$deviceQuery->select('id_equipo', 'id_empleado')->from('inventario_computo')->setMaxResults(1);
$device = $deviceQuery->executeQuery()->fetch();
$deviceId = (int)($device['id_equipo'] ?? 0);
$deviceEmployeeId = (int)($device['id_empleado'] ?? 0);
$employeeQuery = $db->getQueryBuilder();
$employeeQuery->select('Id_empleados', 'Id_user')->from('empleados')
	->where($employeeQuery->expr()->isNotNull('Id_user'))->setMaxResults(100);
$employeeRows = $employeeQuery->executeQuery()->fetchAll();
$employee = null;
foreach ($employeeRows as $candidate) {
	if ($users->get((string)$candidate['Id_user']) !== null && (int)$candidate['Id_empleados'] !== $deviceEmployeeId) {
		$employee = $candidate;
		break;
	}
}
if ($employee === null || $deviceId <= 0) {
	throw new RuntimeException('Se requiere un empleado con usuario y un equipo para la prueba real.');
}

$uid = (string)$employee['Id_user'];
$session->setUser($users->get($uid));
$beforeQuery = $db->getQueryBuilder();
$beforeQuery->selectAlias($beforeQuery->createFunction('COALESCE(MAX(id), 0)'), 'max_id')->from('inv_movimientos');
$movementBefore = (int)$beforeQuery->executeQuery()->fetchOne();
$createdMovementIds = [];
$costBefore = $reports->getCostosPorLider(8, 8, 2026, [(int)$employee['Id_empleados']]);
$employeeCostBefore = array_values(array_filter(
	$costBefore['empleados'],
	static fn(array $row): bool => (int)$row['id_empleado'] === (int)$employee['Id_empleados']
))[0] ?? ['total_minutos' => 0, 'minutos_cargables' => 0];

try {
	$result = $service->registrarSoporte(
		$deviceId,
		'Validación integrada ' . bin2hex(random_bytes(4)),
		'diagnostico',
		'media',
		null,
		75,
		'2026-08-01 10:30:00'
	);
	$idSupport = (int)$result['id_soporte'];
	$idReport = (int)$result['id_reporte'];
	$report = $reports->findByOrigin(SoporteReporteTiempoService::ORIGEN, $idSupport);
	assertSupport($report !== null && (int)$report['id_reporte'] === $idReport, 'crear soporte crea reporte relacionado');
	assertSupport((int)$report['id_empleado'] === (int)$employee['Id_empleados'], 'reporte pertenece al técnico autenticado');
	assertSupport((int)$report['id_empleado'] !== $deviceEmployeeId, 'reporte no pertenece al empleado dueño del equipo');
	assertSupport(
		(int)$report['tiempo_registrado'] === 75
		&& $report['id_cliente'] === null
		&& ($report['tipo_trabajo'] ?? null) === reportetiempo::TIPO_INTERNO,
		'duración, cliente nulo y tipo interno son correctos',
	);
	assertSupport($report['fecha_registro'] === '2026-08-01', 'fecha del reporte corresponde a la fecha del soporte');

	$activity = $activities->findById((int)$report['id_actividad']);
	assertSupport(($activity[0]['clave_sistema'] ?? '') === 'soporte_ti' && (int)$activity[0]['cargable'] === 0, 'actividad estable es no cargable');
	$personalRows = $reports->findById((int)$employee['Id_empleados'], 0, 0, 8, 8, 2026);
	$personalReport = array_values(array_filter($personalRows, static fn(array $row): bool => (int)$row['id_reporte'] === $idReport));
	assertSupport(count($personalReport) === 1 && (int)$personalReport[0]['cargable'] === 0, 'reporte aparece en vista personal como no cargable');
	$visibleRows = $reports->findAllByEmployeeIds([(int)$employee['Id_empleados']], 0, 0, 8, 8, 2026);
	assertSupport(count(array_filter($visibleRows, static fn($row): bool => (int)$row->getIdReporte() === $idReport)) === 1, 'reporte aparece en alcance administrativo autorizado');
	$activityHours = $reports->getHorasPorActividad(8, 8, 2026, [(int)$employee['Id_empleados']]);
	assertSupport(count(array_filter($activityHours, static fn(array $row): bool => (int)$row['id_actividad'] === (int)$report['id_actividad'])) === 1, 'soporte aparece en horas por actividad');
	$projectHours = $reports->getHorasPorProyecto(8, 8, 2026, [(int)$employee['Id_empleados']]);
	assertSupport(count(array_filter($projectHours, static fn(array $row): bool => (int)$row['id_cliente'] === 0)) === 0, 'soporte no aparece como cliente externo');
	$costAfter = $reports->getCostosPorLider(8, 8, 2026, [(int)$employee['Id_empleados']]);
	$employeeCostAfter = array_values(array_filter(
		$costAfter['empleados'],
		static fn(array $row): bool => (int)$row['id_empleado'] === (int)$employee['Id_empleados']
	))[0];
	assertSupport(
		(float)$employeeCostAfter['total_minutos'] - (float)$employeeCostBefore['total_minutos'] === 75.0
		&& (float)$employeeCostAfter['minutos_cargables'] === (float)$employeeCostBefore['minutos_cargables']
		&& (float)$employeeCostAfter['minutos_internos'] - (float)($employeeCostBefore['minutos_internos'] ?? 0) === 75.0,
		'soporte aumenta costo laboral y ocupación sin aumentar tiempo cargable'
	);
	$reportController = $server->get(reportetiempoController::class);
	assertSupport($reportController->deleteReport($idReport)->getStatus() === 409, 'endpoint bloquea eliminación directa del reporte de soporte');

	$duplicate = $service->registrarSoporte(
		$deviceId,
		(string)$supports->findById($idSupport)['detalles'],
		'diagnostico',
		'media',
		null,
		75,
		'2026-08-01 10:30:00'
	);
	assertSupport($duplicate['duplicado'] === true && (int)$duplicate['id_reporte'] === $idReport, 'petición duplicada reutiliza soporte y reporte');

	$service->actualizarSoporte($idSupport, [
		'accion' => 'diagnostico · media',
		'detalles' => 'Descripción actualizada',
		'fecha' => '2026-08-01 11:00:00',
		'duracion_minutos' => 90,
	]);
	$updated = $reports->findByOrigin(SoporteReporteTiempoService::ORIGEN, $idSupport);
	assertSupport((int)$updated['id_reporte'] === $idReport && (int)$updated['tiempo_registrado'] === 90, 'editar actualiza el mismo reporte');

	$service->eliminarSoporte($idSupport);
	assertSupport($supports->findById($idSupport) === null && $reports->findByOrigin('soporte_ti', $idSupport) === null, 'eliminar soporte elimina reporte exacto');

	$manual = new reportetiempo();
	$manual->setIdEmpleado((int)$employee['Id_empleados']);
	$manual->setIdActividad((int)$activity[0]['id_actividad']);
	$manual->setTiempoRegistrado(15.0);
	$manual->setFechaRegistro('2026-08-01');
	$manual->setDescripcion('Reporte manual temporal');
	$manual = $reports->insert($manual);
	$manualId = (int)$manual->getId();
	assertSupport($reportController->deleteReport($manualId)->getStatus() === 200 && $reports->findReportById($manualId) === null, 'reporte manual conserva eliminación normal');

	$countBefore = $supports->findByEquipo($deviceId);
	$failing = new InventarioMovimientoService($db, $session, $devices, $movements, $employees, $supports, new FailingSupportReportIntegration());
	$failed = false;
	try {
		$failing->registrarSoporte($deviceId, 'Fallo transaccional', 'reparacion', 'alta', null, 30, '2026-08-01 12:00:00');
	} catch (RuntimeException $e) {
		$failed = str_contains($e->getMessage(), 'simulado');
	}
	assertSupport($failed && count($supports->findByEquipo($deviceId)) === count($countBefore), 'fallo de reporte revierte soporte y movimiento');
} finally {
	$cleanupSelect = $db->getQueryBuilder();
	$cleanupSelect->select('id')->from('inv_movimientos')
		->where($cleanupSelect->expr()->gt('id', $cleanupSelect->createNamedParameter($movementBefore)))
		->andWhere($cleanupSelect->expr()->eq('id_equipo', $cleanupSelect->createNamedParameter($deviceId)));
	$createdMovementIds = array_map('intval', array_column($cleanupSelect->executeQuery()->fetchAll(), 'id'));
	if ($createdMovementIds !== []) {
		$cleanup = $db->getQueryBuilder();
		$cleanup->delete('inv_movimientos')->where($cleanup->expr()->in('id', $cleanup->createNamedParameter($createdMovementIds, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT_ARRAY)))->executeStatement();
	}
	$session->setUser(null);
}

echo '1..18', PHP_EOL;
