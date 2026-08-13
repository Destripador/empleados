<?php
declare(strict_types=1);

namespace OCA\Empleados\Migration\Repair;

use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use OCP\IConfig;

final class RepairEmpleadosTables implements IRepairStep {

    public function __construct(private IDBConnection $db, private IConfig $config,) {}

    public function getName(): string {
        return 'Empleados: verificar/crear tablas, índices y semillas';
    }

    public function run(IOutput $output): void {
        $output->info('RepairEmpleadosTables: iniciando…');
        $platform = $this->db->getDatabasePlatform()->getName(); // mysql | postgresql | sqlite

        // 1) Crear tablas que falten (NO destructivo)
        $this->ensureTableEmpleados($output, $platform);
        $this->ensureTableContactosEmergencia($output, $platform);
		$this->ensureTableInventarioMovimientos($output, $platform);
		$this->ensureSupportTimeIntegrationSchema($output, $platform);
        $this->ensureTablePuestos($output, $platform);
        $this->ensureTableDepartamentos($output, $platform);
        $this->ensureTableEmpleadosConf($output, $platform);
        $this->ensureTableAniversarios($output, $platform);
        $this->ensureTableTipoAusencia($output, $platform);
        $this->ensureTableAusencias($output, $platform);
        $this->ensureTableHistorialAusencias($output, $platform);
        $this->ensureTableEquipos($output, $platform);
        $this->ensureTableUserAhorro($output, $platform);
        $this->ensureTableHistorialAhorro($output, $platform);
        $this->ensureTableCapitalHumano($output, $platform);

        // 1.1) Asegurar DEFAULT de timestamp (idempotente; y rellena NULLs antes de NOT NULL)
        $this->ensureTimestampDefault($output, 'aniversarios');
        $this->ensureTimestampDefault($output, 'ausencias');
        $this->ensureTimestampDefault($output, 'historial_ausencias');

        // 2) Índices no destructivos (si faltan)
        $this->ensureIndex($output, $platform, 'empleados', 'idx_id_gerente', 'Id_gerente');
        $this->ensureIndex($output, $platform, 'empleados', 'idx_id_socio',   'Id_socio');
        $this->ensureIndex($output, $platform, 'equipos',   'idx_id_jefe_equipo', 'Id_jefe_equipo');
        $this->ensureIndex($output, $platform, 'equipos',   'idx_nombre_equipo',  'Nombre');

        // 3) Semillas idempotentes en empleados_conf
        $this->ensureConfig($output, 'empleados_conf', [
            'usuario_almacenamiento',
            'automatic_save_note',
            'acumular_vacaciones',
            'modulo_ahorro',
            'modulo_ausencias',
            'ausencias_readonly',
        ]);

        $output->info('RepairEmpleadosTables: terminado.');
    }

    /* ----------------- helpers de nombres/prefijos/existencia ----------------- */

    private function tn(string $base): string {
        // algunos drivers no exponen getPrefix(); obtenlo de la config
        $prefix = 'oc_';
        try {
            $prefix = $this->config->getSystemValueString('dbtableprefix', 'oc_');
        } catch (\Throwable) {
            // fallback silencioso a 'oc_'
        }
        return $prefix . $base;
    }

    private function tableExistsExact(string $exactName): bool {
        try {
            $p = $this->db->getDatabasePlatform()->getName();
            if ($p === 'mysql') {
                return (bool) $this->db->executeQuery(
                    "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
                    [$exactName]
                )->fetchOne();
            } elseif ($p === 'postgresql') {
                return (bool) $this->db->executeQuery(
                    "SELECT 1 FROM pg_tables WHERE schemaname = current_schema() AND tablename = ?",
                    [$exactName]
                )->fetchOne();
            } else { // sqlite
                return (bool) $this->db->executeQuery(
                    "SELECT 1 FROM sqlite_master WHERE type='table' AND name = ?",
                    [$exactName]
                )->fetchOne();
            }
        } catch (\Throwable) {
            return false;
        }
    }

	private function tableExistsAny(string $base): bool {
        // detecta con y sin prefijo
        return $this->tableExistsExact($base) || $this->tableExistsExact($this->tn($base));
    }

    /* ----------------- asegurar índice (usa SIEMPRE nombre prefijado) ----------------- */

    private function ensureIndex(IOutput $output, string $platform, string $tableBase, string $idxName, string $col): void {
        $phys = $this->tn($tableBase); // nombre físico con prefijo
        if (!$this->tableExistsExact($phys)) {
            $output->info("Tabla $phys no existe; omito índice $idxName.");
            return;
        }
        try {
            $exists = false;
            if ($platform === 'mysql') {
                $exists = (bool) $this->db->executeQuery(
                    "SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?",
                    [$phys, $idxName]
                )->fetchOne();
                if (!$exists) {
                    $this->db->executeStatement("CREATE INDEX `$idxName` ON `$phys` (`$col`)");
                    $output->info("Creado índice $idxName en $phys($col).");
                }
            } elseif ($platform === 'postgresql') {
                $exists = (bool) $this->db->executeQuery(
                    "SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?",
                    [$phys, $idxName]
                )->fetchOne();
                if (!$exists) {
                    $this->db->executeStatement("CREATE INDEX \"$idxName\" ON \"$phys\" (\"$col\")");
                    $output->info("Creado índice $idxName en $phys($col).");
                }
            } else { // sqlite
                $exists = (bool) $this->db->executeQuery(
                    "SELECT 1 FROM sqlite_master WHERE type='index' AND tbl_name = ? AND name = ?",
                    [$phys, $idxName]
                )->fetchOne();
                if (!$exists) {
                    $this->db->executeStatement("CREATE INDEX \"$idxName\" ON \"$phys\" (\"$col\")");
                    $output->info("Creado índice $idxName en $phys($col).");
                }
            }
        } catch (\Throwable $e) {
            $output->warning("No fue posible asegurar índice $idxName en $phys: " . $e->getMessage());
        }
    }

    /* ----------------- semillas (QueryBuilder maneja el prefijo solo) ----------------- */

    private function ensureConfig(IOutput $output, string $tableBase, array $keys): void {
        // OJO: QueryBuilder agrega prefijo solo; por eso usamos el base name aquí.
        if (!$this->tableExistsAny($tableBase)) {
            $output->info("Tabla {$this->tn($tableBase)} no existe; omito semillas.");
            return;
        }
        foreach ($keys as $nombre) {
            $sel = $this->db->getQueryBuilder();
            $sel->select('Id_conf')->from($tableBase)
                ->where($sel->expr()->eq('Nombre', $sel->createNamedParameter($nombre)));
            $exists = $sel->executeQuery()->fetchOne();
            if ($exists !== false) {
                continue;
            }
            $ins = $this->db->getQueryBuilder();
            $ins->insert($tableBase)->values(['Nombre' => $ins->createNamedParameter($nombre)])
                ->executeStatement();
            $output->info("Insertado {$this->tn($tableBase)}.Nombre='$nombre'.");
        }
    }

