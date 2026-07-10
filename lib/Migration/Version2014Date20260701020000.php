<?php

declare(strict_types=1);

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2014Date20260701020000 extends SimpleMigrationStep {

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
				'module' => 'clientes',
				'permission' => 'admin',
				'group_id' => 'clientes_admin',
				'label' => 'Clientes - Administradores',
				'description' => 'Puede administrar clientes, grupos empresariales y actividades.',
				'restricted' => 1,
				'sort_order' => 60,
			],
			[
				'module' => 'inventario',
				'permission' => 'admin',
				'group_id' => 'ti_admin',
				'label' => 'TI - Administradores',
				'description' => 'Puede administrar inventario, equipos y solicitudes de soporte.',
				'restricted' => 1,
				'sort_order' => 70,
			],
			[
				'module' => 'reporte_tiempos',
				'permission' => 'admin',
				'group_id' => 'reportes_admin',
				'label' => 'Reportes - Administradores',
				'description' => 'Puede consultar reportes administrativos y seguimiento de cumplimiento.',
				'restricted' => 1,
				'sort_order' => 80,
			],
			[
				'module' => 'ahorro',
				'permission' => 'admin',
				'group_id' => 'ahorro_admin',
				'label' => 'Ahorro - Administradores',
				'description' => 'Puede administrar solicitudes y panel del fondo de ahorro.',
				'restricted' => 1,
				'sort_order' => 90,
			],
			[
				'module' => 'ausencias',
				'permission' => 'admin',
				'group_id' => 'ausencias_admin',
				'label' => 'Ausencias - Administradores',
				'description' => 'Puede administrar ausencias, vacaciones y calendario laboral.',
				'restricted' => 1,
				'sort_order' => 100,
			],
			[
				'module' => 'empleados',
				'permission' => 'admin',
				'group_id' => 'empleados_admin',
				'label' => 'Empleados - Administradores',
				'description' => 'Puede administrar empleados, áreas, puestos y equipos sin requerir acceso total de Recursos Humanos.',
				'restricted' => 1,
				'sort_order' => 110,
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
		$exists = $result->fetch();
		$result->closeCursor();

		if ($exists) {
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