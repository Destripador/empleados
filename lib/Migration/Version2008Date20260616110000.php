<?php

declare(strict_types=1);

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2008Date20260616110000 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('empleados_actividades')) {
			return null;
		}

		$table = $schema->getTable('empleados_actividades');

		if (!$table->hasColumn('cargable')) {
			$table->addColumn('cargable', 'boolean', [
				'default' => false,
			]);
		}

		return $schema;
	}
}