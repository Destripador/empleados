<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class configuracionesMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'empleados_conf', configuraciones::class);
	}

	public function GetConfig(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName());

		$result = $qb->executeQuery();
		$config = $result->fetchAll();
		$result->closeCursor();

		return $config;
	}

	public function ActualizarGestor($id_gestor): void {
		$query = $this->db->getQueryBuilder();
		$query->update($this->getTableName())
			->set('Data', $query->createNamedParameter($id_gestor))
			->where($query->expr()->eq('Nombre', $query->createNamedParameter('usuario_almacenamiento')));

		$query->executeStatement();
	}

	public function ActualizarConfiguracion($id_configuracion, $data): void {
		$query = $this->db->getQueryBuilder();
		$query->update($this->getTableName())
			->set('Data', $query->createNamedParameter($data))
			->where($query->expr()->eq('Nombre', $query->createNamedParameter($id_configuracion)));

		$query->executeStatement();
	}

	public function GetNotasGuardado(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('Data')
			->from($this->getTableName())
			->where($qb->expr()->eq('Nombre', $qb->createNamedParameter('automatic_save_note')));

		$result = $qb->executeQuery();
		$config = $result->fetchAll();
		$result->closeCursor();

		return $config;
	}

	public function GetGestor(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('Data')
			->from($this->getTableName())
			->where($qb->expr()->eq('Nombre', $qb->createNamedParameter('usuario_almacenamiento')));

		$result = $qb->executeQuery();
		$config = $result->fetchAll();
		$result->closeCursor();

		return $config;
	}

	/**
	 * Inserta keys en empleados_conf si faltan (idempotente).
	 *
	 * @param array<string,string> $defaults Nombre => Data
	 */
	public function seedDefaults(array $defaults): void {
		$table = $this->getTableName();

		foreach ($defaults as $name => $value) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('Nombre')
				->from($table)
				->where($qb->expr()->eq('Nombre', $qb->createNamedParameter($name)))
				->setMaxResults(1);

			$exists = (bool) $qb->executeQuery()->fetchOne();
			if ($exists) {
				continue;
			}

			$ins = $this->db->getQueryBuilder();
			$ins->insert($table)->values([
				'Nombre' => $ins->createNamedParameter($name),
				'Data'   => $ins->createNamedParameter($value),
			]);

			$ins->executeStatement();
		}
	}
}