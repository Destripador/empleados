<?php

declare(strict_types=1);

/**
 * Migración base para OCA\Empleados.
 *
 * Esta migración es idempotente:
 * - Crea las tablas que no existen.
 * - Omite las tablas existentes.
 * - Inserta configuraciones base solo si no existen.
 *
 * No elimina ni modifica datos existentes.
 */

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2000Date20260424181244 extends SimpleMigrationStep {

	/** @var IDBConnection */
	private $db;

	public function __construct(IDBConnection $db) {
		$this->db = $db;
	}

	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		// Sin acciones previas.
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$this->createEmpleados($schema);
		$this->createPuestos($schema);
		$this->createDepartamentos($schema);
		$this->createEmpleadosConf($schema);

		$this->createAniversarios($schema);
		$this->createTipoAusencia($schema);
		$this->createAusencias($schema);
		$this->createHistorialAusencias($schema);

		$this->createEquipos($schema);
		$this->createUserAhorro($schema);
		$this->createHistorialAhorro($schema);

		$this->createCapitalHumano($schema);

		$this->createEmpleadosClientes($schema);
		$this->createEmpleadosActividades($schema);
		$this->createEmpleadosRepTiempos($schema);

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$configs = [
			'usuario_almacenamiento' => null,
			'automatic_save_note' => null,
			'acumular_vacaciones' => null,
			'modulo_ahorro' => null,
			'modulo_ausencias' => null,
			'ausencias_readonly' => null,
			'modulo_clientes' => 'false',
			'modulo_reporte_tiempos' => 'false',
		];

		foreach ($configs as $nombre => $data) {
			$created = $this->insertConfig($nombre, $data);

			if ($created) {
				$output->info("Seed empleados_conf.Nombre='{$nombre}' insertado.");
			} else {
				$output->info("Seed empleados_conf.Nombre='{$nombre}' ya existía, omitido.");
			}
		}
	}

	private function createEmpleados(ISchemaWrapper $schema): void {
		if ($schema->hasTable('empleados')) {
			return;
		}

		$table = $schema->createTable('empleados');

		$table->addColumn('Id_empleados', 'integer', [
			'autoincrement' => true,
			'unsigned' => true,
			'notnull' => true,
		]);
		$table->addColumn('Id_user', 'string', ['notnull' => true, 'length' => 64]);
		$table->addColumn('Numero_empleado', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('Ingreso', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('Correo_contacto', 'string', ['notnull' => false, 'length' => 190]);
		$table->addColumn('Id_departamento', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('Id_puesto', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('Id_equipo', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('Id_gerente', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('Id_socio', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('Fondo_clave', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('Fondo_ahorro', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('Numero_cuenta', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('Equipo_asignado', 'string', ['notnull' => false, 'length' => 190]);
		$table->addColumn('Sueldo', 'decimal', ['notnull' => false, 'precision' => 12, 'scale' => 2]);
		$table->addColumn('Notas', 'text', ['notnull' => false]);
		$table->addColumn('Fecha_nacimiento', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('Estado', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('Direccion', 'string', ['notnull' => false, 'length' => 190]);
		$table->addColumn('Estado_civil', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('Telefono_contacto', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('Curp', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('Rfc', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('Imss', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('Genero', 'string', ['notnull' => false, 'length' => 32]);
		$table->addColumn('Contacto_emergencia', 'string', ['notnull' => false, 'length' => 190]);
		$table->addColumn('Numero_emergencia', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('created_at', 'string', ['notnull' => true, 'length' => 32]);
		$table->addColumn('updated_at', 'string', ['notnull' => true, 'length' => 32]);

		$table->setPrimaryKey(['Id_empleados']);
		$table->addIndex(['Id_empleados'], 'Id_empleados');
		$table->addIndex(['Id_user'], 'idx_id_user');
		$table->addIndex(['Numero_empleado'], 'idx_numero_empleado');
		$table->addIndex(['Correo_contacto'], 'idx_correo_contacto');
		$table->addIndex(['Id_departamento'], 'idx_id_departamento');
		$table->addIndex(['Id_puesto'], 'idx_id_puesto');
		$table->addIndex(['Id_equipo'], 'idx_id_equipo');
		$table->addIndex(['Id_gerente'], 'idx_id_gerente');
		$table->addIndex(['Id_socio'], 'idx_id_socio');
	}

	private function createPuestos(ISchemaWrapper $schema): void {
		if ($schema->hasTable('puestos')) {
			return;
		}

		$table = $schema->createTable('puestos');

		$table->addColumn('Id_puestos', 'integer', [
			'autoincrement' => true,
			'unsigned' => true,
			'notnull' => true,
		]);
		$table->addColumn('Nombre', 'string', ['notnull' => false, 'length' => 190]);
		$table->addColumn('created_at', 'string', ['notnull' => true, 'length' => 32]);
		$table->addColumn('updated_at', 'string', ['notnull' => true, 'length' => 32]);

		$table->setPrimaryKey(['Id_puestos']);
		$table->addIndex(['Id_puestos'], 'Id_puestos');
		$table->addIndex(['Nombre'], 'idx_puestos_nombre');
	}

	private function createDepartamentos(ISchemaWrapper $schema): void {
		if ($schema->hasTable('departamentos')) {
			return;
		}

		$table = $schema->createTable('departamentos');

		$table->addColumn('Id_departamento', 'integer', [
			'autoincrement' => true,
			'unsigned' => true,
			'notnull' => true,
		]);
		$table->addColumn('Id_padre', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('Nombre', 'string', ['notnull' => false, 'length' => 190]);
		$table->addColumn('created_at', 'string', ['notnull' => true, 'length' => 32]);
		$table->addColumn('updated_at', 'string', ['notnull' => true, 'length' => 32]);

		$table->setPrimaryKey(['Id_departamento']);
		$table->addIndex(['Id_departamento'], 'Id_departamento');
		$table->addIndex(['Nombre'], 'idx_departamentos_nombre');
		$table->addIndex(['Id_padre'], 'idx_departamentos_padre');
	}

	private function createEmpleadosConf(ISchemaWrapper $schema): void {
		if ($schema->hasTable('empleados_conf')) {
			return;
		}

		$table = $schema->createTable('empleados_conf');

		$table->addColumn('Id_conf', 'integer', [
			'autoincrement' => true,
			'unsigned' => true,
			'notnull' => true,
		]);
		$table->addColumn('Nombre', 'string', ['length' => 190, 'notnull' => true]);
		$table->addColumn('Data', 'string', ['length' => 255, 'notnull' => false]);

		$table->setPrimaryKey(['Id_conf']);
		$table->addUniqueIndex(['Nombre'], 'uq_empleados_conf_nombre');
	}

	private function createAniversarios(ISchemaWrapper $schema): void {
		if ($schema->hasTable('aniversarios')) {
			return;
		}

		$table = $schema->createTable('aniversarios');

		$table->addColumn('id_aniversario', 'integer', [
			'autoincrement' => true,
			'unsigned' => true,
			'notnull' => true,
		]);
		$table->addColumn('numero_aniversario', 'integer', ['notnull' => true]);
		$table->addColumn('fecha_de', 'datetime', ['notnull' => false]);
		$table->addColumn('fecha_hasta', 'datetime', ['notnull' => false]);
		$table->addColumn('dias', 'decimal', ['precision' => 5, 'scale' => 2, 'notnull' => true]);

		$table->setPrimaryKey(['id_aniversario']);
		$table->addIndex(['numero_aniversario'], 'aniv_idx_numero');
	}

	private function createTipoAusencia(ISchemaWrapper $schema): void {
		if ($schema->hasTable('tipo_ausencia')) {
			return;
		}

		$table = $schema->createTable('tipo_ausencia');

		$table->addColumn('id_tipo_ausencia', 'integer', [
			'autoincrement' => true,
			'unsigned' => true,
			'notnull' => true,
		]);
		$table->addColumn('nombre', 'string', ['length' => 255, 'notnull' => true]);
		$table->addColumn('descripcion', 'text', ['notnull' => false]);
		$table->addColumn('solicitar_archivo', 'integer', ['notnull' => true]);
		$table->addColumn('solicitar_prima_vacacional', 'integer', ['notnull' => true, 'default' => 0]);

		$table->setPrimaryKey(['id_tipo_ausencia']);
		$table->addIndex(['nombre'], 'tipo_idx_nombre');
	}

	private function createAusencias(ISchemaWrapper $schema): void {
		if ($schema->hasTable('ausencias')) {
			return;
		}

		$table = $schema->createTable('ausencias');

		$table->addColumn('id_ausencias', 'integer', [
			'autoincrement' => true,
			'unsigned' => true,
			'notnull' => true,
		]);
		$table->addColumn('id_empleado', 'integer', ['unsigned' => true, 'notnull' => true]);
		$table->addColumn('id_aniversario', 'integer', ['unsigned' => true, 'notnull' => false]);
		$table->addColumn('dias_disponibles', 'decimal', [
			'precision' => 5,
			'scale' => 2,
			'notnull' => false,
			'default' => 0.00,
		]);
		$table->addColumn('prima_vacacional', 'boolean', ['notnull' => false, 'default' => false]);
		$table->addColumn('timestamp', 'datetime', ['notnull' => true]);

		$table->setPrimaryKey(['id_ausencias']);
		$table->addUniqueIndex(['id_empleado'], 'uniq_aus_empleado');
		$table->addIndex(['id_aniversario'], 'aus_idx_aniv');
	}

	private function createHistorialAusencias(ISchemaWrapper $schema): void {
		if ($schema->hasTable('historial_ausencias')) {
			return;
		}

		$table = $schema->createTable('historial_ausencias');

		$table->addColumn('id_historial_ausencias', 'integer', [
			'autoincrement' => true,
			'unsigned' => true,
			'notnull' => true,
		]);
		$table->addColumn('id_ausencias', 'integer', ['unsigned' => true, 'notnull' => true]);
		$table->addColumn('id_aniversario', 'integer', ['unsigned' => true, 'notnull' => false]);
		$table->addColumn('id_tipo_ausencia', 'integer', ['unsigned' => true, 'notnull' => true]);
		$table->addColumn('fecha_de', 'datetime', ['notnull' => true]);
		$table->addColumn('fecha_hasta', 'datetime', ['notnull' => true]);
		$table->addColumn('prima_vacacional', 'boolean', ['notnull' => false, 'default' => false]);
		$table->addColumn('archivo', 'string', ['length' => 255, 'notnull' => false]);
		$table->addColumn('timestamp', 'datetime', ['notnull' => true]);
		$table->addColumn('a_socio', 'boolean', ['notnull' => false, 'default' => false]);
		$table->addColumn('a_gerente', 'boolean', ['notnull' => false, 'default' => false]);
		$table->addColumn('a_capital_humano', 'boolean', ['notnull' => false, 'default' => false]);
		$table->addColumn('notas', 'string', ['notnull' => false, 'length' => 255]);

		$table->setPrimaryKey(['id_historial_ausencias']);
		$table->addIndex(['id_ausencias'], 'hist_idx_aus');
		$table->addIndex(['id_tipo_ausencia'], 'hist_idx_tipo');
		$table->addIndex(['id_aniversario'], 'hist_idx_aniv');
	}

	private function createEquipos(ISchemaWrapper $schema): void {
		if ($schema->hasTable('equipos')) {
			return;
		}

		$table = $schema->createTable('equipos');

		$table->addColumn('Id_equipo', 'integer', [
			'autoincrement' => true,
			'unsigned' => true,
			'notnull' => true,
		]);
		$table->addColumn('Id_jefe_equipo', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('Nombre', 'string', ['notnull' => false, 'length' => 190]);
		$table->addColumn('created_at', 'string', ['notnull' => true, 'length' => 32]);
		$table->addColumn('updated_at', 'string', ['notnull' => true, 'length' => 32]);

		$table->setPrimaryKey(['Id_equipo']);
		$table->addIndex(['Id_equipo'], 'Id_equipo');
		$table->addIndex(['Id_jefe_equipo'], 'idx_id_jefe_equipo');
		$table->addIndex(['Nombre'], 'idx_nombre_equipo');
	}

	private function createUserAhorro(ISchemaWrapper $schema): void {
		if ($schema->hasTable('user_ahorro')) {
			return;
		}

		$table = $schema->createTable('user_ahorro');

		$table->addColumn('id_ahorro', 'integer', [
			'autoincrement' => true,
			'notnull' => true,
		]);
		$table->addColumn('id_user', 'integer', ['notnull' => true]);
		$table->addColumn('id_permision', 'string', ['notnull' => true, 'length' => 190]);
		$table->addColumn('state', 'string', ['notnull' => true, 'length' => 64]);
		$table->addColumn('last_modified', 'string', ['notnull' => true, 'length' => 32]);

		$table->setPrimaryKey(['id_ahorro']);
		$table->addIndex(['id_user'], 'user_ahorro_uid');
		$table->addIndex(['id_permision'], 'user_ahorro_perm');
		$table->addIndex(['state'], 'user_ahorro_state');
	}

	private function createHistorialAhorro(ISchemaWrapper $schema): void {
		if ($schema->hasTable('historial_ahorro')) {
			return;
		}

		$table = $schema->createTable('historial_ahorro');

		$table->addColumn('id_historial', 'integer', [
			'autoincrement' => true,
			'notnull' => true,
		]);
		$table->addColumn('id_ahorro', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('cantidad_solicitada', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('cantidad_total', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('fecha_solicitud', 'string', ['notnull' => false, 'length' => 32]);
		$table->addColumn('estado', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('nota', 'string', ['notnull' => false, 'length' => 255]);

		$table->setPrimaryKey(['id_historial']);
		$table->addIndex(['id_ahorro'], 'hist_ahorro_id');
		$table->addIndex(['estado'], 'hist_ahorro_estado');
		$table->addIndex(['fecha_solicitud'], 'hist_ahorro_fecha');
	}

	private function createCapitalHumano(ISchemaWrapper $schema): void {
		if ($schema->hasTable('CapitalHumano')) {
			return;
		}

		$table = $schema->createTable('CapitalHumano');

		$table->addColumn('Id_ch', 'integer', [
			'autoincrement' => true,
			'notnull' => true,
		]);
		$table->addColumn('Id_empleado', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('created_at', 'string', ['notnull' => true, 'length' => 32]);
		$table->addColumn('updated_at', 'string', ['notnull' => true, 'length' => 32]);

		$table->setPrimaryKey(['Id_ch']);
		$table->addIndex(['Id_ch'], 'Id_ch');
		$table->addIndex(['Id_empleado'], 'CapitalHumano_Id_empleado');
	}

	private function createEmpleadosClientes(ISchemaWrapper $schema): void {
		if ($schema->hasTable('empleados_clientes')) {
			return;
		}

		$table = $schema->createTable('empleados_clientes');

		$table->addColumn('id_cliente', 'integer', [
			'autoincrement' => true,
			'notnull' => true,
		]);
		$table->addColumn('nombre', 'string', ['length' => 200, 'notnull' => true]);
		$table->addColumn('detalles', 'text', ['notnull' => false]);
		$table->addColumn('cliente_padre', 'integer', ['notnull' => false]);
		$table->addColumn('timestamp', 'datetime', ['notnull' => true, 'default' => 'CURRENT_TIMESTAMP']);

		$table->setPrimaryKey(['id_cliente']);
		$table->addIndex(['nombre'], 'emp_clientes_nombre');
		$table->addIndex(['cliente_padre'], 'emp_clientes_padre');
	}

	private function createEmpleadosActividades(ISchemaWrapper $schema): void {
		if ($schema->hasTable('empleados_actividades')) {
			return;
		}

		$table = $schema->createTable('empleados_actividades');

		$table->addColumn('id_actividad', 'integer', [
			'autoincrement' => true,
			'notnull' => true,
		]);
		$table->addColumn('nombre', 'string', ['length' => 200, 'notnull' => true]);
		$table->addColumn('detalles', 'text', ['notnull' => false]);
		$table->addColumn('tiempo_estimado', 'decimal', ['precision' => 8, 'scale' => 2, 'notnull' => false]);
		$table->addColumn('tiempo_real', 'decimal', ['precision' => 8, 'scale' => 2, 'notnull' => false]);

		$table->setPrimaryKey(['id_actividad']);
		$table->addIndex(['nombre'], 'emp_actividades_nombre');
	}

	private function createEmpleadosRepTiempos(ISchemaWrapper $schema): void {
		if ($schema->hasTable('empleados_rep_tiempos')) {
			return;
		}

		$table = $schema->createTable('empleados_rep_tiempos');

		$table->addColumn('id_reporte', 'integer', [
			'autoincrement' => true,
			'notnull' => true,
		]);
		$table->addColumn('id_empleado', 'string', ['length' => 64, 'notnull' => true]);
		$table->addColumn('id_cliente', 'integer', ['notnull' => false]);
		$table->addColumn('id_actividad', 'integer', ['notnull' => false]);
		$table->addColumn('descripcion', 'text', ['notnull' => false]);
		$table->addColumn('tiempo_registrado', 'decimal', ['precision' => 8, 'scale' => 2, 'notnull' => true]);
		$table->addColumn('fecha_registro', 'date', ['notnull' => true]);
		$table->addColumn('created_at', 'datetime', ['notnull' => true, 'default' => 'CURRENT_TIMESTAMP']);
		$table->addColumn('updated_at', 'datetime', ['notnull' => true, 'default' => 'CURRENT_TIMESTAMP']);

		$table->setPrimaryKey(['id_reporte']);
		$table->addIndex(['id_empleado'], 'emp_rep_tiempos_empleado');
		$table->addIndex(['id_cliente'], 'emp_rep_tiempos_cliente');
		$table->addIndex(['id_actividad'], 'emp_rep_tiempos_actividad');
		$table->addIndex(['fecha_registro'], 'emp_rep_tiempos_fecha');
	}

	private function insertConfig(string $nombre, ?string $data): bool {
		$qb = $this->db->getQueryBuilder();

		$qb->select('Id_conf')
			->from('empleados_conf')
			->where($qb->expr()->eq(
				'Nombre',
				$qb->createNamedParameter($nombre, IQueryBuilder::PARAM_STR)
			))
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$exists = $result->fetch();
		$result->closeCursor();

		if ($exists) {
			return false;
		}

		$qb = $this->db->getQueryBuilder();

		$values = [
			'Nombre' => $qb->createNamedParameter($nombre, IQueryBuilder::PARAM_STR),
		];

		if ($data !== null) {
			$values['Data'] = $qb->createNamedParameter($data, IQueryBuilder::PARAM_STR);
		}

		$qb->insert('empleados_conf')
			->values($values);

		if (method_exists($qb, 'executeStatement')) {
			$qb->executeStatement();
		} else {
			$qb->execute();
		}

		return true;
	}
}