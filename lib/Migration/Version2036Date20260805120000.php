<?php

declare(strict_types=1);

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2036Date20260805120000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('empleados_mov_archivos')) {
			return null;
		}

		$table = $schema->createTable('empleados_mov_archivos');
		$table->addColumn('id', 'bigint', ['autoincrement' => true, 'unsigned' => true, 'notnull' => true]);
		$table->addColumn('id_empleado', 'integer', ['unsigned' => true, 'notnull' => false]);
		$table->addColumn('uid_actor', 'string', ['length' => 255, 'notnull' => false]);
		$table->addColumn('tipo_evento', 'string', ['length' => 24, 'notnull' => true]);
		$table->addColumn('file_id', 'bigint', ['unsigned' => true, 'notnull' => false]);
		$table->addColumn('storage_id', 'string', ['length' => 255, 'notnull' => false]);
		$table->addColumn('ruta_anterior', 'text', ['notnull' => false]);
		$table->addColumn('ruta_actual', 'text', ['notnull' => false]);
		$table->addColumn('nombre_archivo', 'string', ['length' => 255, 'notnull' => false]);
		$table->addColumn('mime_type', 'string', ['length' => 255, 'notnull' => false]);
		$table->addColumn('tamanio', 'bigint', ['unsigned' => true, 'notnull' => false]);
		// Nextcloud requires boolean columns to remain nullable for Oracle compatibility.
		$table->addColumn('es_carpeta', 'boolean', ['default' => false, 'notnull' => false]);
		$table->addColumn('fecha_evento', 'datetime', ['notnull' => true]);
		$table->addColumn('remote_addr', 'string', ['length' => 45, 'notnull' => false]);
		$table->addColumn('user_agent', 'string', ['length' => 512, 'notnull' => false]);

		$table->setPrimaryKey(['id'], 'emp_mov_arc_pk');
		$table->addIndex(['uid_actor'], 'emp_mov_arc_uid_idx');
		$table->addIndex(['id_empleado'], 'emp_mov_arc_emp_idx');
		$table->addIndex(['tipo_evento'], 'emp_mov_arc_tipo_idx');
		$table->addIndex(['fecha_evento'], 'emp_mov_arc_fecha_idx');
		$table->addIndex(['file_id'], 'emp_mov_arc_file_idx');

		return $schema;
	}
}
