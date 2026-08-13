<?php

declare(strict_types=1);

use Doctrine\DBAL\Schema\Schema;
use OCA\Empleados\Db\MovimientoArchivo;
use OCA\Empleados\Db\MovimientoArchivoMapper;
use OCA\Empleados\Migration\Version2036Date20260805120000;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;

require '/var/www/html/lib/base.php';

function assertMovimientoArchivo(bool $condition, string $name): void {
	if (!$condition) {
		throw new RuntimeException('Falló: ' . $name);
	}
	echo 'ok - ', $name, PHP_EOL;
}

$server = OC::$server;
$db = $server->get(IDBConnection::class);
$config = $server->get(IConfig::class);
$prefix = $config->getSystemValueString('dbtableprefix', 'oc_');
$installed = $db->createSchema();
$table = $installed->getTable($prefix . 'empleados_mov_archivos');

$requiredColumns = [
	'id', 'id_empleado', 'uid_actor', 'tipo_evento', 'file_id', 'storage_id',
	'ruta_anterior', 'ruta_actual', 'nombre_archivo', 'mime_type', 'tamanio',
	'es_carpeta', 'fecha_evento', 'remote_addr', 'user_agent',
];
foreach ($requiredColumns as $column) {
	assertMovimientoArchivo($table->hasColumn($column), 'existe columna ' . $column);
}

foreach (['emp_mov_arc_uid_idx', 'emp_mov_arc_emp_idx', 'emp_mov_arc_tipo_idx', 'emp_mov_arc_fecha_idx', 'emp_mov_arc_file_idx'] as $index) {
	assertMovimientoArchivo($table->hasIndex($index), 'existe índice ' . $index);
}

$connection = $db instanceof OC\DB\ConnectionAdapter ? $db->getInner() : $db;
$definition = new OC\DB\SchemaWrapper($connection, new Schema());
$migration = new Version2036Date20260805120000();
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
assertMovimientoArchivo($definition->hasTable('empleados_mov_archivos'), 'migración idempotente');

$mapper = $server->get(MovimientoArchivoMapper::class);
$movement = new MovimientoArchivo();
$movement->setUidActor('__empleados_audit_smoke__');
$movement->setTipoEvento('creado');
$movement->setNombreArchivo('smoke.txt');
$movement->setEsCarpeta(false);
$movement->setFechaEvento(date('Y-m-d H:i:s'));
$inserted = $mapper->insert($movement);

try {
	$read = $mapper->findById((int)$inserted->getId());
	assertMovimientoArchivo($read->getUidActor() === '__empleados_audit_smoke__', 'mapper inserta y recupera un movimiento');
} finally {
	$mapper->delete($inserted);
}

echo '1..22', PHP_EOL;
