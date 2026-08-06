<?php

declare(strict_types=1);

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2035Date20260805090000 extends SimpleMigrationStep {
	public function __construct(private IDBConnection $db) {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('empleados_actividades')) {
			$activities = $schema->getTable('empleados_actividades');
			if (!$activities->hasColumn('tipo_actividad')) {
				$activities->addColumn('tipo_actividad', 'string', ['length' => 16, 'notnull' => true, 'default' => 'cliente']);
			}
			if (!$activities->hasColumn('alcance')) {
				$activities->addColumn('alcance', 'string', ['length' => 16, 'notnull' => true, 'default' => 'global']);
			}
			if (!$activities->hasIndex('emp_act_tipo_alc_idx')) {
				$activities->addIndex(['tipo_actividad', 'alcance'], 'emp_act_tipo_alc_idx');
			}
		}

		if (!$schema->hasTable('empleados_actividad_areas')) {
			$areas = $schema->createTable('empleados_actividad_areas');
			$areas->addColumn('id', 'bigint', ['autoincrement' => true, 'unsigned' => true, 'notnull' => true]);
			$areas->addColumn('id_actividad', 'integer', ['unsigned' => true, 'notnull' => true]);
			$areas->addColumn('id_departamento', 'integer', ['unsigned' => true, 'notnull' => true]);
			$areas->addColumn('created_at', 'datetime', ['notnull' => true]);
			$areas->setPrimaryKey(['id'], 'emp_act_area_pk');
			$areas->addIndex(['id_actividad'], 'emp_act_area_act_idx');
			$areas->addIndex(['id_departamento'], 'emp_act_area_dep_idx');
			$areas->addUniqueIndex(['id_actividad', 'id_departamento'], 'emp_act_area_unique');
		}

		if ($schema->hasTable('empleados_rep_tiempos')) {
			$reports = $schema->getTable('empleados_rep_tiempos');
			if (!$reports->hasColumn('tipo_trabajo')) {
				$reports->addColumn('tipo_trabajo', 'string', ['length' => 16, 'notnull' => false]);
			}
			if (!$reports->hasIndex('emp_rep_tipo_idx')) {
				$reports->addIndex(['tipo_trabajo'], 'emp_rep_tipo_idx');
			}
		}

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$activity = $this->db->getQueryBuilder();
		$updatedActivities = $activity->update('empleados_actividades')
			->set('tipo_actividad', $activity->createNamedParameter('interno'))
			->set('alcance', $activity->createNamedParameter('global'))
			->set('cargable', $activity->createNamedParameter(0, IQueryBuilder::PARAM_INT))
			->where($activity->expr()->eq('clave_sistema', $activity->createNamedParameter('soporte_ti')))
			->executeStatement();
		$output->info('Actividades de Soporte TI clasificadas como internas: ' . $updatedActivities);

		$absence = $this->db->getQueryBuilder();
		$absenceCount = $absence->update('empleados_rep_tiempos')
			->set('tipo_trabajo', $absence->createNamedParameter('ausencia'))
			->where($absence->expr()->isNull('tipo_trabajo'))
			->andWhere($absence->expr()->orX(
				$absence->expr()->eq('id_cliente', $absence->createNamedParameter(99999, IQueryBuilder::PARAM_INT)),
				$absence->expr()->eq('id_actividad', $absence->createNamedParameter(99999, IQueryBuilder::PARAM_INT)),
			))
			->executeStatement();
		$output->info('Reportes clasificados como ausencia: ' . $absenceCount);

		$internal = $this->db->getQueryBuilder();
		$internalCount = $internal->update('empleados_rep_tiempos')
			->set('tipo_trabajo', $internal->createNamedParameter('interno'))
			->where($internal->expr()->isNull('tipo_trabajo'))
			->andWhere($internal->expr()->orX(
				$internal->expr()->eq('origen', $internal->createNamedParameter('soporte_ti')),
				$internal->expr()->isNull('id_cliente'),
			))
			->executeStatement();
		$output->info('Reportes clasificados como trabajo interno: ' . $internalCount);

		$client = $this->db->getQueryBuilder();
		$clientCount = $client->update('empleados_rep_tiempos')
			->set('tipo_trabajo', $client->createNamedParameter('cliente'))
			->where($client->expr()->isNull('tipo_trabajo'))
			->executeStatement();
		$output->info('Reportes clasificados como trabajo para cliente: ' . $clientCount);
	}
}
