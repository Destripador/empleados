<?php

declare(strict_types=1);

use OCA\Empleados\Db\departamentosMapper;
use OCA\Empleados\Db\InventarioComputoMapper;
use OCA\Empleados\Db\MantenimientoCambioMapper;
use OCA\Empleados\Db\MantenimientoChecklistMapper;
use OCA\Empleados\Db\MantenimientoEquipoMapper;
use OCA\Empleados\Db\MantenimientoGrupoMapper;
use OCA\Empleados\Exception\MantenimientoStorageException;
use OCA\Empleados\Mantenimiento\ChecklistPreventivoCatalogo;
use OCA\Empleados\Service\MantenimientoService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

require '/var/www/html/lib/base.php';

function assertMaintenanceCreation(bool $condition, string $name): void {
	if (!$condition) throw new RuntimeException('Falló: ' . $name);
	echo 'ok - ', $name, PHP_EOL;
}

function countMaintenanceRows(IDBConnection $db, string $table, string $field, int $id): int {
	$qb = $db->getQueryBuilder();
	$result = $qb->select($qb->createFunction('COUNT(*)'))->from($table)
		->where($qb->expr()->eq($field, $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
		->executeQuery();
	$count = (int)$result->fetchOne();
	$result->closeCursor();
	return $count;
}

function cleanupMaintenanceGroup(IDBConnection $db, int $groupId): void {
	$qb = $db->getQueryBuilder();
	$result = $qb->select('id')->from('inv_mantenimientos')
		->where($qb->expr()->eq('id_grupo', $qb->createNamedParameter($groupId, IQueryBuilder::PARAM_INT)))
		->executeQuery();
	$maintenanceIds = array_map('intval', array_column($result->fetchAll(), 'id'));
	$result->closeCursor();

	if ($maintenanceIds !== []) {
		$deleteChecks = $db->getQueryBuilder();
		$deleteChecks->delete('inv_mant_checks')
			->where($deleteChecks->expr()->in('id_mantenimiento', array_map(
				fn(int $id) => $deleteChecks->createNamedParameter($id, IQueryBuilder::PARAM_INT),
				$maintenanceIds,
			)))
			->executeStatement();
	}

	foreach (['inv_mant_cambios', 'inv_mantenimientos', 'inv_mant_grupos'] as $table) {
		$delete = $db->getQueryBuilder();
		$field = $table === 'inv_mant_grupos' ? 'id' : 'id_grupo';
		$delete->delete($table)
			->where($delete->expr()->eq($field, $delete->createNamedParameter($groupId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}
}

$server = OC::$server;
$db = $server->get(IDBConnection::class);
$service = $server->get(MantenimientoService::class);
$inventory = $server->get(InventarioComputoMapper::class);
$createdGroupIds = [];

$equipmentQuery = $db->getQueryBuilder();
$equipmentResult = $equipmentQuery->select('c.id_equipo')
	->from('inventario_computo', 'c')
	->innerJoin('c', 'empleados', 'e', $equipmentQuery->expr()->eq('e.Id_empleados', 'c.id_empleado'))
	->where($equipmentQuery->expr()->isNotNull('e.Id_departamento'))
	->setMaxResults(1)
	->executeQuery();
$equipmentId = $equipmentResult->fetchOne();
$equipmentResult->closeCursor();
if ($equipmentId === false) throw new RuntimeException('El smoke test requiere un equipo con custodio y departamento.');
$snapshot = $inventory->findCampaignEquipmentByIds([(int)$equipmentId])[0];
$departmentId = (int)$snapshot['id_departamento'];
$marker = 'maintenance-smoke-' . bin2hex(random_bytes(6));
$base = [
	'id_departamento' => $departmentId,
	'include_descendants' => false,
	'fecha_inicio' => '2099-01-01',
	'fecha_fin' => '2099-01-02',
	'hora_inicio' => null,
	'hora_fin' => null,
	'tecnico_uid' => null,
	'descripcion' => 'Prueba transaccional; se elimina al finalizar.',
];

try {
	foreach ([MantenimientoService::TYPE_CORRECTIVE, MantenimientoService::TYPE_PREVENTIVE] as $type) {
		$created = $service->createGroup(
			array_merge($base, ['titulo' => $marker . '-' . $type, 'tipo' => $type]),
			[(int)$equipmentId],
			'__maintenance_smoke__',
			'Mantenimiento Smoke',
			true,
		);
		$groupId = (int)$created['group']['id'];
		$maintenanceId = (int)$created['maintenances'][0]['id'];
		$createdGroupIds[] = $groupId;
		assertMaintenanceCreation(countMaintenanceRows($db, 'inv_mantenimientos', 'id_grupo', $groupId) === 1, "campaña $type crea su mantenimiento");
		$expectedChecks = $type === MantenimientoService::TYPE_PREVENTIVE ? count(ChecklistPreventivoCatalogo::ITEMS) : 0;
		assertMaintenanceCreation(countMaintenanceRows($db, 'inv_mant_checks', 'id_mantenimiento', $maintenanceId) === $expectedChecks, "campaña $type crea el checklist esperado");
		assertMaintenanceCreation(countMaintenanceRows($db, 'inv_mant_cambios', 'id_grupo', $groupId) === 2, "campaña $type registra auditoría individual y de grupo");
	}

	$failingMapper = new class($db) extends MantenimientoEquipoMapper {
		public function insertMaintenance(array $data): \OCA\Empleados\Db\MantenimientoEquipo {
			throw new RuntimeException('forced maintenance insert failure');
		}
	};
	$failingService = new MantenimientoService(
		$db,
		$server->get(MantenimientoGrupoMapper::class),
		$failingMapper,
		$server->get(MantenimientoChecklistMapper::class),
		$server->get(MantenimientoCambioMapper::class),
		$inventory,
		$server->get(departamentosMapper::class),
		$server->get(IUserManager::class),
		$server->get(ITimeFactory::class),
		$server->get(LoggerInterface::class),
	);
	$rollbackTitle = $marker . '-rollback';
	try {
		$failingService->createGroup(array_merge($base, ['titulo' => $rollbackTitle, 'tipo' => MantenimientoService::TYPE_CORRECTIVE]), [(int)$equipmentId], '__maintenance_smoke__', 'Mantenimiento Smoke', true);
		throw new RuntimeException('La inserción forzada debió fallar.');
	} catch (MantenimientoStorageException) {
		$qb = $db->getQueryBuilder();
		$result = $qb->select($qb->createFunction('COUNT(*)'))->from('inv_mant_grupos')
			->where($qb->expr()->eq('titulo', $qb->createNamedParameter($rollbackTitle)))
			->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();
		assertMaintenanceCreation($count === 0, 'una inserción fallida revierte también el grupo');
	}
} finally {
	foreach ($createdGroupIds as $groupId) cleanupMaintenanceGroup($db, $groupId);
}

echo '1..7', PHP_EOL;
