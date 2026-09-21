<?php

declare(strict_types=1);

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2044Date20260825020800 extends SimpleMigrationStep {

	public function __construct(
		private IDBConnection $db,
	) {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		return null;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$permissions = [
			[
				'module' => 'reporte_tiempos',
				'permission' => 'view',
				'group_id' => 'reportes_view',
				'label' => 'Reportes - Consulta',
				'description' => 'Puede consultar reportes administrativos de todos los empleados y seguimiento de cumplimiento.',
				'restricted' => 0,
				'sort_order' => 81,
			],
		];

		foreach ($permissions as $permission) {
			$this->insertPermissionIfMissing($permission);
		}
	}

	private function insertPermissionIfMissing(array $permission): void {
		$qb = $this->db->getQueryBuilder();

		$qb->select('id')
			->from('emp_perm_groups')
			->where($qb->expr()->eq('module', $qb->createNamedParameter($permission['module'])))
			->andWhere($qb->expr()->eq('permission', $qb->createNamedParameter($permission['permission'])))
			->andWhere($qb->expr()->eq('group_id', $qb->createNamedParameter($permission['group_id'])))
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$exists = $result->fetchOne();
		$result->closeCursor();

		if ($exists !== false) {
			return;
		}

		$insert = $this->db->getQueryBuilder();

		$insert->insert('emp_perm_groups')
			->values([
				'module' => $insert->createNamedParameter($permission['module']),
				'permission' => $insert->createNamedParameter($permission['permission']),
				'group_id' => $insert->createNamedParameter($permission['group_id']),
				'label' => $insert->createNamedParameter($permission['label']),
				'description' => $insert->createNamedParameter($permission['description']),
				'restricted' => $insert->createNamedParameter((int)$permission['restricted']),
				'enabled' => $insert->createNamedParameter(1),
				'sort_order' => $insert->createNamedParameter((int)$permission['sort_order']),
			]);

		$insert->executeStatement();
	}
}
