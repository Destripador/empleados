<?php

declare(strict_types=1);

use Doctrine\DBAL\Schema\Schema;
use OCA\Empleados\Db\InventarioComputoMapper;
use OCA\Empleados\Db\MantenimientoCambio;
use OCA\Empleados\Db\MantenimientoCambioMapper;
use OCA\Empleados\Db\MantenimientoChecklist;
use OCA\Empleados\Db\MantenimientoChecklistMapper;
use OCA\Empleados\Db\MantenimientoEquipo;
use OCA\Empleados\Db\MantenimientoEquipoMapper;
use OCA\Empleados\Db\MantenimientoGrupo;
use OCA\Empleados\Db\MantenimientoGrupoMapper;
use OCA\Empleados\Mantenimiento\ChecklistPreventivoCatalogo;
use OCA\Empleados\Migration\Version2033Date20260803220000;
use OCA\Empleados\Migration\Version2034Date20260804120000;
use OCP\IDBConnection;
use OCP\Migration\IOutput;

require '/var/www/html/lib/base.php';

function assertMaintenance(bool $condition, string $name): void {
	if (!$condition) {
		throw new RuntimeException('Falló: ' . $name);
	}
	echo 'ok - ', $name, PHP_EOL;
}

$db = OC::$server->get(IDBConnection::class);
$connection = $db instanceof OC\DB\ConnectionAdapter ? $db->getInner() : $db;
$schema = new OC\DB\SchemaWrapper($connection, new Schema());
$output = new class implements IOutput {
	public function debug(string $message): void {}
	public function info($message): void {}
	public function warning($message): void {}
	public function startProgress($max = 0): void {}
	public function advance($step = 1, $description = ''): void {}
	public function finishProgress(): void {}
};
$migration = new Version2033Date20260803220000($db);
$schemaClosure = static fn() => $schema;

$migration->changeSchema($output, $schemaClosure, []);
$migration->changeSchema($output, $schemaClosure, []);
$periodMigration = new Version2034Date20260804120000($db);
$periodMigration->changeSchema($output, $schemaClosure, []);
$periodMigration->changeSchema($output, $schemaClosure, []);

$expectedTables = ['inv_mant_grupos', 'inv_mantenimientos', 'inv_mant_checks', 'inv_mant_cambios'];
assertMaintenance(array_reduce($expectedTables, fn(bool $ok, string $table): bool => $ok && $schema->hasTable($table), true), 'la migración crea las cuatro tablas y es idempotente en schema');

$maintenances = $schema->getTable('inv_mantenimientos');
$checks = $schema->getTable('inv_mant_checks');
$groupEquipmentIndex = $maintenances->getIndex('im_grupo_equipo_uniq');
assertMaintenance(
	$maintenances->hasIndex('im_grupo_equipo_uniq')
	&& $groupEquipmentIndex->isUnique()
	&& $groupEquipmentIndex->getColumns() === ['id_grupo', 'id_equipo'],
	'un equipo es único dentro del grupo y puede repetirse en grupos distintos',
);
assertMaintenance($checks->hasIndex('imc_mant_clave_uniq') && $checks->getIndex('imc_mant_clave_uniq')->isUnique(), 'cada clave de checklist es única por mantenimiento');
assertMaintenance(!$maintenances->getColumn('id_empleado')->getNotnull() && !$maintenances->getColumn('id_departamento')->getNotnull(), 'snapshots de empleado y departamento aceptan null');
assertMaintenance(!$schema->getTable('inv_mant_grupos')->getColumn('id_departamento')->getNotnull(), 'campañas especiales aceptan departamento null');
$groups = $schema->getTable('inv_mant_grupos');
assertMaintenance($groups->hasColumn('fecha_inicio') && $groups->hasColumn('fecha_fin') && $groups->hasIndex('img_periodo_idx'), 'la migración de periodo agrega fechas e índice de cruce de forma idempotente');
assertMaintenance(!$maintenances->getColumn('fecha_programada')->getNotnull(), 'la fecha programada individual acepta null');
assertMaintenance(MantenimientoEquipo::ESTADOS_VALIDOS === ['pending', 'scheduled', 'in_progress', 'completed', 'rescheduled', 'cancelled', 'not_applicable'], 'estados operativos válidos definidos');
assertMaintenance(!in_array('overdue', MantenimientoEquipo::ESTADOS_VALIDOS, true) && $maintenances->hasColumn('estado') && !$maintenances->hasColumn('overdue'), 'overdue se calcula y no se persiste');

