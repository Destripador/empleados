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

class capitalhumanoMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'CapitalHumano', capitalhumano::class);
	}

	public function GetCapitalHumano(): array {
		$qb = $this->db->getQueryBuilder();

		/*
		$qb->select('Id_user')
			->from($this->getTableName(), 'o')
			->innerJoin('o', 'empleados', 'e', $qb->expr()->eq('Id_empleados', 'Id_empleado'))
			->where($qb->expr()->eq('Estado', $qb->createNamedParameter(1)));
		*/

		$qb->select('Id_empleado')
			->from($this->getTableName());

		$result = $qb->execute();
		$users = $result->fetchAll();
		$result->closeCursor();
	
		return $users;
	}

public function UpdateCapitalHumano(array $capitalhumano): array {
    $timestamp = date('Y-m-d');
    $qb = $this->db->getQueryBuilder();
    $existingUsers = [];

    // Obtener todos los IDs de empleados existentes en la base de datos
    $qb->select('Id_empleado')
        ->from($this->getTableName());
    $result = $qb->execute();
    $existingUsersRaw = $result->fetchAll();
    $result->closeCursor();

    // Convertimos los resultados en un array simple de IDs
    foreach ($existingUsersRaw as $row) {
        if (isset($row['Id_empleado'])) {
            $existingUsers[] = $row['Id_empleado'];
        }
    }

    $insertedUsers = [];
    $receivedUserIds = [];

    // **Normalizar los datos recibidos**
    foreach ($capitalhumano as $user) {
        if (is_array($user) && isset($user['id'])) {
            $receivedUserIds[] = $user['id'];
        } elseif (is_string($user)) {
            $receivedUserIds[] = $user; // Si es un string, lo tomamos como ID directamente
        }
    }

    // **Eliminar los usuarios que ya no están en la lista recibida**
    $usersToDelete = array_diff($existingUsers, $receivedUserIds);

    if (!empty($usersToDelete)) {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where($qb->expr()->in('Id_empleado', array_map([$qb, 'createNamedParameter'], $usersToDelete)));
        $qb->execute();
    }

    // **Insertar nuevos usuarios si no existen**
    foreach ($receivedUserIds as $userId) {
        if (!in_array($userId, $existingUsers)) {
            $qb = $this->db->getQueryBuilder();
            $qb->insert($this->getTableName())
                ->values([
                    'Id_empleado' => $qb->createNamedParameter($userId),
                    'created_at' => $qb->createNamedParameter($timestamp),
                    'updated_at' => $qb->createNamedParameter($timestamp)
                ]);
            $qb->execute();
            $insertedUsers[] = $userId;
        }
    }

    return [
        'inserted' => $insertedUsers,
        'deleted' => array_values($usersToDelete)
    ];
}


/*
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
			->where($qb->expr()->eq('Nombre', $qb->createNamedParameter("automatic_save_note")));
			
		$result = $qb->execute();
		$config = $result->fetchAll();
		$result->closeCursor();
	
		return $config;
	}
*/
}