<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\QueryBuilder\IQueryBuilder;

class actividadesMapper extends QBMapper {

    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'empleados_actividades', actividades::class);
    }

    public function findById(int $id): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where(
                $qb->expr()->eq('id_actividad', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
            );

        $result = $qb->executeQuery();
        $data = $result->fetchAll();
        $result->closeCursor();

        return $data;
    }

    public function findAll(?int $limit = null, int $offset = 0): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->orderBy('id_actividad', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        $result = $qb->executeQuery();
        $data = $result->fetchAll();
        $result->closeCursor();

        return $data;
    }

    public function deleteById(int $id): void {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where(
                $qb->expr()->eq('id_actividad', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
            );

        $qb->executeStatement();
    }

    public function updateActividad(
        ?int $id_actividad,
        string $nombre,
        ?string $detalles,
        float $tiempoestimado,
        bool $cargable
    ): void {
        $query = $this->db->getQueryBuilder();
        $query->update($this->getTableName())
            ->set('nombre', $query->createNamedParameter($nombre))
            ->set('detalles', $query->createNamedParameter($detalles))
            ->set('tiempo_estimado', $query->createNamedParameter($tiempoestimado))
            ->set('cargable', $query->createNamedParameter((int)$cargable, IQueryBuilder::PARAM_INT))
            ->where(
                $query->expr()->eq('id_actividad', $query->createNamedParameter($id_actividad, IQueryBuilder::PARAM_INT))
            );

        $query->executeStatement();
    }
}