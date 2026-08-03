<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;

class SoporteHistorialMapper extends QBMapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'soporte_historial', SoporteHistorial::class);
	}

	public function findByEquipo(int $idEquipo): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('s.*')
			->selectAlias('r.id_reporte', 'id_reporte')
			->from($this->getTableName(), 's')
			->leftJoin('s', 'empleados_rep_tiempos', 'r', "r.origen = 'soporte_ti' AND r.origen_id = s.id_soporte")
			->where(
				$qb->expr()->eq('s.id_equipo', $qb->createNamedParameter($idEquipo, IQueryBuilder::PARAM_INT))
			)
			->orderBy('s.fecha', 'DESC')
			->addOrderBy('s.id_soporte', 'DESC');

		$result = $qb->executeQuery();
		$data = $result->fetchAll();
		$result->closeCursor();

		return $data;
	}

	public function findById(int $id): ?array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('id_soporte', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			)
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		return $row ?: null;
	}

	public function findRecentDuplicate(
		int $idEquipo,
		string $accion,
		string $detalles,
		string $usuarioSoporte,
		string $fecha,
		int $duracionMinutos,
		string $desde
	): ?array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id_equipo', $qb->createNamedParameter($idEquipo, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('accion', $qb->createNamedParameter($accion)))
			->andWhere($qb->expr()->eq('detalles', $qb->createNamedParameter($detalles)))
			->andWhere($qb->expr()->eq('usuario_soporte', $qb->createNamedParameter($usuarioSoporte)))
			->andWhere($qb->expr()->eq('fecha', $qb->createNamedParameter($fecha)))
			->andWhere($qb->expr()->eq('duracion_minutos', $qb->createNamedParameter($duracionMinutos, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->gte('created_at', $qb->createNamedParameter($desde)))
			->orderBy('id_soporte', 'DESC')
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		return $row ?: null;
	}

	public function create(array $data): int {
		$now = date('Y-m-d H:i:s');

		$qb = $this->db->getQueryBuilder();

		$qb->insert($this->getTableName())
			->values([
				'id_equipo' => $qb->createNamedParameter($data['id_equipo'], IQueryBuilder::PARAM_INT),
				'accion' => $qb->createNamedParameter($data['accion'] ?? null),
				'detalles' => $qb->createNamedParameter($data['detalles'] ?? null),
				'fecha' => $qb->createNamedParameter($data['fecha'] ?? $now),
				'usuario_actual' => $qb->createNamedParameter($data['usuario_actual'] ?? null),
				'usuario_soporte' => $qb->createNamedParameter($data['usuario_soporte'] ?? null),
				'duracion_minutos' => $qb->createNamedParameter($data['duracion_minutos'] ?? null, IQueryBuilder::PARAM_INT),
				'created_at' => $qb->createNamedParameter($now),
				'updated_at' => $qb->createNamedParameter($now),
			]);

		$qb->executeStatement();

		return (int) $this->db->lastInsertId($this->getTableName());
	}

	public function updateById(int $id, array $data): void {
		$qb = $this->db->getQueryBuilder();

		$qb->update($this->getTableName())
			->set('accion', $qb->createNamedParameter($data['accion'] ?? null))
			->set('detalles', $qb->createNamedParameter($data['detalles'] ?? null))
			->set('fecha', $qb->createNamedParameter($data['fecha'] ?? null))
			->set('usuario_actual', $qb->createNamedParameter($data['usuario_actual'] ?? null))
			->set('usuario_soporte', $qb->createNamedParameter($data['usuario_soporte'] ?? null))
			->set('duracion_minutos', $qb->createNamedParameter($data['duracion_minutos'] ?? null, IQueryBuilder::PARAM_INT))
			->set('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->where(
				$qb->expr()->eq('id_soporte', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);

		$qb->executeStatement();
	}

	public function findWithDurationWithoutReport(int $limit): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('s.*')
			->from($this->getTableName(), 's')
			->leftJoin('s', 'empleados_rep_tiempos', 'r', "r.origen = 'soporte_ti' AND r.origen_id = s.id_soporte")
			->where($qb->expr()->isNotNull('s.duracion_minutos'))
			->andWhere($qb->expr()->isNull('r.id_reporte'))
			->orderBy('s.id_soporte', 'ASC')
			->setMaxResults(max(1, $limit));
		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return $rows;
	}

	public function countWithoutDuration(): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('COUNT(*)'))->from($this->getTableName())
			->where($qb->expr()->isNull('duracion_minutos'));
		$result = $qb->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();
		return $count;
	}

	public function deleteById(int $id): void {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq('id_soporte', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);

		$qb->executeStatement();
	}
}
