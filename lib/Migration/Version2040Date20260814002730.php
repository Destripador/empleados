<?php

declare(strict_types=1);

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Agrega el campo RFC a la tabla de clientes/empresas.
 */
class Version2040Date20260814002730 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('empleados_clientes')) {
			return null;
		}

		$table = $schema->getTable('empleados_clientes');

		if (!$table->hasColumn('rfc')) {
			$table->addColumn('rfc', 'string', [
				'notnull' => false,
				'length' => 64,
			]);
		}

		return $schema;
	}
}