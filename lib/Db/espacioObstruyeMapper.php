<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\QueryBuilder\IQueryBuilder;

class espacioObstruyeMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
		parent::__construct($db, 'espacio_obstruye', espacioObstruye::class);
	}

    /* Obtener los espacios que obstruyen al espacio enviado*/
    public function findIdsObstruyen(int $id_espacio): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('id_obstruye')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id_espacio', $qb->createNamedParameter($id_espacio, IQueryBuilder::PARAM_INT)));

        $result = $qb->executeQuery();
        $ids = $result->fetchAll(\PDO::FETCH_COLUMN);
        $result->closeCursor();
        return $ids;
    }

    /* Borra todas las asignaciones de espacios que obstruyen del espacio enviado para reinsertar */
    public function deleteByEspacio(int $id_espacio): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('id_espacio', $qb->createNamedParameter($id_espacio)));
		$qb->executeStatement();
	}
}