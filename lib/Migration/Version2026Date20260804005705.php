<?php

declare(strict_types=1);

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use OCP\Security\ICrypto;
use Override;

class Version2026Date20260804005705 extends SimpleMigrationStep {

	/**
	 * Inyectamos las dependencias de Base de Datos y Encriptación de Nextcloud
	 */
	public function __construct(
		private IDBConnection $connection,
		private ICrypto $crypto
	) {
	}

	#[Override]
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}

	/**
	 * Define la estructura de las nuevas tablas
	 */
	#[Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		// 1. Tabla de Configuración NOI
		if (!$schema->hasTable('empleados_noi_conf')) {
			$table = $schema->createTable('empleados_noi_conf');
			
			$table->addColumn('id_noi_conf', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('nombre', 'string', [
				'length' => 190,
				'notnull' => true,
			]);
			$table->addColumn('data', 'string', [
				'length' => 255,
				'notnull' => false,
			]);
			
			$table->setPrimaryKey(['id_noi_conf']);
			$table->addUniqueIndex(['nombre'], 'noi_conf_nombre_idx');
		}

		// 2. Tabla de Entidades Federativas NOI
		if (!$schema->hasTable('empleados_ent_fed_noi')) {
			$table = $schema->createTable('empleados_ent_fed_noi');
			
			$table->addColumn('id_ent_fed_noi', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('clave', 'string', [
				'length' => 2,
				'notnull' => true,
			]);
			$table->addColumn('nombre', 'string', [
				'length' => 20,
				'notnull' => true,
			]);
			
			$table->setPrimaryKey(['id_ent_fed_noi']);
		}

		return $schema;
	}

	/**
	 * Inserta los registros semilla una vez creadas las tablas
	 */
	#[Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		// --- 1. Insertar Datos de Configuración NOI ---
		$encryptedPassword = $this->crypto->encrypt('masterkey');

		$configEntries = [
			'Servidor_NOI'           => '192.168.2.105',
			'Bd_NOI'                 => 'C:\\Program Files (x86)\\Common Files\\Aspel\\Sistemas Aspel\\NOI11.00\\Datos\\Empresa01\\NOI11EMPRE01.FDB',
			'No_Empresa_NOI'         => '01',
			'Contrasena_Aspel'       => $encryptedPassword,
			'Pref_Tabla_Nominas'     => 'NWNOMINAS',
			'Pref_Tabla_Trabajadores' => 'TB',
			'No_Correo'				 => 'nomina.torreon@crowe.mx',
		];

		foreach ($configEntries as $nombre => $data) {
			if ($this->configExists($nombre)) {
				continue;
			}
			$query = $this->connection->getQueryBuilder();
			$query->insert('empleados_noi_conf')
				->values([
					'nombre' => $query->createNamedParameter($nombre),
					'data'   => $query->createNamedParameter($data),
				])
				->executeStatement();
		}

		// --- 2. Insertar Estados / Entidades Federativas ---
		$estados = [
			' 1' => 'Aguascalientes',
			' 2' => 'Baja Calif. Norte',
			' 3' => 'Baja Calif. Sur',
			' 4' => 'Campeche',
			' 5' => 'Coahuila',
			' 6' => 'Colima',
			' 7' => 'Chiapas',
			' 8' => 'Chihuahua',
			' 9' => 'Ciudad de México',
			'10' => 'Durango',
			'11' => 'Guanajuato',
			'12' => 'Guerrero',
			'13' => 'Hidalgo',
			'14' => 'Jalisco',
			'15' => 'Estado de México',
			'16' => 'Michoacán',
			'17' => 'Morelos',
			'18' => 'Nayarit',
			'19' => 'Nuevo León',
			'20' => 'Oaxaca',
			'21' => 'Puebla',
			'22' => 'Querétaro',
			'23' => 'Quintana Roo',
			'24' => 'San Luis Potosí',
			'25' => 'Sinaloa',
			'26' => 'Sonora',
			'27' => 'Tabasco',
			'28' => 'Tamaulipas',
			'29' => 'Tlaxcala',
			'30' => 'Veracruz',
			'31' => 'Yucatán',
			'32' => 'Zacatecas',
		];

		foreach ($estados as $clave => $nombre) {
			if ($this->estadoExists($clave)) {
				continue;
			}
			$query = $this->connection->getQueryBuilder();
			$query->insert('empleados_ent_fed_noi')
				->values([
					'clave'  => $query->createNamedParameter($clave),
					'nombre' => $query->createNamedParameter($nombre),
				])
				->executeStatement();
		}
	}

	private function configExists(string $nombre): bool {
		$qb = $this->connection->getQueryBuilder();
		$result = $qb->select('id_noi_conf')
			->from('empleados_noi_conf')
			->where($qb->expr()->eq('nombre', $qb->createNamedParameter($nombre)))
			->setMaxResults(1)
			->executeQuery();
		$exists = $result->fetchOne();
		$result->closeCursor();
		return $exists !== false;
	}

	private function estadoExists(string $clave): bool {
		$qb = $this->connection->getQueryBuilder();
		$result = $qb->select('id_ent_fed_noi')
			->from('empleados_ent_fed_noi')
			->where($qb->expr()->eq('clave', $qb->createNamedParameter($clave)))
			->setMaxResults(1)
			->executeQuery();
		$exists = $result->fetchOne();
		$result->closeCursor();
		return $exists !== false;
	}
}