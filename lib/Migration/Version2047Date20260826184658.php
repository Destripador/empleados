<?php

declare(strict_types=1);

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2047Date20260826184658 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('empleados_honorarios')) {
			return $schema;
		}

		$table = $schema->getTable('empleados_honorarios');

		if (!$table->hasColumn('solicitud_generada')) {
			$table->addColumn('solicitud_generada', 'boolean', [
				'notnull' => false,
				'default' => false,
			]);
		}

		return $schema;
	}
}