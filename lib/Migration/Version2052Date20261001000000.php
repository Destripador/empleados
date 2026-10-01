<?php

declare(strict_types=1);

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IConfig;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2052Date20261001000000 extends SimpleMigrationStep {
	private const APP_ID = 'empleados';

	private const LEGACY_APP_CONFIG_KEYS = [
		'parking_mode',
		'parking_maintenance_reason',
		'parking_maintenance_started_at',
		'parking_maintenance_started_by',
		'parking_maintenance_until',
		'parking_maintenance_published_at',
		'parking_maintenance_published_by',
	];

	public function __construct(private IConfig $config) {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		// Drop dependent tables before their parent tables.
		foreach ([
			'espacio_obstruye',
			'emp_esp_disp',
			'espacio_empleados',
			'espacio',
			'empleados_noi_conf',
			'empleados_ent_fed_noi',
		] as $tableName) {
			if ($schema->hasTable($tableName)) {
				$schema->dropTable($tableName);
			}
		}

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		foreach (self::LEGACY_APP_CONFIG_KEYS as $key) {
			$this->config->deleteAppValue(self::APP_ID, $key);
		}

		$output->info('Removed obsolete space-assignment tables and external payroll connection settings.');
	}
}
