<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use DateTime;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

use OCP\AppFramework\Db\DoesNotExistException;

class configuracionesMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'empleados_conf', configuraciones::class);
	}

	public function GetConfig(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName());
			
		$result = $qb->execute();
		$config = $result->fetchAll();
		$result->closeCursor();
	
		return $config;
	}

	public function ActualizarGestor($id_gestor): void {
		$query = $this->db->getQueryBuilder();
			$query->update($this->getTableName())
				->set('Data', $query->createNamedParameter($id_gestor))
				->where($query->expr()->eq('Id_conf', $query->createNamedParameter("1")));
	
			$query->execute();
	}

	public function ActualizarConfiguracion($id_configuracion, $data): void {
		$query = $this->db->getQueryBuilder();
			$query->update($this->getTableName())
				->set('Data', $query->createNamedParameter($data))
				->where($query->expr()->eq('Nombre', $query->createNamedParameter($id_configuracion)));
	
			$query->execute();
	}

	public function GetNotasGuardado(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('Data')
			->from($this->getTableName())
			->where($qb->expr()->eq('automatic_save_note'));
			
		$result = $qb->execute();
		$config = $result->fetchAll();
		$result->closeCursor();
	
		return $config;
	}

	public function GetGestor(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('Data')
			->from($this->getTableName())
			->where($qb->expr()->eq('Id_conf', $qb->createNamedParameter("1")));
			
		$result = $qb->execute();
		$config = $result->fetchAll();
		$result->closeCursor();
	
		return $config;
	}
}