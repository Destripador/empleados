<?php

declare(strict_types=1);

namespace OCA\empleados\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class historialausenciasMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'historial_ausencias', historialausencias::class);
	}

	public function EnviarAusencia(
		int $id_tipo_ausencia,
		$id_ausencias,
		$fecha_de,
		$fecha_hasta,
		int $prima_vacacional,
		string $notas,
		$id_aniverario,
		int $dias_solicitados
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
				'dias_solicitados'  => $insert->createNamedParameter($dias_solicitados), // ← NUEVO
				'timestamp'         => $insert->createNamedParameter((new \DateTime())->format('Y-m-d H:i:s')),
			]);

		$insert->executeStatement();
		return (int) $this->db->lastInsertId('historial_ausencias');
	}

	public function GetAusenciasEnRango(string $desde, string $hasta, int $id): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('h.*', 't.nombre AS tipo_nombre')
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
		int $dias_solicitados 
	): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('id_tipo_ausencia', $qb->createNamedParameter($id_tipo_ausencia))
			->set('fecha_de',         $qb->createNamedParameter($fecha_de))
			->set('fecha_hasta',      $qb->createNamedParameter($fecha_hasta))
			->set('prima_vacacional', $qb->createNamedParameter($prima_vacacional))
			->set('notas',            $qb->createNamedParameter($notas))
			->set('dias_solicitados', $qb->createNamedParameter($dias_solicitados))
			->where($qb->expr()->eq('id_historial_ausencias', $qb->createNamedParameter($id)));
		$qb->executeStatement();
	}

	/**
	 * Verifica si el empleado ya tiene una prima vacacional activa en el año actual.
	 * Se ignora el registro con $exclude_id (útil al editar).
	 */
	public function PrimaVacacionalUsadaEsteAnio(int $id_ausencias, int $exclude_id = 0): bool {
		$qb = $this->db->getQueryBuilder();

		$anioActual = (new \DateTime())->format('Y');
		$inicio = $anioActual . '-01-01';
		$fin    = $anioActual . '-12-31';

		$qb->select($qb->createFunction('COUNT(*)'))
			->from($this->getTableName())
			->where($qb->expr()->eq('id_ausencias', $qb->createNamedParameter($id_ausencias)))
			->andWhere($qb->expr()->eq('prima_vacacional', $qb->createNamedParameter(1, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->gte('fecha_de', $qb->createNamedParameter($inicio)))
			->andWhere($qb->expr()->lte('fecha_de', $qb->createNamedParameter($fin)))
			// Excluir canceladas: está cancelada cuando a_gerente = 3 O a_socio = 3
			->andWhere(
				$qb->expr()->andX(
					$qb->expr()->neq('a_gerente', $qb->createNamedParameter(3, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)),
					$qb->expr()->neq('a_socio',   $qb->createNamedParameter(3, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT))
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

	public function GetHistorialReporteCompleto(string $desde, string $hasta): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('h.*', 't.nombre AS tipo_ausencia', 'e.Id_user AS nombre_empleado')
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
}