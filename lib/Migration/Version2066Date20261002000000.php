<?php

declare(strict_types=1);

namespace OCA\Empleados\Migration;

use Closure;
use OCA\Empleados\Service\PermissionCatalogInitializer;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Completa el catálogo oficial sin alterar filas ni grupos existentes.
 */
final class Version2066Date20261002000000 extends SimpleMigrationStep {
	public function __construct(
		private IDBConnection $db,
	) {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		return null;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$inserted = (new PermissionCatalogInitializer($this->db))->initialize();
		$output->info(sprintf('Added %d missing official permission definitions.', count($inserted)));
	}
}
