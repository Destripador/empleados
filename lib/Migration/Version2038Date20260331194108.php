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
 * FIXME Auto-generated migration step: Please modify to your needs!
 */
class Version2038Date20260331194108 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	#[Override]
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	#[Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        // 1. Tabla: espacio
        if (!$schema->hasTable('espacio')) {
            $table = $schema->createTable('espacio');
            $table->addColumn('id_espacio', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('numero', 'integer', ['notnull' => false]);
            $table->addColumn('created_at', 'datetime', ['notnull' => true]);
            $table->addColumn('updated_at', 'datetime', ['notnull' => true]);
            $table->setPrimaryKey(['id_espacio']);
        }

        // 2. Tabla: espacio_empleados
        if (!$schema->hasTable('espacio_empleados')) {
            $table = $schema->createTable('espacio_empleados');
            $table->addColumn('id_espacio_empleado', 'integer', ['autoincrement' => true, 'notnull' => true]);
			$table->addColumn('id_espacio', 'integer', ['notnull' => true]);
            $table->addColumn('id_empleados', 'integer', ['notnull' => true]);
            $table->addColumn('created_at', 'datetime', ['notnull' => true]);
            $table->addColumn('updated_at', 'datetime', ['notnull' => true]);
            
            $table->setPrimaryKey(['id_espacio_empleado']);
			$table->addForeignKeyConstraint('espacio', ['id_espacio'], ['id_espacio'], ['onDelete' => 'CASCADE']);
            $table->addForeignKeyConstraint('empleados', ['id_empleados'], ['id_empleados'], ['onDelete' => 'CASCADE']);
        }

        // 3. Tabla: empleados_espacio_disponible
        if (!$schema->hasTable('emp_esp_disp')) {
            $table = $schema->createTable('emp_esp_disp');
            $table->addColumn('id_emp_esp_disp', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('id_espacio_empleado', 'integer', ['notnull' => true]);
            $table->addColumn('fecha', 'date', ['notnull' => true]);
            $table->addColumn('todo_dia', 'boolean', ['notnull' => false]);
            $table->addColumn('hora_inicial', 'time', ['notnull' => false]);
            $table->addColumn('hora_final', 'time', ['notnull' => false]);
            $table->addColumn('created_at', 'datetime', ['notnull' => true]);
            $table->addColumn('updated_at', 'datetime', ['notnull' => true]);

            $table->setPrimaryKey(['id_emp_esp_disp']);
            $table->addForeignKeyConstraint('espacio_empleados', 
                ['id_espacio_empleado'],
                ['onDelete' => 'CASCADE']
            );
        }

        return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	#[Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}
}
