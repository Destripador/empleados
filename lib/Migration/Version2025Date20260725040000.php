<?php

declare(strict_types=1);

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2025Date20260725040000 extends SimpleMigrationStep {
	public function changeSchema(
		IOutput $output,
		Closure $schemaClosure,
		array $options
	): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		foreach (['ausencias', 'historial_ausencias'] as $tableName) {
			if (!$schema->hasTable($tableName)) {
				continue;
			}

			$table = $schema->getTable($tableName);

			if (!$table->hasColumn('timestamp')) {
				continue;
			}

			$table->changeColumn('timestamp', [
				'notnull' => true,
				'default' => 'CURRENT_TIMESTAMP',
			]);

			$output->info(
				"Configurado DEFAULT CURRENT_TIMESTAMP en {$tableName}.timestamp"
			);
		}

		return $schema;
	}
}