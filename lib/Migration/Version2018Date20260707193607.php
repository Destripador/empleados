<?php

declare(strict_types=1);

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2018Date20260707193607 extends SimpleMigrationStep {

	public function changeSchema(
		IOutput $output,
		Closure $schemaClosure,
		array $options
	): ?ISchemaWrapper {

		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('historial_vacaciones')) {
			$table = $schema->getTable('historial_vacaciones');

			if (!$table->hasColumn('dias_acumulados')) {
				$table->addColumn('dias_acumulados', Types::DECIMAL, [
					'notnull' => true,
					'precision' => 6,
					'scale' => 2,
					'default' => 0,
				]);
			}

			if (!$table->hasColumn('dias_acumulados_restantes')) {
				$table->addColumn('dias_acumulados_restantes', Types::DECIMAL, [
					'notnull' => true,
					'precision' => 6,
					'scale' => 2,
					'default' => 0,
				]);
			}

			if (!$table->hasColumn('fecha_expiracion_acumulados')) {
				$table->addColumn('fecha_expiracion_acumulados', Types::STRING, [
					'notnull' => false,
					'length' => 10,
					'default' => null,
				]);
			}
		}

		// ── historial_ausencias: marca cuántos días salieron del colchón ──
		if ($schema->hasTable('historial_ausencias')) {
			$table = $schema->getTable('historial_ausencias');

			if (!$table->hasColumn('dias_de_acumulado')) {
				$table->addColumn('dias_de_acumulado', Types::DECIMAL, [
					'notnull' => true,
					'precision' => 6,
					'scale' => 2,
					'default' => 0,
				]);
			}
		}

		return $schema;
	}
}