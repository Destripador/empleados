<?php
declare(strict_types=1);

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2037Date20260806220436 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        $tipoTable = $schema->getTable('tipo_ausencia');
        if (!$tipoTable->hasColumn('es_medio_dia')) {
            $tipoTable->addColumn('es_medio_dia', 'smallint', [
                'notnull' => true,
                'default' => 0,
            ]);
        }

        $historialTable = $schema->getTable('historial_ausencias');
        if (!$historialTable->hasColumn('turno')) {
            $historialTable->addColumn('turno', 'string', [
                'notnull' => false,
                'length' => 10,
            ]);
        }

        if ($historialTable->hasColumn('dias_solicitados')) {
        	$historialTable->changeColumn('dias_solicitados', [
        	'type' => \Doctrine\DBAL\Types\Type::getType('decimal'),
        	'precision' => 5,
        	'scale' => 1,
        	'notnull' => true,
        	]);
        }

        return $schema;
    }
}