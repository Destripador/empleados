<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class empleadosorganigramaposMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'emp_org_pos');
    }

    public function GetAll(): array {
        $qb = $this->db->getQueryBuilder();

        $qb->select('id_empleado', 'pos_x', 'pos_y')
            ->from($this->getTableName());

        $result = $qb->executeQuery();
        $rows = $result->fetchAll();
        $result->closeCursor();

        return $rows;
    }

    public function GuardarPosicion(int $idEmpleado, float $x, float $y): void {
        $qb = $this->db->getQueryBuilder();

        $qb->select('id_empleado')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id_empleado', $qb->createNamedParameter($idEmpleado, \PDO::PARAM_INT)));

        $result = $qb->executeQuery();
        $existe = $result->fetch() !== false;
        $result->closeCursor();

        if ($existe) {
            $update = $this->db->getQueryBuilder();
            $update->update($this->getTableName())
                ->set('pos_x', $update->createNamedParameter($x))
                ->set('pos_y', $update->createNamedParameter($y))
                ->where($update->expr()->eq('id_empleado', $update->createNamedParameter($idEmpleado, \PDO::PARAM_INT)));
            $update->executeStatement();
        } else {
            $insert = $this->db->getQueryBuilder();
            $insert->insert($this->getTableName())
                ->values([
                    'id_empleado' => $insert->createNamedParameter($idEmpleado, \PDO::PARAM_INT),
                    'pos_x' => $insert->createNamedParameter($x),
                    'pos_y' => $insert->createNamedParameter($y),
                ]);
            $insert->executeStatement();
        }
    }

    public function GuardarPosicionesMasivas(array $posiciones): void {
        foreach ($posiciones as $pos) {
            $this->GuardarPosicion((int) $pos['id_empleado'], (float) $pos['x'], (float) $pos['y']);
        }
    }
}