<?php

declare(strict_types=1);

/**
 * Migración para agregar el módulo de compras a OCA\Empleados.
 *
 * Esta migración es idempotente:
 * - Crea las tablas de compras si no existen.
 * - Inserta configuraciones base solo si no existen.
 * - No elimina ni modifica datos existentes.
 */

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2003Date20260507022325 extends SimpleMigrationStep {

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

		$this->createComprasSolicitudes($schema);
		$this->createComprasDetalles($schema);
		$this->createComprasProveedores($schema);
		$this->createComprasCotizaciones($schema);
		$this->createComprasAutorizaciones($schema);
		$this->createComprasAdjuntos($schema);
		$this->createComprasOrdenes($schema);
		$this->createComprasHistorial($schema);

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$configs = [
			'modulo_compras' => 'false',
			'compras_requiere_autorizacion' => 'true',
			'compras_moneda_default' => 'MXN',
			'compras_requiere_cotizacion' => 'false',
			'compras_numero_cotizaciones' => '1',
			'compras_monto_autorizacion_doble' => '5000',
			'compras_grupo_solicitantes' => 'compras_solicitantes',
			'compras_grupo_autorizadores' => 'compras_autorizadores',
			'compras_grupo_admin' => 'compras_admin',
			'compras_grupo_contabilidad' => 'compras_contabilidad',
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

	private function createComprasSolicitudes(ISchemaWrapper $schema): void {
		if ($schema->hasTable('emp_comp_solicitudes')) {
			return;
		}

		$table = $schema->createTable('emp_comp_solicitudes');

		$table->addColumn('id_solicitud', 'integer', [
			'autoincrement' => true,
			'unsigned' => true,
			'notnull' => true,
		]);
		$table->addColumn('folio', 'string', ['length' => 64, 'notnull' => true]);
		$table->addColumn('id_user', 'string', ['length' => 64, 'notnull' => true]);
		$table->addColumn('id_empleado', 'string', ['length' => 64, 'notnull' => false]);
		$table->addColumn('id_departamento', 'string', ['length' => 64, 'notnull' => false]);
		$table->addColumn('id_equipo', 'string', ['length' => 64, 'notnull' => false]);
		$table->addColumn('id_cliente', 'integer', ['unsigned' => true, 'notnull' => false]);
		$table->addColumn('titulo', 'string', ['length' => 190, 'notnull' => true]);
		$table->addColumn('descripcion', 'text', ['notnull' => false]);
		$table->addColumn('justificacion', 'text', ['notnull' => false]);
		$table->addColumn('monto_estimado', 'decimal', ['precision' => 12, 'scale' => 2, 'notnull' => false]);
		$table->addColumn('monto_final', 'decimal', ['precision' => 12, 'scale' => 2, 'notnull' => false]);
		$table->addColumn('moneda', 'string', ['length' => 3, 'notnull' => true, 'default' => 'MXN']);
		$table->addColumn('prioridad', 'string', ['length' => 32, 'notnull' => true, 'default' => 'normal']);
		$table->addColumn('estado', 'string', ['length' => 64, 'notnull' => true, 'default' => 'borrador']);
		$table->addColumn('fecha_requerida', 'date', ['notnull' => false]);
		$table->addColumn('fecha_envio', 'datetime', ['notnull' => false]);
		$table->addColumn('fecha_autorizacion', 'datetime', ['notnull' => false]);
		$table->addColumn('fecha_cierre', 'datetime', ['notnull' => false]);
		$table->addColumn('proveedor_seleccionado', 'integer', ['unsigned' => true, 'notnull' => false]);
		$table->addColumn('created_at', 'datetime', ['notnull' => true, 'default' => 'CURRENT_TIMESTAMP']);
		$table->addColumn('updated_at', 'datetime', ['notnull' => true, 'default' => 'CURRENT_TIMESTAMP']);
		$table->addColumn('created_by', 'string', ['length' => 64, 'notnull' => true]);
		$table->addColumn('updated_by', 'string', ['length' => 64, 'notnull' => false]);

		$table->setPrimaryKey(['id_solicitud']);
		$table->addUniqueIndex(['folio'], 'comp_sol_folio_uq');
		$table->addIndex(['id_user'], 'comp_sol_user_idx');
		$table->addIndex(['id_empleado'], 'comp_sol_emp_idx');
		$table->addIndex(['id_departamento'], 'comp_sol_depto_idx');
		$table->addIndex(['id_equipo'], 'comp_sol_equipo_idx');
		$table->addIndex(['id_cliente'], 'comp_sol_cliente_idx');
		$table->addIndex(['estado'], 'comp_sol_estado_idx');
		$table->addIndex(['prioridad'], 'comp_sol_prior_idx');
		$table->addIndex(['fecha_requerida'], 'comp_sol_fecha_req_idx');
		$table->addIndex(['proveedor_seleccionado'], 'comp_sol_prov_idx');
	}

	private function createComprasDetalles(ISchemaWrapper $schema): void {
		if ($schema->hasTable('emp_comp_detalles')) {
			return;
		}

		$table = $schema->createTable('emp_comp_detalles');

		$table->addColumn('id_detalle', 'integer', [
			'autoincrement' => true,
			'unsigned' => true,
			'notnull' => true,
		]);
		$table->addColumn('id_solicitud', 'integer', ['unsigned' => true, 'notnull' => true]);
		$table->addColumn('descripcion', 'text', ['notnull' => true]);
		$table->addColumn('cantidad', 'decimal', ['precision' => 12, 'scale' => 2, 'notnull' => true, 'default' => 1.00]);
		$table->addColumn('unidad', 'string', ['length' => 64, 'notnull' => false]);
		$table->addColumn('precio_estimado', 'decimal', ['precision' => 12, 'scale' => 2, 'notnull' => false]);
		$table->addColumn('subtotal', 'decimal', ['precision' => 12, 'scale' => 2, 'notnull' => false]);
		$table->addColumn('notas', 'text', ['notnull' => false]);
		$table->addColumn('created_at', 'datetime', ['notnull' => true, 'default' => 'CURRENT_TIMESTAMP']);
		$table->addColumn('updated_at', 'datetime', ['notnull' => true, 'default' => 'CURRENT_TIMESTAMP']);

		$table->setPrimaryKey(['id_detalle']);
		$table->addIndex(['id_solicitud'], 'comp_det_sol_idx');
	}

	private function createComprasProveedores(ISchemaWrapper $schema): void {
		if ($schema->hasTable('emp_comp_proveedores')) {
			return;
		}

		$table = $schema->createTable('emp_comp_proveedores');

		$table->addColumn('id_proveedor', 'integer', [
			'autoincrement' => true,
			'unsigned' => true,
			'notnull' => true,
		]);
		$table->addColumn('nombre', 'string', ['length' => 190, 'notnull' => true]);
		$table->addColumn('rfc', 'string', ['length' => 32, 'notnull' => false]);
		$table->addColumn('correo', 'string', ['length' => 190, 'notnull' => false]);
		$table->addColumn('telefono', 'string', ['length' => 64, 'notnull' => false]);
		$table->addColumn('contacto', 'string', ['length' => 190, 'notnull' => false]);
		$table->addColumn('direccion', 'text', ['notnull' => false]);
		$table->addColumn('notas', 'text', ['notnull' => false]);
		$table->addColumn('activo', 'integer', ['notnull' => true, 'default' => 1]);
		$table->addColumn('created_at', 'datetime', ['notnull' => true, 'default' => 'CURRENT_TIMESTAMP']);
		$table->addColumn('updated_at', 'datetime', ['notnull' => true, 'default' => 'CURRENT_TIMESTAMP']);

		$table->setPrimaryKey(['id_proveedor']);
		$table->addIndex(['nombre'], 'comp_prov_nombre_idx');
		$table->addIndex(['rfc'], 'comp_prov_rfc_idx');
		$table->addIndex(['activo'], 'comp_prov_activo_idx');
	}

	private function createComprasCotizaciones(ISchemaWrapper $schema): void {
		if ($schema->hasTable('emp_comp_cotizaciones')) {
			return;
		}

		$table = $schema->createTable('emp_comp_cotizaciones');

		$table->addColumn('id_cotizacion', 'integer', [
			'autoincrement' => true,
			'unsigned' => true,
			'notnull' => true,
		]);
		$table->addColumn('id_solicitud', 'integer', ['unsigned' => true, 'notnull' => true]);
		$table->addColumn('id_proveedor', 'integer', ['unsigned' => true, 'notnull' => false]);
		$table->addColumn('monto', 'decimal', ['precision' => 12, 'scale' => 2, 'notnull' => false]);
		$table->addColumn('moneda', 'string', ['length' => 3, 'notnull' => true, 'default' => 'MXN']);
		$table->addColumn('archivo_file_id', 'integer', ['unsigned' => true, 'notnull' => false]);
		$table->addColumn('archivo_nombre', 'string', ['length' => 255, 'notnull' => false]);
		$table->addColumn('seleccionada', 'integer', ['notnull' => true, 'default' => 0]);
		$table->addColumn('notas', 'text', ['notnull' => false]);
		$table->addColumn('created_by', 'string', ['length' => 64, 'notnull' => false]);
		$table->addColumn('created_at', 'datetime', ['notnull' => true, 'default' => 'CURRENT_TIMESTAMP']);

		$table->setPrimaryKey(['id_cotizacion']);
		$table->addIndex(['id_solicitud'], 'comp_cot_sol_idx');
		$table->addIndex(['id_proveedor'], 'comp_cot_prov_idx');
		$table->addIndex(['archivo_file_id'], 'comp_cot_file_idx');
		$table->addIndex(['seleccionada'], 'comp_cot_sel_idx');
	}

	private function createComprasAutorizaciones(ISchemaWrapper $schema): void {
		if ($schema->hasTable('emp_comp_autoriza')) {
			return;
		}

		$table = $schema->createTable('emp_comp_autoriza');

		$table->addColumn('id_autorizacion', 'integer', [
			'autoincrement' => true,
			'unsigned' => true,
			'notnull' => true,
		]);
		$table->addColumn('id_solicitud', 'integer', ['unsigned' => true, 'notnull' => true]);
		$table->addColumn('id_autorizador', 'string', ['length' => 64, 'notnull' => true]);
		$table->addColumn('id_empleado_autorizador', 'string', ['length' => 64, 'notnull' => false]);
		$table->addColumn('nivel', 'integer', ['notnull' => true, 'default' => 1]);
		$table->addColumn('estado', 'string', ['length' => 64, 'notnull' => true, 'default' => 'pendiente']);
		$table->addColumn('comentario', 'text', ['notnull' => false]);
		$table->addColumn('fecha_autorizacion', 'datetime', ['notnull' => false]);
		$table->addColumn('created_at', 'datetime', ['notnull' => true, 'default' => 'CURRENT_TIMESTAMP']);
		$table->addColumn('updated_at', 'datetime', ['notnull' => true, 'default' => 'CURRENT_TIMESTAMP']);

		$table->setPrimaryKey(['id_autorizacion']);
		$table->addIndex(['id_solicitud'], 'comp_aut_sol_idx');
		$table->addIndex(['id_autorizador'], 'comp_aut_user_idx');
		$table->addIndex(['id_empleado_autorizador'], 'comp_aut_emp_idx');
		$table->addIndex(['estado'], 'comp_aut_estado_idx');
		$table->addIndex(['nivel'], 'comp_aut_nivel_idx');
	}

	private function createComprasAdjuntos(ISchemaWrapper $schema): void {
		if ($schema->hasTable('emp_comp_adjuntos')) {
			return;
		}

		$table = $schema->createTable('emp_comp_adjuntos');

		$table->addColumn('id_adjunto', 'integer', [
			'autoincrement' => true,
			'unsigned' => true,
			'notnull' => true,
		]);
		$table->addColumn('id_solicitud', 'integer', ['unsigned' => true, 'notnull' => true]);
		$table->addColumn('tipo', 'string', ['length' => 64, 'notnull' => true]);
		$table->addColumn('file_id', 'integer', ['unsigned' => true, 'notnull' => false]);
		$table->addColumn('nombre_archivo', 'string', ['length' => 255, 'notnull' => false]);
		$table->addColumn('mime', 'string', ['length' => 190, 'notnull' => false]);
		$table->addColumn('tamano', 'integer', ['unsigned' => true, 'notnull' => false]);
		$table->addColumn('created_by', 'string', ['length' => 64, 'notnull' => false]);
		$table->addColumn('created_at', 'datetime', ['notnull' => true, 'default' => 'CURRENT_TIMESTAMP']);

		$table->setPrimaryKey(['id_adjunto']);
		$table->addIndex(['id_solicitud'], 'comp_adj_sol_idx');
		$table->addIndex(['tipo'], 'comp_adj_tipo_idx');
		$table->addIndex(['file_id'], 'comp_adj_file_idx');
	}

	private function createComprasOrdenes(ISchemaWrapper $schema): void {
		if ($schema->hasTable('emp_comp_ordenes')) {
			return;
		}

		$table = $schema->createTable('emp_comp_ordenes');

		$table->addColumn('id_orden', 'integer', [
			'autoincrement' => true,
			'unsigned' => true,
			'notnull' => true,
		]);
		$table->addColumn('id_solicitud', 'integer', ['unsigned' => true, 'notnull' => true]);
		$table->addColumn('folio_orden', 'string', ['length' => 64, 'notnull' => true]);
		$table->addColumn('id_proveedor', 'integer', ['unsigned' => true, 'notnull' => false]);
		$table->addColumn('monto_total', 'decimal', ['precision' => 12, 'scale' => 2, 'notnull' => false]);
		$table->addColumn('moneda', 'string', ['length' => 3, 'notnull' => true, 'default' => 'MXN']);
		$table->addColumn('estado', 'string', ['length' => 64, 'notnull' => true, 'default' => 'generada']);
		$table->addColumn('archivo_file_id', 'integer', ['unsigned' => true, 'notnull' => false]);
		$table->addColumn('archivo_nombre', 'string', ['length' => 255, 'notnull' => false]);
		$table->addColumn('created_by', 'string', ['length' => 64, 'notnull' => false]);
		$table->addColumn('created_at', 'datetime', ['notnull' => true, 'default' => 'CURRENT_TIMESTAMP']);
		$table->addColumn('updated_at', 'datetime', ['notnull' => true, 'default' => 'CURRENT_TIMESTAMP']);

		$table->setPrimaryKey(['id_orden']);
		$table->addUniqueIndex(['folio_orden'], 'comp_ord_folio_uq');
		$table->addIndex(['id_solicitud'], 'comp_ord_sol_idx');
		$table->addIndex(['id_proveedor'], 'comp_ord_prov_idx');
		$table->addIndex(['estado'], 'comp_ord_estado_idx');
		$table->addIndex(['archivo_file_id'], 'comp_ord_file_idx');
	}

	private function createComprasHistorial(ISchemaWrapper $schema): void {
		if ($schema->hasTable('emp_comp_historial')) {
			return;
		}

		$table = $schema->createTable('emp_comp_historial');

		$table->addColumn('id_historial', 'integer', [
			'autoincrement' => true,
			'unsigned' => true,
			'notnull' => true,
		]);
		$table->addColumn('id_solicitud', 'integer', ['unsigned' => true, 'notnull' => true]);
		$table->addColumn('accion', 'string', ['length' => 64, 'notnull' => true]);
		$table->addColumn('estado_anterior', 'string', ['length' => 64, 'notnull' => false]);
		$table->addColumn('estado_nuevo', 'string', ['length' => 64, 'notnull' => false]);
		$table->addColumn('comentario', 'text', ['notnull' => false]);
		$table->addColumn('metadata', 'text', ['notnull' => false]);
		$table->addColumn('created_by', 'string', ['length' => 64, 'notnull' => false]);
		$table->addColumn('created_at', 'datetime', ['notnull' => true, 'default' => 'CURRENT_TIMESTAMP']);

		$table->setPrimaryKey(['id_historial']);
		$table->addIndex(['id_solicitud'], 'comp_hist_sol_idx');
		$table->addIndex(['accion'], 'comp_hist_accion_idx');
		$table->addIndex(['estado_nuevo'], 'comp_hist_estado_idx');
		$table->addIndex(['created_at'], 'comp_hist_fecha_idx');
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
