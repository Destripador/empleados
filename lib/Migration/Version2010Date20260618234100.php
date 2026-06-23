<?php

declare(strict_types=1);

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2010Date20260618234100 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('empleados_honorarios')) {
			$table = $schema->createTable('empleados_honorarios');

			$table->addColumn('id_honorario', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('id_cliente', Types::INTEGER, [
				'notnull' => true,
				'default' => 0,
			]);

			$table->addColumn('importe_total', Types::FLOAT, [
				'notnull' => true,
				'default' => 0,
			]);
			$table->addColumn('tipo_moneda', Types::STRING, [
				'notnull' => true,
				'length' => 16,
				'default' => 'MXN',
			]);

			$table->addColumn('fecha_inicio', Types::STRING, [
				'notnull' => false,
				'length' => 16,
			]);
			$table->addColumn('fecha_fin', Types::STRING, [
				'notnull' => false,
				'length' => 16,
			]);
			$table->addColumn('numero_parcialidades', Types::INTEGER, [
				'notnull' => true,
				'default' => 0,
			]);

			$table->addColumn('tipo_servicio', Types::STRING, [
				'notnull' => false,
				'length' => 255,
			]);

			$table->addColumn('activo', Types::INTEGER, [
				'notnull' => true,
				'default' => 1,
				'length' => 1,
			]);

			$table->setPrimaryKey(['id_honorario']);
			$table->addIndex(['id_cliente'], 'hon_cliente_idx');
		}

		// ── empleados_honorarios_parcialidades ───────────────────────────
		if (!$schema->hasTable('empleados_honorarios_p')) {
			$table = $schema->createTable('empleados_honorarios_p');

			$table->addColumn('id_parcialidad', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('id_honorario', Types::INTEGER, [
				'notnull' => true,
				'default' => 0,
			]);

			$table->addColumn('numero_parcialidad', Types::INTEGER, [
				'notnull' => true,
				'default' => 0,
			]);
			$table->addColumn('pfecha_inicio', Types::STRING, [
				'notnull' => false,
				'length' => 16,
			]);
			$table->addColumn('pfecha_fin', Types::STRING, [
				'notnull' => false,
				'length' => 16,
			]);
			$table->addColumn('importe_parcialidad', Types::FLOAT, [
				'notnull' => true,
				'default' => 0,
			]);

			$table->addColumn('pagado', Types::BOOLEAN, [
				'notnull' => true,
				'default' => false,
			]);
			$table->addColumn('fecha_pago', Types::STRING, [
				'notnull' => false,
				'length' => 16,
			]);

			$table->setPrimaryKey(['id_parcialidad']);
			$table->addIndex(['id_honorario'], 'parc_honorario_idx');
		}

		return $schema;
	}
}