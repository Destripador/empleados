<?php

declare(strict_types=1);

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2012Date20260624184640 extends SimpleMigrationStep {

	public function changeSchema(
		IOutput $output,
		Closure $schemaClosure,
		array $options
	): ?ISchemaWrapper {

		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('empleados_festivos')) {
			$schema->dropTable('empleados_festivos');
		}


		$table = $schema->createTable('empleados_festivos');

		$table->addColumn('id_festivo', Types::INTEGER, [
			'autoincrement' => true,
			'notnull' => true,
			'unsigned' => true,
		]);

		$table->addColumn('nombre', Types::STRING, [
			'notnull' => true,
			'length' => 255,
			'default' => '',
		]);

		$table->addColumn('fecha', Types::STRING, [
			'notnull' => true,
			'length' => 5,
		]);

		$table->setPrimaryKey(['id_festivo']);

		$table->addIndex(
			['fecha'],
			'festivos_fecha_idx'
		);

		return $schema;
	}
}