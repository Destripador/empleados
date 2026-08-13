<?php

declare(strict_types=1);

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2034Date20260804120000 extends SimpleMigrationStep {
	public function __construct(
		private IDBConnection $db,
	) {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if (!$schema->hasTable('inv_mant_grupos') || !$schema->hasTable('inv_mantenimientos')) {
			return $schema;
		}

		$groups = $schema->getTable('inv_mant_grupos');
		if (!$groups->hasColumn('fecha_inicio')) {
			$groups->addColumn('fecha_inicio', 'date', ['notnull' => false]);
		}
		if (!$groups->hasColumn('fecha_fin')) {
			$groups->addColumn('fecha_fin', 'date', ['notnull' => false]);
		}
		if (!$groups->hasIndex('img_periodo_idx')) {
			$groups->addIndex(['fecha_inicio', 'fecha_fin'], 'img_periodo_idx');
		}

		$maintenances = $schema->getTable('inv_mantenimientos');
		if ($maintenances->hasColumn('fecha_programada')) {
			$maintenances->getColumn('fecha_programada')->setNotnull(false);
		}

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		// Existing campaigns were single-day campaigns. If their historical date is
		// unavailable we deliberately leave the new period nullable.
		$qb = $this->db->getQueryBuilder();
		$qb->update('inv_mant_grupos')
			->set('fecha_inicio', 'fecha_programada')
			->set('fecha_fin', 'fecha_programada')
			->where($qb->expr()->isNull('fecha_inicio'))
			->andWhere($qb->expr()->isNotNull('fecha_programada'))
			->executeStatement();
	}
}
