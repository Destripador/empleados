<?php

declare(strict_types=1);

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2033Date20260803220000 extends SimpleMigrationStep {
	public function __construct(
		private IDBConnection $db,
	) {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$this->createMaintenanceGroups($schema);
		$this->createMaintenances($schema);
		$this->createMaintenanceChecks($schema);
		$this->createMaintenanceChanges($schema);

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$permissions = [
			[
				'module' => 'inventario',
				'permission' => 'technician',
				'group_id' => 'ti_tecnicos',
				'label' => 'TI - Técnicos',
				'description' => 'Puede atender y actualizar los mantenimientos que tenga asignados.',
				'sort_order' => 71,
			],
			[
				'module' => 'inventario',
				'permission' => 'view',
				'group_id' => 'ti_consulta',
				'label' => 'TI - Consulta',
				'description' => 'Puede consultar inventario, calendario y avance de mantenimientos.',
				'sort_order' => 72,
			],
		];

		foreach ($permissions as $permission) {
			$this->insertPermissionIfMissing($permission);
		}
	}

	private function createMaintenanceGroups(ISchemaWrapper $schema): void {
		if ($schema->hasTable('inv_mant_grupos')) {
			return;
		}

		$table = $schema->createTable('inv_mant_grupos');
		$table->addColumn('id', 'bigint', ['autoincrement' => true, 'unsigned' => true, 'notnull' => true]);
		$table->addColumn('titulo', 'string', ['length' => 255, 'notnull' => true]);
		$table->addColumn('id_departamento', 'integer', ['unsigned' => true, 'notnull' => false]);
		$table->addColumn('departamento_nombre', 'string', ['length' => 190, 'notnull' => false]);
		$table->addColumn('tipo', 'string', ['length' => 40, 'notnull' => true]);
		$table->addColumn('fecha_programada', 'date', ['notnull' => true]);
		$table->addColumn('hora_inicio', 'string', ['length' => 8, 'notnull' => false]);
		$table->addColumn('hora_fin', 'string', ['length' => 8, 'notnull' => false]);
		$table->addColumn('tecnico_uid', 'string', ['length' => 255, 'notnull' => false]);
		$table->addColumn('tecnico_nombre', 'string', ['length' => 255, 'notnull' => false]);
		$table->addColumn('estado_admin', 'string', ['length' => 20, 'notnull' => true, 'default' => 'active']);
		$table->addColumn('descripcion', 'text', ['notnull' => false]);
		$table->addColumn('creado_por', 'string', ['length' => 255, 'notnull' => true]);
		$table->addColumn('fecha_creacion', 'datetime', ['notnull' => true]);
		$table->addColumn('fecha_actualizacion', 'datetime', ['notnull' => true]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['fecha_programada'], 'img_fecha_idx');
		$table->addIndex(['id_departamento', 'fecha_programada'], 'img_depto_fecha_idx');
		$table->addIndex(['tecnico_uid', 'fecha_programada'], 'img_tec_fecha_idx');
		$table->addIndex(['tipo', 'fecha_programada'], 'img_tipo_fecha_idx');
		$table->addIndex(['estado_admin', 'fecha_programada'], 'img_estado_fecha_idx');
	}

	private function createMaintenances(ISchemaWrapper $schema): void {
		if ($schema->hasTable('inv_mantenimientos')) {
			return;
		}

		$table = $schema->createTable('inv_mantenimientos');
		$table->addColumn('id', 'bigint', ['autoincrement' => true, 'unsigned' => true, 'notnull' => true]);
		$table->addColumn('id_grupo', 'bigint', ['unsigned' => true, 'notnull' => true]);
		$table->addColumn('id_equipo', 'integer', ['unsigned' => true, 'notnull' => true]);
		$table->addColumn('equipo_nombre', 'string', ['length' => 255, 'notnull' => true]);
		$table->addColumn('equipo_identificador', 'string', ['length' => 255, 'notnull' => false]);
		$table->addColumn('id_modelo', 'integer', ['unsigned' => true, 'notnull' => false]);
		$table->addColumn('modelo_nombre', 'string', ['length' => 255, 'notnull' => false]);
		$table->addColumn('numero_serie', 'string', ['length' => 190, 'notnull' => false]);
		$table->addColumn('id_empleado', 'integer', ['unsigned' => true, 'notnull' => false]);
		$table->addColumn('empleado_uid', 'string', ['length' => 255, 'notnull' => false]);
		$table->addColumn('empleado_nombre', 'string', ['length' => 255, 'notnull' => false]);
		$table->addColumn('id_departamento', 'integer', ['unsigned' => true, 'notnull' => false]);
		$table->addColumn('departamento_nombre', 'string', ['length' => 190, 'notnull' => false]);
		$table->addColumn('tecnico_uid', 'string', ['length' => 255, 'notnull' => false]);
		$table->addColumn('tecnico_nombre', 'string', ['length' => 255, 'notnull' => false]);
		$table->addColumn('tipo', 'string', ['length' => 40, 'notnull' => true]);
		$table->addColumn('fecha_programada', 'date', ['notnull' => true]);
		$table->addColumn('hora_inicio_programada', 'string', ['length' => 8, 'notnull' => false]);
		$table->addColumn('hora_fin_programada', 'string', ['length' => 8, 'notnull' => false]);
		$table->addColumn('fecha_inicio_real', 'datetime', ['notnull' => false]);
		$table->addColumn('fecha_fin_real', 'datetime', ['notnull' => false]);
		$table->addColumn('estado', 'string', ['length' => 24, 'notnull' => true, 'default' => 'pending']);
		$table->addColumn('resultado', 'text', ['notnull' => false]);
		$table->addColumn('acciones_realizadas', 'text', ['notnull' => false]);
		$table->addColumn('incidencias', 'text', ['notnull' => false]);
		$table->addColumn('repuestos', 'text', ['notnull' => false]);
		$table->addColumn('observaciones', 'text', ['notnull' => false]);
		$table->addColumn('proxima_fecha', 'date', ['notnull' => false]);
		$table->addColumn('creado_por', 'string', ['length' => 255, 'notnull' => true]);
		$table->addColumn('actualizado_por', 'string', ['length' => 255, 'notnull' => true]);
		$table->addColumn('fecha_creacion', 'datetime', ['notnull' => true]);
		$table->addColumn('fecha_actualizacion', 'datetime', ['notnull' => true]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['id_grupo'], 'im_grupo_idx');
		$table->addIndex(['id_grupo', 'estado'], 'im_grupo_estado_idx');
		$table->addIndex(['id_equipo', 'fecha_programada'], 'im_equipo_fecha_idx');
		$table->addIndex(['id_departamento', 'fecha_programada'], 'im_depto_fecha_idx');
		$table->addIndex(['tecnico_uid', 'fecha_programada'], 'im_tec_fecha_idx');
		$table->addIndex(['estado', 'fecha_programada'], 'im_estado_fecha_idx');
		$table->addIndex(['tipo', 'fecha_programada'], 'im_tipo_fecha_idx');
		$table->addUniqueIndex(['id_grupo', 'id_equipo'], 'im_grupo_equipo_uniq');
	}

	private function createMaintenanceChecks(ISchemaWrapper $schema): void {
		if ($schema->hasTable('inv_mant_checks')) {
			return;
		}

		$table = $schema->createTable('inv_mant_checks');
		$table->addColumn('id', 'bigint', ['autoincrement' => true, 'unsigned' => true, 'notnull' => true]);
		$table->addColumn('id_mantenimiento', 'bigint', ['unsigned' => true, 'notnull' => true]);
		$table->addColumn('clave', 'string', ['length' => 80, 'notnull' => true]);
		$table->addColumn('etiqueta', 'string', ['length' => 255, 'notnull' => true]);
		$table->addColumn('orden', 'integer', ['unsigned' => true, 'notnull' => true]);
		$table->addColumn('resultado', 'string', ['length' => 24, 'notnull' => true, 'default' => 'pending']);
		$table->addColumn('observacion', 'text', ['notnull' => false]);
		$table->addColumn('actualizado_por', 'string', ['length' => 255, 'notnull' => true]);
		$table->addColumn('fecha_actualizacion', 'datetime', ['notnull' => true]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['id_mantenimiento'], 'imc_mant_idx');
		$table->addIndex(['id_mantenimiento', 'orden'], 'imc_mant_orden_idx');
		$table->addUniqueIndex(['id_mantenimiento', 'clave'], 'imc_mant_clave_uniq');
	}

	private function createMaintenanceChanges(ISchemaWrapper $schema): void {
		if ($schema->hasTable('inv_mant_cambios')) {
			return;
		}

		$table = $schema->createTable('inv_mant_cambios');
		$table->addColumn('id', 'bigint', ['autoincrement' => true, 'unsigned' => true, 'notnull' => true]);
		$table->addColumn('id_grupo', 'bigint', ['unsigned' => true, 'notnull' => false]);
		$table->addColumn('id_mantenimiento', 'bigint', ['unsigned' => true, 'notnull' => false]);
		$table->addColumn('tipo_cambio', 'string', ['length' => 40, 'notnull' => true]);
		$table->addColumn('valor_anterior', 'text', ['notnull' => false]);
		$table->addColumn('valor_nuevo', 'text', ['notnull' => false]);
		$table->addColumn('comentario', 'text', ['notnull' => false]);
		$table->addColumn('usuario_uid', 'string', ['length' => 255, 'notnull' => true]);
		$table->addColumn('usuario_nombre', 'string', ['length' => 255, 'notnull' => true]);
		$table->addColumn('fecha', 'datetime', ['notnull' => true]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['id_grupo', 'fecha'], 'imca_grupo_fecha_idx');
		$table->addIndex(['id_mantenimiento', 'fecha'], 'imca_mant_fecha_idx');
		$table->addIndex(['usuario_uid', 'fecha'], 'imca_user_fecha_idx');
	}

	private function insertPermissionIfMissing(array $permission): void {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('id')
			->from('emp_perm_groups')
			->where($qb->expr()->eq('module', $qb->createNamedParameter($permission['module'])))
			->andWhere($qb->expr()->eq('permission', $qb->createNamedParameter($permission['permission'])))
			->andWhere($qb->expr()->eq('group_id', $qb->createNamedParameter($permission['group_id'])))
			->setMaxResults(1)
			->executeQuery();
		$exists = $result->fetchOne();
		$result->closeCursor();

		if ($exists !== false) {
			return;
		}

		$insert = $this->db->getQueryBuilder();
		$insert->insert('emp_perm_groups')->values([
			'module' => $insert->createNamedParameter($permission['module']),
			'permission' => $insert->createNamedParameter($permission['permission']),
			'group_id' => $insert->createNamedParameter($permission['group_id']),
			'label' => $insert->createNamedParameter($permission['label']),
			'description' => $insert->createNamedParameter($permission['description']),
			'restricted' => $insert->createNamedParameter(0),
			'enabled' => $insert->createNamedParameter(1),
			'sort_order' => $insert->createNamedParameter($permission['sort_order']),
		])->executeStatement();
	}
}
