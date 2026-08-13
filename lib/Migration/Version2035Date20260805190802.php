<?php

declare(strict_types=1);

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2035Date20260805190802 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('historial_ausencias');

		if (!$table->hasColumn('motivo_rechazo')) {
			$table->addColumn('motivo_rechazo', 'string', [
				'notnull' => false,
				'length'  => 500,
			]);
		}

		return $schema;
	}
}