    /* ----------------- creadores por tabla (NO destructivos; usan tn()) ----------------- */

    private function ensureTableContactosEmergencia(IOutput $output, string $p): void {
        $base = 'emp_cont_emer'; $phys = $this->tn($base);
        if ($this->tableExistsAny($base)) return;
        try {
            if ($p === 'mysql') {
                $this->db->executeStatement("CREATE TABLE `$phys` (`id` BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, `id_empleado` INT NOT NULL, `nombre` VARCHAR(200) NOT NULL, `relacion` VARCHAR(120) NOT NULL, `numero_contacto` VARCHAR(80) NOT NULL, `medio_alternativo` VARCHAR(255) NULL, `tipo_ayuda` VARCHAR(255) NULL, `notas` LONGTEXT NULL, `es_principal` SMALLINT DEFAULT 0 NOT NULL, `principal_empleado` INT NULL, `orden` INT DEFAULT 0 NOT NULL, `created_at` VARCHAR(32) NOT NULL, `updated_at` VARCHAR(32) NOT NULL, PRIMARY KEY (`id`), UNIQUE (`principal_empleado`))");
            } elseif ($p === 'postgresql') {
                $this->db->executeStatement("CREATE TABLE \"$phys\" (\"id\" BIGSERIAL PRIMARY KEY, \"id_empleado\" INT NOT NULL, \"nombre\" VARCHAR(200) NOT NULL, \"relacion\" VARCHAR(120) NOT NULL, \"numero_contacto\" VARCHAR(80) NOT NULL, \"medio_alternativo\" VARCHAR(255) NULL, \"tipo_ayuda\" VARCHAR(255) NULL, \"notas\" TEXT NULL, \"es_principal\" SMALLINT DEFAULT 0 NOT NULL, \"principal_empleado\" INT NULL UNIQUE, \"orden\" INT DEFAULT 0 NOT NULL, \"created_at\" VARCHAR(32) NOT NULL, \"updated_at\" VARCHAR(32) NOT NULL)");
            } else {
                $this->db->executeStatement("CREATE TABLE \"$phys\" (\"id\" INTEGER PRIMARY KEY AUTOINCREMENT, \"id_empleado\" INT NOT NULL, \"nombre\" VARCHAR(200) NOT NULL, \"relacion\" VARCHAR(120) NOT NULL, \"numero_contacto\" VARCHAR(80) NOT NULL, \"medio_alternativo\" VARCHAR(255) NULL, \"tipo_ayuda\" VARCHAR(255) NULL, \"notas\" TEXT NULL, \"es_principal\" SMALLINT DEFAULT 0 NOT NULL, \"principal_empleado\" INT NULL UNIQUE, \"orden\" INT DEFAULT 0 NOT NULL, \"created_at\" VARCHAR(32) NOT NULL, \"updated_at\" VARCHAR(32) NOT NULL)");
            }
            $this->ensureIndex($output, $p, $base, 'emp_cont_empleado_idx', 'id_empleado');
            $this->ensureIndex($output, $p, $base, 'emp_cont_principal_idx', 'es_principal');
            $output->info('Creada tabla de contactos de emergencia.');
        } catch (\Throwable $e) {
            $output->warning('No fue posible crear contactos de emergencia: ' . $e->getMessage());
        }
    }

	private function ensureTableInventarioMovimientos(IOutput $output, string $p): void {
		$base = 'inv_movimientos'; $phys = $this->tn($base);
		if ($this->tableExistsAny($base)) return;
		try {
			if ($p === 'mysql') {
				$this->db->executeStatement("CREATE TABLE `$phys` (`id` BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, `id_equipo` INT UNSIGNED NOT NULL, `tipo_movimiento` VARCHAR(40) NOT NULL, `actor_uid` VARCHAR(255) NOT NULL, `actor_nombre` VARCHAR(255) NOT NULL, `empleado_anterior_uid` VARCHAR(255) NULL, `empleado_anterior_nombre` VARCHAR(255) NULL, `empleado_nuevo_uid` VARCHAR(255) NULL, `empleado_nuevo_nombre` VARCHAR(255) NULL, `estado_anterior` VARCHAR(80) NULL, `estado_nuevo` VARCHAR(80) NULL, `descripcion` LONGTEXT NULL, `cambios` LONGTEXT NULL, `fecha` DATETIME NOT NULL, PRIMARY KEY (`id`))");
				$this->db->executeStatement("CREATE INDEX `inv_mov_equipo_fecha` ON `$phys` (`id_equipo`, `fecha`)");
			} elseif ($p === 'postgresql') {
				$this->db->executeStatement("CREATE TABLE \"$phys\" (\"id\" BIGSERIAL PRIMARY KEY, \"id_equipo\" INT NOT NULL, \"tipo_movimiento\" VARCHAR(40) NOT NULL, \"actor_uid\" VARCHAR(255) NOT NULL, \"actor_nombre\" VARCHAR(255) NOT NULL, \"empleado_anterior_uid\" VARCHAR(255) NULL, \"empleado_anterior_nombre\" VARCHAR(255) NULL, \"empleado_nuevo_uid\" VARCHAR(255) NULL, \"empleado_nuevo_nombre\" VARCHAR(255) NULL, \"estado_anterior\" VARCHAR(80) NULL, \"estado_nuevo\" VARCHAR(80) NULL, \"descripcion\" TEXT NULL, \"cambios\" TEXT NULL, \"fecha\" TIMESTAMP NOT NULL)");
				$this->db->executeStatement("CREATE INDEX \"inv_mov_equipo_fecha\" ON \"$phys\" (\"id_equipo\", \"fecha\")");
			} else {
				$this->db->executeStatement("CREATE TABLE \"$phys\" (\"id\" INTEGER PRIMARY KEY AUTOINCREMENT, \"id_equipo\" INT NOT NULL, \"tipo_movimiento\" VARCHAR(40) NOT NULL, \"actor_uid\" VARCHAR(255) NOT NULL, \"actor_nombre\" VARCHAR(255) NOT NULL, \"empleado_anterior_uid\" VARCHAR(255) NULL, \"empleado_anterior_nombre\" VARCHAR(255) NULL, \"empleado_nuevo_uid\" VARCHAR(255) NULL, \"empleado_nuevo_nombre\" VARCHAR(255) NULL, \"estado_anterior\" VARCHAR(80) NULL, \"estado_nuevo\" VARCHAR(80) NULL, \"descripcion\" TEXT NULL, \"cambios\" TEXT NULL, \"fecha\" TEXT NOT NULL)");
				$this->db->executeStatement("CREATE INDEX \"inv_mov_equipo_fecha\" ON \"$phys\" (\"id_equipo\", \"fecha\")");
			}
			$output->info('Creada tabla de movimientos de inventario.');
		} catch (\Throwable $e) {
			$output->warning('No fue posible crear movimientos de inventario: ' . $e->getMessage());
		}
	}

