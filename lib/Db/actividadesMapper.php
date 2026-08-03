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
		$activity = $this->findById($id);
		if (($activity[0]['clave_sistema'] ?? null) !== null) {
			throw new \RuntimeException('Las actividades internas del sistema no se pueden eliminar.');
		}
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where(
                $qb->expr()->eq('id_actividad', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
            );

        $qb->executeStatement();
    }

	public function ensureSystemActivity(string $key, string $name, string $details): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id_actividad')->from($this->getTableName())
			->where($qb->expr()->eq('clave_sistema', $qb->createNamedParameter($key)))
			->setMaxResults(1);
		$result = $qb->executeQuery();
		$id = $result->fetchOne();
		$result->closeCursor();

		if ($id !== false) {
			$update = $this->db->getQueryBuilder();
			$update->update($this->getTableName())
				->set('cargable', $update->createNamedParameter(0, IQueryBuilder::PARAM_INT))
				->where($update->expr()->eq('id_actividad', $update->createNamedParameter((int)$id, IQueryBuilder::PARAM_INT)))
				->executeStatement();
			return (int)$id;
		}

		$insert = $this->db->getQueryBuilder();
		$insert->insert($this->getTableName())->values([
			'nombre' => $insert->createNamedParameter($name),
			'detalles' => $insert->createNamedParameter($details),
			'tiempo_estimado' => $insert->createNamedParameter(0),
			'cargable' => $insert->createNamedParameter(0, IQueryBuilder::PARAM_INT),
			'clave_sistema' => $insert->createNamedParameter($key),
		])->executeStatement();

		return (int)$this->db->lastInsertId($this->getTableName());
	}

    public function updateActividad(
        ?int $id_actividad,
        string $nombre,
        ?string $detalles,
        float $tiempoestimado,
        bool $cargable
    ): void {
		$actual = $id_actividad !== null ? $this->findById($id_actividad) : [];
		if (($actual[0]['clave_sistema'] ?? null) !== null) {
			$cargable = false;
		}
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

    /**
     * Asegura que exista la actividad con id 99999, usada para reportes de tiempo generados automáticamente 
     * por ausencias. Si ya existe, no hace nada. Si no existe, la crea con cargable = 0.
     */
    public function ensureActividadAusencia(): void {
        $qb = $this->db->getQueryBuilder();
        $qb->select('id_actividad')
            ->from($this->getTableName())
            ->where(
                $qb->expr()->eq('id_actividad', $qb->createNamedParameter(99999, IQueryBuilder::PARAM_INT))
            );

        $result = $qb->executeQuery();
        $existe = $result->fetch();
        $result->closeCursor();

        if ($existe) {
            return;
        }

        $insert = $this->db->getQueryBuilder();
        $insert->insert($this->getTableName())
            ->values([
                'id_actividad'    => $insert->createNamedParameter(99999, IQueryBuilder::PARAM_INT),
                'nombre'          => $insert->createNamedParameter('Ausencia'),
                'detalles'        => $insert->createNamedParameter('Actividad para reportes generados por ausencias.'),
                'tiempo_estimado' => $insert->createNamedParameter(0),
                'cargable'        => $insert->createNamedParameter(0, IQueryBuilder::PARAM_INT),
            ]);

        $insert->executeStatement();
    }
}
