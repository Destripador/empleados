<?php
declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class movimientosMapper extends QBMapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'empleados_movimientos', movimientos::class);
	}

	public function registrar(
        string $modulo,
        ?int $idEmpleadoActor,
        ?string $nombreActor,
        ?int $idEmpleadoAfectado,
        ?string $nombreAfectado,
        string $tipoMovimiento,
        string $mensaje,
        ?int $idReferencia = null
    ): movimientos {
        $mov = new movimientos();
        $mov->setModulo($modulo);
        $mov->setFecha((new \DateTime())->format('Y-m-d H:i:s'));
        $mov->setIdEmpleadoActor($idEmpleadoActor);
        $mov->setNombreActor($nombreActor);
        $mov->setIdEmpleadoAfectado($idEmpleadoAfectado);
        $mov->setNombreAfectado($nombreAfectado);
        $mov->setTipoMovimiento($tipoMovimiento);
        $mov->setIdReferencia($idReferencia);
        $mov->setMensaje($mensaje);

        return $this->insert($mov);
    }

    /**
     * @param int|null $idEmpleado Filtra movimientos donde este empleado sea actor O afectado.
     */
    public function GetMovimientos(
        int $limit = 300,
        ?string $modulo = null,
        ?string $tipo = null,
        ?int $idEmpleado = null,
        ?string $desde = null,
        ?string $hasta = null
    ): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from('empleados_movimientos');

        if ($modulo) {
            $qb->andWhere($qb->expr()->eq('modulo', $qb->createNamedParameter($modulo)));
        }
        if ($tipo) {
            $qb->andWhere($qb->expr()->eq('tipo_movimiento', $qb->createNamedParameter($tipo)));
        }
        if ($idEmpleado) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->eq('id_empleado_actor', $qb->createNamedParameter($idEmpleado, IQueryBuilder::PARAM_INT)),
                $qb->expr()->eq('id_empleado_afectado', $qb->createNamedParameter($idEmpleado, IQueryBuilder::PARAM_INT))
            ));
        }
        if ($desde) {
            $qb->andWhere($qb->expr()->gte('fecha', $qb->createNamedParameter($desde . ' 00:00:00')));
        }
        if ($hasta) {
            $qb->andWhere($qb->expr()->lte('fecha', $qb->createNamedParameter($hasta . ' 23:59:59')));
        }

        $qb->orderBy('fecha', 'DESC')->setMaxResults($limit);

        return $qb->executeQuery()->fetchAll();
    }

    public function GetMovimientosDeEmpleado(int $idEmpleado, int $limit = 300, ?string $modulo = null): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('empleados_movimientos')
            ->where($qb->expr()->orX(
                $qb->expr()->eq('id_empleado_actor', $qb->createNamedParameter($idEmpleado, IQueryBuilder::PARAM_INT)),
                $qb->expr()->eq('id_empleado_afectado', $qb->createNamedParameter($idEmpleado, IQueryBuilder::PARAM_INT))
            ));

        if ($modulo) {
            $qb->andWhere($qb->expr()->eq('modulo', $qb->createNamedParameter($modulo)));
        }

        $qb->orderBy('fecha', 'DESC')->setMaxResults($limit);

        return $qb->executeQuery()->fetchAll();
    }

    /**
     * Lista de empleados (id + nombre) que han aparecido en la bitácora,
     * como actor o como afectado. Sirve para poblar el select de "Empleado"
     * en el filtro, sin tener que depender de empleadosMapper.
     */
    public function GetEmpleadosConMovimientos(): array {
        $qbActor = $this->db->getQueryBuilder();
        $qbActor->select('id_empleado_actor', 'nombre_actor')
            ->from('empleados_movimientos')
            ->where($qbActor->expr()->isNotNull('id_empleado_actor'));
        $actores = $qbActor->executeQuery()->fetchAll();

        $qbAfectado = $this->db->getQueryBuilder();
        $qbAfectado->select('id_empleado_afectado', 'nombre_afectado')
            ->from('empleados_movimientos')
            ->where($qbAfectado->expr()->isNotNull('id_empleado_afectado'));
        $afectados = $qbAfectado->executeQuery()->fetchAll();

        $unicos = [];

        foreach ($actores as $row) {
            $id = (int) ($row['id_empleado_actor'] ?? 0);
            $nombre = $row['nombre_actor'] ?? null;
            if ($id > 0 && !empty($nombre)) {
                $unicos[$id] = ['id_empleado' => $id, 'nombre' => $nombre];
            }
        }

        foreach ($afectados as $row) {
            $id = (int) ($row['id_empleado_afectado'] ?? 0);
            $nombre = $row['nombre_afectado'] ?? null;
            if ($id > 0 && !empty($nombre) && !isset($unicos[$id])) {
                $unicos[$id] = ['id_empleado' => $id, 'nombre' => $nombre];
            }
        }

        $resultado = array_values($unicos);
        usort($resultado, fn($a, $b) => strcasecmp($a['nombre'], $b['nombre']));

        return $resultado;
    }
}