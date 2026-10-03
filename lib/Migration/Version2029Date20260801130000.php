<?php

declare(strict_types=1);

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2029Date20260801130000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		if ($schema->hasTable('inv_movimientos')) return null;

		$table = $schema->createTable('inv_movimientos');
		$table->addColumn('id', 'bigint', ['autoincrement' => true, 'unsigned' => true, 'notnull' => true]);
		$table->addColumn('id_equipo', 'integer', ['unsigned' => true, 'notnull' => true]);
		$table->addColumn('tipo_movimiento', 'string', ['length' => 40, 'notnull' => true]);
		$table->addColumn('actor_uid', 'string', ['length' => 255, 'notnull' => true]);
		$table->addColumn('actor_nombre', 'string', ['length' => 255, 'notnull' => true]);
		$table->addColumn('empleado_anterior_uid', 'string', ['length' => 255, 'notnull' => false]);
		$table->addColumn('empleado_anterior_nombre', 'string', ['length' => 255, 'notnull' => false]);
		$table->addColumn('empleado_nuevo_uid', 'string', ['length' => 255, 'notnull' => false]);
		$table->addColumn('empleado_nuevo_nombre', 'string', ['length' => 255, 'notnull' => false]);
		$table->addColumn('estado_anterior', 'string', ['length' => 80, 'notnull' => false]);
		$table->addColumn('estado_nuevo', 'string', ['length' => 80, 'notnull' => false]);
		$table->addColumn('descripcion', 'text', ['notnull' => false]);
		$table->addColumn('cambios', 'text', ['notnull' => false]);
		$table->addColumn('fecha', 'datetime', ['notnull' => true]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['id_equipo', 'fecha'], 'inv_mov_equipo_fecha');

		return $schema;
	}
}
