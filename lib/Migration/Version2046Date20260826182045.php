<?php

declare(strict_types=1);

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2046Date20260826182045 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('empleados_honorarios')) {
			return $schema;
		}

		$table = $schema->getTable('empleados_honorarios');

		if (!$table->hasColumn('descripcion')) {
			$table->addColumn('descripcion', 'text', [
				'notnull' => false,
			]);
		}

		return $schema;
	}
}