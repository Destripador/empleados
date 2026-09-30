<?php

declare(strict_types=1);

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2051Date20260929192723 extends SimpleMigrationStep {
	public function __construct(private IDBConnection $db) {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('empleados')) {
			$table = $schema->getTable('empleados');

			if (!$table->hasColumn('Fecha_baja')) {
				$table->addColumn('Fecha_baja', 'date', [
					'notnull' => false,
					'default' => null,
				]);
			}
		}

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		// Bajas ya existentes: usa updated_at como fecha aproximada.
		$qb = $this->db->getQueryBuilder();
		$qb->update('empleados')
			->set('Fecha_baja', 'updated_at')
			->where($qb->expr()->eq('Estado', $qb->createNamedParameter(0)))
			->andWhere($qb->expr()->isNull('Fecha_baja'))
			->andWhere($qb->expr()->isNotNull('updated_at'))
			->executeStatement();

		$output->info('Columna Fecha_baja agregada a empleados y rellenada para inactivos existentes.');
	}
}