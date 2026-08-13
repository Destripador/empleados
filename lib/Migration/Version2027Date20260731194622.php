<?php
declare(strict_types=1);

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2027Date20260731194622 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$table = $schema->getTable('empleados_festivos');

		if (!$table->hasColumn('tipo')) {
			$table->addColumn('tipo', 'string', ['notnull' => true, 'length' => 10, 'default' => 'fijo']);
		}
		if (!$table->hasColumn('oficial')) {
			$table->addColumn('oficial', 'smallint', ['notnull' => true, 'default' => 0]);
		}
		if (!$table->hasColumn('regla_mes')) {
			$table->addColumn('regla_mes', 'smallint', ['notnull' => false]);
		}
		if (!$table->hasColumn('regla_semana')) {
			// 1,2,3,4 = primera..cuarta semana; -1 = última semana del mes
			$table->addColumn('regla_semana', 'smallint', ['notnull' => false]);
		}
		if (!$table->hasColumn('regla_dia_semana')) {
			// ISO-8601: 1 = lunes ... 7 = domingo
			$table->addColumn('regla_dia_semana', 'smallint', ['notnull' => false]);
		}
		if (!$table->hasColumn('anio_calculado')) {
			$table->addColumn('anio_calculado', 'integer', ['notnull' => false]);
		}

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		// Sembrar los 7 festivos oficiales solo si la tabla está vacía de oficiales
		$connection = \OC::$server->getDatabaseConnection();
		$qb = $connection->getQueryBuilder();
		$count = $qb->select($qb->createFunction('COUNT(*)'))
			->from('empleados_festivos')
			->where($qb->expr()->eq('oficial', $qb->createNamedParameter(1)))
			->executeQuery()->fetchOne();

		if ((int)$count > 0) {
			return;
		}

		$anio = (int)date('Y');
		$oficiales = [
			['nombre' => 'Año Nuevo',              'tipo' => 'fijo',     'fecha' => '01-01'],
			['nombre' => 'Día de la Constitución',  'tipo' => 'variable', 'mes' => 2,  'semana' => 1, 'dia' => 1],
			['nombre' => 'Natalicio de Benito Juárez','tipo' => 'variable','mes' => 3,  'semana' => 3, 'dia' => 1],
			['nombre' => 'Día del Trabajo',          'tipo' => 'fijo',     'fecha' => '05-01'],
			['nombre' => 'Independencia de México',  'tipo' => 'fijo',     'fecha' => '09-16'],
			['nombre' => 'Revolución Mexicana',      'tipo' => 'variable', 'mes' => 11, 'semana' => 3, 'dia' => 1],
			['nombre' => 'Navidad',                  'tipo' => 'fijo',     'fecha' => '12-25'],
		];

		foreach ($oficiales as $f) {
			$fecha = $f['tipo'] === 'fijo'
				? $f['fecha']
				: \OCA\Empleados\Service\FestivosCalculator::nthWeekday($anio, $f['mes'], $f['dia'], $f['semana'])->format('m-d');

			$insert = $connection->getQueryBuilder();
			$insert->insert('empleados_festivos')
				->values([
					'nombre' => $insert->createNamedParameter($f['nombre']),
					'fecha' => $insert->createNamedParameter($fecha),
					'tipo' => $insert->createNamedParameter($f['tipo']),
					'oficial' => $insert->createNamedParameter(1),
					'regla_mes' => $insert->createNamedParameter($f['mes'] ?? null),
					'regla_semana' => $insert->createNamedParameter($f['semana'] ?? null),
					'regla_dia_semana' => $insert->createNamedParameter($f['dia'] ?? null),
					'anio_calculado' => $insert->createNamedParameter($f['tipo'] === 'variable' ? $anio : null),
				])
				->executeStatement();
		}
	}
}