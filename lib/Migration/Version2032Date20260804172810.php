<?php

declare(strict_types=1);

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2032Date20260804172810 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('boarding_catalogo')) {
			$schema->dropTable('boarding_catalogo');
		}

		if ($schema->hasTable('empleados_boarding')) {
			$schema->dropTable('empleados_boarding');
		}

		$catalogoTable = $schema->createTable('boarding_catalogo');

		$catalogoTable->addColumn('id_boarding', Types::INTEGER, [
			'autoincrement' => true,
			'notnull' => true,
			'unsigned' => true,
		]);

		$catalogoTable->addColumn('nombre', Types::STRING, [
			'notnull' => true,
			'length' => 255,
		]);

		$catalogoTable->addColumn('on', Types::INTEGER, [
			'notnull' => true,
			'unsigned' => true,
			'default' => 1,
		]);

		$catalogoTable->setPrimaryKey(['id_boarding']);
		$catalogoTable->addIndex(['on'], 'emp_board_cat_on_idx');

		$pivoteTable = $schema->createTable('empleados_boarding');

		$pivoteTable->addColumn('id_empleado_boarding', Types::INTEGER, [
			'autoincrement' => true,
			'notnull' => true,
			'unsigned' => true,
		]);

		$pivoteTable->addColumn('id_empleado', Types::INTEGER, [
			'notnull' => true,
			'unsigned' => true,
		]);

		$pivoteTable->addColumn('id_boarding', Types::INTEGER, [
			'notnull' => true,
			'unsigned' => true,
		]);

		$pivoteTable->addColumn('nombre', Types::STRING, [
			'notnull' => true,
			'length' => 255,
		]);

		$pivoteTable->addColumn('status', Types::INTEGER, [
			'notnull' => true,
			'unsigned' => true,
			'default' => 0,
		]);

		$pivoteTable->setPrimaryKey(['id_empleado_boarding']);

		$pivoteTable->addUniqueIndex(['id_empleado', 'id_boarding'], 'emp_board_emp_item_uniq');
		$pivoteTable->addIndex(['id_empleado'], 'emp_board_empleado_idx');
		$pivoteTable->addIndex(['id_boarding'], 'emp_board_boarding_idx');

		return $schema;
	}
}