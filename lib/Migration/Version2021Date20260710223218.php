<?php
declare(strict_types=1);

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2021Date20260710223218 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('historial_ausencias');

		if (!$table->hasColumn('a_capital_humano')) {
			$table->addColumn('a_capital_humano', 'integer', [
				'notnull' => true,
				'default' => 0,
			]);
		}

		return $schema;
	}
}