<?php

declare(strict_types=1);

use Doctrine\DBAL\Schema\Schema;
use OCA\Empleados\Db\actividades;
use OCA\Empleados\Db\reportetiempo;
use OCA\Empleados\Exception\ReporteTiempoRuleException;
use OCA\Empleados\Migration\Version2035Date20260805090000;
use OCA\Empleados\Service\ReporteTiempoRules;
use OCP\AppFramework\Http;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;

require '/var/www/html/lib/base.php';

function assertInternalWork(bool $condition, string $name): void {
	if (!$condition) throw new RuntimeException('Falló: ' . $name);
	echo 'ok - ', $name, PHP_EOL;
}

$server = OC::$server;
$db = $server->get(IDBConnection::class);
$config = $server->get(IConfig::class);
$prefix = $config->getSystemValueString('dbtableprefix', 'oc_');
$installed = $db->createSchema();
$activities = $installed->getTable($prefix . 'empleados_actividades');
$reports = $installed->getTable($prefix . 'empleados_rep_tiempos');
$areas = $installed->getTable($prefix . 'empleados_actividad_areas');

assertInternalWork($activities->hasColumn('tipo_actividad') && $activities->hasColumn('alcance'), 'actividades contiene tipo y alcance');
assertInternalWork($reports->hasColumn('tipo_trabajo') && $reports->hasIndex('emp_rep_tipo_idx'), 'reportes contiene tipo de trabajo indexado');
assertInternalWork($areas->hasIndex('emp_act_area_act_idx') && $areas->hasIndex('emp_act_area_dep_idx'), 'relación de áreas está indexada en ambos sentidos');
assertInternalWork($areas->hasIndex('emp_act_area_unique') && $areas->getIndex('emp_act_area_unique')->isUnique(), 'relación actividad-área no admite duplicados');

$supportQb = $db->getQueryBuilder();
$supportQb->select('tipo_actividad', 'alcance', 'cargable')->from('empleados_actividades')
	->where($supportQb->expr()->eq('clave_sistema', $supportQb->createNamedParameter('soporte_ti')))
	->setMaxResults(1);
$support = $supportQb->executeQuery()->fetch();
assertInternalWork(
	$support !== false
	&& ($support['tipo_actividad'] ?? null) === actividades::TIPO_INTERNO
	&& (int)($support['cargable'] ?? 1) === 0,
	'Soporte TI quedó como actividad interna no cargable',
);

$connection = $db instanceof OC\DB\ConnectionAdapter ? $db->getInner() : $db;
$definition = new OC\DB\SchemaWrapper($connection, new Schema());
$activityDefinition = $definition->createTable('empleados_actividades');
$activityDefinition->addColumn('id_actividad', 'integer');
$reportDefinition = $definition->createTable('empleados_rep_tiempos');
$reportDefinition->addColumn('id_reporte', 'integer');
$migration = new Version2035Date20260805090000($db);
$output = new class implements IOutput {
	public function debug(string $message): void {}
	public function info($message): void {}
	public function warning($message): void {}
	public function startProgress($max = 0): void {}
	public function advance($step = 1, $description = ''): void {}
	public function finishProgress(): void {}
};
$schemaClosure = static fn() => $definition;
$migration->changeSchema($output, $schemaClosure, []);
$migration->changeSchema($output, $schemaClosure, []);
assertInternalWork($definition->hasTable('empleados_actividad_areas'), 'definición de migración es idempotente');

$internal = [
	'id_actividad' => 14,
	'tipo_actividad' => actividades::TIPO_INTERNO,
	'alcance' => actividades::ALCANCE_AREAS,
	'area_ids' => [3, 9],
	'cargable' => 0,
	'clave_sistema' => null,
];
assertInternalWork(
	ReporteTiempoRules::validateManual(reportetiempo::TIPO_INTERNO, null, $internal, 9)
	=== [null, reportetiempo::ORIGEN_MANUAL_INTERNO],
	'una actividad interna acepta cualquiera de sus áreas configuradas',
);

$forbidden = false;
try {
	ReporteTiempoRules::validateManual(reportetiempo::TIPO_INTERNO, null, $internal, 8);
} catch (ReporteTiempoRuleException $e) {
	$forbidden = $e->getHttpStatus() === Http::STATUS_FORBIDDEN;
}
assertInternalWork($forbidden, 'payload manipulado de otra área se rechaza con 403');
assertInternalWork(
	ReporteTiempoRules::normalizeExistingType(['id_cliente' => null, 'origen' => 'soporte_ti']) === reportetiempo::TIPO_INTERNO
	&& ReporteTiempoRules::normalizeExistingType(['id_cliente' => 99999]) === reportetiempo::TIPO_AUSENCIA,
	'compatibilidad heredada distingue soporte interno y ausencia',
);

echo '1..9', PHP_EOL;
