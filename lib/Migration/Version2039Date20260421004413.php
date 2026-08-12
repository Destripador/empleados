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
class Version2039Date20260421004413 extends SimpleMigrationStep {

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

        $tableEspacio = $schema->getTable('espacio');

        // 1. Modificar tabla 'espacio'
        // Quitar campo 'centro' si existe
        if ($tableEspacio->hasColumn('centro')) {
            $tableEspacio->dropColumn('centro');
        }

        // Agregar campo 'disponible'
        $tableEspacio->addColumn('disponible', 'smallint', [
            'notnull' => true,
            'default' => 1,
        ]);

        // 2. Crear tabla 'espacio_obstruye'
        if (!$schema->hasTable('espacio_obstruye')) {
            $tableObstruye = $schema->createTable('espacio_obstruye');
            
            $tableObstruye->addColumn('id_espacio_obstruye', 'integer', [
                'autoincrement' => true,
                'notnull' => true,
            ]);
            
            $tableObstruye->addColumn('id_espacio', 'integer', [
                'notnull' => true,
            ]);
            
            $tableObstruye->addColumn('id_obstruye', 'integer', [
                'notnull' => true,
            ]);

            $tableObstruye->setPrimaryKey(['id_espacio_obstruye']);

            // Llave foránea: El espacio que es obstruido
            $tableObstruye->addForeignKeyConstraint($schema->getTable('espacio'), 
                ['id_espacio'], ['id_espacio'], 
                ['onDelete' => 'CASCADE']
            );

            // Llave foránea: El espacio que está obstruyendo al primero
            $tableObstruye->addForeignKeyConstraint($schema->getTable('espacio'), 
                ['id_obstruye'], ['id_espacio'], 
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
