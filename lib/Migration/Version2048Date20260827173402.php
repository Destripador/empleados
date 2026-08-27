<?php

declare(strict_types=1);

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2048Date20260827173402 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('empleados_monedas')) {
			$table = $schema->createTable('empleados_monedas');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('tipo_moneda', 'string', [
				'notnull' => true,
				'length' => 10,
			]);
			$table->addColumn('serie', 'string', [
				'notnull' => true,
				'length' => 20,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['tipo_moneda'], 'empl_monedas_tipo_uidx');
		}

		if (!$schema->hasTable('empleados_tipo_cambio')) {
			$table = $schema->createTable('empleados_tipo_cambio');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('id_moneda', 'integer', [
				'notnull' => true,
			]);
			$table->addColumn('fecha', 'date', [
				'notnull' => true,
			]);
			$table->addColumn('valor', 'decimal', [
				'notnull' => true,
				'precision' => 12,
				'scale' => 6,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['id_moneda', 'fecha'], 'empl_tc_moneda_fecha_uidx');
		}

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$connection = \OC::$server->get(\OCP\IDBConnection::class);

		$qb = $connection->getQueryBuilder();
		$count = $qb->select($qb->func()->count('*'))
			->from('empleados_monedas')
			->executeQuery()
			->fetchOne();

		if ((int)$count > 0) {
			return;
		}

		$monedas = [
			['tipo_moneda' => 'USD', 'serie' => 'SF43718'],
			['tipo_moneda' => 'EUR', 'serie' => 'SF46410'],
		];

		foreach ($monedas as $moneda) {
			$qb = $connection->getQueryBuilder();
			$qb->insert('empleados_monedas')
				->values([
					'tipo_moneda' => $qb->createNamedParameter($moneda['tipo_moneda']),
					'serie' => $qb->createNamedParameter($moneda['serie']),
				])
				->executeStatement();
		}

		$output->info('Monedas por defecto (USD, EUR) insertadas.');
	}
}