	private function columnExists(string $tableBase, string $column): bool {
		try {
			$schema = $this->db->createSchema();
			return $schema->hasTable($this->tn($tableBase))
				&& $schema->getTable($this->tn($tableBase))->hasColumn($column);
		} catch (\Throwable) {
			return false;
		}
	}

	private function ensureSupportTimeIntegrationSchema(IOutput $output, string $platform): void {
		$definitions = [
			'soporte_historial' => [
				'duracion_minutos' => $platform === 'mysql' ? 'INT UNSIGNED NULL' : 'INTEGER NULL',
			],
			'empleados_rep_tiempos' => [
				'origen' => 'VARCHAR(40) NULL',
				'origen_id' => $platform === 'mysql' ? 'INT UNSIGNED NULL' : 'INTEGER NULL',
			],
			'empleados_actividades' => ['clave_sistema' => 'VARCHAR(64) NULL'],
		];

		foreach ($definitions as $table => $columns) {
			if (!$this->tableExistsAny($table)) continue;
			foreach ($columns as $column => $definition) {
				if ($this->columnExists($table, $column)) continue;
				$quotedTable = $platform === 'mysql' ? '`' . $this->tn($table) . '`' : '"' . $this->tn($table) . '"';
				$quotedColumn = $platform === 'mysql' ? '`' . $column . '`' : '"' . $column . '"';
				$this->db->executeStatement("ALTER TABLE $quotedTable ADD COLUMN $quotedColumn $definition");
				$output->info("Agregada columna $table.$column.");
			}
		}

		$this->ensureCompositeIndex($output, $platform, 'empleados_rep_tiempos', 'emp_rep_origen_idx', ['origen', 'origen_id'], false);
		$this->ensureCompositeIndex($output, $platform, 'empleados_rep_tiempos', 'emp_rep_origen_unique', ['origen', 'origen_id'], true);
		$this->ensureCompositeIndex($output, $platform, 'empleados_actividades', 'emp_actividad_clave_unique', ['clave_sistema'], true);
	}

	private function ensureCompositeIndex(IOutput $output, string $platform, string $table, string $name, array $columns, bool $unique): void {
		$physical = $this->tn($table);
		try {
			$schema = $this->db->createSchema();
			if ($schema->hasTable($physical) && $schema->getTable($physical)->hasIndex($name)) return;
			$quote = static fn(string $value): string => $platform === 'mysql' ? '`' . $value . '`' : '"' . $value . '"';
			$sql = 'CREATE ' . ($unique ? 'UNIQUE ' : '') . 'INDEX ' . $quote($name)
				. ' ON ' . $quote($physical) . ' (' . implode(', ', array_map($quote, $columns)) . ')';
			$this->db->executeStatement($sql);
			$output->info("Creado índice $name en $physical.");
		} catch (\Throwable $e) {
			$output->warning("No fue posible asegurar el índice $name: " . $e->getMessage());
		}
	}

