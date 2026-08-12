<?php

declare(strict_types=1);

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2038Date20260807183307 extends SimpleMigrationStep {

	/**
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$empleadosTable = $schema->getTable('empleados');

		if ($empleadosTable->hasColumn('Id_supervisor')) {
			$empleadosTable->dropColumn('Id_supervisor');
		}

		$empleadosTable->addColumn('Id_supervisor', Types::STRING, [
			'length' => 64,
			'notnull' => false,
		]);

		$historialTable = $schema->getTable('historial_ausencias');
		if (!$historialTable->hasColumn('a_supervisor')) {
			$historialTable->addColumn('a_supervisor', Types::BOOLEAN, [
				'notnull' => false,
				'default' => false,
			]);
		}

		return $schema;
	}
}