<?php

declare(strict_types=1);

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2013Date20260626164414 extends SimpleMigrationStep {

    public function changeSchema(
        IOutput $output,
        Closure $schemaClosure,
        array $options
    ): ?ISchemaWrapper {

        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('historial_ausencias')) {
            return null;
        }

        $table = $schema->getTable('historial_ausencias');

        if (!$table->hasColumn('dias_solicitados')) {
            $table->addColumn('dias_solicitados', Types::INTEGER, [
                'notnull'  => true,
                'default'  => 0,
                'unsigned' => true,
            ]);
        }

        return $schema;
    }
}