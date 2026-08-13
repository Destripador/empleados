<?php

declare(strict_types=1);

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2024Date20260717192013 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('empleados_organigrama')) {
			$schema->dropTable('empleados_organigrama');
		}

		if ($schema->hasTable('emp_organigrama')) {
			$schema->dropTable('emp_organigrama');
		}

		if ($schema->hasTable('empleados_organigrama_pos')) {
			$schema->dropTable('empleados_organigrama_pos');
		}

		if ($schema->hasTable('emp_org_pos')) {
			$schema->dropTable('emp_org_pos');
		}

		$table = $schema->createTable('emp_organigrama');

		$table->addColumn('id', Types::INTEGER, [
			'autoincrement' => true,
			'notnull' => true,
			'unsigned' => true,
		]);

		$table->addColumn('id_empleado', Types::INTEGER, [
			'notnull' => true,
			'unsigned' => true,
		]);

		$table->addColumn('id_dependiente', Types::INTEGER, [
			'notnull' => true,
			'unsigned' => true,
		]);

		$table->addColumn('created_at', Types::STRING, [
			'notnull' => false,
			'length' => 64,
		]);

		$table->setPrimaryKey(['id']);

		$table->addUniqueIndex(['id_empleado', 'id_dependiente'], 'empl_org_pair_uniq');
		$table->addIndex(['id_dependiente'], 'empl_org_dep_idx');

		$posTable = $schema->createTable('emp_org_pos');

		$posTable->addColumn('id_empleado', Types::INTEGER, [
			'notnull' => true,
			'unsigned' => true,
		]);

		$posTable->addColumn('pos_x', Types::FLOAT, [
			'notnull' => true,
		]);

		$posTable->addColumn('pos_y', Types::FLOAT, [
			'notnull' => true,
		]);

		$posTable->setPrimaryKey(['id_empleado']);

		return $schema;
	}
}