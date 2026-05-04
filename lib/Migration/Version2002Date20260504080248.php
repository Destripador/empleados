<?php

declare(strict_types=1);

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2002Date20260504080248 extends SimpleMigrationStep {

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

		$this->createInventarioModelos($schema);
		$this->createInventarioComputo($schema);
		$this->createSoporteHistorial($schema);

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$configs = [
			'modulo_inventario' => 'false',
			'modulo_soporte' => 'false',
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

	private function createInventarioModelos(ISchemaWrapper $schema): void {
		if ($schema->hasTable('inventario_modelos')) {
			return;
		}

		$table = $schema->createTable('inventario_modelos');

		$table->addColumn('id_modelo', 'integer', [
			'autoincrement' => true,
			'unsigned' => true,
			'notnull' => true,
		]);

		$table->addColumn('marca', 'string', [
			'notnull' => false,
			'length' => 100,
		]);

		$table->addColumn('modelo', 'string', [
			'notnull' => false,
			'length' => 150,
		]);

		$table->addColumn('procesador', 'string', [
			'notnull' => false,
			'length' => 150,
		]);

		$table->addColumn('ram', 'string', [
			'notnull' => false,
			'length' => 100,
		]);

		$table->addColumn('disco_duro', 'string', [
			'notnull' => false,
			'length' => 150,
		]);

		$table->addColumn('tipo', 'string', [
			'notnull' => false,
			'length' => 100,
		]);

		$table->addColumn('touch', 'boolean', [
			'notnull' => false,
			'default' => false,
		]);

		$table->addColumn('created_at', 'datetime', [
			'notnull' => false,
		]);

		$table->addColumn('updated_at', 'datetime', [
			'notnull' => false,
		]);

		$table->setPrimaryKey(['id_modelo']);
		$table->addIndex(['marca'], 'inv_modelos_marca');
		$table->addIndex(['modelo'], 'inv_modelos_modelo');
		$table->addIndex(['tipo'], 'inv_modelos_tipo');
	}

	private function createInventarioComputo(ISchemaWrapper $schema): void {
		if ($schema->hasTable('inventario_computo')) {
			return;
		}

		$table = $schema->createTable('inventario_computo');

		$table->addColumn('id_equipo', 'integer', [
			'autoincrement' => true,
			'unsigned' => true,
			'notnull' => true,
		]);

		$table->addColumn('id_empleado', 'integer', [
			'unsigned' => true,
			'notnull' => false,
		]);

		$table->addColumn('id_modelo', 'integer', [
			'unsigned' => true,
			'notnull' => false,
		]);

		$table->addColumn('nombre_dispositivo', 'string', [
			'notnull' => false,
			'length' => 150,
		]);

		$table->addColumn('nombre_sistema', 'string', [
			'notnull' => false,
			'length' => 150,
		]);

		$table->addColumn('numero_serie', 'string', [
			'notnull' => false,
			'length' => 190,
		]);

		$table->addColumn('estado', 'string', [
			'notnull' => false,
			'length' => 80,
			'default' => 'activo',
		]);

		$table->addColumn('info', 'text', [
			'notnull' => false,
		]);

		$table->addColumn('created_at', 'datetime', [
			'notnull' => false,
		]);

		$table->addColumn('updated_at', 'datetime', [
			'notnull' => false,
		]);

		$table->setPrimaryKey(['id_equipo']);

		$table->addIndex(['id_empleado'], 'inv_comp_empleado');
		$table->addIndex(['id_modelo'], 'inv_comp_modelo');
		$table->addIndex(['numero_serie'], 'inv_comp_serie');
		$table->addIndex(['estado'], 'inv_comp_estado');

		/*
		 * No agrego foreign keys todavía.
		 *
		 * Motivo:
		 * En apps de Nextcloud suele ser más seguro manejar relaciones por índice
		 * para evitar problemas en upgrades, instalaciones antiguas o datos heredados.
		 *
		 * Relaciones lógicas:
		 * - inventario_computo.id_empleado → empleados.Id_empleados
		 * - inventario_computo.id_modelo → inventario_modelos.id_modelo
		 */
	}

	private function createSoporteHistorial(ISchemaWrapper $schema): void {
		if ($schema->hasTable('soporte_historial')) {
			return;
		}

		$table = $schema->createTable('soporte_historial');

		$table->addColumn('id_soporte', 'integer', [
			'autoincrement' => true,
			'unsigned' => true,
			'notnull' => true,
		]);

		$table->addColumn('id_equipo', 'integer', [
			'unsigned' => true,
			'notnull' => true,
		]);

		$table->addColumn('accion', 'string', [
			'notnull' => false,
			'length' => 150,
		]);

		$table->addColumn('detalles', 'text', [
			'notnull' => false,
		]);

		$table->addColumn('fecha', 'datetime', [
			'notnull' => false,
		]);

		$table->addColumn('usuario_actual', 'string', [
			'notnull' => false,
			'length' => 100,
		]);

		$table->addColumn('usuario_soporte', 'string', [
			'notnull' => false,
			'length' => 100,
		]);

		$table->addColumn('created_at', 'datetime', [
			'notnull' => false,
		]);

		$table->addColumn('updated_at', 'datetime', [
			'notnull' => false,
		]);

		$table->setPrimaryKey(['id_soporte']);

		$table->addIndex(['id_equipo'], 'soporte_hist_equipo');
		$table->addIndex(['accion'], 'soporte_hist_accion');
		$table->addIndex(['fecha'], 'soporte_hist_fecha');
		$table->addIndex(['usuario_actual'], 'soporte_hist_usuario');
		$table->addIndex(['usuario_soporte'], 'soporte_hist_tecnico');

		/*
		 * Relación lógica:
		 * - soporte_historial.id_equipo → inventario_computo.id_equipo
		 */
	}

	private function insertConfig(string $nombre, ?string $data): bool {
		if (!$this->tableExists('empleados_conf')) {
			return false;
		}

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
			->values($values)
			->executeStatement();

		return true;
	}

	private function tableExists(string $table): bool {
		try {
			$schema = $this->db->createSchema();
			return $schema->hasTable($table);
		} catch (\Throwable) {
			return false;
		}
	}
}