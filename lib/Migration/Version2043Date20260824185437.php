<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

/**
 * Agrega la columna fecha_factura. La migración de datos existentes
 * (fecha_pago -> fecha_factura para registros ya facturados/pagados)
 * se maneja por separado.
 */
class Version2043Date20260824185437 extends SimpleMigrationStep {

	#[Override]
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}

	#[Override]
	public function changeSchema(
		IOutput $output,
		Closure $schemaClosure,
		array $options
	): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('empleados_honorarios_p')) {
			return $schema;
		}

		$table = $schema->getTable('empleados_honorarios_p');

		if (!$table->hasColumn('fecha_factura')) {
			$table->addColumn('fecha_factura', 'string', [
				'notnull' => false,
				'length' => 16,
			]);
		}

		return $schema;
	}

	#[Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}
}