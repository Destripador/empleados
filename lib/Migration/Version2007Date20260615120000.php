<?php

declare(strict_types=1);

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2007Date20260615120000 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('empleados_clientes')) {
			return null;
		}

		$table = $schema->getTable('empleados_clientes');

		if (!$table->hasColumn('razon_social')) {
			$table->addColumn('razon_social', 'string', [
				'notnull' => false,
				'length' => 255,
			]);
		}

		if (!$table->hasColumn('tipo_cliente')) {
			$table->addColumn('tipo_cliente', 'string', [
				'notnull' => false,
				'length' => 100,
			]);
		}

		if (!$table->hasColumn('tipo_servicio')) {
			$table->addColumn('tipo_servicio', 'string', [
				'notnull' => false,
				'length' => 100,
			]);
		}

		if (!$table->hasColumn('lider_proyecto')) {
			$table->addColumn('lider_proyecto', 'string', [
				'notnull' => false,
				'length' => 255,
			]);
		}

		if (!$table->hasColumn('nombre_contacto')) {
			$table->addColumn('nombre_contacto', 'string', [
				'notnull' => false,
				'length' => 255,
			]);
		}

		if (!$table->hasColumn('telefono')) {
			$table->addColumn('telefono', 'string', [
				'notnull' => false,
				'length' => 50,
			]);
		}

		if (!$table->hasColumn('correo')) {
			$table->addColumn('correo', 'string', [
				'notnull' => false,
				'length' => 255,
			]);
		}

		if (!$table->hasColumn('status')) {
			$table->addColumn('status', 'string', [
				'notnull' => false,
				'length' => 50,
			]);
		}

		if (!$table->hasColumn('ubicacion')) {
			$table->addColumn('ubicacion', 'string', [
				'notnull' => false,
				'length' => 255,
			]);
		}

		if (!$table->hasColumn('honorarios')) {
			$table->addColumn('honorarios', 'string', [
				'notnull' => false,
				'length' => 100,
			]);
		}

		if (!$table->hasColumn('tipo_moneda')) {
			$table->addColumn('tipo_moneda', 'string', [
				'notnull' => false,
				'length' => 10,
			]);
		}

		if (!$table->hasColumn('especial')) {
			$table->addColumn('especial', 'boolean', [
				'notnull' => true,
				'default' => false,
			]);
		}

		return $schema;
	}
}