<?php

declare(strict_types=1);

namespace OCA\Empleados\Migration\Repair;

use OCA\Empleados\Service\PermissionCatalogInitializer;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

/**
 * Las migraciones de datos postSchemaChange no se ejecutan de forma fiable en
 * todas las rutas de app:install; este paso garantiza el catálogo en altas nuevas.
 */
final class InitializePermissionCatalog implements IRepairStep {
	public function __construct(
		private PermissionCatalogInitializer $initializer,
	) {
	}

	public function getName(): string {
		return 'Initialize the official Empleados permission catalog';
	}

	public function run(IOutput $output): void {
		$inserted = $this->initializer->initialize();
		$output->info(sprintf('Initialized %d missing official permission definitions.', count($inserted)));
	}
}
