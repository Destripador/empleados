<?php

declare(strict_types=1);

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2026Date20260728225430 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('tipo_ausencia')) {
			$table = $schema->getTable('tipo_ausencia');

			if (!$table->hasColumn('privado')) {
				$table->addColumn('privado', Types::INTEGER, [
					'notnull' => true,
					'unsigned' => true,
					'default' => 0,
				]);
			}
		}

		return $schema;
	}
}