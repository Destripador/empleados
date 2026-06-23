<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;

class clientesMapper extends QBMapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'empleados_clientes', clientes::class);
	}

	public function findById(int $id): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq(
					'id',
					$qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)
				)
			);

		$result = $qb->executeQuery();
		$data = $result->fetch();
		$result->closeCursor();

		if (!$data) {
			return [];
		}

		$data['colaboradores'] = json_decode($data['colaboradores'] ?? '[]', true) ?: [];

		return $data;
	}

	public function findAll(?int $limit = null, int $offset = 0): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select(
				'p.id',
				'p.nombre',
				'p.detalles',
				'p.lider_proyecto',
				'p.colaboradores',
				'p.razon_social',
				'p.nombre_contacto',
				'p.telefono',
				'p.correo',
				'p.ubicacion',
				'p.especial',
				'p.cliente_padre',
				'p.estado',
				$qb->createFunction('COUNT(c.id) AS child_count')
			)
			->from($this->getTableName(), 'p')
			->leftJoin(
				'p',
				$this->getTableName(),
				'c',
				$qb->expr()->eq('c.cliente_padre', 'p.id')
			)
			->groupBy(
				'p.id',
				'p.nombre',
				'p.detalles',
				'p.lider_proyecto',
				'p.colaboradores',
				'p.razon_social',
				'p.nombre_contacto',
				'p.telefono',
				'p.correo',
				'p.ubicacion',
				'p.especial',
				'p.cliente_padre',
				'p.estado'
			)
			->orderBy('p.id', 'ASC')
			->setMaxResults($limit)
			->setFirstResult($offset);

		$result = $qb->executeQuery();
		$data = $result->fetchAll();
		$result->closeCursor();

		foreach ($data as &$row) {
			$row['colaboradores'] = json_decode($row['colaboradores'] ?? '[]', true) ?: [];
		}

		return $data;
	}

	public function deleteById(int $id): void {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq(
					'id',
					$qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)
				)
			);

		$qb->executeStatement();
	}

	public function updateClientes(
		int $id,
		string $nombre,
		?string $detalles,
		?int $lider_proyecto,
		?array $colaboradores,
		?string $razon_social,
		?string $nombre_contacto,
		?string $telefono,
		?string $correo,
		?string $ubicacion,
		?bool $especial,
		?int $cliente_padre,
		?bool $estado
	): void {
		$query = $this->db->getQueryBuilder();

		$query->update($this->getTableName())
			->set('nombre', $query->createNamedParameter($nombre))
			->set('detalles', $query->createNamedParameter($detalles))
			->set('lider_proyecto', $query->createNamedParameter($lider_proyecto))
			->set('colaboradores', $query->createNamedParameter(
				json_encode($colaboradores ?? [])
			))
			->set('razon_social', $query->createNamedParameter($razon_social))
			->set('nombre_contacto', $query->createNamedParameter($nombre_contacto))
			->set('telefono', $query->createNamedParameter($telefono))
			->set('correo', $query->createNamedParameter($correo))
			->set('ubicacion', $query->createNamedParameter($ubicacion))
			->set('especial', $query->createNamedParameter((int)($especial ?? false), IQueryBuilder::PARAM_INT))
			->set('cliente_padre', $query->createNamedParameter($cliente_padre))
			->set('estado', $query->createNamedParameter((int)($estado ?? true), IQueryBuilder::PARAM_INT))
			->where(
				$query->expr()->eq(
					'id',
					$query->createNamedParameter($id, IQueryBuilder::PARAM_INT)
				)
			);

		$query->executeStatement();
	}
}