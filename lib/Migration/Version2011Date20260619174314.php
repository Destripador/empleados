<?php

declare(strict_types=1);

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

class Version2011Date20260619174314 extends SimpleMigrationStep {

	#[Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('empleados_honorarios_parcialidades')) {
			return null;
		}

		$table = $schema->getTable('empleados_honorarios_parcialidades');

		if ($table->hasColumn('pagado')) {
			$table->dropColumn('pagado');
		}

		$table->addColumn('pagado', Types::INTEGER, [
			'notnull' => true,
			'default' => 0,
		]);

		return $schema;
	}
}