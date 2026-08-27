<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class empleadosorganigramaMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'emp_organigrama', empleadosorganigrama::class);
    }

    /**
     * Obtiene todas las relaciones jefe -> dependiente,
     * con datos de usuario/avatar de ambos lados.
     */
    public function GetOrganigrama(): array {
        $qb = $this->db->getQueryBuilder();

        $qb->select(
            'o.id',
            'o.id_empleado',
            'o.id_dependiente',
            'jefe.Id_user AS jefe_user',
            'dep.Id_user AS dependiente_user'
        )
            ->from($this->getTableName(), 'o')
            ->innerJoin('o', 'empleados', 'jefe', 'o.id_empleado = jefe.Id_empleados')
            ->innerJoin('o', 'empleados', 'dep', 'o.id_dependiente = dep.Id_empleados');

        $result = $qb->executeQuery();
        $rows = $result->fetchAll();
        $result->closeCursor();

        return $rows;
    }

	/**
	 * Devuelve las relaciones jerarquicas sin joins para que los servicios de
	 * dominio puedan detectar ciclos y omitir referencias invalidas por lote.
	 *
	 * @return array<int,array{id_empleado:int,id_dependiente:int}>
	 */
	public function getRelationIds(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id_empleado', 'id_dependiente')
			->from($this->getTableName());

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return array_map(static fn (array $row): array => [
			'id_empleado' => (int)($row['id_empleado'] ?? 0),
			'id_dependiente' => (int)($row['id_dependiente'] ?? 0),
		], $rows);
	}

    public function ExisteRelacion(int $idEmpleado, int $idDependiente): bool {
        $qb = $this->db->getQueryBuilder();

        $qb->select('id')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id_empleado', $qb->createNamedParameter($idEmpleado, \PDO::PARAM_INT)))
            ->andWhere($qb->expr()->eq('id_dependiente', $qb->createNamedParameter($idDependiente, \PDO::PARAM_INT)));

        $result = $qb->executeQuery();
        $row = $result->fetch();
        $result->closeCursor();

        return $row !== false;
    }

    public function CrearRelacion(int $idEmpleado, int $idDependiente): empleadosorganigrama {
        $relacion = new empleadosorganigrama();
        $relacion->setid_empleado($idEmpleado);
        $relacion->setid_dependiente($idDependiente);
        $relacion->setcreated_at(date('Y-m-d H:i:s'));

        return $this->insert($relacion);
    }

    public function EliminarRelacion(int $idEmpleado, int $idDependiente): void {
        $qb = $this->db->getQueryBuilder();

        $qb->delete($this->getTableName())
            ->where($qb->expr()->eq('id_empleado', $qb->createNamedParameter($idEmpleado, \PDO::PARAM_INT)))
            ->andWhere($qb->expr()->eq('id_dependiente', $qb->createNamedParameter($idDependiente, \PDO::PARAM_INT)));

        $qb->executeStatement();
    }

    public function EliminarPorEmpleado(int $idEmpleado): void {
        $qb = $this->db->getQueryBuilder();

        $qb->delete($this->getTableName())
            ->where($qb->expr()->eq('id_empleado', $qb->createNamedParameter($idEmpleado, \PDO::PARAM_INT)))
            ->orWhere($qb->expr()->eq('id_dependiente', $qb->createNamedParameter($idEmpleado, \PDO::PARAM_INT)));

        $qb->executeStatement();
    }
}
