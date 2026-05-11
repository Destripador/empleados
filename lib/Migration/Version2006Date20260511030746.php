<?php

declare(strict_types=1);

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2006Date20260511030746 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('emp_comp_solicitudes')) {
			return null;
		}

		$table = $schema->getTable('emp_comp_solicitudes');

		if (!$table->hasColumn('firmado_file_id')) {
			$table->addColumn('firmado_file_id', 'bigint', [
				'notnull' => false,
				'unsigned' => true,
			]);
		}

		if (!$table->hasColumn('firmado_nombre')) {
			$table->addColumn('firmado_nombre', 'string', [
				'notnull' => false,
				'length' => 255,
			]);
		}

		if (!$table->hasColumn('firmado_mime')) {
			$table->addColumn('firmado_mime', 'string', [
				'notnull' => false,
				'length' => 120,
			]);
		}

		if (!$table->hasColumn('firmado_subido_at')) {
			$table->addColumn('firmado_subido_at', 'datetime', [
				'notnull' => false,
			]);
		}

		if (!$table->hasColumn('firmado_subido_by')) {
			$table->addColumn('firmado_subido_by', 'string', [
				'notnull' => false,
				'length' => 64,
			]);
		}

		return $schema;
	}
}