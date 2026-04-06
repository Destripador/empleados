// lib/Migration/Version12Date20251021172139.php
<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * FIXME Auto-generated migration step: Please modify to your needs!
 */
class Version12Date20251021172139 extends SimpleMigrationStep {

    /**
     * @param IOutput $output
     * @param Closure(): ISchemaWrapper $schemaClosure
     * @param array $options
     */
    public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
    }

    /**
     * @param IOutput $output
     * @param Closure(): ISchemaWrapper $schemaClosure
     * @param array $options
     * @return null|ISchemaWrapper
     */
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        // Tabla CLIENTES
        if (!$schema->hasTable('empleados_clientes')) {
            $table = $schema->createTable('empleados_clientes');
            $table->addColumn('id_cliente', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('nombre', 'string', ['length' => 200, 'notnull' => true]);
            $table->addColumn('detalles', 'text', ['notnull' => false]);
            $table->addColumn('cliente_padre', 'integer', ['notnull' => false]);
            $table->addColumn('timestamp', 'datetime', ['notnull' => true, 'default' => 'CURRENT_TIMESTAMP']);
            $table->setPrimaryKey(['id_cliente']);
        }

        // Tabla ACTIVIDADES
        if (!$schema->hasTable('empleados_actividades')) {
            $table = $schema->createTable('empleados_actividades');
            $table->addColumn('id_actividad', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('nombre', 'string', ['length' => 200, 'notnull' => true]);
            $table->addColumn('detalles', 'text', ['notnull' => false]);
            $table->addColumn('tiempo_estimado', 'decimal', ['precision' => 8, 'scale' => 2, 'notnull' => false]);
            $table->addColumn('tiempo_real', 'decimal', ['precision' => 8, 'scale' => 2, 'notnull' => false]);
            $table->setPrimaryKey(['id_actividad']);
        }

        return $schema;
    }

    /**
     * @param IOutput $output
     * @param Closure(): ISchemaWrapper $schemaClosure
     * @param array $options
     */
    public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
    }
}
