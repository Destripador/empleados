<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class historialausenciasMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'historial_ausencias', historialausencias::class);
	}

	public function EnviarAusencia(
		int $id_tipo_ausencia,
		int $id_ausencias,
		string $fecha_de,
		string $fecha_hasta,
		int $prima_vacacional,
		string $notas,
		int $id_aniverario,
		float $dias_solicitados,
		float $dias_de_acumulado = 0.0,
		float $dias_de_periodo = 0.0,
		?string $solicitud_grupo = null,
		?string $idempotency_key = null
	): int {
		$insert = $this->db->getQueryBuilder();
		$insert->insert($this->getTableName())
			->values([
				'id_ausencias'      => $insert->createNamedParameter($id_ausencias),
				'id_aniversario'    => $insert->createNamedParameter($id_aniverario),
				'id_tipo_ausencia'  => $insert->createNamedParameter($id_tipo_ausencia),
				'fecha_de'          => $insert->createNamedParameter($fecha_de),
				'fecha_hasta'       => $insert->createNamedParameter($fecha_hasta),
				'prima_vacacional'  => $insert->createNamedParameter($prima_vacacional),
				'notas'             => $insert->createNamedParameter($notas),
					'dias_solicitados'  => $insert->createNamedParameter($dias_solicitados),
					'dias_de_acumulado' => $insert->createNamedParameter($dias_de_acumulado),
					'dias_de_periodo'   => $insert->createNamedParameter($dias_de_periodo),
					'solicitud_grupo'   => $insert->createNamedParameter($solicitud_grupo),
					'idempotency_key'   => $insert->createNamedParameter($idempotency_key),
					'timestamp'         => $insert->createNamedParameter((new \DateTime())->format('Y-m-d H:i:s')),
				]);

		$insert->executeStatement();
		return (int) $this->db->lastInsertId('historial_ausencias');
	}

	public function GetAusenciasEnRango(string $desde, string $hasta, int $id): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('h.*', 't.nombre AS tipo_nombre', 't.solicitar_prima_vacacional')
			->from($this->getTableName(), 'h')
			->innerJoin('h', 'tipo_ausencia', 't', $qb->expr()->eq('h.id_tipo_ausencia', 't.id_tipo_ausencia'))
			->where($qb->expr()->eq('h.id_ausencias', $qb->createNamedParameter($id)))
			->andWhere(
				$qb->expr()->andX(
					$qb->expr()->lte('h.fecha_de', $qb->createNamedParameter($hasta)),
					$qb->expr()->gte('h.fecha_hasta', $qb->createNamedParameter($desde))
				)
			);

		$result = $qb->executeQuery();
		$ausencias = $result->fetchAll();
		$result->closeCursor();

		return $ausencias;
	}

	/**
	 * Devuelve todas las solicitudes con la metadata necesaria para reconstruir
	 * el saldo. No filtra por fechas: al cambiar Ingreso también deben
	 * conservarse y auditarse solicitudes que queden fuera del calendario nuevo.
	 */
	public function getSolicitudesParaCalculo(int $idAusencias): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select(
				'h.*',
				't.solicitar_prima_vacacional',
				't.privado'
			)
			->from($this->getTableName(), 'h')
			->innerJoin(
				'h',
				'tipo_ausencia',
				't',
				$qb->expr()->eq('h.id_tipo_ausencia', 't.id_tipo_ausencia')
			)
			->where(
				$qb->expr()->eq(
					'h.id_ausencias',
					$qb->createNamedParameter($idAusencias, IQueryBuilder::PARAM_INT)
				)
			)
			->orderBy('h.fecha_de', 'ASC')
			->addOrderBy('h.timestamp', 'ASC')
			->addOrderBy('h.id_historial_ausencias', 'ASC');

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return $rows;
	}

	public function getByIdempotencyKey(int $idAusencias, string $idempotencyKey): ?array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq(
					'id_ausencias',
					$qb->createNamedParameter($idAusencias, IQueryBuilder::PARAM_INT)
				)
			)
			->andWhere(
				$qb->expr()->eq(
					'idempotency_key',
					$qb->createNamedParameter($idempotencyKey)
				)
			)
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		return $row !== false ? $row : null;
	}

	/**
	 * Las solicitudes nuevas ocupan una sola fila. Este método mantiene soporte
	 * para filas agrupadas que puedan existir durante una transición.
	 */
	public function GetGrupoById(int $id): array {
		$base = $this->GetById($id);
		if (empty($base) || empty($base[0]['solicitud_grupo'])) {
			return $base;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq(
					'solicitud_grupo',
					$qb->createNamedParameter($base[0]['solicitud_grupo'])
				)
			)
			->orderBy('id_historial_ausencias', 'ASC');

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return $rows;
	}

	public function actualizarAsignacion(
		int $id,
		float $diasDeAcumulado,
		float $diasDePeriodo
	): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('dias_de_acumulado', $qb->createNamedParameter(max(0.0, $diasDeAcumulado)))
			->set('dias_de_periodo', $qb->createNamedParameter(max(0.0, $diasDePeriodo)))
			->where(
				$qb->expr()->eq(
					'id_historial_ausencias',
					$qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)
				)
			);
		$qb->executeStatement();
	}

	public function GetAusenciasHistorialGerente(int $id): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('h.*', 't.nombre AS tipo_nombre')
			->from($this->getTableName(), 'h')
			->innerJoin('h', 'tipo_ausencia', 't', $qb->expr()->eq('h.id_tipo_ausencia', 't.id_tipo_ausencia'))
			->where($qb->expr()->eq('h.id_ausencias', $qb->createNamedParameter($id)))
			->andWhere($qb->expr()->lte('h.a_gerente', $qb->createNamedParameter(0)));

		$result = $qb->executeQuery();
		$ausencias = $result->fetchAll();
		$result->closeCursor();

		return $ausencias;
	}

	public function GetAusenciasHistorialSocio(int $id): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('h.*', 't.nombre AS tipo_nombre')
			->from($this->getTableName(), 'h')
			->innerJoin('h', 'tipo_ausencia', 't', $qb->expr()->eq('h.id_tipo_ausencia', 't.id_tipo_ausencia'))
			->where($qb->expr()->eq('h.id_ausencias', $qb->createNamedParameter($id)))
			->andWhere($qb->expr()->lte('h.a_socio', $qb->createNamedParameter(0)));

		$result = $qb->executeQuery();
		$ausencias = $result->fetchAll();
		$result->closeCursor();

		return $ausencias;
	}

	/**
	 * Obtiene el detalle completo de una ausencia por su id_historial_ausencias,
	 */
	public function GetDetalleById(int $id): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('h.*', 't.nombre AS tipo_nombre', 't.solicitar_prima_vacacional')
			->from($this->getTableName(), 'h')
			->innerJoin('h', 'tipo_ausencia', 't',
				$qb->expr()->eq('h.id_tipo_ausencia', 't.id_tipo_ausencia'))
			->where($qb->expr()->eq('h.id_historial_ausencias', $qb->createNamedParameter($id)));

		$result = $qb->executeQuery();
		$rows   = $result->fetchAll();
		$result->closeCursor();

		return $rows;
	}

	/**
	 * Cancela una ausencia marcando a_gerente y a_socio como 3.
	 */
	public function CancelarAusencia(int $id): void {
		$qb = $this->db->getQueryBuilder();

		$qb->update($this->getTableName())
			->set('a_gerente', $qb->createNamedParameter(3))
			->set('a_socio',   $qb->createNamedParameter(3))
			->where($qb->expr()->eq('id_historial_ausencias', $qb->createNamedParameter($id)));

		$qb->executeStatement();
	}

	public function GetById(int $id): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id_historial_ausencias', $qb->createNamedParameter($id)));
		$result = $qb->executeQuery();
		$row = $result->fetchAll();
		$result->closeCursor();
		return $row;
	}
	
	public function EditarAusencia(
		int $id,
		int $id_tipo_ausencia,
		string $fecha_de,
		string $fecha_hasta,
		int $prima_vacacional,
		string $notas,
		float $dias_solicitados,
		int $id_aniversario,
		float $dias_de_acumulado,
		float $dias_de_periodo
	): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('id_tipo_ausencia', $qb->createNamedParameter($id_tipo_ausencia))
			->set('fecha_de',         $qb->createNamedParameter($fecha_de))
			->set('fecha_hasta',      $qb->createNamedParameter($fecha_hasta))
			->set('prima_vacacional', $qb->createNamedParameter($prima_vacacional))
				->set('notas',            $qb->createNamedParameter($notas))
				->set('dias_solicitados', $qb->createNamedParameter($dias_solicitados))
				->set('id_aniversario',   $qb->createNamedParameter($id_aniversario, IQueryBuilder::PARAM_INT))
				->set('dias_de_acumulado', $qb->createNamedParameter(max(0.0, $dias_de_acumulado)))
				->set('dias_de_periodo',  $qb->createNamedParameter(max(0.0, $dias_de_periodo)))
				->where($qb->expr()->eq('id_historial_ausencias', $qb->createNamedParameter($id)));
			$qb->executeStatement();
	}

	/**
	 * Verifica si el empleado ya tiene una prima vacacional activa en el año
	 */
	public function PrimaVacacionalUsadaEsteAnio(int $id_ausencias, int $anio, int $exclude_id = 0): bool {
		$qb = $this->db->getQueryBuilder();
		$desde = sprintf('%04d-01-01', $anio);
		$hasta = sprintf('%04d-01-01', $anio + 1);

		$qb->select($qb->createFunction('COUNT(*)'))
				->from($this->getTableName())
				->where($qb->expr()->eq('id_ausencias', $qb->createNamedParameter($id_ausencias)))
				->andWhere($qb->expr()->gte('fecha_de', $qb->createNamedParameter($desde)))
				->andWhere($qb->expr()->lt('fecha_de', $qb->createNamedParameter($hasta)))
			->andWhere($qb->expr()->eq('prima_vacacional', $qb->createNamedParameter(1, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)))
			// Excluir canceladas (3) Y rechazadas (2) en ambos roles
			->andWhere(
				$qb->expr()->andX(
					$qb->expr()->neq('a_gerente', $qb->createNamedParameter(3, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)),
					$qb->expr()->neq('a_socio',   $qb->createNamedParameter(3, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)),
					$qb->expr()->neq('a_gerente', $qb->createNamedParameter(2, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)),
					$qb->expr()->neq('a_socio',   $qb->createNamedParameter(2, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT))
				)
			);

		if ($exclude_id > 0) {
			$qb->andWhere(
				$qb->expr()->neq('id_historial_ausencias', $qb->createNamedParameter($exclude_id, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT))
			);
		}

		$result = $qb->executeQuery();
		$count  = (int) $result->fetchOne();
		$result->closeCursor();

		return $count > 0;
	}
	
	public function GetAusenciasEnRangoConFecha(string $desde, string $hasta, int $id): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('h.*', 't.nombre AS tipo_ausencia')
			->from($this->getTableName(), 'h')
			->innerJoin('h', 'tipo_ausencia', 't', $qb->expr()->eq('h.id_tipo_ausencia', 't.id_tipo_ausencia'))
			->where($qb->expr()->eq('h.id_ausencias', $qb->createNamedParameter($id)))
			->andWhere(
				$qb->expr()->andX(
					$qb->expr()->lte('h.fecha_de', $qb->createNamedParameter($hasta)),
					$qb->expr()->gte('h.fecha_hasta', $qb->createNamedParameter($desde))
				)
			)
			->orderBy('h.timestamp', 'DESC');

		$result = $qb->executeQuery();
		$ausencias = $result->fetchAll();
		$result->closeCursor();

		return $ausencias;
	}

	/**
	 * Igual que GetHistorialReporteCompleto pero filtrando por id_aniversario
	 * en vez de por rango de fechas.
	 */
	public function GetHistorialPorAniversario(int $id_ausencias, int $numero_aniversario): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select(
				'h.*',
				't.nombre AS tipo_ausencia',
				't.solicitar_prima_vacacional',
				'e.Id_user AS nombre_empleado',
				'e.Id_empleados AS id_empleado'
			)
			->from($this->getTableName(), 'h')
			->innerJoin('h', 'tipo_ausencia', 't', $qb->expr()->eq('h.id_tipo_ausencia', 't.id_tipo_ausencia'))
			->innerJoin('h', 'ausencias', 'a', $qb->expr()->eq('h.id_ausencias', 'a.id_ausencias'))
			->innerJoin('a', 'empleados', 'e', $qb->expr()->eq('a.id_empleado', 'e.Id_empleados'))
			->where($qb->expr()->eq('h.id_ausencias', $qb->createNamedParameter($id_ausencias, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('h.id_aniversario', $qb->createNamedParameter($numero_aniversario, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)))
			->orderBy('h.timestamp', 'DESC');

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return $rows;
	}

	public function GetHistorialReporteCompleto(string $desde, string $hasta): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select(
				'h.*',
				't.nombre AS tipo_ausencia',
				't.solicitar_prima_vacacional',
				'e.Id_user AS nombre_empleado',
				'e.Id_empleados AS id_empleado',
				'e.Ingreso AS ingreso_empleado'
			)
			->from($this->getTableName(), 'h')
			->innerJoin('h', 'tipo_ausencia', 't', $qb->expr()->eq('h.id_tipo_ausencia', 't.id_tipo_ausencia'))
			->innerJoin('h', 'ausencias', 'a', $qb->expr()->eq('h.id_ausencias', 'a.id_ausencias'))
			->innerJoin('a', 'empleados', 'e', $qb->expr()->eq('a.id_empleado', 'e.Id_empleados'))
			->where(
				$qb->expr()->andX(
					$qb->expr()->lte('h.fecha_de', $qb->createNamedParameter($hasta)),
					$qb->expr()->gte('h.fecha_hasta', $qb->createNamedParameter($desde))
				)
			)
			->orderBy('h.timestamp', 'DESC');

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return $rows;
	}

	public function SetEstadoGerente(int $id, int $estado): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('a_gerente', $qb->createNamedParameter($estado, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('id_historial_ausencias', $qb->createNamedParameter($id)));
		$qb->executeStatement();
	}

	public function SetEstadoSocio(int $id, int $estado): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('a_socio', $qb->createNamedParameter($estado, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('id_historial_ausencias', $qb->createNamedParameter($id)));
		$qb->executeStatement();
	}

	public function SetEstadoCapitalHumano(int $id, int $estado): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('a_capital_humano', $qb->createNamedParameter($estado, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('id_historial_ausencias', $qb->createNamedParameter($id)));
		$qb->executeStatement();
	}

	/**
	 * Marca los 3 roles como rechazados de una sola vez (se usa cuando cualquiera rechaza).
	 */
	public function RechazarTodo(int $id): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('a_gerente', $qb->createNamedParameter(2, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT))
			->set('a_socio', $qb->createNamedParameter(2, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT))
			->set('a_capital_humano', $qb->createNamedParameter(2, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('id_historial_ausencias', $qb->createNamedParameter($id)));
		$qb->executeStatement();
	}

	/**
	 * Ausencias que capital humano todavía debe vigilar
	 */
	public function GetAusenciasHistorialCapitalHumano(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select(
				'h.*',
				't.nombre AS tipo_nombre',
				't.solicitar_prima_vacacional',
				'e.Id_user AS nombre_empleado',
				'e.Id_empleados AS id_empleado'
			)
			->from($this->getTableName(), 'h')
			->innerJoin('h', 'tipo_ausencia', 't', $qb->expr()->eq('h.id_tipo_ausencia', 't.id_tipo_ausencia'))
			->innerJoin('h', 'ausencias', 'a', $qb->expr()->eq('h.id_ausencias', 'a.id_ausencias'))
			->innerJoin('a', 'empleados', 'e', $qb->expr()->eq('a.id_empleado', 'e.Id_empleados'))
			->where(
				// no está 100% aprobada todavía
				$qb->expr()->orX(
					$qb->expr()->neq('h.a_gerente', $qb->createNamedParameter(1, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)),
					$qb->expr()->neq('h.a_socio', $qb->createNamedParameter(1, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)),
					$qb->expr()->neq('h.a_capital_humano', $qb->createNamedParameter(1, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT))
				)
			)
			// y tampoco está rechazada ni cancelada
			->andWhere($qb->expr()->neq('h.a_gerente', $qb->createNamedParameter(2, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->neq('h.a_gerente', $qb->createNamedParameter(3, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->neq('h.a_socio', $qb->createNamedParameter(2, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->neq('h.a_socio', $qb->createNamedParameter(3, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->neq('h.a_capital_humano', $qb->createNamedParameter(2, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)));

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return $rows;
	}
}
