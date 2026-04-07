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
}