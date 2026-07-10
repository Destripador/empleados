<?php
declare(strict_types=1);

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

class Version2020Date20260708193202 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        $table = $schema->getTable('historial_vacaciones');

        if (!$table->hasColumn('asignado_manualmente')) {
            $table->addColumn('asignado_manualmente', 'smallint', [
                'notnull' => true,
                'default' => 0,
            ]);
        }

        return $schema;
    }
}