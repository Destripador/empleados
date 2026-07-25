<?php

declare(strict_types=1);

namespace OCA\Empleados\Command;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ResetBetaSchemaCommand extends Command {
	protected static $defaultName = 'empleados:reset-beta-schema';

	private const APP_ID = 'empleados';

	/**
	 * Tablas base conocidas.
	 *
	 * La detección automática obtiene las tablas desde las migraciones, pero
	 * esta lista sirve como respaldo para instalaciones beta antiguas.
	 */
	private const FALLBACK_TABLES = [
		'empleados',
		'puestos',
		'departamentos',
		'empleados_conf',
		'aniversarios',
		'tipo_ausencia',
		'ausencias',
		'historial_ausencias',
		'equipos',
		'user_ahorro',
		'historial_ahorro',
		'CapitalHumano',

		'empleados_clientes',
		'empleados_actividades',
		'empleados_rep_tiempos',

		'empleados_honorarios',
		'empleados_honorarios_p',
		'empleados_honorarios_parcialidades',
		'empleados_festivos',

		'inventario_modelos',
		'inventario_computo',
		'soporte_historial',

		'emp_comp_solicitudes',
		'emp_comp_detalles',
		'emp_comp_proveedores',
		'emp_comp_cotizaciones',
		'emp_comp_autoriza',
		'emp_comp_adjuntos',
		'emp_comp_ordenes',
		'emp_comp_historial',
		'emp_comp_firmas',
		'emp_comp_docs',
	];

	/**
	 * Tablas de Nextcloud que nunca deben eliminarse.
	 */
	private const PROTECTED_TABLES = [
		'migrations',
		'appconfig',
		'jobs',
		'users',
		'groups',
		'filecache',
		'storages',
	];

	public function __construct(
		private IDBConnection $db,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setDescription(
				'Elimina completamente las tablas y migraciones beta de Empleados y reconstruye el esquema desde cero.'
			)
			->addOption(
				'dry-run',
				null,
				InputOption::VALUE_NONE,
				'Muestra las tablas y migraciones que serían eliminadas sin modificar nada.'
			)
			->addOption(
				'force',
				'f',
				InputOption::VALUE_NONE,
				'Confirma la eliminación irreversible de todos los datos del módulo Empleados.'
			);
	}

	protected function execute(
		InputInterface $input,
		OutputInterface $output,
	): int {
		$dryRun = (bool)$input->getOption('dry-run');
		$force = (bool)$input->getOption('force');

		$output->writeln('');
		$output->writeln('<info>Empleados: reinicio completo del esquema beta</info>');
		$output->writeln('<comment>Esta operación elimina todos los datos almacenados por el módulo.</comment>');
		$output->writeln('');

		try {
			$managedTables = $this->discoverManagedTables();
			$existingTables = $this->findExistingTables($managedTables);
			$executedMigrations = $this->getExecutedMigrations();
		} catch (\Throwable $e) {
			$output->writeln(
				'<error>No fue posible preparar la operación: '
				. $e->getMessage()
				. '</error>'
			);

			return Command::FAILURE;
		}

		$this->printPlan(
			$output,
			$existingTables,
			$executedMigrations
		);

		if ($dryRun) {
			$output->writeln('');
			$output->writeln(
				'<info>Simulación terminada. No se modificó la base de datos.</info>'
			);

			return Command::SUCCESS;
		}

		if (!$force) {
			$output->writeln('');
			$output->writeln('<error>Operación cancelada.</error>');
			$output->writeln(
				'Debes confirmar la eliminación usando: <comment>--force</comment>'
			);

			return Command::INVALID;
		}

		/*
		 * MigrationService es una clase interna de Nextcloud, pero es la misma
		 * utilizada por el comando oficial migrations:migrate.
		 */
		if (!$this->repairCommandExists()) {
			$output->writeln(
				'<error>No se encuentra el comando empleados:repair-config.</error>'
			);

			return Command::FAILURE;
		}

		try {
			$output->writeln('');
			$output->writeln('<info>1. Eliminando tablas del módulo...</info>');

			$this->dropTables($existingTables, $output);

			$output->writeln('');
			$output->writeln('<info>2. Eliminando historial de migraciones...</info>');

			$deletedMigrations = $this->deleteMigrationHistory();

			$output->writeln(
				'<info>Registros eliminados de migrations:</info> '
				. $deletedMigrations
			);

			$output->writeln('');
			$output->writeln('<info>3. Ejecutando migraciones desde cero...</info>');

			$migrationResult = $this->runMigrations($output);

			if ($migrationResult !== Command::SUCCESS) {
				throw new \RuntimeException(
					'El comando migrations:migrate terminó con código: '
					. $migrationResult
				);
			}

			$output->writeln('');
			$output->writeln('<info>4. Reparando configuraciones...</info>');

			$repairResult = $this->runRepairConfig($output);

			if ($repairResult !== Command::SUCCESS) {
				$output->writeln('');
				$output->writeln(
					'<error>Las migraciones terminaron, pero la reparación de configuración reportó errores.</error>'
				);

				return Command::FAILURE;
			}
		} catch (\Throwable $e) {
			$output->writeln('');
			$output->writeln(
				'<error>Error durante la reconstrucción: '
				. $e->getMessage()
				. '</error>'
			);

			$output->writeln('');
			$output->writeln(
				'<comment>Puedes corregir la migración y volver a ejecutar este mismo comando.</comment>'
			);

			return Command::FAILURE;
		}

		$output->writeln('');
		$output->writeln(
			'<info>Esquema de Empleados reconstruido correctamente.</info>'
		);
		$output->writeln(
			'<info>No quedaron tablas ni registros de migraciones anteriores.</info>'
		);

		return Command::SUCCESS;
	}

