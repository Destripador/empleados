<?php

declare(strict_types=1);

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2032Date20260803190000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		return null;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$db = \OC::$server->getDatabaseConnection();
		$counts = ['migrados' => 0, 'correctos' => 0, 'conflictos' => 0, 'inexistentes' => 0];

		$select = $db->getQueryBuilder();
		$result = $select->select('Id_empleados', 'Equipo_asignado')
			->from('empleados')
			->where($select->expr()->isNotNull('Equipo_asignado'))
			->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		foreach ($rows as $row) {
			$legacyId = trim((string)($row['Equipo_asignado'] ?? ''));
			if ($legacyId === '' || $legacyId === '0') {
				continue;
			}
			if (!ctype_digit($legacyId) || (int)$legacyId <= 0) {
				$counts['inexistentes']++;
				continue;
			}

			$idEquipo = (int)$legacyId;
			$idEmpleado = (int)$row['Id_empleados'];
			$check = $db->getQueryBuilder();
			$checkResult = $check->select('id_empleado')
				->from('inventario_computo')
				->where($check->expr()->eq('id_equipo', $check->createNamedParameter($idEquipo, IQueryBuilder::PARAM_INT)))
				->setMaxResults(1)
				->executeQuery();
			$equipment = $checkResult->fetch();
			$checkResult->closeCursor();

			if ($equipment === false) {
				$counts['inexistentes']++;
				continue;
			}

			$currentEmployee = $equipment['id_empleado'] === null ? null : (int)$equipment['id_empleado'];
			if ($currentEmployee === $idEmpleado) {
				$counts['correctos']++;
				continue;
			}
			if ($currentEmployee !== null) {
				$counts['conflictos']++;
				continue;
			}

			$update = $db->getQueryBuilder();
			$updated = $update->update('inventario_computo')
				->set('id_empleado', $update->createNamedParameter($idEmpleado, IQueryBuilder::PARAM_INT))
				->where($update->expr()->eq('id_equipo', $update->createNamedParameter($idEquipo, IQueryBuilder::PARAM_INT)))
				->andWhere($update->expr()->isNull('id_empleado'))
				->executeStatement();
			$counts[$updated === 1 ? 'migrados' : 'conflictos']++;
		}

		$output->info(sprintf(
			'Asignaciones de inventario: %d migrados, %d ya correctos, %d conflictos, %d equipos inexistentes.',
			$counts['migrados'],
			$counts['correctos'],
			$counts['conflictos'],
			$counts['inexistentes'],
		));
	}
}
