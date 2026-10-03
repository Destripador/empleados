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
				'p.rfc',
				'p.ubicacion',
				'p.especial',
				'p.cliente_padre',
				'p.estado',
				'p.logo',
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
				'p.rfc',
				'p.ubicacion',
				'p.especial',
				'p.cliente_padre',
				'p.estado',
				'p.logo'
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
		?string $rfc,
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
			->set('rfc', $query->createNamedParameter($rfc))
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

	public function updateLogo(int $id, ?string $logo): void {
		$qb = $this->db->getQueryBuilder();

		$qb->update($this->getTableName())
			->set('logo', $qb->createNamedParameter($logo))
			->where(
				$qb->expr()->eq(
					'id',
					$qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)
				)
			);

		$qb->executeStatement();
	}

	/**
	 * Catálogo ligero para el dashboard (sin child_count ni JSON de colaboradores).
	 *
	 * @return list<array<string,mixed>>
	 */
	public function findDashboardCatalog(array $filters): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select(
			'id',
			'nombre',
			'logo',
			'estado',
			'especial',
			'cliente_padre',
			'lider_proyecto'
		)
			->from($this->getTableName())
			->orderBy('nombre', 'ASC');

		$this->applyDashboardClientFilters($qb, $filters, '');

		$result = $qb->executeQuery();
		$data = $result->fetchAll();
		$result->closeCursor();

		return $data;
	}

	public function applyDashboardClientFilters($qb, array $filters, string $alias): void {
		$prefix = $alias !== '' ? $alias . '.' : '';

		if (!empty($filters['id_cliente'])) {
			$qb->andWhere($qb->expr()->eq(
				$prefix . 'id',
				$qb->createNamedParameter((int)$filters['id_cliente'], IQueryBuilder::PARAM_INT)
			));
		}

		if (!empty($filters['cliente_padre'])) {
			$qb->andWhere($qb->expr()->eq(
				$prefix . 'cliente_padre',
				$qb->createNamedParameter((int)$filters['cliente_padre'], IQueryBuilder::PARAM_INT)
			));
		}

		if (!empty($filters['lider_proyecto'])) {
			$qb->andWhere($qb->expr()->eq(
				$prefix . 'lider_proyecto',
				$qb->createNamedParameter((int)$filters['lider_proyecto'], IQueryBuilder::PARAM_INT)
			));
		}

		if (isset($filters['estado']) && $filters['estado'] !== null) {
			$qb->andWhere($qb->expr()->eq(
				$prefix . 'estado',
				$qb->createNamedParameter((int)$filters['estado'], IQueryBuilder::PARAM_INT)
			));
		}

		if (isset($filters['especial']) && $filters['especial'] !== null) {
			$qb->andWhere($qb->expr()->eq(
				$prefix . 'especial',
				$qb->createNamedParameter((int)$filters['especial'], IQueryBuilder::PARAM_INT)
			));
		}
	}
}