<?php
declare(strict_types=1);

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2041Date20260814200104 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('empleados_honorarios_p')) {
			return $schema;
		}

		$table = $schema->getTable('empleados_honorarios_p');

		if (!$table->hasColumn('id_cliente_pagador')) {
			$table->addColumn('id_cliente_pagador', 'integer', [
				'notnull' => false,
			]);
		}

		return $schema;
	}
}