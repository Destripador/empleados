<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class CompraDetalleMapper extends QBMapper {

	private const TABLE = 'emp_comp_detalles';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, self::TABLE, CompraDetalle::class);
	}

	public function find(int $id): CompraDetalle {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from(self::TABLE)
			->where($qb->expr()->eq(
				'id_detalle',
				$qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)
			))
			->setMaxResults(1);

		return $this->findEntity($qb);
	}

	public function findBySolicitud(int $idSolicitud): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from(self::TABLE)
			->where($qb->expr()->eq(
				'id_solicitud',
				$qb->createNamedParameter($idSolicitud, IQueryBuilder::PARAM_INT)
			))
			->orderBy('id_detalle', 'ASC');

		return $this->findEntities($qb);
	}

	public function insertDetalle(array $data): CompraDetalle {
		$qb = $this->db->getQueryBuilder();

		$fields = [
			'id_solicitud',
			'descripcion',
			'cantidad',
			'unidad',
			'precio_estimado',
			'subtotal',
			'notas',
		];

		$values = [];

		foreach ($fields as $field) {
			if (array_key_exists($field, $data)) {
				$values[$field] = $qb->createNamedParameter($data[$field]);
			}
		}

		$qb->insert(self::TABLE)->values($values);
		$this->executeStatement($qb);

		$id = (int)$this->db->lastInsertId();

		return $this->find($id);
	}

	public function deleteBySolicitud(int $idSolicitud): int {
		$qb = $this->db->getQueryBuilder();

		$qb->delete(self::TABLE)
			->where($qb->expr()->eq(
				'id_solicitud',
				$qb->createNamedParameter($idSolicitud, IQueryBuilder::PARAM_INT)
			));

		return $this->executeStatement($qb);
	}

	public function replaceBySolicitud(int $idSolicitud, array $detalles): array {
		$this->deleteBySolicitud($idSolicitud);

		$insertados = [];

		foreach ($detalles as $detalle) {
			$detalle['id_solicitud'] = $idSolicitud;
			$insertados[] = $this->insertDetalle($detalle);
		}

		return $insertados;
	}

	private function executeStatement(IQueryBuilder $qb): int {
		if (method_exists($qb, 'executeStatement')) {
			return $qb->executeStatement();
		}

		return $qb->execute();
	}
}
