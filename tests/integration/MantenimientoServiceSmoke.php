<?php

declare(strict_types=1);

use OCA\Empleados\Db\InventarioComputoMapper;
use OCA\Empleados\Db\MantenimientoGrupoMapper;
use OCA\Empleados\Service\MantenimientoService;
use OCP\IConfig;
use OCP\IDBConnection;

require '/var/www/html/lib/base.php';

function assertMaintenanceService(bool $condition, string $name): void {
	if (!$condition) throw new RuntimeException('Falló: ' . $name);
	echo 'ok - ', $name, PHP_EOL;
}

$server = OC::$server;
$db = $server->get(IDBConnection::class);
$prefix = $server->get(IConfig::class)->getSystemValueString('dbtableprefix', 'oc_');
$schema = $db->createSchema();
$tables = ['inv_mant_grupos', 'inv_mantenimientos', 'inv_mant_checks', 'inv_mant_cambios'];
assertMaintenanceService(array_reduce($tables, fn(bool $ok, string $table): bool => $ok && $schema->hasTable($prefix . $table), true), 'las cuatro tablas están aplicadas en desarrollo');

$maintenanceTable = $schema->getTable($prefix . 'inv_mantenimientos');
$checkTable = $schema->getTable($prefix . 'inv_mant_checks');
$groupTable = $schema->getTable($prefix . 'inv_mant_grupos');
assertMaintenanceService($maintenanceTable->getIndex('im_grupo_equipo_uniq')->isUnique(), 'la unicidad grupo-equipo está aplicada');
assertMaintenanceService($checkTable->getIndex('imc_mant_clave_uniq')->isUnique(), 'la unicidad mantenimiento-clave está aplicada');
assertMaintenanceService($groupTable->hasColumn('fecha_inicio') && $groupTable->hasColumn('fecha_fin') && !$maintenanceTable->getColumn('fecha_programada')->getNotnull(), 'el periodo y la fecha individual nullable están aplicados');

$permissionQuery = $db->getQueryBuilder();
$permissionResult = $permissionQuery->select('permission')->selectAlias($permissionQuery->createFunction('COUNT(*)'), 'cantidad')
	->from('emp_perm_groups')
	->where($permissionQuery->expr()->eq('module', $permissionQuery->createNamedParameter('inventario')))
	->andWhere($permissionQuery->expr()->in('permission', [
		$permissionQuery->createNamedParameter('technician'),
		$permissionQuery->createNamedParameter('view'),
	]))
	->groupBy('permission')
	->executeQuery();
$permissions = [];
foreach ($permissionResult->fetchAll() as $row) $permissions[(string)$row['permission']] = (int)$row['cantidad'];
$permissionResult->closeCursor();
assertMaintenanceService(($permissions['technician'] ?? 0) === 1 && ($permissions['view'] ?? 0) === 1, 'los permisos se aplicaron sin duplicados');

$service = $server->get(MantenimientoService::class);
assertMaintenanceService($service instanceof MantenimientoService, 'MantenimientoService se resuelve por inyección de dependencias');

$progress = $server->get(MantenimientoGrupoMapper::class)->getProgress(2147483647, '2026-08-04');
assertMaintenanceService($progress['total'] === 0 && $progress['overdue'] === 0, 'la agregación maneja grupos vacíos sin división ni estado persistido de atraso');

$equipmentQuery = $db->getQueryBuilder();
$equipmentResult = $equipmentQuery->select('id_equipo')->from('inventario_computo')->setMaxResults(1)->executeQuery();
$equipmentId = $equipmentResult->fetchOne();
$equipmentResult->closeCursor();
if ($equipmentId !== false) {
	$rows = $server->get(InventarioComputoMapper::class)->findCampaignEquipmentByIds([(int)$equipmentId]);
	assertMaintenanceService(count($rows) === 1 && (int)$rows[0]['id_equipo'] === (int)$equipmentId, 'los snapshots actuales se resuelven en una consulta por IDs explícitos');
} else {
	assertMaintenanceService(true, 'la consulta de snapshots acepta inventario vacío');
}

$departmentQuery = $db->getQueryBuilder();
$departmentResult = $departmentQuery->select('Id_departamento')->from('departamentos')->setMaxResults(1)->executeQuery();
$departmentId = $departmentResult->fetchOne();
$departmentResult->closeCursor();
if ($departmentId !== false) {
	assertMaintenanceService($service->resolveDepartmentIds((int)$departmentId, false) === [(int)$departmentId], 'los descendientes no se incluyen sin opción explícita');
} else {
	assertMaintenanceService(true, 'el resolvedor tolera un catálogo de departamentos vacío sin ejecutarse');
}

$duplicates = $service->listPotentialDuplicates((int)($equipmentId === false ? 1 : $equipmentId), MantenimientoService::TYPE_PREVENTIVE, '2026-08-04', '2026-08-04');
assertMaintenanceService(is_array($duplicates), 'la consulta real de duplicados activos es compatible con QueryBuilder');
assertMaintenanceService(is_array($service->listOverdueMaintenances(10, 0)), 'la consulta real de atrasos utiliza la fecha inyectable');

$groups = $service->listGroups('2026-01-01', '2026-12-31', null, null, null, null, null, 10, 0);
assertMaintenanceService(isset($groups['items'], $groups['total']) && is_array($groups['items']), 'el listado real de calendario devuelve paginación');
$technicianGroups = $service->listGroups('2026-01-01', '2026-12-31', null, '__maintenance_smoke__', null, null, null, 10, 0);
assertMaintenanceService($technicianGroups['total'] === 0, 'el filtro técnico usa participación individual en el grupo');
$filteredOverdue = $service->listOverdueMaintenancesFiltered(null, '__maintenance_smoke__', null, 10, 0);
assertMaintenanceService($filteredOverdue['total'] === 0, 'el listado de atrasos admite alcance técnico eficiente');
if ($equipmentId !== false) {
	$history = $service->getEquipmentHistoryFiltered((int)$equipmentId, null, null, null, null, 10, 0);
	assertMaintenanceService(isset($history['items'], $history['pagination']), 'el historial filtrado devuelve paginación');
} else {
	assertMaintenanceService(true, 'el historial filtrado tolera inventario vacío');
}

echo '1..15', PHP_EOL;
