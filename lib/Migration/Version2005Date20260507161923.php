<?php

declare(strict_types=1);

/**
 * Migra el módulo de compras para soportar exportación de documentos PDF
 * tipo "Solicitud de compra personal".
 *
 * Esta migración:
 * - Agrega snapshots del solicitante.
 * - Agrega campos administrativos y de requisición.
 * - Agrega tabla de firmas/aprobadores.
 * - Agrega tabla de documentos generados.
 * - No elimina datos existentes.
 */

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2005Date20260507161923 extends SimpleMigrationStep {

	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		// Sin acciones previas.
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$this->updateSolicitudes($schema);
		$this->updateDetalles($schema);
		$this->createFirmas($schema);
		$this->createDocumentos($schema);

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		// Sin acciones posteriores.
	}

	private function updateSolicitudes(ISchemaWrapper $schema): void {
		if (!$schema->hasTable('emp_comp_solicitudes')) {
			return;
		}

		$table = $schema->getTable('emp_comp_solicitudes');

		$this->addColumn($table, 'solicitante_nombre', 'string', [
			'length' => 190,
			'notnull' => false,
		]);

		$this->addColumn($table, 'solicitante_depto', 'string', [
			'length' => 190,
			'notnull' => false,
		]);

		$this->addColumn($table, 'solicitante_cargo', 'string', [
			'length' => 190,
			'notnull' => false,
		]);

		$this->addColumn($table, 'jefe_directo_nombre', 'string', [
			'length' => 190,
			'notnull' => false,
		]);

		$this->addColumn($table, 'tipo_compra', 'string', [
			'length' => 64,
			'notnull' => false,
		]);

		$this->addColumn($table, 'garantia', 'integer', [
			'notnull' => true,
			'default' => 0,
		]);

		$this->addColumn($table, 'uso_compra', 'string', [
			'length' => 64,
			'notnull' => false,
			'default' => 'empresa',
		]);

		$this->addColumn($table, 'informacion', 'text', [
			'notnull' => false,
		]);

		$this->addColumn($table, 'motivo', 'text', [
			'notnull' => false,
		]);

		$this->addColumn($table, 'proveedor_nombre', 'string', [
			'length' => 190,
			'notnull' => false,
		]);

		$this->addColumn($table, 'atencion', 'string', [
			'length' => 190,
			'notnull' => false,
		]);

		$this->addColumn($table, 'entrega', 'string', [
			'length' => 190,
			'notnull' => false,
		]);

		$this->addColumn($table, 'marca_modelo', 'string', [
			'length' => 190,
			'notnull' => false,
		]);

		$this->addColumn($table, 'especificaciones', 'text', [
			'notnull' => false,
		]);

		$this->addColumn($table, 'comentarios_req', 'text', [
			'notnull' => false,
		]);

		$this->addColumn($table, 'oficina_pct', 'decimal', [
			'precision' => 5,
			'scale' => 2,
			'notnull' => false,
		]);

		$this->addColumn($table, 'empleado_pct', 'decimal', [
			'precision' => 5,
			'scale' => 2,
			'notnull' => false,
		]);

		$this->addColumn($table, 'tipo_pago', 'string', [
			'length' => 64,
			'notnull' => false,
		]);

		$this->addColumn($table, 'quincenas', 'integer', [
			'notnull' => false,
		]);

		$this->addColumn($table, 'total_excl_iva', 'decimal', [
			'precision' => 12,
			'scale' => 2,
			'notnull' => false,
		]);

		$this->addColumn($table, 'iva', 'decimal', [
			'precision' => 12,
			'scale' => 2,
			'notnull' => false,
		]);

		$this->addColumn($table, 'total_incl_iva', 'decimal', [
			'precision' => 12,
			'scale' => 2,
			'notnull' => false,
		]);

		$this->addColumn($table, 'comentarios_admin', 'text', [
			'notnull' => false,
		]);

		$this->addColumn($table, 'pdf_file_id', 'integer', [
			'unsigned' => true,
			'notnull' => false,
		]);

		$this->addColumn($table, 'pdf_nombre', 'string', [
			'length' => 255,
			'notnull' => false,
		]);

		$this->addColumn($table, 'pdf_generado_at', 'datetime', [
			'notnull' => false,
		]);

		$this->addIndex($table, ['tipo_compra'], 'ecs_tipo_idx');
		$this->addIndex($table, ['uso_compra'], 'ecs_uso_idx');
		$this->addIndex($table, ['pdf_file_id'], 'ecs_pdf_idx');
	}

	private function updateDetalles(ISchemaWrapper $schema): void {
		if (!$schema->hasTable('emp_comp_detalles')) {
			return;
		}

		$table = $schema->getTable('emp_comp_detalles');

		$this->addColumn($table, 'marca_modelo', 'string', [
			'length' => 190,
			'notnull' => false,
		]);

		$this->addColumn($table, 'especificaciones', 'text', [
			'notnull' => false,
		]);

		$this->addColumn($table, 'iva', 'decimal', [
			'precision' => 12,
			'scale' => 2,
			'notnull' => false,
		]);

		$this->addColumn($table, 'total', 'decimal', [
			'precision' => 12,
			'scale' => 2,
			'notnull' => false,
		]);

		$this->addColumn($table, 'proveedor_nombre', 'string', [
			'length' => 190,
			'notnull' => false,
		]);

		$this->addColumn($table, 'entrega', 'string', [
			'length' => 190,
			'notnull' => false,
		]);

		$this->addColumn($table, 'atencion', 'string', [
			'length' => 190,
			'notnull' => false,
		]);
	}

	private function createFirmas(ISchemaWrapper $schema): void {
		if ($schema->hasTable('emp_comp_firmas')) {
			return;
		}

		$table = $schema->createTable('emp_comp_firmas');

		$table->addColumn('id_firma', 'integer', [
			'autoincrement' => true,
			'unsigned' => true,
			'notnull' => true,
		]);

		$table->addColumn('id_solicitud', 'integer', [
			'unsigned' => true,
			'notnull' => true,
		]);

		$table->addColumn('rol', 'string', [
			'length' => 64,
			'notnull' => true,
		]);

		$table->addColumn('uid', 'string', [
			'length' => 64,
			'notnull' => false,
		]);

		$table->addColumn('nombre', 'string', [
			'length' => 190,
			'notnull' => false,
		]);

		$table->addColumn('estado', 'string', [
			'length' => 64,
			'notnull' => true,
			'default' => 'pendiente',
		]);

		$table->addColumn('comentario', 'text', [
			'notnull' => false,
		]);

		$table->addColumn('fecha_firma', 'datetime', [
			'notnull' => false,
		]);

		$table->addColumn('created_at', 'datetime', [
			'notnull' => true,
			'default' => 'CURRENT_TIMESTAMP',
		]);

		$table->addColumn('updated_at', 'datetime', [
			'notnull' => true,
			'default' => 'CURRENT_TIMESTAMP',
		]);

		$table->setPrimaryKey(['id_firma']);
		$table->addIndex(['id_solicitud'], 'ecf_sol_idx');
		$table->addIndex(['rol'], 'ecf_rol_idx');
		$table->addIndex(['uid'], 'ecf_uid_idx');
		$table->addIndex(['estado'], 'ecf_est_idx');
	}

	private function createDocumentos(ISchemaWrapper $schema): void {
		if ($schema->hasTable('emp_comp_docs')) {
			return;
		}

		$table = $schema->createTable('emp_comp_docs');

		$table->addColumn('id_doc', 'integer', [
			'autoincrement' => true,
			'unsigned' => true,
			'notnull' => true,
		]);

		$table->addColumn('id_solicitud', 'integer', [
			'unsigned' => true,
			'notnull' => true,
		]);

		$table->addColumn('tipo_doc', 'string', [
			'length' => 64,
			'notnull' => true,
			'default' => 'solicitud_compra',
		]);

		$table->addColumn('version', 'integer', [
			'notnull' => true,
			'default' => 1,
		]);

		$table->addColumn('file_id', 'integer', [
			'unsigned' => true,
			'notnull' => false,
		]);

		$table->addColumn('nombre_archivo', 'string', [
			'length' => 255,
			'notnull' => false,
		]);

		$table->addColumn('token', 'string', [
			'length' => 190,
			'notnull' => false,
		]);

		$table->addColumn('qr_text', 'text', [
			'notnull' => false,
		]);

		$table->addColumn('generado_por', 'string', [
			'length' => 64,
			'notnull' => false,
		]);

		$table->addColumn('generado_at', 'datetime', [
			'notnull' => true,
			'default' => 'CURRENT_TIMESTAMP',
		]);

		$table->setPrimaryKey(['id_doc']);
		$table->addIndex(['id_solicitud'], 'ecd_sol_idx');
		$table->addIndex(['tipo_doc'], 'ecd_tipo_idx');
		$table->addIndex(['file_id'], 'ecd_file_idx');
		$table->addIndex(['token'], 'ecd_tok_idx');
	}

	private function addColumn($table, string $name, string $type, array $options): void {
		if ($table->hasColumn($name)) {
			return;
		}

		$table->addColumn($name, $type, $options);
	}

	private function addIndex($table, array $columns, string $indexName): void {
		if ($table->hasIndex($indexName)) {
			return;
		}

		$table->addIndex($columns, $indexName);
	}
}
