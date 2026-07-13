<?php

declare(strict_types=1);

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2017Date20260706183000 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('empleados_clientes')) {
			$table = $schema->createTable('empleados_clientes');

			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);

			$this->addClienteColumns($table);

			$table->setPrimaryKey(['id']);

			return $schema;
		}

		$table = $schema->getTable('empleados_clientes');

		// Estructura vieja: id_cliente -> id
		if ($table->hasColumn('id_cliente') && !$table->hasColumn('id')) {
			$table->renameColumn('id_cliente', 'id');
		}

		if (!$table->hasColumn('nombre')) {
			$table->addColumn('nombre', Types::STRING, [
				'notnull' => true,
				'length' => 255,
				'default' => '',
			]);
		}

		if (!$table->hasColumn('detalles')) {
			$table->addColumn('detalles', Types::TEXT, [
				'notnull' => false,
			]);
		}

		if (!$table->hasColumn('lider_proyecto')) {
			$table->addColumn('lider_proyecto', Types::INTEGER, [
				'notnull' => false,
			]);
		}

		if (!$table->hasColumn('colaboradores')) {
			$table->addColumn('colaboradores', Types::TEXT, [
				'notnull' => false,
			]);
		}

		if (!$table->hasColumn('razon_social')) {
			$table->addColumn('razon_social', Types::STRING, [
				'notnull' => false,
				'length' => 255,
			]);
		}

		if (!$table->hasColumn('nombre_contacto')) {
			$table->addColumn('nombre_contacto', Types::STRING, [
				'notnull' => false,
				'length' => 255,
			]);
		}

		if (!$table->hasColumn('telefono')) {
			$table->addColumn('telefono', Types::STRING, [
				'notnull' => false,
				'length' => 64,
			]);
		}

		if (!$table->hasColumn('correo')) {
			$table->addColumn('correo', Types::STRING, [
				'notnull' => false,
				'length' => 255,
			]);
		}

		if (!$table->hasColumn('ubicacion')) {
			$table->addColumn('ubicacion', Types::STRING, [
				'notnull' => false,
				'length' => 255,
			]);
		}

		if (!$table->hasColumn('especial')) {
			$table->addColumn('especial', Types::INTEGER, [
				'notnull' => true,
				'default' => 0,
				'length' => 1,
			]);
		}

		if (!$table->hasColumn('cliente_padre')) {
			$table->addColumn('cliente_padre', Types::INTEGER, [
				'notnull' => false,
			]);
		}

		if (!$table->hasColumn('estado')) {
			$table->addColumn('estado', Types::INTEGER, [
				'notnull' => true,
				'default' => 1,
				'length' => 1,
			]);
		}

		return $schema;
	}

	private function addClienteColumns($table): void {
		$table->addColumn('nombre', Types::STRING, [
			'notnull' => true,
			'length' => 255,
			'default' => '',
		]);

		$table->addColumn('detalles', Types::TEXT, [
			'notnull' => false,
		]);

		$table->addColumn('lider_proyecto', Types::INTEGER, [
			'notnull' => false,
		]);

		$table->addColumn('colaboradores', Types::TEXT, [
			'notnull' => false,
		]);

		$table->addColumn('razon_social', Types::STRING, [
			'notnull' => false,
			'length' => 255,
		]);

		$table->addColumn('nombre_contacto', Types::STRING, [
			'notnull' => false,
			'length' => 255,
		]);

		$table->addColumn('telefono', Types::STRING, [
			'notnull' => false,
			'length' => 64,
		]);

		$table->addColumn('correo', Types::STRING, [
			'notnull' => false,
			'length' => 255,
		]);

		$table->addColumn('ubicacion', Types::STRING, [
			'notnull' => false,
			'length' => 255,
		]);

		$table->addColumn('especial', Types::INTEGER, [
			'notnull' => true,
			'default' => 0,
			'length' => 1,
		]);

		$table->addColumn('cliente_padre', Types::INTEGER, [
			'notnull' => false,
		]);

		$table->addColumn('estado', Types::INTEGER, [
			'notnull' => true,
			'default' => 1,
			'length' => 1,
		]);
	}
}