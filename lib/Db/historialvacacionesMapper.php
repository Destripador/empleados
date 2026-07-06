<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class historialvacacionesMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'historial_vacaciones', historialvacaciones::class);
	}

	public function getByEmpleadoYAniversario(int $idEmpleado, int $numeroAniversario): ?array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id_empleado', $qb->createNamedParameter($idEmpleado, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('numero_aniversario', $qb->createNamedParameter($numeroAniversario, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		return $row !== false ? $row : null;
	}

	public function getByEmpleado(int $idEmpleado): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id_empleado', $qb->createNamedParameter($idEmpleado, IQueryBuilder::PARAM_INT)))
			->orderBy('numero_aniversario', 'ASC');

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return $rows;
	}

	public function guardar(int $idEmpleado, int $numeroAniversario, string $periodoInicio, string $periodoFin, float $diasDerecho): void {
		$timestamp = date('Y-m-d H:i:s');
		$existente = $this->getByEmpleadoYAniversario($idEmpleado, $numeroAniversario);

		$qb = $this->db->getQueryBuilder();

		if ($existente) {
			// Ya existe un registro "congelado" para este aniversario: no se pisa el dias_derecho histórico.
			return;
		}

		$qb->insert($this->getTableName())
			->values([
				'id_empleado' => $qb->createNamedParameter($idEmpleado, IQueryBuilder::PARAM_INT),
				'numero_aniversario' => $qb->createNamedParameter($numeroAniversario, IQueryBuilder::PARAM_INT),
				'periodo_inicio' => $qb->createNamedParameter($periodoInicio),
				'periodo_fin' => $qb->createNamedParameter($periodoFin),
				'dias_derecho' => $qb->createNamedParameter($diasDerecho),
				'created_at' => $qb->createNamedParameter($timestamp),
				'updated_at' => $qb->createNamedParameter($timestamp),
			]);

		$qb->executeStatement();
	}

	/**
     * Corrige periodo_inicio/periodo_fin de un aniversario ya congelado
     */
    public function actualizarFechas(int $idEmpleado, int $numeroAniversario, string $periodoInicio, string $periodoFin): void {
        $qb = $this->db->getQueryBuilder();
        $qb->update($this->getTableName())
            ->set('periodo_inicio', $qb->createNamedParameter($periodoInicio))
            ->set('periodo_fin', $qb->createNamedParameter($periodoFin))
            ->set('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')))
            ->where($qb->expr()->eq('id_empleado', $qb->createNamedParameter($idEmpleado, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('numero_aniversario', $qb->createNamedParameter($numeroAniversario, IQueryBuilder::PARAM_INT)));
        $qb->executeStatement();
    }
}