    private function ensureTableEmpleados(IOutput $output, string $p): void {
        $base = 'empleados'; $phys = $this->tn($base);
        if ($this->tableExistsAny($base)) return;
        try {
            if ($p === 'mysql') {
                $this->db->executeStatement(
"CREATE TABLE `$phys` (
 `Id_empleados` INT AUTO_INCREMENT NOT NULL,
 `Id_user` VARCHAR(255) NOT NULL,
 `Numero_empleado` VARCHAR(255) NULL,
 `Ingreso` VARCHAR(255) NULL,
 `Correo_contacto` VARCHAR(255) NULL,
 `Id_departamento` VARCHAR(255) NULL,
 `Id_puesto` VARCHAR(255) NULL,
 `Id_equipo` VARCHAR(255) NULL,
 `Id_gerente` VARCHAR(255) NULL,
 `Id_socio` VARCHAR(255) NULL,
 `Fondo_clave` VARCHAR(255) NULL,
 `Fondo_ahorro` VARCHAR(255) NULL,
 `Numero_cuenta` VARCHAR(255) NULL,
 `Equipo_asignado` VARCHAR(255) NULL,
 `Sueldo` DECIMAL(10,0) NULL,
 `Notas` LONGTEXT NULL,
 `Fecha_nacimiento` VARCHAR(255) NULL,
 `Estado` VARCHAR(255) NULL,
 `Direccion` VARCHAR(255) NULL,
 `Estado_civil` VARCHAR(255) NULL,
 `Telefono_contacto` VARCHAR(255) NULL,
 `Curp` VARCHAR(255) NULL,
 `Rfc` VARCHAR(255) NULL,
 `Imss` VARCHAR(255) NULL,
 `Genero` VARCHAR(255) NULL,
 `Contacto_emergencia` VARCHAR(255) NULL,
 `Numero_emergencia` VARCHAR(255) NULL,
 `created_at` VARCHAR(255) NOT NULL,
 `updated_at` VARCHAR(255) NOT NULL,
 PRIMARY KEY(`Id_empleados`)
)"
                );
                $this->db->executeStatement("CREATE INDEX `Id_empleados` ON `$phys` (`Id_empleados`)");
                $this->db->executeStatement("CREATE INDEX `idx_id_gerente` ON `$phys` (`Id_gerente`)");
                $this->db->executeStatement("CREATE INDEX `idx_id_socio`   ON `$phys` (`Id_socio`)");
            } elseif ($p === 'postgresql') {
                $this->db->executeStatement(
"CREATE TABLE \"$phys\" (
 \"Id_empleados\" SERIAL PRIMARY KEY,
 \"Id_user\" VARCHAR(255) NOT NULL,
 \"Numero_empleado\" VARCHAR(255) NULL,
 \"Ingreso\" VARCHAR(255) NULL,
 \"Correo_contacto\" VARCHAR(255) NULL,
 \"Id_departamento\" VARCHAR(255) NULL,
 \"Id_puesto\" VARCHAR(255) NULL,
 \"Id_equipo\" VARCHAR(255) NULL,
 \"Id_gerente\" VARCHAR(255) NULL,
 \"Id_socio\" VARCHAR(255) NULL,
 \"Fondo_clave\" VARCHAR(255) NULL,
 \"Fondo_ahorro\" VARCHAR(255) NULL,
 \"Numero_cuenta\" VARCHAR(255) NULL,
 \"Equipo_asignado\" VARCHAR(255) NULL,
 \"Sueldo\" NUMERIC NULL,
 \"Notas\" TEXT NULL,
 \"Fecha_nacimiento\" VARCHAR(255) NULL,
 \"Estado\" VARCHAR(255) NULL,
 \"Direccion\" VARCHAR(255) NULL,
 \"Estado_civil\" VARCHAR(255) NULL,
 \"Telefono_contacto\" VARCHAR(255) NULL,
 \"Curp\" VARCHAR(255) NULL,
 \"Rfc\" VARCHAR(255) NULL,
 \"Imss\" VARCHAR(255) NULL,
 \"Genero\" VARCHAR(255) NULL,
 \"Contacto_emergencia\" VARCHAR(255) NULL,
 \"Numero_emergencia\" VARCHAR(255) NULL,
 \"created_at\" VARCHAR(255) NOT NULL,
 \"updated_at\" VARCHAR(255) NOT NULL
)"
                );
                $this->db->executeStatement("CREATE INDEX \"Id_empleados\" ON \"$phys\" (\"Id_empleados\")");
                $this->db->executeStatement("CREATE INDEX idx_id_gerente ON \"$phys\" (\"Id_gerente\")");
                $this->db->executeStatement("CREATE INDEX idx_id_socio   ON \"$phys\" (\"Id_socio\")");
            } else {
                $this->db->executeStatement(
"CREATE TABLE \"$phys\" (
 \"Id_empleados\" INTEGER PRIMARY KEY AUTOINCREMENT,
 \"Id_user\" TEXT NOT NULL,
 \"Numero_empleado\" TEXT NULL,
 \"Ingreso\" TEXT NULL,
 \"Correo_contacto\" TEXT NULL,
 \"Id_departamento\" TEXT NULL,
 \"Id_puesto\" TEXT NULL,
 \"Id_equipo\" TEXT NULL,
 \"Id_gerente\" TEXT NULL,
 \"Id_socio\" TEXT NULL,
 \"Fondo_clave\" TEXT NULL,
 \"Fondo_ahorro\" TEXT NULL,
 \"Numero_cuenta\" TEXT NULL,
 \"Equipo_asignado\" TEXT NULL,
 \"Sueldo\" NUMERIC NULL,
 \"Notas\" TEXT NULL,
 \"Fecha_nacimiento\" TEXT NULL,
 \"Estado\" TEXT NULL,
 \"Direccion\" TEXT NULL,
 \"Estado_civil\" TEXT NULL,
 \"Telefono_contacto\" TEXT NULL,
 \"Curp\" TEXT NULL,
 \"Rfc\" TEXT NULL,
 \"Imss\" TEXT NULL,
 \"Genero\" TEXT NULL,
 \"Contacto_emergencia\" TEXT NULL,
 \"Numero_emergencia\" TEXT NULL,
 \"created_at\" TEXT NOT NULL,
 \"updated_at\" TEXT NOT NULL
)"
                );
                $this->db->executeStatement("CREATE INDEX Id_empleados ON \"$phys\" (\"Id_empleados\")");
                $this->db->executeStatement("CREATE INDEX idx_id_gerente ON \"$phys\" (\"Id_gerente\")");
                $this->db->executeStatement("CREATE INDEX idx_id_socio   ON \"$phys\" (\"Id_socio\")");
            }
            $output->info('Creada tabla empleados.');
        } catch (\Throwable $e) {
            $output->warning('No fue posible crear empleados: ' . $e->getMessage());
        }
    }

    private function ensureTablePuestos(IOutput $output, string $p): void {
        $base='puestos'; $phys=$this->tn($base);
        if ($this->tableExistsAny($base)) return;
        try {
            if ($p === 'mysql') {
                $this->db->executeStatement(
"CREATE TABLE `$phys` (
 `Id_puestos` INT AUTO_INCREMENT NOT NULL,
 `Nombre` VARCHAR(255) NULL,
 `created_at` VARCHAR(255) NOT NULL,
 `updated_at` VARCHAR(255) NOT NULL,
 PRIMARY KEY(`Id_puestos`)
)"
                );
                $this->db->executeStatement("CREATE INDEX `Id_puestos` ON `$phys` (`Id_puestos`)");
            } elseif ($p === 'postgresql') {
                $this->db->executeStatement(
"CREATE TABLE \"$phys\" (
 \"Id_puestos\" SERIAL PRIMARY KEY,
 \"Nombre\" VARCHAR(255) NULL,
 \"created_at\" VARCHAR(255) NOT NULL,
 \"updated_at\" VARCHAR(255) NOT NULL
)"
                );
                $this->db->executeStatement("CREATE INDEX \"Id_puestos\" ON \"$phys\" (\"Id_puestos\")");
            } else {
                $this->db->executeStatement(
"CREATE TABLE \"$phys\" (
 \"Id_puestos\" INTEGER PRIMARY KEY AUTOINCREMENT,
 \"Nombre\" TEXT NULL,
 \"created_at\" TEXT NOT NULL,
 \"updated_at\" TEXT NOT NULL
)"
                );
                $this->db->executeStatement("CREATE INDEX Id_puestos ON \"$phys\" (\"Id_puestos\")");
            }
            $output->info('Creada tabla puestos.');
        } catch (\Throwable $e) {
            $output->warning('No fue posible crear puestos: ' . $e->getMessage());
        }
    }

    private function ensureTableDepartamentos(IOutput $output, string $p): void {
        $base='departamentos'; $phys=$this->tn($base);
        if ($this->tableExistsAny($base)) return;
        try {
            if ($p === 'mysql') {
                $this->db->executeStatement(
"CREATE TABLE `$phys` (
 `Id_departamento` INT AUTO_INCREMENT NOT NULL,
 `Id_padre` VARCHAR(255) NULL,
 `Nombre` VARCHAR(255) NULL,
 `created_at` VARCHAR(255) NOT NULL,
 `updated_at` VARCHAR(255) NOT NULL,
 PRIMARY KEY(`Id_departamento`)
)"
                );
                $this->db->executeStatement("CREATE INDEX `Id_departamento` ON `$phys` (`Id_departamento`)");
            } elseif ($p === 'postgresql') {
                $this->db->executeStatement(
"CREATE TABLE \"$phys\" (
 \"Id_departamento\" SERIAL PRIMARY KEY,
 \"Id_padre\" VARCHAR(255) NULL,
 \"Nombre\" VARCHAR(255) NULL,
 \"created_at\" VARCHAR(255) NOT NULL,
 \"updated_at\" VARCHAR(255) NOT NULL
)"
                );
                $this->db->executeStatement("CREATE INDEX \"Id_departamento\" ON \"$phys\" (\"Id_departamento\")");
            } else {
                $this->db->executeStatement(
"CREATE TABLE \"$phys\" (
 \"Id_departamento\" INTEGER PRIMARY KEY AUTOINCREMENT,
 \"Id_padre\" TEXT NULL,
 \"Nombre\" TEXT NULL,
 \"created_at\" TEXT NOT NULL,
 \"updated_at\" TEXT NOT NULL
)"
                );
                $this->db->executeStatement("CREATE INDEX Id_departamento ON \"$phys\" (\"Id_departamento\")");
            }
            $output->info('Creada tabla departamentos.');
        } catch (\Throwable $e) {
            $output->warning('No fue posible crear departamentos: ' . $e->getMessage());
        }
    }

    private function ensureTableEmpleadosConf(IOutput $output, string $p): void {
        $base='empleados_conf'; $phys=$this->tn($base);
        if ($this->tableExistsAny($base)) return;
        try {
            if ($p === 'mysql') {
                $this->db->executeStatement(
"CREATE TABLE `$phys` (
 `Id_conf` INT AUTO_INCREMENT NOT NULL,
 `Nombre` VARCHAR(255) NULL,
 `Data` VARCHAR(255) NULL,
 PRIMARY KEY(`Id_conf`)
)"
                );
                $this->db->executeStatement("CREATE INDEX `Id_conf` ON `$phys` (`Id_conf`)");
            } elseif ($p === 'postgresql') {
                $this->db->executeStatement(
"CREATE TABLE \"$phys\" (
 \"Id_conf\" SERIAL PRIMARY KEY,
 \"Nombre\" VARCHAR(255) NULL,
 \"Data\" VARCHAR(255) NULL
)"
                );
                $this->db->executeStatement("CREATE INDEX \"Id_conf\" ON \"$phys\" (\"Id_conf\")");
            } else {
                $this->db->executeStatement(
"CREATE TABLE \"$phys\" (
 \"Id_conf\" INTEGER PRIMARY KEY AUTOINCREMENT,
 \"Nombre\" TEXT NULL,
 \"Data\" TEXT NULL
)"
                );
                $this->db->executeStatement("CREATE INDEX Id_conf ON \"$phys\" (\"Id_conf\")");
            }
            $output->info('Creada tabla empleados_conf.');
        } catch (\Throwable $e) {
            $output->warning('No fue posible crear empleados_conf: ' . $e->getMessage());
        }
    }

    private function ensureTableAniversarios(IOutput $output, string $p): void {
        $base='aniversarios'; $phys=$this->tn($base);
        if ($this->tableExistsAny($base)) return;
        try {
            if ($p === 'mysql') {
                $this->db->executeStatement(
"CREATE TABLE `$phys` (
 `id_aniversario` INT AUTO_INCREMENT NOT NULL,
 `numero_aniversario` INT NOT NULL,
 `fecha_de` DATETIME NULL,
 `fecha_hasta` DATETIME NULL,
 `dias` DECIMAL(5,2) NOT NULL,
 `timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(`id_aniversario`)
)"
                );
                $this->db->executeStatement("CREATE INDEX `aniv_idx_numero` ON `$phys` (`numero_aniversario`)");
            } elseif ($p === 'postgresql') {
                $this->db->executeStatement(
"CREATE TABLE \"$phys\" (
 \"id_aniversario\" SERIAL PRIMARY KEY,
 \"numero_aniversario\" INTEGER NOT NULL,
 \"fecha_de\" TIMESTAMP NULL,
 \"fecha_hasta\" TIMESTAMP NULL,
 \"dias\" NUMERIC(5,2) NOT NULL,
 \"timestamp\" TIMESTAMP NOT NULL
)"
                );
                $this->db->executeStatement("CREATE INDEX aniv_idx_numero ON \"$phys\" (\"numero_aniversario\")");
            } else {
                $this->db->executeStatement(
"CREATE TABLE \"$phys\" (
 \"id_aniversario\" INTEGER PRIMARY KEY AUTOINCREMENT,
 \"numero_aniversario\" INTEGER NOT NULL,
 \"fecha_de\" TEXT NULL,
 \"fecha_hasta\" TEXT NULL,
 \"dias\" NUMERIC NOT NULL,
 \"timestamp\" TEXT NOT NULL
)"
                );
                $this->db->executeStatement("CREATE INDEX aniv_idx_numero ON \"$phys\" (\"numero_aniversario\")");
            }
            $output->info('Creada tabla aniversarios.');
        } catch (\Throwable $e) {
            $output->warning('No fue posible crear aniversarios: ' . $e->getMessage());
        }
    }

    private function ensureTableTipoAusencia(IOutput $output, string $p): void {
        $base='tipo_ausencia'; $phys=$this->tn($base);
        if ($this->tableExistsAny($base)) return;
        try {
            if ($p === 'mysql') {
                $this->db->executeStatement(
"CREATE TABLE `$phys` (
 `id_tipo_ausencia` INT AUTO_INCREMENT NOT NULL,
 `nombre` VARCHAR(255) NOT NULL,
 `descripcion` LONGTEXT NULL,
 `solicitar_archivo` INT NOT NULL,
 `solicitar_prima_vacacional` INT NOT NULL,
 PRIMARY KEY(`id_tipo_ausencia`)
)"
                );
                $this->db->executeStatement("CREATE INDEX `tipo_idx_nombre` ON `$phys` (`nombre`)");
            } elseif ($p === 'postgresql') {
                $this->db->executeStatement(
"CREATE TABLE \"$phys\" (
 \"id_tipo_ausencia\" SERIAL PRIMARY KEY,
 \"nombre\" VARCHAR(255) NOT NULL,
 \"descripcion\" TEXT NULL,
 \"solicitar_archivo\" INTEGER NOT NULL,
 \"solicitar_prima_vacacional\" INTEGER NOT NULL
)"
                );
                $this->db->executeStatement("CREATE INDEX tipo_idx_nombre ON \"$phys\" (\"nombre\")");
            } else {
                $this->db->executeStatement(
"CREATE TABLE \"$phys\" (
 \"id_tipo_ausencia\" INTEGER PRIMARY KEY AUTOINCREMENT,
 \"nombre\" TEXT NOT NULL,
 \"descripcion\" TEXT NULL,
 \"solicitar_archivo\" INTEGER NOT NULL,
 \"solicitar_prima_vacacional\" INTEGER NOT NULL
)"
                );
                $this->db->executeStatement("CREATE INDEX tipo_idx_nombre ON \"$phys\" (\"nombre\")");
            }
            $output->info('Creada tabla tipo_ausencia.');
        } catch (\Throwable $e) {
            $output->warning('No fue posible crear tipo_ausencia: ' . $e->getMessage());
        }
    }

    private function ensureTableAusencias(IOutput $output, string $p): void {
        $base='ausencias'; $phys=$this->tn($base);
        if ($this->tableExistsAny($base)) return;
        try {
            if ($p === 'mysql') {
                $this->db->executeStatement(
"CREATE TABLE `$phys` (
 `id_ausencias` INT AUTO_INCREMENT NOT NULL,
 `id_empleado` INT NOT NULL,
 `id_aniversario` INT NULL,
 `dias_disponibles` DECIMAL(5,2) NULL DEFAULT 0.00,
 `prima_vacacional` TINYINT(1) NULL DEFAULT 0,
 `timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(`id_ausencias`),
 UNIQUE KEY `uniq_aus_empleado` (`id_empleado`)
)"
                );
            } elseif ($p === 'postgresql') {
                $this->db->executeStatement(
"CREATE TABLE \"$phys\" (
 \"id_ausencias\" SERIAL PRIMARY KEY,
 \"id_empleado\" INTEGER NOT NULL,
 \"id_aniversario\" INTEGER NULL,
 \"dias_disponibles\" NUMERIC(5,2) NULL DEFAULT 0.00,
 \"prima_vacacional\" BOOLEAN NULL DEFAULT FALSE,
 \"timestamp\" TIMESTAMP NOT NULL
)"
                );
                $this->db->executeStatement("CREATE UNIQUE INDEX uniq_aus_empleado ON \"$phys\" (\"id_empleado\")");
            } else {
                $this->db->executeStatement(
"CREATE TABLE \"$phys\" (
 \"id_ausencias\" INTEGER PRIMARY KEY AUTOINCREMENT,
 \"id_empleado\" INTEGER NOT NULL,
 \"id_aniversario\" INTEGER NULL,
 \"dias_disponibles\" NUMERIC NULL DEFAULT 0.00,
 \"prima_vacacional\" INTEGER NULL DEFAULT 0,
 \"timestamp\" TEXT NOT NULL
)"
                );
                $this->db->executeStatement("CREATE UNIQUE INDEX uniq_aus_empleado ON \"$phys\" (\"id_empleado\")");
            }
            $output->info('Creada tabla ausencias.');
        } catch (\Throwable $e) {
            $output->warning('No fue posible crear ausencias: ' . $e->getMessage());
        }
    }

    private function ensureTableHistorialAusencias(IOutput $output, string $p): void {
        $base='historial_ausencias'; $phys=$this->tn($base);
        if ($this->tableExistsAny($base)) return;
        try {
            if ($p === 'mysql') {
                $this->db->executeStatement(
"CREATE TABLE `$phys` (
 `id_historial_ausencias` INT AUTO_INCREMENT NOT NULL,
 `id_ausencias` INT NOT NULL,
 `id_aniversario` INT NULL,
 `id_tipo_ausencia` INT NOT NULL,
 `fecha_de` DATETIME NOT NULL,
 `fecha_hasta` DATETIME NOT NULL,
 `prima_vacacional` TINYINT(1) NULL DEFAULT 0,
 `archivo` VARCHAR(255) NULL,
 `timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 `a_socio` TINYINT(1) NULL DEFAULT 0,
 `a_gerente` TINYINT(1) NULL DEFAULT 0,
 `a_capital_humano` TINYINT(1) NULL DEFAULT 0,
 PRIMARY KEY(`id_historial_ausencias`)
)"
                );
                $this->db->executeStatement("CREATE INDEX `hist_idx_aus`  ON `$phys` (`id_ausencias`)");
                $this->db->executeStatement("CREATE INDEX `hist_idx_tipo` ON `$phys` (`id_tipo_ausencia`)");
                $this->db->executeStatement("CREATE INDEX `hist_idx_aniv` ON `$phys` (`id_aniversario`)");
            } elseif ($p === 'postgresql') {
                $this->db->executeStatement(
"CREATE TABLE \"$phys\" (
 \"id_historial_ausencias\" SERIAL PRIMARY KEY,
 \"id_ausencias\" INTEGER NOT NULL,
 \"id_aniversario\" INTEGER NULL,
 \"id_tipo_ausencia\" INTEGER NOT NULL,
 \"fecha_de\" TIMESTAMP NOT NULL,
 \"fecha_hasta\" TIMESTAMP NOT NULL,
 \"prima_vacacional\" BOOLEAN NULL DEFAULT FALSE,
 \"archivo\" VARCHAR(255) NULL,
 \"timestamp\" TIMESTAMP NOT NULL,
 \"a_socio\" BOOLEAN NULL DEFAULT FALSE,
 \"a_gerente\" BOOLEAN NULL DEFAULT FALSE,
 \"a_capital_humano\" BOOLEAN NULL DEFAULT FALSE
)"
                );
                $this->db->executeStatement("CREATE INDEX hist_idx_aus  ON \"$phys\" (\"id_ausencias\")");
                $this->db->executeStatement("CREATE INDEX hist_idx_tipo ON \"$phys\" (\"id_tipo_ausencia\")");
                $this->db->executeStatement("CREATE INDEX hist_idx_aniv ON \"$phys\" (\"id_aniversario\")");
            } else {
                $this->db->executeStatement(
"CREATE TABLE \"$phys\" (
 \"id_historial_ausencias\" INTEGER PRIMARY KEY AUTOINCREMENT,
 \"id_ausencias\" INTEGER NOT NULL,
 \"id_aniversario\" INTEGER NULL,
 \"id_tipo_ausencia\" INTEGER NOT NULL,
 \"fecha_de\" TEXT NOT NULL,
 \"fecha_hasta\" TEXT NOT NULL,
 \"prima_vacacional\" INTEGER NULL DEFAULT 0,
 \"archivo\" TEXT NULL,
 \"timestamp\" TEXT NOT NULL,
 \"a_socio\" INTEGER NULL DEFAULT 0,
 \"a_gerente\" INTEGER NULL DEFAULT 0,
 \"a_capital_humano\" INTEGER NULL DEFAULT 0
)"
                );
                $this->db->executeStatement("CREATE INDEX hist_idx_aus  ON \"$phys\" (\"id_ausencias\")");
                $this->db->executeStatement("CREATE INDEX hist_idx_tipo ON \"$phys\" (\"id_tipo_ausencia\")");
                $this->db->executeStatement("CREATE INDEX hist_idx_aniv ON \"$phys\" (\"id_aniversario\")");
            }
            $output->info('Creada tabla historial_ausencias.');
        } catch (\Throwable $e) {
            $output->warning('No fue posible crear historial_ausencias: ' . $e->getMessage());
        }
    }

    private function ensureTableEquipos(IOutput $output, string $p): void {
        $base='equipos'; $phys=$this->tn($base);
        if ($this->tableExistsAny($base)) return;
        try {
            if ($p === 'mysql') {
                $this->db->executeStatement(
"CREATE TABLE `$phys` (
 `Id_equipo` INT AUTO_INCREMENT NOT NULL,
 `Id_jefe_equipo` VARCHAR(255) NULL,
 `Nombre` VARCHAR(255) NULL,
 `created_at` VARCHAR(255) NOT NULL,
 `updated_at` VARCHAR(255) NOT NULL,
 PRIMARY KEY(`Id_equipo`)
)"
                );
                $this->db->executeStatement("CREATE INDEX `Id_equipo` ON `$phys` (`Id_equipo`)");
            } elseif ($p === 'postgresql') {
                $this->db->executeStatement(
"CREATE TABLE \"$phys\" (
 \"Id_equipo\" SERIAL PRIMARY KEY,
 \"Id_jefe_equipo\" VARCHAR(255) NULL,
 \"Nombre\" VARCHAR(255) NULL,
 \"created_at\" VARCHAR(255) NOT NULL,
 \"updated_at\" VARCHAR(255) NOT NULL
)"
                );
                $this->db->executeStatement("CREATE INDEX \"Id_equipo\" ON \"$phys\" (\"Id_equipo\")");
            } else {
                $this->db->executeStatement(
"CREATE TABLE \"$phys\" (
 \"Id_equipo\" INTEGER PRIMARY KEY AUTOINCREMENT,
 \"Id_jefe_equipo\" TEXT NULL,
 \"Nombre\" TEXT NULL,
 \"created_at\" TEXT NOT NULL,
 \"updated_at\" TEXT NOT NULL
)"
                );
                $this->db->executeStatement("CREATE INDEX Id_equipo ON \"$phys\" (\"Id_equipo\")");
            }
            $output->info('Creada tabla equipos.');
        } catch (\Throwable $e) {
            $output->warning('No fue posible crear equipos: ' . $e->getMessage());
        }
    }

    private function ensureTableUserAhorro(IOutput $output, string $p): void {
        $base='user_ahorro'; $phys=$this->tn($base);
        if ($this->tableExistsAny($base)) return;
        try {
            if ($p === 'mysql') {
                $this->db->executeStatement(
"CREATE TABLE `$phys` (
 `id_ahorro` INT AUTO_INCREMENT NOT NULL,
 `id_user` INT NOT NULL,
 `id_permision` VARCHAR(255) NOT NULL,
 `state` VARCHAR(255) NOT NULL,
 `last_modified` VARCHAR(255) NOT NULL,
 PRIMARY KEY(`id_ahorro`)
)"
                );
                $this->db->executeStatement("CREATE INDEX `user_ahorro_uid`   ON `$phys` (`id_user`)");
                $this->db->executeStatement("CREATE INDEX `user_ahorro_perm`  ON `$phys` (`id_permision`)");
                $this->db->executeStatement("CREATE INDEX `user_ahorro_state` ON `$phys` (`state`)");
            } elseif ($p === 'postgresql') {
                $this->db->executeStatement(
"CREATE TABLE \"$phys\" (
 \"id_ahorro\" SERIAL PRIMARY KEY,
 \"id_user\" INTEGER NOT NULL,
 \"id_permision\" VARCHAR(255) NOT NULL,
 \"state\" VARCHAR(255) NOT NULL,
 \"last_modified\" VARCHAR(255) NOT NULL
)"
                );
                $this->db->executeStatement("CREATE INDEX user_ahorro_uid   ON \"$phys\" (\"id_user\")");
                $this->db->executeStatement("CREATE INDEX user_ahorro_perm  ON \"$phys\" (\"id_permision\")");
                $this->db->executeStatement("CREATE INDEX user_ahorro_state ON \"$phys\" (\"state\")");
            } else {
                $this->db->executeStatement(
"CREATE TABLE \"$phys\" (
 \"id_ahorro\" INTEGER PRIMARY KEY AUTOINCREMENT,
 \"id_user\" INTEGER NOT NULL,
 \"id_permision\" TEXT NOT NULL,
 \"state\" TEXT NOT NULL,
 \"last_modified\" TEXT NOT NULL
)"
                );
                $this->db->executeStatement("CREATE INDEX user_ahorro_uid   ON \"$phys\" (\"id_user\")");
                $this->db->executeStatement("CREATE INDEX user_ahorro_perm  ON \"$phys\" (\"id_permision\")");
                $this->db->executeStatement("CREATE INDEX user_ahorro_state ON \"$phys\" (\"state\")");
            }
            $output->info('Creada tabla user_ahorro.');
        } catch (\Throwable $e) {
            $output->warning('No fue posible crear user_ahorro: ' . $e->getMessage());
        }
    }

    private function ensureTimestampDefault(\OCP\Migration\IOutput $out, string $tableBase, string $col = 'timestamp'): void {
        $platform = $this->db->getDatabasePlatform()->getName(); // mysql|postgresql|sqlite

        $prefix = 'oc_';
        try {
            $prefix = $this->config->getSystemValueString('dbtableprefix', 'oc_');
        } catch (\Throwable) {
            // usa 'oc_' si falla
        }
        $phys = $prefix . $tableBase;

        if (!$this->tableExistsExact($phys)) {
            $out->info("Tabla $phys no existe; omito default $col.");
            return;
        }

        try {
            // 1) Prefill NULLs para no fallar al poner NOT NULL
            if ($platform === 'mysql') {
                $this->db->executeStatement("UPDATE `$phys` SET `$col` = CURRENT_TIMESTAMP WHERE `$col` IS NULL");
                // 2) DEFAULT + NOT NULL (MariaDB ≥10.2 / MySQL ≥5.6)
                $this->db->executeStatement("ALTER TABLE `$phys` MODIFY `$col` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
            } elseif ($platform === 'postgresql') {
                $this->db->executeStatement("UPDATE \"$phys\" SET \"$col\" = CURRENT_TIMESTAMP WHERE \"$col\" IS NULL");
                $this->db->executeStatement("ALTER TABLE \"$phys\" ALTER COLUMN \"$col\" SET DEFAULT CURRENT_TIMESTAMP");
                $this->db->executeStatement("ALTER TABLE \"$phys\" ALTER COLUMN \"$col\" SET NOT NULL");
            } else { // sqlite
                // No ALTER COLUMN real: al menos prefill y avisar
                $this->db->executeStatement("UPDATE \"$phys\" SET \"$col\" = datetime('now') WHERE \"$col\" IS NULL");
                $out->warning("SQLite: no puedo fijar DEFAULT en $phys.$col; solo se aplica en CREATE.");
                return;
            }
            $out->info("Default de $phys.$col asegurado.");
        } catch (\Throwable $e) {
            $out->warning("No fue posible asegurar default en $phys.$col: " . $e->getMessage());
        }
    }

    private function ensureTableHistorialAhorro(IOutput $output, string $p): void {
        $base='historial_ahorro'; $phys=$this->tn($base);
        if ($this->tableExistsAny($base)) return;
        try {
            if ($p === 'mysql') {
                $this->db->executeStatement(
"CREATE TABLE `$phys` (
 `id_historial` INT AUTO_INCREMENT NOT NULL,
 `id_ahorro` VARCHAR(255) NULL,
 `cantidad_solicitada` VARCHAR(255) NULL,
 `cantidad_total` VARCHAR(255) NULL,
 `fecha_solicitud` VARCHAR(255) NULL,
 `estado` VARCHAR(255) NULL,
 `nota` VARCHAR(255) NULL,
 PRIMARY KEY(`id_historial`)
)"
                );
                $this->db->executeStatement("CREATE INDEX `hist_ahorro_id`    ON `$phys` (`id_ahorro`)");
                $this->db->executeStatement("CREATE INDEX `hist_ahorro_estado` ON `$phys` (`estado`)");
                $this->db->executeStatement("CREATE INDEX `hist_ahorro_fecha`  ON `$phys` (`fecha_solicitud`)");
            } elseif ($p === 'postgresql') {
                $this->db->executeStatement(
"CREATE TABLE \"$phys\" (
 \"id_historial\" SERIAL PRIMARY KEY,
 \"id_ahorro\" VARCHAR(255) NULL,
 \"cantidad_solicitada\" VARCHAR(255) NULL,
 \"cantidad_total\" VARCHAR(255) NULL,
 \"fecha_solicitud\" VARCHAR(255) NULL,
 \"estado\" VARCHAR(255) NULL,
 \"nota\" VARCHAR(255) NULL
)"
                );
                $this->db->executeStatement("CREATE INDEX hist_ahorro_id    ON \"$phys\" (\"id_ahorro\")");
                $this->db->executeStatement("CREATE INDEX hist_ahorro_estado ON \"$phys\" (\"estado\")");
                $this->db->executeStatement("CREATE INDEX hist_ahorro_fecha  ON \"$phys\" (\"fecha_solicitud\")");
            } else {
                $this->db->executeStatement(
"CREATE TABLE \"$phys\" (
 \"id_historial\" INTEGER PRIMARY KEY AUTOINCREMENT,
 \"id_ahorro\" TEXT NULL,
 \"cantidad_solicitada\" TEXT NULL,
 \"cantidad_total\" TEXT NULL,
 \"fecha_solicitud\" TEXT NULL,
 \"estado\" TEXT NULL,
 \"nota\" TEXT NULL
)"
                );
                $this->db->executeStatement("CREATE INDEX hist_ahorro_id    ON \"$phys\" (\"id_ahorro\")");
                $this->db->executeStatement("CREATE INDEX hist_ahorro_estado ON \"$phys\" (\"estado\")");
                $this->db->executeStatement("CREATE INDEX hist_ahorro_fecha  ON \"$phys\" (\"fecha_solicitud\")");
            }
            $output->info('Creada tabla historial_ahorro.');
        } catch (\Throwable $e) {
            $output->warning('No fue posible crear historial_ahorro: ' . $e->getMessage());
        }
    }

    private function ensureTableCapitalHumano(IOutput $output, string $p): void {
        $base='CapitalHumano'; $phys=$this->tn($base);
        if ($this->tableExistsAny($base)) return;
        try {
            if ($p === 'mysql') {
                $this->db->executeStatement(
"CREATE TABLE `$phys` (
 `Id_ch` INT AUTO_INCREMENT NOT NULL,
 `Id_empleado` VARCHAR(255) NULL,
 `created_at` VARCHAR(255) NOT NULL,
 `updated_at` VARCHAR(255) NOT NULL,
 PRIMARY KEY(`Id_ch`)
)"
                );
                $this->db->executeStatement("CREATE INDEX `Id_ch` ON `$phys` (`Id_ch`)");
                $this->db->executeStatement("CREATE INDEX `CapitalHumano_Id_empleado` ON `$phys` (`Id_empleado`)");
            } elseif ($p === 'postgresql') {
                $this->db->executeStatement(
"CREATE TABLE \"$phys\" (
 \"Id_ch\" SERIAL PRIMARY KEY,
 \"Id_empleado\" VARCHAR(255) NULL,
 \"created_at\" VARCHAR(255) NOT NULL,
 \"updated_at\" VARCHAR(255) NOT NULL
)"
                );
                $this->db->executeStatement("CREATE INDEX \"Id_ch\" ON \"$phys\" (\"Id_ch\")");
                $this->db->executeStatement("CREATE INDEX \"CapitalHumano_Id_empleado\" ON \"$phys\" (\"Id_empleado\")");
            } else {
                $this->db->executeStatement(
"CREATE TABLE \"$phys\" (
 \"Id_ch\" INTEGER PRIMARY KEY AUTOINCREMENT,
 \"Id_empleado\" TEXT NULL,
 \"created_at\" TEXT NOT NULL,
 \"updated_at\" TEXT NOT NULL
)"
                );
                $this->db->executeStatement("CREATE INDEX Id_ch ON \"$phys\" (\"Id_ch\")");
                $this->db->executeStatement("CREATE INDEX CapitalHumano_Id_empleado ON \"$phys\" (\"Id_empleado\")");
            }
            $output->info('Creada tabla CapitalHumano.');
        } catch (\Throwable $e) {
            $output->warning('No fue posible crear CapitalHumano: ' . $e->getMessage());
        }
    }
}
