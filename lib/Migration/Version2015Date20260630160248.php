<?php

declare(strict_types=1);

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2015Date20260630160248 extends SimpleMigrationStep {

	public function changeSchema(
		IOutput $output,
		Closure $schemaClosure,
		array $options
	): ?ISchemaWrapper {

		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('empleados_honorarios')) {
			return null;
		}

		$table = $schema->getTable('empleados_honorarios');

		if (!$table->hasColumn('especial')) {
			$table->addColumn('especial', Types::BOOLEAN, [
				'notnull' => true,
				'default' => false,
			]);
		}

		return $schema;
	}
}