$mapperSource = file_get_contents(__DIR__ . '/../../lib/Db/MantenimientoEquipoMapper.php');
$groupMapperSource = file_get_contents(__DIR__ . '/../../lib/Db/MantenimientoGrupoMapper.php');
$inventoryMapperSource = file_get_contents(__DIR__ . '/../../lib/Db/InventarioComputoMapper.php');
$migrationSource = file_get_contents(__DIR__ . '/../../lib/Migration/Version2033Date20260803220000.php');
assertMaintenance(str_contains($mapperSource, 'findByDateRange') && str_contains($mapperSource, "orderBy('fecha_programada', 'ASC')"), 'consulta por rango ordenada disponible');
assertMaintenance(str_contains($groupMapperSource, 'COALESCE(g.fecha_inicio, g.fecha_programada)') && str_contains($groupMapperSource, 'COALESCE(g.fecha_fin, g.fecha_programada)'), 'el calendario consulta campañas por intersección y conserva fallback histórico');
$visibleRange = static fn(string $campaignStart, string $campaignEnd, string $visibleStart, string $visibleEnd): bool => $campaignStart <= $visibleEnd && $campaignEnd >= $visibleStart;
assertMaintenance(
	$visibleRange('2026-08-11', '2026-08-12', '2026-08-10', '2026-08-20')
	&& $visibleRange('2026-08-01', '2026-08-12', '2026-08-10', '2026-08-20')
	&& $visibleRange('2026-08-12', '2026-08-25', '2026-08-10', '2026-08-20')
	&& $visibleRange('2026-08-01', '2026-08-25', '2026-08-10', '2026-08-20')
	&& !$visibleRange('2026-08-21', '2026-08-25', '2026-08-10', '2026-08-20'),
	'la regla de intersección cubre dentro, ambos cruces, cobertura total y fuera de rango',
);
assertMaintenance(str_contains($groupMapperSource, 'SUM(CASE WHEN') && str_contains($groupMapperSource, "'overdue'"), 'progreso y atraso usan agregación SQL');
assertMaintenance(str_contains($mapperSource, 'findHistoryByEquipment') && str_contains($mapperSource, "orderBy('fecha_programada', 'DESC')"), 'historial por equipo se ordena del más reciente');
assertMaintenance(str_contains($inventoryMapperSource, "innerJoin('c', 'empleados', 'e'") && !str_contains($inventoryMapperSource, 'Equipo_asignado'), 'consulta departamental usa el custodio oficial');
assertMaintenance(str_contains($inventoryMapperSource, "'baja', 'inactivo', 'inactive', 'eliminado', 'deleted'") && str_contains($inventoryMapperSource, 'if (!$includeInactive)'), 'equipos inactivos se excluyen por defecto');
assertMaintenance(substr_count($migrationSource, 'insertPermissionIfMissing($permission)') === 1 && str_contains($migrationSource, "'permission' => 'technician'") && str_contains($migrationSource, "'permission' => 'view'"), 'permisos técnico y consulta usan inserción idempotente');

$classes = [
	MantenimientoGrupo::class, MantenimientoGrupoMapper::class,
	MantenimientoEquipo::class, MantenimientoEquipoMapper::class,
	MantenimientoChecklist::class, MantenimientoChecklistMapper::class,
	MantenimientoCambio::class, MantenimientoCambioMapper::class,
	InventarioComputoMapper::class, ChecklistPreventivoCatalogo::class,
];
assertMaintenance(array_reduce($classes, fn(bool $ok, string $class): bool => $ok && class_exists($class), true), 'nuevas clases cargan por PSR-4');
assertMaintenance(count(ChecklistPreventivoCatalogo::ITEMS) === 13 && array_column(ChecklistPreventivoCatalogo::ITEMS, 'orden') === range(10, 130, 10), 'catálogo preventivo contiene 13 elementos ordenados');

echo '1..19', PHP_EOL;
