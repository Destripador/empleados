<?php

declare(strict_types=1);

namespace OCA\empleados\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class historialausenciasMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'historial_ausencias', historialausencias::class);
	}

	public function EnviarAusencia(int $id_tipo_ausencia, $id_ausencias, $fecha_de, $fecha_hasta, int $prima_vacacional, string $notas, $id_aniverario): int {
		$insert = $this->db->getQueryBuilder();
		$insert->insert($this->getTableName())
			->values([
				'id_ausencias' => $insert->createNamedParameter($id_ausencias),
				'id_aniversario' => $insert->createNamedParameter($id_aniverario),
				'id_tipo_ausencia' => $insert->createNamedParameter($id_tipo_ausencia),
				'fecha_de' => $insert->createNamedParameter($fecha_de),
				'fecha_hasta' => $insert->createNamedParameter($fecha_hasta),
				'prima_vacacional' => $insert->createNamedParameter($prima_vacacional),
				'notas' => $insert->createNamedParameter($notas),
				'timestamp' => $insert->createNamedParameter((new \DateTime())->format('Y-m-d H:i:s')), // ← agregar
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
		string $notas
	): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('id_tipo_ausencia', $qb->createNamedParameter($id_tipo_ausencia))
			->set('fecha_de',         $qb->createNamedParameter($fecha_de))
			->set('fecha_hasta',      $qb->createNamedParameter($fecha_hasta))
			->set('prima_vacacional', $qb->createNamedParameter($prima_vacacional))
			->set('notas',            $qb->createNamedParameter($notas))
			->where($qb->expr()->eq('id_historial_ausencias', $qb->createNamedParameter($id)));
		$qb->executeStatement();
	}
}