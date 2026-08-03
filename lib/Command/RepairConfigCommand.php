<?php
declare(strict_types=1);

namespace OCA\Empleados\Command;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\IDBConnection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class RepairConfigCommand extends Command {
	protected static $defaultName = 'empleados:repair-config';

	private const APP_ID = 'empleados';
	private const CONFIG_TABLE = 'empleados_conf';

	/** Configuraciones almacenadas en oc_empleados_conf. */
	private const REQUIRED_CONFIG = [
		'usuario_almacenamiento' => null,
		'automatic_save_note' => 'false',
		'acumular_vacaciones' => 'false',
		'modulo_ahorro' => 'false',
		'modulo_ausencias' => 'false',
		'ausencias_readonly' => 'false',
		'modulo_clientes' => 'false',
		'modulo_reporte_tiempos' => 'false',
		'modulo_inventario' => 'false',
		'modulo_soporte' => 'false',
		'modulo_compras' => 'false',
	];

	/** Configuraciones almacenadas en oc_appconfig. */
	private const REQUIRED_APP_CONFIG = [
		'reportes_recordatorios_enabled' => 'true',
		'reportes_recordatorios_grupo' => 'empleados',
		'reportes_recordatorios_hora' => '17',
		'reportes_recordatorios_zona_horaria' => 'America/Mexico_City',
		'reportes_recordatorios_email' => 'true',
		'reportes_horas_minimas' => '0',
		'reportes_admin_reports_group' => 'recursos_humanos',
	];

	/**
	 * Esquema crítico esperado después de ejecutar todas las migraciones.
	 * Los nombres deben coincidir con los usados por los mappers/controladores.
	 */
	private const REQUIRED_SCHEMA = [
		'empleados_conf' => ['Id_conf', 'Nombre', 'Data'],
		'empleados' => [
			'Id_empleados', 'Id_user', 'Numero_empleado', 'Ingreso',
			'Correo_contacto', 'Id_departamento', 'Id_puesto', 'Id_equipo',
			'Id_gerente', 'Id_socio', 'Fondo_clave', 'Fondo_ahorro',
			'Numero_cuenta', 'Equipo_asignado', 'Sueldo', 'Notas',
			'Fecha_nacimiento', 'Estado', 'Direccion', 'Estado_civil',
			'Telefono_contacto', 'Curp', 'Rfc', 'Imss', 'Genero',
			'Contacto_emergencia', 'Numero_emergencia', 'created_at', 'updated_at',
		],
		'departamentos' => ['Id_departamento', 'Id_padre', 'Nombre', 'created_at', 'updated_at'],
		'puestos' => ['Id_puestos', 'Nombre', 'created_at', 'updated_at'],
		'equipos' => ['Id_equipo', 'Id_jefe_equipo', 'Nombre', 'created_at', 'updated_at'],
		'aniversarios' => ['id_aniversario', 'numero_aniversario', 'fecha_de', 'fecha_hasta', 'dias'],
		'tipo_ausencia' => ['id_tipo_ausencia', 'nombre', 'descripcion', 'solicitar_archivo', 'solicitar_prima_vacacional'],
		'ausencias' => ['id_ausencias', 'id_empleado', 'id_aniversario', 'dias_disponibles', 'prima_vacacional', 'timestamp'],
		'historial_ausencias' => [
			'id_historial_ausencias', 'id_ausencias', 'id_aniversario',
			'id_tipo_ausencia', 'fecha_de', 'fecha_hasta', 'prima_vacacional',
			'archivo', 'timestamp', 'a_socio', 'a_gerente', 'a_capital_humano', 'notas',
		],
		'user_ahorro' => ['id_ahorro', 'id_user', 'id_permision', 'state', 'last_modified'],
		'historial_ahorro' => ['id_historial', 'id_ahorro', 'cantidad_solicitada', 'cantidad_total', 'fecha_solicitud', 'estado', 'nota'],
		'CapitalHumano' => ['Id_ch', 'Id_empleado', 'created_at', 'updated_at'],
		'empleados_clientes' => [
			'id', 'nombre', 'detalles', 'lider_proyecto', 'colaboradores',
			'razon_social', 'nombre_contacto', 'telefono', 'correo',
			'ubicacion', 'especial', 'cliente_padre', 'estado',
		],
		'soporte_historial' => ['id_soporte', 'id_equipo', 'duracion_minutos'],
		'empleados_rep_tiempos' => ['id_reporte', 'id_empleado', 'origen', 'origen_id'],
		'empleados_actividades' => ['id_actividad', 'nombre', 'cargable', 'clave_sistema'],
	];

