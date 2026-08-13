<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class MantenimientoChecklistMapper extends QBMapper {
	private const TABLE = 'inv_mant_checks';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, self::TABLE, MantenimientoChecklist::class);
	}

	public function insertResponse(array $data): MantenimientoChecklist {
		$this->assertValidResult((string)($data['resultado'] ?? MantenimientoChecklist::RESULTADO_PENDING));
		$qb = $this->db->getQueryBuilder();
		$qb->insert(self::TABLE)->values([
			'id_mantenimiento' => $qb->createNamedParameter($data['id_mantenimiento'], IQueryBuilder::PARAM_INT),
			'clave' => $qb->createNamedParameter($data['clave']),
			'etiqueta' => $qb->createNamedParameter($data['etiqueta']),
			'orden' => $qb->createNamedParameter($data['orden'], IQueryBuilder::PARAM_INT),
			'resultado' => $qb->createNamedParameter($data['resultado'] ?? MantenimientoChecklist::RESULTADO_PENDING),
			'observacion' => $qb->createNamedParameter($data['observacion'] ?? null),
			'actualizado_por' => $qb->createNamedParameter($data['actualizado_por']),
			'fecha_actualizacion' => $qb->createNamedParameter($data['fecha_actualizacion'] ?? date('Y-m-d H:i:s')),
		])->executeStatement();
		return $this->findById((int)$this->db->lastInsertId(self::TABLE));
	}

	public function updateResponse(int $id, string $result, ?string $observation, string $updatedBy): MantenimientoChecklist {
		$this->assertValidResult($result);
		$qb = $this->db->getQueryBuilder();
		$qb->update(self::TABLE)
			->set('resultado', $qb->createNamedParameter($result))
			->set('observacion', $qb->createNamedParameter($observation))
			->set('actualizado_por', $qb->createNamedParameter($updatedBy))
			->set('fecha_actualizacion', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->executeStatement();
		return $this->findById($id);
	}

	public function upsert(array $data): MantenimientoChecklist {
		$id = $this->findIdByMaintenanceAndKey((int)$data['id_mantenimiento'], (string)$data['clave']);
		if ($id === null) {
			return $this->insertResponse($data);
		}
		return $this->updateResponse(
			$id,
			(string)($data['resultado'] ?? MantenimientoChecklist::RESULTADO_PENDING),
			$data['observacion'] ?? null,
			(string)$data['actualizado_por'],
		);
	}

	public function listByMaintenance(int $maintenanceId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from(self::TABLE)
			->where($qb->expr()->eq('id_mantenimiento', $qb->createNamedParameter($maintenanceId, IQueryBuilder::PARAM_INT)))
			->orderBy('orden', 'ASC')->addOrderBy('id', 'ASC');
		return $this->findEntities($qb);
	}

	public function deleteByMaintenance(int $maintenanceId): int {
		$qb = $this->db->getQueryBuilder();
		return $qb->delete(self::TABLE)
			->where($qb->expr()->eq('id_mantenimiento', $qb->createNamedParameter($maintenanceId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	/** The caller owns the transaction; only the specified maintenance is replaced. */
	public function replaceForMaintenance(int $maintenanceId, array $items): array {
		$this->deleteByMaintenance($maintenanceId);
		$inserted = [];
		foreach ($items as $item) {
			$item['id_mantenimiento'] = $maintenanceId;
			$inserted[] = $this->insertResponse($item);
		}
		return $inserted;
	}

	private function findById(int $id): MantenimientoChecklist {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from(self::TABLE)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1);
		return $this->findEntity($qb);
	}

	private function findIdByMaintenanceAndKey(int $maintenanceId, string $key): ?int {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('id')->from(self::TABLE)
			->where($qb->expr()->eq('id_mantenimiento', $qb->createNamedParameter($maintenanceId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('clave', $qb->createNamedParameter($key)))
			->setMaxResults(1)->executeQuery();
		$id = $result->fetchOne();
		$result->closeCursor();
		return $id === false ? null : (int)$id;
	}

	private function assertValidResult(string $result): void {
		if (!in_array($result, MantenimientoChecklist::RESULTADOS_VALIDOS, true)) {
			throw new \InvalidArgumentException('Resultado de checklist inválido.');
		}
	}
}
