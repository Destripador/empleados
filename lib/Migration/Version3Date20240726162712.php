<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2024 FIXME Your name <your@email.com>
 *
 * FIXME @author Your name <your@email.com>
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

/**
 * FIXME Auto-generated migration step: Please modify to your needs!
 */
class Version3Date20240726162712 extends SimpleMigrationStep {

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
		if (!$schema->hasTable('empleados_files')) {
			$table = $schema->createTable('empleados_files');
			$table->addColumn('Id_archivo', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
			]);

			$table->addColumn('Id_empleado', 'string', [
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

			$table->setPrimaryKey(['Id_archivo']);
			$table->addIndex(['Id_archivo'], 'Id_archivo');
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
