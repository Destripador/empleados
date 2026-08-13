<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class MantenimientoCambioMapper extends QBMapper {
	private const TABLE = 'inv_mant_cambios';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, self::TABLE, MantenimientoCambio::class);
	}

	public function recordChange(array $data): MantenimientoCambio {
		$type = (string)$data['tipo_cambio'];
		if (!in_array($type, MantenimientoCambio::TIPOS_VALIDOS, true)) {
			throw new \InvalidArgumentException('Tipo de cambio de mantenimiento inválido.');
		}
		$qb = $this->db->getQueryBuilder();
		$qb->insert(self::TABLE)->values([
			'id_grupo' => $qb->createNamedParameter($data['id_grupo'] ?? null),
			'id_mantenimiento' => $qb->createNamedParameter($data['id_mantenimiento'] ?? null),
			'tipo_cambio' => $qb->createNamedParameter($type),
			'valor_anterior' => $qb->createNamedParameter($data['valor_anterior'] ?? null),
			'valor_nuevo' => $qb->createNamedParameter($data['valor_nuevo'] ?? null),
			'comentario' => $qb->createNamedParameter($data['comentario'] ?? null),
			'usuario_uid' => $qb->createNamedParameter($data['usuario_uid']),
			'usuario_nombre' => $qb->createNamedParameter($data['usuario_nombre']),
			'fecha' => $qb->createNamedParameter($data['fecha'] ?? date('Y-m-d H:i:s')),
		])->executeStatement();

		return $this->findById((int)$this->db->lastInsertId(self::TABLE));
	}

	public function findByMaintenance(int $maintenanceId, int $limit = 100, int $offset = 0): array {
		return $this->findChronologically('id_mantenimiento', $maintenanceId, $limit, $offset);
	}

	public function findByGroup(int $groupId, int $limit = 100, int $offset = 0): array {
		return $this->findChronologically('id_grupo', $groupId, $limit, $offset);
	}

	private function findById(int $id): MantenimientoCambio {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from(self::TABLE)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1);
		return $this->findEntity($qb);
	}

	private function findChronologically(string $field, int $id, int $limit, int $offset): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from(self::TABLE)
			->where($qb->expr()->eq($field, $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->orderBy('fecha', 'ASC')->addOrderBy('id', 'ASC')
			->setMaxResults(max(1, min(500, $limit)))->setFirstResult(max(0, $offset));
		return $this->findEntities($qb);
	}
}
