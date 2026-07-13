<?php

declare(strict_types=1);

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2017Date20260703000115 extends SimpleMigrationStep {

	public function changeSchema(
		IOutput $output,
		Closure $schemaClosure,
		array $options
	): ?ISchemaWrapper {

		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('historial_vacaciones')) {
			$schema->dropTable('historial_vacaciones');
		}

		$table = $schema->createTable('historial_vacaciones');

		$table->addColumn('id_historial', Types::INTEGER, [
			'autoincrement' => true,
			'notnull' => true,
			'unsigned' => true,
		]);

		$table->addColumn('id_empleado', Types::INTEGER, [
			'notnull' => true,
			'unsigned' => true,
		]);

		$table->addColumn('numero_aniversario', Types::INTEGER, [
			'notnull' => true,
			'unsigned' => true,
		]);

		$table->addColumn('periodo_inicio', Types::STRING, [
			'notnull' => true,
			'length' => 10,
			'default' => '',
		]);

		$table->addColumn('periodo_fin', Types::STRING, [
			'notnull' => true,
			'length' => 10,
			'default' => '',
		]);

		$table->addColumn('dias_derecho', Types::DECIMAL, [
			'notnull' => true,
			'precision' => 6,
			'scale' => 2,
			'default' => 0,
		]);

		$table->addColumn('created_at', Types::STRING, [
			'notnull' => true,
			'length' => 30,
			'default' => '',
		]);

		$table->addColumn('updated_at', Types::STRING, [
			'notnull' => true,
			'length' => 30,
			'default' => '',
		]);

		$table->setPrimaryKey(['id_historial']);

		$table->addUniqueIndex(
			['id_empleado', 'numero_aniversario'],
			'historial_vac_emp_aniv_idx'
		);

		return $schema;
	}
}