	/**
	 * Obtiene tablas mencionadas en las migraciones.
	 *
	 * Detecta tanto tablas actuales creadas mediante createTable() como tablas
	 * heredadas eliminadas mediante dropTable().
	 */
	private function discoverManagedTables(): array {
		$migrationDirectory = dirname(__DIR__) . '/Migration';
		$files = glob($migrationDirectory . '/Version*.php');

		if ($files === false) {
			throw new \RuntimeException(
				'No se pudo leer el directorio de migraciones.'
			);
		}

		$tables = [];

		foreach ($files as $file) {
			$content = file_get_contents($file);

			if ($content === false) {
				throw new \RuntimeException(
					'No se pudo leer la migración: ' . basename($file)
				);
			}

			/*
			 * Ejemplos detectados:
			 *
			 * $schema->createTable('empleados');
			 * $schema->dropTable('empleados_clientes');
			 */
			$matchesFound = preg_match_all(
				'/->\s*(?:createTable|dropTable)\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/',
				$content,
				$matches
			);

			if ($matchesFound === false) {
				throw new \RuntimeException(
					'No se pudo analizar la migración: ' . basename($file)
				);
			}

			foreach ($matches[1] ?? [] as $table) {
				$tables[] = (string)$table;
			}
		}

		foreach (self::FALLBACK_TABLES as $table) {
			$tables[] = $table;
		}

		$tables = array_values(array_unique($tables));

		$tables = array_values(array_filter(
			$tables,
			function (string $table): bool {
				if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/', $table)) {
					return false;
				}

				return !in_array(
					strtolower($table),
					self::PROTECTED_TABLES,
					true
				);
			}
		));

		return $tables;
	}

	private function findExistingTables(array $tables): array {
		$existing = [];

		foreach ($tables as $table) {
			if ($this->db->tableExists($table)) {
				$existing[] = $table;
			}
		}

		return $existing;
	}

	private function getExecutedMigrations(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('version')
			->from('migrations')
			->where(
				$qb->expr()->eq(
					'app',
					$qb->createNamedParameter(
						self::APP_ID,
						IQueryBuilder::PARAM_STR
					)
				)
			)
			->orderBy('version', 'ASC');

		$result = $qb->executeQuery();
		$versions = [];

		while ($row = $result->fetch()) {
			if (isset($row['version'])) {
				$versions[] = (string)$row['version'];
			}
		}

		$result->closeCursor();

		return $versions;
	}

	private function printPlan(
		OutputInterface $output,
		array $tables,
		array $migrations,
	): void {
		$output->writeln(
			'<comment>Tablas que serán eliminadas: '
			. count($tables)
			. '</comment>'
		);

		if ($tables === []) {
			$output->writeln('  Ninguna tabla encontrada.');
		} else {
			foreach (array_reverse($tables) as $table) {
				$output->writeln('  - ' . $table);
			}
		}

		$output->writeln('');
		$output->writeln(
			'<comment>Migraciones registradas que serán eliminadas: '
			. count($migrations)
			. '</comment>'
		);

		if ($migrations === []) {
			$output->writeln('  Ninguna migración registrada.');
		} else {
			foreach ($migrations as $migration) {
				$output->writeln('  - ' . $migration);
			}
		}
	}

	private function dropTables(
		array $tables,
		OutputInterface $output,
	): void {
		/*
		 * Las tablas se eliminan en orden inverso a su creación para reducir
		 * problemas con dependencias entre tablas.
		 */
		foreach (array_reverse($tables) as $table) {
			if (!$this->db->tableExists($table)) {
				continue;
			}

			$this->db->dropTable($table);

			$output->writeln('<info>DROP:</info> ' . $table);
		}
	}

	private function deleteMigrationHistory(): int {
		$qb = $this->db->getQueryBuilder();

		$qb->delete('migrations')
			->where(
				$qb->expr()->eq(
					'app',
					$qb->createNamedParameter(
						self::APP_ID,
						IQueryBuilder::PARAM_STR
					)
				)
			);

		return $qb->executeStatement();
	}

	private function repairCommandExists(): bool {
		$application = $this->getApplication();

		return $application !== null
			&& $application->has('empleados:repair-config');
	}

	private function runRepairConfig(OutputInterface $output): int {
		$application = $this->getApplication();

		if ($application === null) {
			return Command::FAILURE;
		}

		$command = $application->find('empleados:repair-config');

		$repairInput = new ArrayInput([
			'command' => 'empleados:repair-config',
		]);

		$repairInput->setInteractive(false);

		return $command->run($repairInput, $output);
	}

	private function runMigrations(OutputInterface $output): int {
		$application = $this->getApplication();

		if ($application === null) {
			$output->writeln(
				'<error>No se pudo obtener la aplicación de consola.</error>'
			);

			return Command::FAILURE;
		}

		if (!$application->has('migrations:migrate')) {
			$output->writeln(
				'<error>Nextcloud no tiene disponible el comando migrations:migrate.</error>'
			);

			return Command::FAILURE;
		}

		$command = $application->find('migrations:migrate');

		$migrationInput = new ArrayInput([
			'command' => 'migrations:migrate',
			'app' => self::APP_ID,
			'version' => 'latest',
		]);

		$migrationInput->setInteractive(false);

		return $command->run($migrationInput, $output);
	}
}