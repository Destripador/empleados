<?php
declare(strict_types=1);

namespace OCA\Empleados\Service;

use OCP\IDBConnection;
use OCP\DB\QueryBuilder\IQueryBuilder;

class ConfigRepairer {
    public function __construct(private IDBConnection $db) {}

    public function run(): void {
        $keys = [
            'usuario_almacenamiento',
            'automatic_save_note',
            'acumular_vacaciones',
            'modulo_ahorro',
            'modulo_ausencias',
            'ausencias_readonly',
        ];

        $table = 'empleados_conf';

        $this->db->beginTransaction();
        try {
            foreach ($keys as $k) {
                $qb = $this->db->getQueryBuilder();
                $qb->select('*')
                   ->from($table)
                   ->where($qb->expr()->eq('Nombre', $qb->createNamedParameter($k)))
                   ->setMaxResults(1);

                $exists = $qb->executeQuery()->fetchOne();

                if ($exists === false || $exists === null) {
                    $ib = $this->db->getQueryBuilder();
                    $ib->insert($table)
                       ->values([
                           'Nombre' => $ib->createNamedParameter($k),
                           'Data'   => $ib->createNamedParameter(null, IQueryBuilder::PARAM_NULL),
                       ])
                       ->executeStatement();
                }
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
