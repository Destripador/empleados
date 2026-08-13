<?php

declare(strict_types=1);

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2013Date20260701010000 extends SimpleMigrationStep {

	public function __construct(
		private IDBConnection $db,
	) {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('emp_perm_groups')) {
			$table = $schema->createTable('emp_perm_groups');

			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'unsigned' => true,
				'notnull' => true,
			]);

			$table->addColumn('module', 'string', [
				'length' => 64,
				'notnull' => true,
			]);

			$table->addColumn('permission', 'string', [
				'length' => 64,
				'notnull' => true,
			]);

			$table->addColumn('group_id', 'string', [
				'length' => 190,
				'notnull' => true,
			]);

			$table->addColumn('label', 'string', [
				'length' => 190,
				'notnull' => true,
			]);

			$table->addColumn('description', 'text', [
				'notnull' => false,
			]);

			$table->addColumn('restricted', 'integer', [
				'notnull' => true,
				'default' => 0,
			]);

			$table->addColumn('enabled', 'integer', [
				'notnull' => true,
				'default' => 1,
			]);

			$table->addColumn('sort_order', 'integer', [
				'notnull' => true,
				'default' => 0,
			]);

			$table->addColumn('created_at', 'datetime', [
				'notnull' => true,
				'default' => 'CURRENT_TIMESTAMP',
			]);

			$table->addColumn('updated_at', 'datetime', [
				'notnull' => false,
			]);

			$table->setPrimaryKey(['id']);

			$table->addUniqueIndex(
				['module', 'permission', 'group_id'],
				'emp_perm_group_uq'
			);

			$table->addIndex(['module'], 'emp_perm_module_idx');
			$table->addIndex(['group_id'], 'emp_perm_group_idx');
			$table->addIndex(['enabled'], 'emp_perm_enabled_idx');
			$table->addIndex(['restricted'], 'emp_perm_restricted_idx');
			$table->addIndex(['sort_order'], 'emp_perm_sort_idx');
		}

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$permissions = [
			[
				'module' => 'compras',
				'permission' => 'request',
				'group_id' => 'compras_solicitantes',
				'label' => 'Compras - Solicitantes',
				'description' => 'Puede crear y dar seguimiento a sus propias solicitudes de compra.',
				'restricted' => 0,
				'sort_order' => 10,
			],
			[
				'module' => 'compras',
				'permission' => 'approve',
				'group_id' => 'compras_autorizadores',
				'label' => 'Compras - Autorizadores',
				'description' => 'Puede revisar, aprobar o rechazar solicitudes de compra.',
				'restricted' => 0,
				'sort_order' => 20,
			],
			[
				'module' => 'compras',
				'permission' => 'admin',
				'group_id' => 'compras_admin',
				'label' => 'Compras - Administradores',
				'description' => 'Control total del módulo de compras.',
				'restricted' => 1,
				'sort_order' => 30,
			],
			[
				'module' => 'compras',
				'permission' => 'accounting',
				'group_id' => 'compras_contabilidad',
				'label' => 'Compras - Contabilidad',
				'description' => 'Puede revisar solicitudes para seguimiento contable.',
				'restricted' => 0,
				'sort_order' => 40,
			],
			[
				'module' => 'empleados',
				'permission' => 'hr',
				'group_id' => 'recursos_humanos',
				'label' => 'Recursos Humanos',
				'description' => 'Puede administrar empleados, áreas, puestos, equipos, ahorro y ausencias.',
				'restricted' => 1,
				'sort_order' => 50,
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