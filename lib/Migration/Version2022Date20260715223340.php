<?php

declare(strict_types=1);

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2022Date20260715223340 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('prima_vacacional_pagos')) {
			return null;
		}

		$table = $schema->createTable('prima_vacacional_pagos');

		$table->addColumn('id', Types::INTEGER, [
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
		]);

		$table->addColumn('fecha_pago', Types::STRING, [
			'notnull' => true,
			'length' => 10,
		]);

		$table->addColumn('dias_pagados', Types::DECIMAL, [
			'notnull' => true,
			'precision' => 6,
			'scale' => 2,
			'default' => 0,
		]);

		$table->addColumn('created_at', Types::STRING, [
			'notnull' => true,
			'length' => 32,
		]);

		$table->addColumn('updated_at', Types::STRING, [
			'notnull' => true,
			'length' => 32,
		]);

		$table->setPrimaryKey(['id']);

		$table->addUniqueIndex(['id_empleado', 'numero_aniversario'], 'prima_pago_emp_aniv_uniq');

		return $schema;
	}
}