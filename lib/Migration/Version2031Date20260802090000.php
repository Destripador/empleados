<?php

declare(strict_types=1);

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2031Date20260802090000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		if ($schema->hasTable('soporte_historial')) {
			$soporte = $schema->getTable('soporte_historial');
			if (!$soporte->hasColumn('duracion_minutos')) {
				$soporte->addColumn('duracion_minutos', 'integer', [
					'unsigned' => true,
					'notnull' => false,
				]);
			}
		}

		if ($schema->hasTable('empleados_rep_tiempos')) {
			$reportes = $schema->getTable('empleados_rep_tiempos');
			if (!$reportes->hasColumn('origen')) {
				$reportes->addColumn('origen', 'string', ['length' => 40, 'notnull' => false]);
			}
			if (!$reportes->hasColumn('origen_id')) {
				$reportes->addColumn('origen_id', 'integer', ['unsigned' => true, 'notnull' => false]);
			}
			if (!$reportes->hasIndex('emp_rep_origen_idx')) {
				$reportes->addIndex(['origen', 'origen_id'], 'emp_rep_origen_idx');
			}
			if (!$reportes->hasIndex('emp_rep_origen_unique')) {
				$reportes->addUniqueIndex(['origen', 'origen_id'], 'emp_rep_origen_unique');
			}
		}

		if ($schema->hasTable('empleados_actividades')) {
			$actividades = $schema->getTable('empleados_actividades');
			if (!$actividades->hasColumn('clave_sistema')) {
				$actividades->addColumn('clave_sistema', 'string', ['length' => 64, 'notnull' => false]);
			}
			if (!$actividades->hasIndex('emp_actividad_clave_unique')) {
				$actividades->addUniqueIndex(['clave_sistema'], 'emp_actividad_clave_unique');
			}
		}

		return $schema;
	}
}