	public function __construct(
		private IDBConnection $db,
		private IConfig $config,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setDescription('Verifica migraciones y esquema; agrega únicamente configuraciones faltantes.')
			->addOption(
				'check-only',
				null,
				InputOption::VALUE_NONE,
				'Solo verifica; no modifica ninguna configuración.'
			)
			->addOption(
				'skip-schema-check',
				null,
				InputOption::VALUE_NONE,
				'Omite la validación del esquema. Úsalo solamente para diagnóstico.'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$checkOnly = (bool)$input->getOption('check-only');
		$skipSchemaCheck = (bool)$input->getOption('skip-schema-check');

		$migrationErrors = $this->verifyMigrations($output);
		if ($migrationErrors !== []) {
			$this->printErrors($output, 'Hay migraciones faltantes. No se modificó nada.', $migrationErrors);
			$output->writeln('Ejecuta primero: <comment>php occ upgrade</comment>');
			return Command::FAILURE;
		}

		if (!$skipSchemaCheck) {
			$schemaErrors = $this->verifySchema($output);
			if ($schemaErrors !== []) {
				$this->printErrors($output, 'El esquema está incompleto. No se modificó nada.', $schemaErrors);
				$output->writeln('Ejecuta <comment>php occ upgrade</comment> y revisa las migraciones indicadas.');
				return Command::FAILURE;
			}
		}

		$configErrors = $this->verifyConfiguration($output);

		if ($checkOnly) {
			$output->writeln('');
			if ($configErrors !== []) {
				$this->printErrors($output, 'La configuración está incompleta.', $configErrors);
				return Command::FAILURE;
			}

			$output->writeln('<info>Verificación terminada: migraciones, esquema y configuración correctos.</info>');
			return Command::SUCCESS;
		}

		if ($configErrors === []) {
			$output->writeln('');
			$output->writeln('<info>No hay configuraciones faltantes.</info>');
			return Command::SUCCESS;
		}

		try {
			$this->repairTableConfiguration($output);
			$this->repairAppConfiguration($output);
		} catch (\Throwable $e) {
			$output->writeln('');
			$output->writeln('<error>Error durante la reparación: ' . $e->getMessage() . '</error>');
			return Command::FAILURE;
		}

		$output->writeln('');
		$output->writeln('<info>Verificando nuevamente la configuración...</info>');
		$remainingErrors = $this->verifyConfiguration($output);

		if ($remainingErrors !== []) {
			$this->printErrors($output, 'La reparación terminó parcialmente.', $remainingErrors);
			return Command::FAILURE;
		}

		$output->writeln('');
		$output->writeln('<info>Reparación terminada correctamente.</info>');
		return Command::SUCCESS;
	}

	/** Descubre automáticamente todos los archivos lib/Migration/Version*.php. */
	private function discoverRequiredMigrations(): array {
		$migrationDirectory = dirname(__DIR__) . '/Migration';
		$files = glob($migrationDirectory . '/Version*.php');

		if ($files === false) {
			throw new \RuntimeException('No se pudo leer el directorio de migraciones.');
		}

		$versions = [];
		foreach ($files as $file) {
			$name = pathinfo($file, PATHINFO_FILENAME);
			if (preg_match('/^Version(.+)$/', $name, $matches) === 1) {
				$versions[] = $matches[1];
			}
		}

		sort($versions, SORT_NATURAL);
		return array_values(array_unique($versions));
	}

	private function verifyMigrations(OutputInterface $output): array {
		$output->writeln('<info>Verificando migraciones...</info>');

		try {
			$required = $this->discoverRequiredMigrations();
			if ($required === []) {
				return ['No se encontraron archivos Version*.php en lib/Migration.'];
			}

			$qb = $this->db->getQueryBuilder();
			$qb->select('version')
				->from('migrations')
				->where($qb->expr()->eq('app', $qb->createNamedParameter(self::APP_ID)));

			$result = $qb->executeQuery();
			$rows = $result->fetchAll();
			$result->closeCursor();

			$executed = [];
			foreach ($rows as $row) {
				if (isset($row['version'])) {
					$executed[] = (string)$row['version'];
				}
			}

			$errors = [];
			foreach ($required as $migration) {
				if (!in_array($migration, $executed, true)) {
					$errors[] = 'Migración faltante: ' . $migration;
					$output->writeln('<error>FALTA:</error> ' . $migration);
				} else {
					$output->writeln('<info>OK:</info> ' . $migration);
				}
			}

			return $errors;
		} catch (\Throwable $e) {
			return ['No se pudieron verificar las migraciones: ' . $e->getMessage()];
		}
	}

	private function verifySchema(OutputInterface $output): array {
		$output->writeln('');
		$output->writeln('<info>Verificando tablas y columnas...</info>');
		$errors = [];

		foreach (self::REQUIRED_SCHEMA as $table => $columns) {
			if (!$this->tableExists($table, $error)) {
				$errors[] = 'Tabla faltante o inaccesible: ' . $this->prefixedTable($table) . ' | ' . $error;
				$output->writeln('<error>FALTA TABLA:</error> ' . $this->prefixedTable($table));
				continue;
			}

			$output->writeln('<info>OK tabla:</info> ' . $this->prefixedTable($table));
			foreach ($columns as $column) {
				if (!$this->columnExists($table, $column, $error)) {
					$errors[] = 'Columna faltante en ' . $this->prefixedTable($table) . ': ' . $column . ' | ' . $error;
					$output->writeln('<error>  FALTA COLUMNA:</error> ' . $column);
				}
			}
		}

		return $errors;
	}

	private function verifyConfiguration(OutputInterface $output): array {
		$output->writeln('');
		$output->writeln('<info>Verificando configuraciones...</info>');
		$errors = [];

		foreach (self::REQUIRED_CONFIG as $key => $defaultValue) {
			$count = $this->countConfigKey($key);
			if ($count === 0) {
				$errors[] = 'Falta en ' . $this->prefixedTable(self::CONFIG_TABLE) . ': ' . $key;
				$output->writeln('<error>FALTA:</error> ' . $key);
			} elseif ($count > 1) {
				$errors[] = 'Clave duplicada en ' . $this->prefixedTable(self::CONFIG_TABLE) . ': ' . $key . ' (' . $count . ' filas)';
				$output->writeln('<error>DUPLICADA:</error> ' . $key . ' (' . $count . ')');
			} else {
				$output->writeln('<info>OK:</info> ' . $key);
			}
		}

		foreach (self::REQUIRED_APP_CONFIG as $key => $defaultValue) {
			if ($this->config->getAppValue(self::APP_ID, $key, '') === '') {
				$errors[] = 'Falta en appconfig: ' . $key;
				$output->writeln('<error>FALTA appconfig:</error> ' . $key);
			} else {
				$output->writeln('<info>OK appconfig:</info> ' . $key);
			}
		}

		return $errors;
	}

	private function repairTableConfiguration(OutputInterface $output): void {
		$this->db->beginTransaction();
		try {
			foreach (self::REQUIRED_CONFIG as $key => $defaultValue) {
				if ($this->countConfigKey($key) !== 0) {
					continue;
				}

				$this->insertConfigKey($key, $defaultValue);
				$output->writeln('<info>INSERT:</info> ' . $key . ' -> ' . ($defaultValue ?? 'NULL'));
			}
			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

	private function repairAppConfiguration(OutputInterface $output): void {
		foreach (self::REQUIRED_APP_CONFIG as $key => $defaultValue) {
			if ($this->config->getAppValue(self::APP_ID, $key, '') !== '') {
				continue;
			}

			$this->config->setAppValue(self::APP_ID, $key, $defaultValue);
			$output->writeln('<info>INSERT appconfig:</info> ' . $key . ' -> ' . $defaultValue);
		}
	}

	private function tableExists(string $table, ?string &$error = null): bool {
		try {
			$qb = $this->db->getQueryBuilder();
			$result = $qb->select('*')->from($table)->setMaxResults(1)->executeQuery();
			$result->closeCursor();
			return true;
		} catch (\Throwable $e) {
			$error = $e->getMessage();
			return false;
		}
	}

	private function columnExists(string $table, string $column, ?string &$error = null): bool {
		try {
			$qb = $this->db->getQueryBuilder();
			$result = $qb->select($column)->from($table)->setMaxResults(1)->executeQuery();
			$result->closeCursor();
			return true;
		} catch (\Throwable $e) {
			$error = $e->getMessage();
			return false;
		}
	}

	private function countConfigKey(string $key): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('COUNT(*)'))
			->from(self::CONFIG_TABLE)
			->where($qb->expr()->eq('Nombre', $qb->createNamedParameter($key)));

		$result = $qb->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();
		return $count;
	}

	private function insertConfigKey(string $key, ?string $value): void {
		$qb = $this->db->getQueryBuilder();
		$parameter = $value === null
			? $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL)
			: $qb->createNamedParameter($value, IQueryBuilder::PARAM_STR);

		$qb->insert(self::CONFIG_TABLE)
			->values([
				'Nombre' => $qb->createNamedParameter($key, IQueryBuilder::PARAM_STR),
				'Data' => $parameter,
			])
			->executeStatement();
	}

	private function prefixedTable(string $table): string {
		return $this->config->getSystemValueString('dbtableprefix', 'oc_') . $table;
	}

	private function printErrors(OutputInterface $output, string $title, array $errors): void {
		$output->writeln('');
		$output->writeln('<error>' . $title . '</error>');
		foreach ($errors as $error) {
			$output->writeln('<error>- ' . $error . '</error>');
		}
		$output->writeln('');
	}
}
