<?php

declare(strict_types=1);

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2042Date20260820183843 extends SimpleMigrationStep {

	public function changeSchema(
		IOutput $output,
		Closure $schemaClosure,
		array $options
	): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		// Eliminar la tabla si ya existe
		if ($schema->hasTable('empleados_movimientos')) {
			$schema->dropTable('empleados_movimientos');
		}

		// Crear la tabla nuevamente
		$table = $schema->createTable('empleados_movimientos');

		$table->addColumn('id', 'bigint', [
			'autoincrement' => true,
			'notnull' => true,
		]);

		$table->addColumn('modulo', 'string', [
			'notnull' => true,
			'length' => 40,
		]);

		$table->addColumn('fecha', 'datetime', [
			'notnull' => true,
		]);

		$table->addColumn('id_empleado_actor', 'integer', [
			'notnull' => false,
		]);

		$table->addColumn('nombre_actor', 'string', [
			'notnull' => false,
			'length' => 190,
		]);

		$table->addColumn('id_empleado_afectado', 'integer', [
			'notnull' => false,
		]);

		$table->addColumn('nombre_afectado', 'string', [
			'notnull' => false,
			'length' => 190,
		]);

		$table->addColumn('tipo_movimiento', 'string', [
			'notnull' => true,
			'length' => 60,
		]);

		$table->addColumn('id_referencia', 'integer', [
			'notnull' => false,
		]);

		$table->addColumn('mensaje', 'text', [
			'notnull' => true,
		]);

		$table->setPrimaryKey(['id']);

		$table->addIndex(
			['modulo', 'tipo_movimiento'],
			'emp_mov_mod_tipo_idx'
		);

		$table->addIndex(
			['id_empleado_afectado'],
			'emp_mov_afectado_idx'
		);

		$table->addIndex(
			['fecha'],
			'emp_mov_fecha_idx'
		);

		return $schema;
	}
}