<?php

declare(strict_types=1);

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Flags de visualización del reporte administrativo por área/departamento.
 * Defaults seguros: mostrar clientes y ausencias (comportamiento operativo actual).
 */
class Version2038Date20260810180000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('departamentos')) {
			return null;
		}

		$table = $schema->getTable('departamentos');

		if (!$table->hasColumn('mostrar_clientes')) {
			$table->addColumn('mostrar_clientes', 'smallint', [
				'notnull' => true,
				'default' => 1,
			]);
		}

		if (!$table->hasColumn('mostrar_ausencias')) {
			$table->addColumn('mostrar_ausencias', 'smallint', [
				'notnull' => true,
				'default' => 1,
			]);
		}

		return $schema;
	}
}
