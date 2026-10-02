<?php

declare(strict_types=1);

namespace OCA\Empleados\Migration\Repair;

use OCA\Empleados\Service\DefaultConfigInitializer;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

final class InitializeDefaultConfig implements IRepairStep {
	public function __construct(
		private DefaultConfigInitializer $initializer,
	) {
	}

	public function getName(): string {
		return 'Initialize required Empleados configuration';
	}

	public function run(IOutput $output): void {
		$inserted = $this->initializer->initialize();

		$output->info(sprintf(
			'Initialized %d empleados_conf defaults and %d app config defaults.',
			count($inserted['table']),
			count($inserted['app']),
		));
	}
}
