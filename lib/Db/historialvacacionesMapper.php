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

	public function getAllForAiContext(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())->orderBy('id_empleado', 'ASC')->addOrderBy('numero_aniversario', 'DESC');
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

	public function guardarConAcumulado(
		int $id_empleado,
		int $numero_aniversario,
		string $periodo_inicio,
		string $periodo_fin,
		float $dias_derecho,
		float $dias_acumulados,
		?string $fecha_expiracion_acumulados
	): void {
		$insert = $this->db->getQueryBuilder();
		$insert->insert($this->getTableName())
			->values([
				'id_empleado'                => $insert->createNamedParameter($id_empleado),
				'numero_aniversario'         => $insert->createNamedParameter($numero_aniversario),
				'periodo_inicio'             => $insert->createNamedParameter($periodo_inicio),
				'periodo_fin'                => $insert->createNamedParameter($periodo_fin),
				'dias_derecho'               => $insert->createNamedParameter($dias_derecho),
				'dias_acumulados'            => $insert->createNamedParameter($dias_acumulados),
				'dias_acumulados_restantes'  => $insert->createNamedParameter($dias_acumulados),
				'fecha_expiracion_acumulados' => $insert->createNamedParameter($fecha_expiracion_acumulados),
				'acumulado_calculado'        => $insert->createNamedParameter(1, IQueryBuilder::PARAM_INT),
			]);
		$insert->executeStatement();
	}

	/**
	 * Marca acumulado_calculado = 1 para que no se vuelva a recalcular después.
	 */
	public function actualizarAcumulado(
		int $id_empleado,
		int $numero_aniversario,
		float $dias_acumulados,
		?string $fecha_expiracion_acumulados
	): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('dias_acumulados', $qb->createNamedParameter($dias_acumulados))
			->set('dias_acumulados_restantes', $qb->createNamedParameter($dias_acumulados))
			->set('fecha_expiracion_acumulados', $qb->createNamedParameter($fecha_expiracion_acumulados))
			->set('acumulado_calculado', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('id_empleado', $qb->createNamedParameter($id_empleado)))
			->andWhere($qb->expr()->eq('numero_aniversario', $qb->createNamedParameter($numero_aniversario)));
		$qb->executeStatement();
	}

	public function descontarAcumulado(int $id_empleado, int $numero_aniversario, float $nuevoRestante): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('dias_acumulados_restantes', $qb->createNamedParameter($nuevoRestante))
			->where($qb->expr()->eq('id_empleado', $qb->createNamedParameter($id_empleado)))
			->andWhere($qb->expr()->eq('numero_aniversario', $qb->createNamedParameter($numero_aniversario)));
		$qb->executeStatement();
	}

	/**
	 * ¿Este empleado ya tiene un aniversario 0 registrado?
	 */
	public function tieneAniversarioCero(int $idEmpleado): bool {
		$qb = $this->db->getQueryBuilder();

		$qb->select($qb->createFunction('COUNT(*)'))
			->from($this->getTableName())
			->where($qb->expr()->eq('id_empleado', $qb->createNamedParameter($idEmpleado, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('numero_aniversario', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)));

		$result = $qb->executeQuery();
		$count = (int) $result->fetchOne();
		$result->closeCursor();

		return $count > 0;
	}

	/**
	 * ¿RH ya hizo AL MENOS UNA asignación manual para este empleado?
	 */
	public function tieneAsignacionManual(int $idEmpleado): bool {
		$qb = $this->db->getQueryBuilder();

		$qb->select($qb->createFunction('COUNT(*)'))
			->from($this->getTableName())
			->where($qb->expr()->eq('id_empleado', $qb->createNamedParameter($idEmpleado, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('asignado_manualmente', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)));

		$result = $qb->executeQuery();
		$count = (int) $result->fetchOne();
		$result->closeCursor();

		return $count > 0;
	}

	/**
	 * Permite a RH sobrescribir manualmente el dias_derecho de un periodo puntual
	 */
	public function actualizarDerecho(int $idEmpleado, int $numeroAniversario, float $diasDerecho): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('dias_derecho', $qb->createNamedParameter($diasDerecho))
			->set('asignado_manualmente', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT))
			->set('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->where($qb->expr()->eq('id_empleado', $qb->createNamedParameter($idEmpleado, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('numero_aniversario', $qb->createNamedParameter($numeroAniversario, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	public function resetearPendiente(int $idEmpleado, int $numeroAniversario): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('dias_derecho', $qb->createNamedParameter(0))
			->set('dias_acumulados', $qb->createNamedParameter(0))
			->set('dias_acumulados_restantes', $qb->createNamedParameter(0))
			->set('fecha_expiracion_acumulados', $qb->createNamedParameter(null))
			->set('acumulado_calculado', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT))
			->set('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->where($qb->expr()->eq('id_empleado', $qb->createNamedParameter($idEmpleado, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('numero_aniversario', $qb->createNamedParameter($numeroAniversario, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	/**
	 * Actualiza dias_derecho + acumulado de un periodo AUTOMÁTICO
	 */
	public function actualizarDerechoAutomatico(
		int $id_empleado,
		int $numero_aniversario,
		float $dias_derecho,
		float $dias_acumulados,
		?string $fecha_expiracion_acumulados
	): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('dias_derecho', $qb->createNamedParameter($dias_derecho))
			->set('dias_acumulados', $qb->createNamedParameter($dias_acumulados))
			->set('dias_acumulados_restantes', $qb->createNamedParameter($dias_acumulados))
			->set('fecha_expiracion_acumulados', $qb->createNamedParameter($fecha_expiracion_acumulados))
			->set('acumulado_calculado', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT))
			->set('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->where($qb->expr()->eq('id_empleado', $qb->createNamedParameter($id_empleado, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('numero_aniversario', $qb->createNamedParameter($numero_aniversario, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	public function invalidarAcumulado(int $idEmpleado, int $numeroAniversario): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('acumulado_calculado', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('id_empleado', $qb->createNamedParameter($idEmpleado, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('numero_aniversario', $qb->createNamedParameter($numeroAniversario, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}
}
