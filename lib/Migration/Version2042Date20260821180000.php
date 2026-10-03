<?php

declare(strict_types=1);

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Permite asignar equipos de cómputo a grupos de Nextcloud además de empleados.
 */
class Version2042Date20260821180000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('inventario_computo')) {
			return null;
		}

		$table = $schema->getTable('inventario_computo');
		if (!$table->hasColumn('gid')) {
			$table->addColumn('gid', 'string', [
				'notnull' => false,
				'length' => 64,
			]);
		}
		if (!$table->hasIndex('inv_comp_gid')) {
			$table->addIndex(['gid'], 'inv_comp_gid');
		}

		return $schema;
	}
}
