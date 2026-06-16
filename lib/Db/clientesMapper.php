<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\QueryBuilder\IQueryBuilder;

class clientesMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'empleados_clientes', clientes::class);
	}

	/** Básico: obtener por ID */
	/** @throws DoesNotExistException|MultipleObjectsReturnedException */
	public function findById(int $id): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('id_cliente', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);

		$result = $qb->executeQuery();
		$data = $result->fetchAll();
		$result->closeCursor();

		return $data;
	}

	/** Listado simple */
	/** @return Cliente[] */
	public function findAll(?int $limit = null, int $offset = 0): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(
				'p.id_cliente',
				'p.nombre',
				'p.razon_social',
				'p.tipo_cliente',
				'p.tipo_servicio',
				'p.lider_proyecto',
				'p.nombre_contacto',
				'p.telefono',
				'p.correo',
				'p.status',
				'p.ubicacion',
				'p.honorarios',
				'p.tipo_moneda',
				'p.detalles',
				'p.especial',
				'p.cliente_padre',
				$qb->createFunction('COUNT(c.id_cliente) AS child_count')
			)
			->from($this->getTableName(), 'p')
			->leftJoin('p', $this->getTableName(), 'c',
				$qb->expr()->eq('c.cliente_padre', 'p.id_cliente')
			)
			->groupBy('p.id_cliente', 'p.nombre', 'p.razon_social', 'p.tipo_cliente', 'p.tipo_servicio', 'p.lider_proyecto', 'p.nombre_contacto', 'p.telefono', 'p.correo', 'p.status', 'p.ubicacion', 'p.honorarios', 'p.tipo_moneda', 'p.detalles', 'p.especial', 'p.cliente_padre')
			->orderBy('p.id_cliente', 'ASC')
			->setMaxResults($limit)
			->setFirstResult($offset);

		$result = $qb->executeQuery();
		$data = $result->fetchAll();
		$result->closeCursor();

		return $data;
	}

	/** Borrado rápido por ID */
	public function deleteById(int $id): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq('id_cliente', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);

		$qb->executeStatement();
	}

	public function updateClientes(
		int $id_cliente,
		string $nombre,
		?string $razon_social,
		?string $tipo_cliente,
		?string $tipo_servicio,
		?string $lider_proyecto,
		?string $nombre_contacto,
		?string $telefono,
		?string $correo,
		?string $status,
		?string $ubicacion,
		?string $honorarios,
		?string $tipo_moneda,
		?string $detalles,
		?bool $especial,
		?int $cliente_padre
	): void {
		$query = $this->db->getQueryBuilder();
		$query->update($this->getTableName())
			->set('nombre', $query->createNamedParameter($nombre))
			->set('razon_social', $query->createNamedParameter($razon_social))
			->set('tipo_cliente', $query->createNamedParameter($tipo_cliente))
			->set('tipo_servicio', $query->createNamedParameter($tipo_servicio))
			->set('lider_proyecto', $query->createNamedParameter($lider_proyecto))
			->set('nombre_contacto', $query->createNamedParameter($nombre_contacto))
			->set('telefono', $query->createNamedParameter($telefono))
			->set('correo', $query->createNamedParameter($correo))
			->set('status', $query->createNamedParameter($status))
			->set('ubicacion', $query->createNamedParameter($ubicacion))
			->set('honorarios', $query->createNamedParameter($honorarios))
			->set('tipo_moneda', $query->createNamedParameter($tipo_moneda))
			->set('detalles', $query->createNamedParameter($detalles))
			->set('especial', $query->createNamedParameter((int)($especial ?? false), IQueryBuilder::PARAM_INT))
			->set('cliente_padre', $query->createNamedParameter($cliente_padre))
			->where(
				$query->expr()->eq('id_cliente', $query->createNamedParameter($id_cliente, IQueryBuilder::PARAM_INT))
			);

		$query->executeStatement();
	}
}