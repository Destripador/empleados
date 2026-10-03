<?php

declare(strict_types=1);

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2050Date20260922171612 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('empleados_honorarios')) {
			$table = $schema->getTable('empleados_honorarios');

			if (!$table->hasColumn('cambio_moneda_factura')) {
				$table->addColumn('cambio_moneda_factura', 'decimal', [
					'notnull' => false,
					'precision' => 12,
					'scale' => 6,
					'default' => null,
				]);
			}
		}

		if ($schema->hasTable('empleados_honorarios_p')) {
			$table = $schema->getTable('empleados_honorarios_p');

			if (!$table->hasColumn('cambio_moneda_factura')) {
				$table->addColumn('cambio_moneda_factura', 'decimal', [
					'notnull' => false,
					'precision' => 12,
					'scale' => 6,
					'default' => null,
				]);
			}
		}

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$output->info('Columna cambio_moneda_factura agregada a empleados_honorarios y empleados_honorarios_p.');
	}
}