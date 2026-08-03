<?php

declare(strict_types=1);

use OCA\Empleados\Db\actividadesMapper;
use OCA\Empleados\Db\SoporteHistorial;
use OCA\Empleados\Db\reportetiempo;
use OCA\Empleados\Db\reportetiempoMapper;
use OCA\Empleados\Service\SoporteReporteTiempoService;
use OCP\IConfig;
use OCP\IDBConnection;

require '/var/www/html/lib/base.php';

function assertSchema(bool $condition, string $name): void {
	if (!$condition) throw new RuntimeException('Falló: ' . $name);
	echo 'ok - ', $name, PHP_EOL;
}

$server = OC::$server;
$db = $server->get(IDBConnection::class);
$config = $server->get(IConfig::class);
$prefix = $config->getSystemValueString('dbtableprefix', 'oc_');
$schema = $db->createSchema();
$supportTable = $schema->getTable($prefix . 'soporte_historial');
$reportTable = $schema->getTable($prefix . 'empleados_rep_tiempos');
$activityTable = $schema->getTable($prefix . 'empleados_actividades');

assertSchema($supportTable->hasColumn('duracion_minutos') && !$supportTable->getColumn('duracion_minutos')->getNotnull(), 'duración nullable preserva soportes históricos');
assertSchema($reportTable->hasColumn('origen') && $reportTable->hasColumn('origen_id'), 'reporte contiene origen y origen_id');
assertSchema($activityTable->hasColumn('clave_sistema'), 'actividad contiene clave estable');
assertSchema($reportTable->hasIndex('emp_rep_origen_unique') && $reportTable->getIndex('emp_rep_origen_unique')->isUnique(), 'índice único evita reportes duplicados');

$activityMapper = $server->get(actividadesMapper::class);
$first = $activityMapper->ensureSystemActivity('soporte_ti', 'Soporte TI', 'Actividad interna');
$second = $activityMapper->ensureSystemActivity('soporte_ti', 'Nombre modificable', 'Actividad interna');
$activity = $activityMapper->findById($first);
assertSchema($first === $second && count($activity) === 1, 'actividad Soporte TI se asegura de forma idempotente');
assertSchema((int)$activity[0]['cargable'] === 0, 'actividad Soporte TI permanece no cargable');

$employeeQuery = $db->getQueryBuilder();
$employeeQuery->select('Id_empleados')->from('empleados')->setMaxResults(1);
$employeeId = (int)$employeeQuery->executeQuery()->fetchOne();
$originId = 2000000000 + random_int(1, 1000000);
$reportMapper = $server->get(reportetiempoMapper::class);
$duplicateBlocked = false;
try {
	$data = [
		'id_empleado' => $employeeId,
		'id_actividad' => $first,
		'descripcion' => 'Prueba temporal de unicidad',
		'tiempo_registrado' => 1,
		'fecha_registro' => '2026-08-01',
		'origen' => 'soporte_ti',
		'origen_id' => $originId,
	];
	$reportMapper->createIntegrated($data);
	try {
		$reportMapper->createIntegrated($data);
	} catch (Throwable) {
		$duplicateBlocked = true;
	}
} finally {
	$reportMapper->deleteByOrigin('soporte_ti', $originId);
}
assertSchema($duplicateBlocked, 'base de datos impide dos reportes para un soporte');

$supportReflection = new ReflectionClass(SoporteHistorial::class);
$reportReflection = new ReflectionClass(reportetiempo::class);
assertSchema(!$supportReflection->hasProperty('id') || $supportReflection->getProperty('id')->getDeclaringClass()->getName() !== SoporteHistorial::class, 'SoporteHistorial no redeclara id');
assertSchema(!$reportReflection->hasProperty('id') || $reportReflection->getProperty('id')->getDeclaringClass()->getName() !== reportetiempo::class, 'ReporteTiempo no redeclara id');
assertSchema(class_exists(SoporteReporteTiempoService::class), 'entidades y servicio cargan en Nextcloud');

echo '1..10', PHP_EOL;
