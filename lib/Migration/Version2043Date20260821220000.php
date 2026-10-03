<?php

declare(strict_types=1);

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Referencia de logo corporativo por cliente y índices usados por el dashboard.
 */
class Version2043Date20260821220000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('empleados_clientes')) {
			return null;
		}

		$table = $schema->getTable('empleados_clientes');

		if (!$table->hasColumn('logo')) {
			$table->addColumn('logo', 'string', [
				'notnull' => false,
				'length' => 32,
			]);
		}

		if (!$table->hasIndex('emp_cli_padre_idx')) {
			$table->addIndex(['cliente_padre'], 'emp_cli_padre_idx');
		}

		if (!$table->hasIndex('emp_cli_lider_idx')) {
			$table->addIndex(['lider_proyecto'], 'emp_cli_lider_idx');
		}

		return $schema;
	}
}
