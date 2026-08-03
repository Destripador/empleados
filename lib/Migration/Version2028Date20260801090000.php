<?php

declare(strict_types=1);

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2028Date20260801090000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		if ($schema->hasTable('emp_cont_emer')) {
			return null;
		}

		$table = $schema->createTable('emp_cont_emer');
		$table->addColumn('id', 'bigint', ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
		$table->addColumn('id_empleado', 'integer', ['notnull' => true]);
		$table->addColumn('nombre', 'string', ['notnull' => true, 'length' => 200]);
		$table->addColumn('relacion', 'string', ['notnull' => true, 'length' => 120]);
		$table->addColumn('numero_contacto', 'string', ['notnull' => true, 'length' => 80]);
		$table->addColumn('medio_alternativo', 'string', ['notnull' => false, 'length' => 255]);
		$table->addColumn('tipo_ayuda', 'string', ['notnull' => false, 'length' => 255]);
		$table->addColumn('notas', 'text', ['notnull' => false]);
		$table->addColumn('es_principal', 'smallint', ['notnull' => true, 'default' => 0]);
		// Clave auxiliar nullable: permite varios NULL y hace exclusiva la fila principal incluso con peticiones concurrentes.
		$table->addColumn('principal_empleado', 'integer', ['notnull' => false]);
		$table->addColumn('orden', 'integer', ['notnull' => true, 'default' => 0]);
		$table->addColumn('created_at', 'string', ['notnull' => true, 'length' => 32]);
		$table->addColumn('updated_at', 'string', ['notnull' => true, 'length' => 32]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['id_empleado'], 'emp_cont_empleado_idx');
		$table->addIndex(['id_empleado', 'es_principal'], 'emp_cont_principal_idx');
		$table->addUniqueIndex(['principal_empleado'], 'emp_cont_principal_uniq');
		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$db = \OC::$server->getDatabaseConnection();
		$db->beginTransaction();
		try {
			$select = $db->getQueryBuilder();
			$result = $select->select('Id_empleados', 'Contacto_emergencia', 'Numero_emergencia')
				->from('empleados')
				->where($select->expr()->orX(
					$select->expr()->isNotNull('Contacto_emergencia'),
					$select->expr()->isNotNull('Numero_emergencia')
				))->executeQuery();
			$rows = $result->fetchAll();
			$result->closeCursor();
			$now = date('Y-m-d H:i:s');
			foreach ($rows as $row) {
				$nombre = trim((string)($row['Contacto_emergencia'] ?? ''));
				$numero = trim((string)($row['Numero_emergencia'] ?? ''));
				if ($nombre === '' || $numero === '') continue;
				$check = $db->getQueryBuilder();
				$checkResult = $check->select('id')->from('emp_cont_emer')
					->where($check->expr()->eq('id_empleado', $check->createNamedParameter((int)$row['Id_empleados'])))
					->setMaxResults(1)->executeQuery();
				$exists = $checkResult->fetchOne();
				$checkResult->closeCursor();
				if ($exists !== false) continue;
				$insert = $db->getQueryBuilder();
				$insert->insert('emp_cont_emer')->values([
					'id_empleado' => $insert->createNamedParameter((int)$row['Id_empleados']),
					'nombre' => $insert->createNamedParameter($nombre),
					'relacion' => $insert->createNamedParameter('Contacto heredado'),
					'numero_contacto' => $insert->createNamedParameter($numero),
					'es_principal' => $insert->createNamedParameter(1),
					'principal_empleado' => $insert->createNamedParameter((int)$row['Id_empleados']),
					'orden' => $insert->createNamedParameter(0),
					'created_at' => $insert->createNamedParameter($now),
					'updated_at' => $insert->createNamedParameter($now),
				])->executeStatement();
			}
			$db->commit();
		} catch (\Throwable $e) {
			$db->rollBack();
			throw $e;
		}
	}
}
