<?php

declare(strict_types=1);

namespace OCA\Empleados\Migration;

use Closure;
use Doctrine\DBAL\Types\Type;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Agrega las columnas de auditoría necesarias para reconstruir vacaciones sin
 * destruir datos publicados. Los índices únicos admiten múltiples NULL en las
 * bases soportadas por Nextcloud, por lo que solicitudes antiguas sin clave de
 * idempotencia no colisionan.
 */
final class Version2027Date20260729120000 extends SimpleMigrationStep {
	public function changeSchema(
		IOutput $output,
		Closure $schemaClosure,
		array $options,
	): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('historial_ausencias')) {
			$table = $schema->getTable('historial_ausencias');

			if ($table->hasColumn('dias_solicitados')) {
				$columna = $table->getColumn('dias_solicitados');
				$columna->setType(Type::getType(Types::DECIMAL));
				$columna->setOptions([
					'precision' => 6,
					'scale' => 2,
					'notnull' => true,
					'default' => 0,
				]);
			}

			if (!$table->hasColumn('dias_de_periodo')) {
				$table->addColumn('dias_de_periodo', Types::DECIMAL, [
					'precision' => 6,
					'scale' => 2,
					'notnull' => true,
					'default' => 0,
				]);
			}

			if (!$table->hasColumn('solicitud_grupo')) {
				$table->addColumn('solicitud_grupo', Types::STRING, [
					'length' => 64,
					'notnull' => false,
					'default' => null,
				]);
			}

			if (!$table->hasColumn('idempotency_key')) {
				$table->addColumn('idempotency_key', Types::STRING, [
					'length' => 64,
					'notnull' => false,
					'default' => null,
				]);
			}

			if (!$table->hasIndex('hist_aus_grupo_idx')) {
				$table->addIndex(['solicitud_grupo'], 'hist_aus_grupo_idx');
			}

			if (!$table->hasIndex('hist_aus_idem_uniq')) {
				$table->addUniqueIndex(
					['id_ausencias', 'idempotency_key'],
					'hist_aus_idem_uniq',
				);
			}
		}

		if ($schema->hasTable('historial_vacaciones')) {
			$table = $schema->getTable('historial_vacaciones');
			$decimales = [
				'dias_periodo_usados',
				'dias_acumulados_usados',
				'dias_acumulados_vencidos',
				'dias_excedentes',
			];

			foreach ($decimales as $columna) {
				if (!$table->hasColumn($columna)) {
					$table->addColumn($columna, Types::DECIMAL, [
						'precision' => 6,
						'scale' => 2,
						'notnull' => true,
						'default' => 0,
					]);
				}
			}

			if (!$table->hasColumn('fecha_ingreso_base')) {
				$table->addColumn('fecha_ingreso_base', Types::STRING, [
					'length' => 10,
					'notnull' => false,
					'default' => null,
				]);
			}

			if (!$table->hasColumn('vigente')) {
				$table->addColumn('vigente', Types::SMALLINT, [
					'notnull' => true,
					'default' => 0,
				]);
			}

			if (!$table->hasColumn('recalculado_at')) {
				$table->addColumn('recalculado_at', Types::STRING, [
					'length' => 30,
					'notnull' => false,
					'default' => null,
				]);
			}

			if (!$table->hasColumn('acumulado_asignado_manualmente')) {
				$table->addColumn('acumulado_asignado_manualmente', Types::SMALLINT, [
					'notnull' => true,
					'default' => 0,
				]);
			}

			if (!$table->hasIndex('historial_vac_emp_aniv_idx')) {
				$table->addUniqueIndex(
					['id_empleado', 'numero_aniversario'],
					'historial_vac_emp_aniv_idx',
				);
			}
		}

		return $schema;
	}
}
