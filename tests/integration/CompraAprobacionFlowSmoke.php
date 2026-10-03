<?php

declare(strict_types=1);

use OCA\Empleados\Db\CompraAutorizacionMapper;
use OCA\Empleados\Controller\CompraSolicitudController;
use OCA\Empleados\Db\CompraDetalleMapper;
use OCA\Empleados\Db\CompraHistorial;
use OCA\Empleados\Db\CompraHistorialMapper;
use OCA\Empleados\Db\CompraSolicitudMapper;
use OCA\Empleados\Db\empleadosMapper;
use OCA\Empleados\Service\CompraFolioService;
use OCA\Empleados\Service\CompraNotificacionService;
use OCA\Empleados\Service\CompraPermisosService;
use OCA\Empleados\Service\CompraSolicitudService;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;

require '/var/www/html/lib/base.php';

class SilentCompraNotifications extends CompraNotificacionService {
	public function __construct() {
	}

	public function notificarAprobadorActual($solicitud, string $approverUid, string $role): void {
	}

	public function notificarSolicitudAutorizada($solicitud, string $aprobadorUserId, ?string $comentario = null): void {
	}

	public function notificarSolicitudRechazada($solicitud, string $aprobadorUserId, ?string $comentario = null): void {
	}
}

class FailingCompraHistoryMapper extends CompraHistorialMapper {
	public function __construct() {
	}

	public function insertHistorial(
		int $idSolicitud,
		string $accion,
		?string $estadoAnterior,
		?string $estadoNuevo,
		?string $comentario,
		?array $metadata,
		?string $createdBy
	): CompraHistorial {
		throw new RuntimeException('Fallo de historial simulado.');
	}
}

function assertFlow(bool $condition, string $name): void {
	if (!$condition) {
		throw new RuntimeException('Falló: ' . $name);
	}
	echo 'ok - ', $name, PHP_EOL;
}

function createRequest(CompraSolicitudMapper $mapper, int $employeeId, string $folio): int {
	$request = $mapper->insertSolicitud([
		'folio' => $folio,
		'id_user' => 'admin',
		'id_empleado' => (string)$employeeId,
		'titulo' => 'Solicitud de prueba transaccional',
		'moneda' => 'MXN',
		'prioridad' => 'normal',
		'estado' => CompraSolicitudService::ESTADO_BORRADOR,
		'created_by' => 'admin',
		'updated_by' => 'admin',
	]);
	return (int)$request->getIdSolicitud();
}

function historyCount(IDBConnection $db, int $idSolicitud): int {
	$qb = $db->getQueryBuilder();
	$qb->select($qb->createFunction('COUNT(*)'))
		->from('emp_comp_historial')
		->where($qb->expr()->eq('id_solicitud', $qb->createNamedParameter($idSolicitud)));
	$result = $qb->executeQuery();
	$count = (int)$result->fetchOne();
	$result->closeCursor();
	return $count;
}

$server = OC::$server;
$db = $server->get(IDBConnection::class);
$userManager = $server->get(IUserManager::class);
$solicitudes = $server->get(CompraSolicitudMapper::class);
$detalles = $server->get(CompraDetalleMapper::class);
$historial = $server->get(CompraHistorialMapper::class);
$autorizaciones = $server->get(CompraAutorizacionMapper::class);
$empleados = $server->get(empleadosMapper::class);
$folioService = $server->get(CompraFolioService::class);
$permissions = $server->get(CompraPermisosService::class);
$notifications = new SilentCompraNotifications();
$service = new CompraSolicitudService(
	$solicitudes,
	$detalles,
	$historial,
	$folioService,
	$permissions,
	$empleados,
	$notifications,
	$autorizaciones,
	$db,
	$userManager
);
$controller = new CompraSolicitudController(
	'empleados',
	$server->get(IRequest::class),
	$service,
	$permissions,
	$server->get(IUserSession::class)
);

$approverUids = [];
foreach ($userManager->search('', 100) as $user) {
	if ($user->isEnabled() && $user->getUID() !== 'admin') {
		$approverUids[] = $user->getUID();
	}
	if (count($approverUids) === 2) {
		break;
	}
}
if (count($approverUids) < 2 || $userManager->get('admin') === null) {
	throw new RuntimeException('Se requieren admin y dos usuarios habilitados para la prueba dirigida.');
}

