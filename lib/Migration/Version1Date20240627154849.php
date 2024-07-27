<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2024 Luis Angel Alvarado Hernandez <luis.alvarado@crowe.mx>
 *
 * @author Luis Angel Alvarado Hernandez <luis.alvarado@crowe.mx>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1Date20240627154849 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('empleados')) {
			$table = $schema->createTable('empleados');
			$table->addColumn('Id_empleados', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
			]);
			
			$table->addColumn('Id_user', 'string', [
				'notnull' => true,
			]);

			$table->addColumn('Numero_empleado', 'string', [
				'notnull' => false,
			]);
			
			$table->addColumn('Ingreso', 'string', [
				'notnull' => false,
			]);
			
			$table->addColumn('Correo_contacto', 'string', [
				'notnull' => false,
			]);
			
			$table->addColumn('Id_departamento', 'string', [
				'notnull' => false,
			]);
			
			$table->addColumn('Id_puesto', 'string', [
				'notnull' => false,
			]);

			$table->addColumn('Id_gerente', 'string', [
				'notnull' => false,
			]);

			$table->addColumn('Id_socio', 'string', [
				'notnull' => false,
			]);

			$table->addColumn('Fondo_clave', 'string', [
				'notnull' => false,
			]);

			$table->addColumn('Numero_cuenta', 'string', [
				'notnull' => false,
			]);

			$table->addColumn('Equipo_asignado', 'string', [
				'notnull' => false,
			]);

			$table->addColumn('Sueldo', 'decimal', [
				'notnull' => false,
			]);

			$table->addColumn('Fecha_nacimiento', 'string', [
				'notnull' => false,
			]);
			
			$table->addColumn('Estado', 'string', [
				'notnull' => false,
			]);


			$table->addColumn('Direccion', 'string', [
				'notnull' => false,
			]);
			$table->addColumn('Estado_civil', 'string', [
				'notnull' => false,
			]);
			$table->addColumn('Telefono_contacto', 'string', [
				'notnull' => false,
			]);
			$table->addColumn('Curp', 'string', [
				'notnull' => false,
			]);
			$table->addColumn('Rfc', 'string', [
				'notnull' => false,
			]);
			$table->addColumn('Imss', 'string', [
				'notnull' => false,
			]);
			$table->addColumn('Genero', 'string', [
				'notnull' => false,
			]);
			$table->addColumn('Contacto_emergencia', 'string', [
				'notnull' => false,
			]);
			$table->addColumn('Numero_emergencia', 'string', [
				'notnull' => false,
			]);
			$table->addColumn('created_at', 'string', [
				'notnull' => true,
			]);
	
			$table->addColumn('updated_at', 'string', [
				'notnull' => true,
			]);
			
			  $table->setPrimaryKey(['Id_empleados']);
			  $table->addIndex(['Id_empleados'], 'Id_empleados');
		  }

		// Tabla de puestos
		  if (!$schema->hasTable('puestos')) {
			$table = $schema->createTable('puestos');
			$table->addColumn('Id_puestos', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
			]);
			
			$table->addColumn('Nombre', 'string', [
				'notnull' => false,
			]);

			$table->addColumn('created_at', 'string', [
				'notnull' => true,
			]);
	
			$table->addColumn('updated_at', 'string', [
				'notnull' => true,
			]);
			
			
			  $table->setPrimaryKey(['Id_puestos']);
			  $table->addIndex(['Id_puestos'], 'Id_puestos');
		  }

			// Tabla de departamento
			if (!$schema->hasTable('departamentos')) {
				$table = $schema->createTable('departamentos');
				$table->addColumn('Id_departamento', 'integer', [
					'autoincrement' => true,
					'notnull' => true,
				]);

				$table->addColumn('Id_padre', 'integer', [
					'notnull' => false,
				]);

				$table->addColumn('Nombre', 'string', [
					'notnull' => false,
				]);
	
				$table->addColumn('created_at', 'string', [
				'notnull' => true,
				]);
		
				$table->addColumn('updated_at', 'string', [
				'notnull' => true,
				]);
				
				$table->setPrimaryKey(['Id_departamento']);
				$table->addIndex(['Id_departamento'], 'Id_departamento');
			}
	
		  return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}
}
