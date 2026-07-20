<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class puestosMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'puestos', puestos::class);
    }

    public function GetPuestosList(): array {
        $qb = $this->db->getQueryBuilder();

        $qb->select('d.Id_puestos', 'd.Nombre', 'd.Nivel')
            ->selectAlias($qb->createFunction('COUNT(e.Id_empleados)'), 'cantidad_empleados')
            ->from($this->getTableName(), 'd')
            ->leftJoin('d', 'empleados', 'e', 'd.Id_puestos = e.Id_puesto')
            ->groupBy('d.Id_puestos');

        $result = $qb->executeQuery();
        $users = $result->fetchAll();
        $result->closeCursor();

        return $users;
    }

    public function CheckExistPuestos($id_departamentos): array {
        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('Id_puestos', $qb->createNamedParameter($id_departamentos)));

        $result = $qb->executeQuery();
        $users = $result->fetchAll();
        $result->closeCursor();

        return $users;
    }

    public function deleteByIdEmpleado(int $id_departamentos): void {
        $qb = $this->db->getQueryBuilder();

        $qb->delete($this->getTableName())
            ->where($qb->expr()->eq('Id_puestos', $qb->createNamedParameter($id_departamentos)));

        $qb->executeStatement();
    }

    public function updatePuestos(string $Id_puestos, string $Nombre, ?int $Nivel): void {
        $timestamp = date('Y-m-d');

        if (empty($Id_puestos) && $Id_puestos != 0) {
            $Id_puestos = null;
        }

        if (empty($Nombre) && $Nombre != 0) {
            $Nombre = null;
        }

        $query = $this->db->getQueryBuilder();
        $query->update($this->getTableName())
            ->set('Nombre', $query->createNamedParameter($Nombre))
            ->set('Nivel', $query->createNamedParameter($Nivel))
            ->set('updated_at', $query->createNamedParameter($timestamp))
            ->where($query->expr()->eq('Id_puestos', $query->createNamedParameter($Id_puestos)));

        $query->executeStatement();
    }

    public function EliminarPuesto(string $Id_puestos): void {
        $qb = $this->db->getQueryBuilder();

        $qb->delete($this->getTableName())
            ->where($qb->expr()->eq('Id_puestos', $qb->createNamedParameter($Id_puestos)));

        $qb->executeStatement();
    }
}