$employeeRows = $empleados->GetMyEmployeeInfo($approverUids[0]);
if ($employeeRows === []) {
	throw new RuntimeException('El primer aprobador no tiene registro de empleado.');
}
$employeeId = (int)$employeeRows[0]['Id_empleados'];
$testPrefix = 'TEST-WF-' . bin2hex(random_bytes(4));

$db->beginTransaction();
try {
	$update = $db->getQueryBuilder();
	$update->update('empleados')
		->set('Id_gerente', $update->createNamedParameter($approverUids[0]))
		->set('Id_socio', $update->createNamedParameter($approverUids[1]))
		->where($update->expr()->eq('Id_empleados', $update->createNamedParameter($employeeId)))
		->executeStatement();

	$id = createRequest($solicitudes, $employeeId, $testPrefix . '-1');
	$service->enviarAutorizacion($id, 'admin');
	$flow = $service->obtenerFlujo($id, 'admin');
	assertFlow($flow['aprobador_actual']['id_autorizador'] === $approverUids[0], 'envío asigna al gerente');

	$forbidden = false;
	try {
		$service->autorizar($id, 'admin');
	} catch (Exception $e) {
		$forbidden = str_contains($e->getMessage(), 'permiso');
		$errorResponse = (new ReflectionMethod(CompraSolicitudController::class, 'errorResponse'))
			->invoke($controller, $e);
	}
	assertFlow(
		$forbidden && isset($errorResponse) && $errorResponse->getStatus() === 403,
		'usuario distinto del aprobador recibe 403'
	);

	$service->autorizar($id, $approverUids[0]);
	$flow = $service->obtenerFlujo($id, $approverUids[0]);
	assertFlow(
		$flow['estado'] === CompraSolicitudService::ESTADO_PENDIENTE_AUTORIZACION
		&& $flow['aprobador_actual']['id_autorizador'] === $approverUids[1],
		'gerente avanza a socio'
	);

	$service->autorizar($id, $approverUids[1]);
	assertFlow(
		$solicitudes->find($id)->getEstado() === CompraSolicitudService::ESTADO_AUTORIZADA,
		'socio completa la solicitud'
	);

	$historyBefore = historyCount($db, $id);
	$duplicateFailed = false;
	try {
		$service->autorizar($id, $approverUids[1]);
	} catch (Exception $e) {
		$duplicateFailed = true;
	}
	assertFlow($duplicateFailed && historyCount($db, $id) === $historyBefore, 'segunda aprobación no duplica historial');

	$rejectedId = createRequest($solicitudes, $employeeId, $testPrefix . '-2');
	$service->enviarAutorizacion($rejectedId, 'admin');
	$service->rechazar($rejectedId, $approverUids[0], 'Rechazo de prueba');
	assertFlow(
		$solicitudes->find($rejectedId)->getEstado() === CompraSolicitudService::ESTADO_RECHAZADA
		&& $autorizaciones->findCurrent($rejectedId) === null,
		'rechazo detiene el flujo'
	);

	$failedId = createRequest($solicitudes, $employeeId, $testPrefix . '-3');
	$service->enviarAutorizacion($failedId, 'admin');
	$failedHistoryCount = historyCount($db, $failedId);
	$failingService = new CompraSolicitudService(
		$solicitudes,
		$detalles,
		new FailingCompraHistoryMapper(),
		$folioService,
		$permissions,
		$empleados,
		$notifications,
		$autorizaciones,
		$db,
		$userManager
	);
	$failed = false;
	try {
		$failingService->autorizar($failedId, $approverUids[0]);
	} catch (RuntimeException $e) {
		$failed = true;
	}
	assertFlow(
		$failed
		&& $solicitudes->find($failedId)->getEstado() === CompraSolicitudService::ESTADO_PENDIENTE_AUTORIZACION
		&& $autorizaciones->findCurrent($failedId)?->getIdAutorizador() === $approverUids[0]
		&& historyCount($db, $failedId) === $failedHistoryCount,
		'fallo de historial revierte estado y etapa'
	);

	$db->rollBack();
} catch (Throwable $e) {
	$db->rollBack();
	throw $e;
}

echo '1..7', PHP_EOL;
