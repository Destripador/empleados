<?php

declare(strict_types=1);

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2030Date20260801170000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		if (!$schema->hasTable('emp_comp_autoriza')) {
			return null;
		}

		$table = $schema->getTable('emp_comp_autoriza');
		if (!$table->hasColumn('rol')) {
			$table->addColumn('rol', 'string', ['length' => 32, 'notnull' => false]);
		}
		if (!$table->hasColumn('autorizador_nombre')) {
			$table->addColumn('autorizador_nombre', 'string', ['length' => 190, 'notnull' => false]);
		}

		return $schema;
	}
}
