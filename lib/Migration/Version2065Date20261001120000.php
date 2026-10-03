<?php

declare(strict_types=1);

namespace OCA\Empleados\Migration;

use Closure;
use OCA\Empleados\Service\DefaultConfigInitializer;
use OCP\DB\ISchemaWrapper;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

final class Version2065Date20261001120000 extends SimpleMigrationStep {
	private DefaultConfigInitializer $initializer;

	public function __construct(IDBConnection $db, IConfig $config) {
		$this->initializer = new DefaultConfigInitializer($db, $config);
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		return null;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$inserted = $this->initializer->initialize();

		$output->info(sprintf(
			'Initialized %d empleados_conf defaults and %d app config defaults.',
			count($inserted['table']),
			count($inserted['app']),
		));
	}
}
