<?php

declare(strict_types=1);

use OCA\Empleados\Db\actividades;
use OCA\Empleados\Db\actividadesMapper;
use OCA\Empleados\Db\reportetiempo;
use OCA\Empleados\Db\reportetiempoMapper;
use OCA\Empleados\Service\ReporteTiempoRules;
use OCP\IDBConnection;

require '/var/www/html/lib/base.php';

function assertInternalActivity(bool $condition, string $name): void {
	if (!$condition) throw new RuntimeException('Falló: ' . $name);
	echo 'ok - ', $name, PHP_EOL;
}

$server = OC::$server;
$db = $server->get(IDBConnection::class);
$activityMapper = $server->get(actividadesMapper::class);
$reportMapper = $server->get(reportetiempoMapper::class);
$employeeQb = $db->getQueryBuilder();
$employee = $employeeQb->select('Id_empleados', 'Id_departamento')->from('empleados')
	->where($employeeQb->expr()->isNotNull('Id_departamento'))->setMaxResults(1)->executeQuery()->fetch();
if ($employee === false) throw new RuntimeException('La prueba requiere al menos un empleado con área.');
$employeeId = (int)$employee['Id_empleados'];
$employeeArea = (int)$employee['Id_departamento'];
$otherAreaQb = $db->getQueryBuilder();
$otherArea = $otherAreaQb->select('Id_departamento')->from('departamentos')
	->where($otherAreaQb->expr()->neq('Id_departamento', $otherAreaQb->createNamedParameter($employeeArea)))
	->setMaxResults(1)->executeQuery()->fetchOne();
$createdOtherArea = false;
if ($otherArea === false) {
	$now = date('Y-m-d H:i:s');
	$insertArea = $db->getQueryBuilder();
	$insertArea->insert('departamentos')->values([
		'Id_padre' => $insertArea->createNamedParameter(null),
		'Nombre' => $insertArea->createNamedParameter('Área temporal prueba trabajo interno'),
		'created_at' => $insertArea->createNamedParameter($now),
		'updated_at' => $insertArea->createNamedParameter($now),
	])->executeStatement();
	$otherArea = (int)$db->lastInsertId('departamentos');
	$createdOtherArea = true;
}
$otherArea = (int)$otherArea;

$ids = [];
$reportId = null;
try {
	$clientId = $activityMapper->createActivity('Prueba cliente temporal', null, 30, true, actividades::TIPO_CLIENTE, actividades::ALCANCE_GLOBAL, []);
	$ids[] = $clientId;
	$globalId = $activityMapper->createActivity('Prueba interna global temporal', null, 30, true, actividades::TIPO_INTERNO, actividades::ALCANCE_GLOBAL, []);
	$ids[] = $globalId;
	$ownAreaId = $activityMapper->createActivity('Prueba interna de área temporal', null, 30, true, actividades::TIPO_INTERNO, actividades::ALCANCE_AREAS, [$employeeArea]);
	$ids[] = $ownAreaId;
	$otherAreaId = $activityMapper->createActivity('Prueba interna ajena temporal', null, 30, false, actividades::TIPO_INTERNO, actividades::ALCANCE_AREAS, [$otherArea]);
	$ids[] = $otherAreaId;
	$multiAreaId = $activityMapper->createActivity('Prueba interna multiárea temporal', null, 30, false, actividades::TIPO_INTERNO, actividades::ALCANCE_AREAS, [$employeeArea, $otherArea]);
	$ids[] = $multiAreaId;

	$global = $activityMapper->findById($globalId)[0];
	$multi = $activityMapper->findById($multiAreaId)[0];
	assertInternalActivity((int)$global['cargable'] === 0 && $global['tipo_actividad'] === actividades::TIPO_INTERNO, 'actividad interna global se fuerza a no cargable');
	assertInternalActivity($multi['area_ids'] === [$employeeArea, $otherArea] || $multi['area_ids'] === [$otherArea, $employeeArea], 'actividad interna conserva varias áreas en tabla relacional');

	$visible = array_column($activityMapper->findManualAvailable($employeeArea), 'id_actividad');
	assertInternalActivity(in_array($clientId, $visible, true) && in_array($globalId, $visible, true), 'empleado ve actividades de cliente e internas globales');
	assertInternalActivity(in_array($ownAreaId, $visible, true) && in_array($multiAreaId, $visible, true), 'empleado ve actividades asignadas a su área');
	assertInternalActivity(!in_array($otherAreaId, $visible, true), 'empleado no ve actividad interna exclusiva de otra área');

	[$effectiveClient, $origin] = ReporteTiempoRules::validateManual(reportetiempo::TIPO_INTERNO, null, $multi, $employeeArea);
	$report = new reportetiempo();
	$report->setIdEmpleado($employeeId);
	$report->setIdCliente($effectiveClient);
	$report->setIdActividad($multiAreaId);
	$report->setTiempoRegistrado(15.0);
	$report->setFechaRegistro((new DateTimeImmutable())->format('Y-m-d'));
	$report->setDescripcion('Reporte interno temporal');
	$report->setTipoTrabajo(reportetiempo::TIPO_INTERNO);
	$report->setOrigen($origin);
	$report = $reportMapper->insert($report);
	$reportId = (int)$report->getId();
	$stored = $reportMapper->findReportById($reportId);
	assertInternalActivity($stored['id_cliente'] === null, 'reporte interno guarda cliente NULL y nunca cero');
	assertInternalActivity($stored['tipo_trabajo'] === reportetiempo::TIPO_INTERNO && $stored['origen'] === reportetiempo::ORIGEN_MANUAL_INTERNO, 'reporte interno manual guarda tipo y origen explícitos');
	$today = new DateTimeImmutable();
	$month = (int)$today->format('n');
	$year = (int)$today->format('Y');
	$summary = $reportMapper->getResumenGeneral($month, $month, $year, [$employeeId]);
	assertInternalActivity((float)$summary['minutos_internos'] >= 15 && (float)$summary['horas_cargables'] >= 0, 'resumen administrativo separa minutos internos y horas cargables');
	$breakdown = $reportMapper->getTrabajoInternoAgrupado($month, $month, $year, [$employeeId]);
	assertInternalActivity(count(array_filter($breakdown['por_actividad'], static fn(array $row): bool => (int)$row['id_actividad'] === $multiAreaId)) === 1, 'trabajo interno se agrupa por actividad y conserva desgloses administrativos');

	$reportMapper->updateReporte($reportId, $globalId, $employeeId, 'Reporte interno editado', 20, (new DateTimeImmutable())->format('Y-m-d'), null, reportetiempo::TIPO_INTERNO, reportetiempo::ORIGEN_MANUAL_INTERNO);
	$updated = $reportMapper->findReportById($reportId);
	assertInternalActivity((int)$updated['tiempo_registrado'] === 20 && $updated['id_cliente'] === null, 'reporte interno manual puede editarse conservando cliente nulo');
	$reportMapper->deleteById($reportId);
	$reportId = null;
	assertInternalActivity($reportMapper->findReportById((int)$report->getId()) === null, 'reporte interno manual puede eliminarse');
} finally {
	if ($reportId !== null) $reportMapper->deleteById($reportId);
	foreach (array_reverse($ids) as $id) $activityMapper->deleteById($id);
	if ($createdOtherArea) {
		$deleteArea = $db->getQueryBuilder();
		$deleteArea->delete('departamentos')
			->where($deleteArea->expr()->eq('Id_departamento', $deleteArea->createNamedParameter($otherArea)))
			->executeStatement();
	}
}

echo '1..11', PHP_EOL;
