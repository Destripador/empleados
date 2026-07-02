<?php
declare(strict_types=1);

namespace OCA\Empleados\Command;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use OCP\IConfig;

class RepairConfigCommand extends Command {
	protected static $defaultName = 'empleados:repair-config';

	private const CONFIG_TABLE = 'empleados_conf';

	private const REQUIRED_CONFIG_KEYS = [
		'usuario_almacenamiento',
		'automatic_save_note',
		'acumular_vacaciones',
		'modulo_ahorro',
		'modulo_ausencias',
		'ausencias_readonly',
	];

	/**
	 * Tablas y columnas críticas.
	 *
	 * OJO:
	 * Aquí van SIN prefijo oc_.
	 * 'empleados' valida realmente oc_empleados.
	 */
	private const REQUIRED_SCHEMA = [
        self::CONFIG_TABLE => [
            'Nombre',
            'Data',
        ],

        'empleados' => [
            'Id_empleados', 
            'Id_user', 
            'Numero_empleado', 
            'Ingreso', 
            'Correo_contacto', 
            'Id_departamento', 
            'Id_puesto',
            'Id_equipo', 
            'Id_gerente',
            'Id_socio',
            'Fondo_clave',
            'Fondo_ahorro',
            'Numero_cuenta',
            'Equipo_asignado',
            'Sueldo',
            'Notas', 
            'Fecha_nacimiento',
            'Estado',
            'Direccion',
            'Estado_civil',
            'Telefono_contacto',
            'Curp',
            'Rfc',
            'Imss',
            'Genero',
            'Contacto_emergencia',
            'Numero_emergencia',
            'created_at',
            'updated_at', 
        ],

        'departamentos' => [
            'id_departamento',
            'nombre',
        ],

        'puestos' => [
            'id_puestos',
            'nombre',
        ],

        'equipos' => [
            'Id_equipo',
            'Id_jefe_equipo',
            'Nombre',
            'created_at',
            'updated_at',
        ],
    ];

	public function __construct(
        private IDBConnection $db,
        private IConfig $config,
    ) {
        parent::__construct();
    }

	protected function configure(): void {
		$this
			->setDescription('Verifica tablas críticas de empleados y repara claves requeridas en empleados_conf.')
			->addOption(
				'check-only',
				null,
				InputOption::VALUE_NONE,
				'Solo verifica estructura; no modifica empleados_conf.'
			)
			->addOption(
				'skip-schema-check',
				null,
				InputOption::VALUE_NONE,
				'Omite validación de estructura y solo repara empleados_conf.'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$checkOnly = (bool)$input->getOption('check-only');
		$skipSchemaCheck = (bool)$input->getOption('skip-schema-check');

		if (!$skipSchemaCheck) {
			$schemaErrors = $this->verifySchema($output);

			if ($schemaErrors !== []) {
				$output->writeln('');
				$output->writeln('<error>La estructura no está completa. No se aplicaron reparaciones.</error>');

				foreach ($schemaErrors as $error) {
					$output->writeln('<error>- ' . $error . '</error>');
				}

				$output->writeln('');
				$output->writeln('Corrige la migración o crea una nueva migración antes de reparar configuración.');
				return Command::FAILURE;
			}
		}

		if ($checkOnly) {
			$output->writeln('');
			$output->writeln('<info>Verificación terminada. No se modificó nada.</info>');
			return Command::SUCCESS;
		}

		$this->db->beginTransaction();

		try {
			foreach (self::REQUIRED_CONFIG_KEYS as $key) {
				$count = $this->countConfigKey($key);

				if ($count === 0) {
					$this->insertConfigKey($key);
					$output->writeln('<info>INSERT:</info> ' . $key . ' -> NULL');
					continue;
				}

				if ($count > 1) {
					$output->writeln('<comment>WARNING:</comment> clave duplicada en empleados_conf: ' . $key . ' (' . $count . ' filas)');
				}

				$this->updateConfigKeyToNull($key);
				$output->writeln('<info>UPDATE:</info> ' . $key . ' -> NULL');
			}

			$this->db->commit();

			$output->writeln('');
			$output->writeln('<info>Done.</info>');
			return Command::SUCCESS;

		} catch (\Throwable $e) {
			$this->db->rollBack();

			$output->writeln('');
			$output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
			return Command::FAILURE;
		}
	}

	/**
	 * Valida que existan tablas y columnas críticas.
	 *
	 * No usa SHOW COLUMNS ni INFORMATION_SCHEMA para mantenerlo más portable.
	 * Si la tabla o columna no existe, la consulta falla y lo reportamos.
	 */
	private function verifySchema(OutputInterface $output): array {
		$errors = [];

		$output->writeln('<info>Verificando estructura de tablas...</info>');

		foreach (self::REQUIRED_SCHEMA as $table => $columns) {
			if (!$this->tableExists($table, $tableError)) {
				$errors[] = 'Tabla faltante o inaccesible: ' . $this->prefixedTable($table) . ' | ' . $tableError;
				$output->writeln('<error>FALTA TABLA:</error> ' . $this->prefixedTable($table));
				continue;
			}

			$output->writeln('<info>OK tabla:</info> ' . $this->prefixedTable($table));

			foreach ($columns as $column) {
				if (!$this->columnExists($table, $column, $columnError)) {
					$errors[] = 'Columna faltante en ' . $this->prefixedTable($table) . ': ' . $column . ' | ' . $columnError;
					$output->writeln('<error>  FALTA COLUMNA:</error> ' . $column);
					continue;
				}

				$output->writeln('<info>  OK columna:</info> ' . $column);
			}
		}

		return $errors;
	}

	private function tableExists(string $table, ?string &$error = null): bool {
		try {
			$qb = $this->db->getQueryBuilder();

			$qb->select('*')
				->from($table)
				->setMaxResults(1);

			$result = $qb->executeQuery();
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

			$qb->select($column)
				->from($table)
				->setMaxResults(1);

			$result = $qb->executeQuery();
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
			->where(
				$qb->expr()->eq(
					'Nombre',
					$qb->createNamedParameter($key)
				)
			);

		$result = $qb->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();

		return $count;
	}

	private function insertConfigKey(string $key): void {
		$qb = $this->db->getQueryBuilder();

		$qb->insert(self::CONFIG_TABLE)
			->values([
				'Nombre' => $qb->createNamedParameter($key),
				'Data' => $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL),
			])
			->executeStatement();
	}

	private function updateConfigKeyToNull(string $key): void {
		$qb = $this->db->getQueryBuilder();

		$qb->update(self::CONFIG_TABLE)
			->set('Data', $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL))
			->where(
				$qb->expr()->eq(
					'Nombre',
					$qb->createNamedParameter($key)
				)
			)
			->executeStatement();
	}

	private function prefixedTable(string $table): string {
        $prefix = $this->config->getSystemValueString('dbtableprefix', 'oc_');
        return $prefix . $table;
    }
}