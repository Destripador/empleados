<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class CompraSolicitudMapper extends QBMapper {

	private const TABLE = 'emp_comp_solicitudes';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, self::TABLE, CompraSolicitud::class);
	}

	public function find(int $id): CompraSolicitud {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from(self::TABLE)
			->where($qb->expr()->eq(
				'id_solicitud',
				$qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)
			))
			->setMaxResults(1);

		return $this->findEntity($qb);
	}

	public function findByFolio(string $folio): CompraSolicitud {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from(self::TABLE)
			->where($qb->expr()->eq(
				'folio',
				$qb->createNamedParameter($folio, IQueryBuilder::PARAM_STR)
			))
			->setMaxResults(1);

		return $this->findEntity($qb);
	}

	public function findByUser(string $idUser, int $limit = 50, int $offset = 0): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from(self::TABLE)
			->where($qb->expr()->eq(
				'id_user',
				$qb->createNamedParameter($idUser, IQueryBuilder::PARAM_STR)
			))
			->orderBy('created_at', 'DESC')
			->setMaxResults($limit)
			->setFirstResult($offset);

		return $this->findEntities($qb);
	}

	public function findByEstado(string $estado, int $limit = 100, int $offset = 0): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from(self::TABLE)
			->where($qb->expr()->eq(
				'estado',
				$qb->createNamedParameter($estado, IQueryBuilder::PARAM_STR)
			))
			->orderBy('created_at', 'DESC')
			->setMaxResults($limit)
			->setFirstResult($offset);

		return $this->findEntities($qb);
	}

	public function findAll(int $limit = 100, int $offset = 0): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from(self::TABLE)
			->orderBy('created_at', 'DESC')
			->setMaxResults($limit)
			->setFirstResult($offset);

		return $this->findEntities($qb);
	}

	public function findPage(?string $idUser, ?string $estado, int $limit, int $offset): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from(self::TABLE)
			->orderBy('created_at', 'DESC')
			->addOrderBy('id_solicitud', 'DESC')
			->setMaxResults($limit)
			->setFirstResult($offset);

		$this->applyListFilters($qb, $idUser, $estado);

		return $this->findEntities($qb);
	}

	public function getListSummary(?string $idUser, ?string $estado): array {
		$qb = $this->db->getQueryBuilder();

		$qb->selectAlias($qb->createFunction('COUNT(*)'), 'total')
			->selectAlias(
				$qb->createFunction("COALESCE(SUM(CASE WHEN estado = 'pendiente_autorizacion' THEN 1 ELSE 0 END), 0)"),
				'pending'
			)
			->selectAlias($qb->createFunction('COALESCE(SUM(monto_estimado), 0)'), 'estimated_amount')
			->from(self::TABLE);

		$this->applyListFilters($qb, $idUser, $estado);

		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		return [
			'total' => (int)($row['total'] ?? 0),
			'pending' => (int)($row['pending'] ?? 0),
			'estimated_amount' => (float)($row['estimated_amount'] ?? 0),
		];
	}

	public function insertSolicitud(array $data): CompraSolicitud {
		$qb = $this->db->getQueryBuilder();

		$fields = $this->getWritableFields();

		$values = [];

		foreach ($fields as $field) {
			if (array_key_exists($field, $data)) {
				$values[$field] = $qb->createNamedParameter($data[$field]);
			}
		}

		$qb->insert(self::TABLE)->values($values);
		$this->executeStatement($qb);

		$id = (int)$this->db->lastInsertId(self::TABLE);

		return $this->find($id);
	}

	public function updateSolicitud(int $id, array $data): CompraSolicitud {
		$qb = $this->db->getQueryBuilder();

		$fields = $this->getWritableFields();

		$qb->update(self::TABLE);

		foreach ($fields as $field) {
			if (array_key_exists($field, $data)) {
				$qb->set($field, $qb->createNamedParameter($data[$field]));
			}
		}

		$qb->set('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->where($qb->expr()->eq(
				'id_solicitud',
				$qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)
			));

		$this->executeStatement($qb);

		return $this->find($id);
	}

	public function cambiarEstado(
		int $id,
		string $estado,
		string $updatedBy,
		?string $fechaCampo = null
	): CompraSolicitud {
		$qb = $this->db->getQueryBuilder();

		$qb->update(self::TABLE)
			->set('estado', $qb->createNamedParameter($estado, IQueryBuilder::PARAM_STR))
			->set('updated_by', $qb->createNamedParameter($updatedBy, IQueryBuilder::PARAM_STR))
			->set('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')));

		if ($fechaCampo !== null) {
			$qb->set($fechaCampo, $qb->createNamedParameter(date('Y-m-d H:i:s')));
		}

		$qb->where($qb->expr()->eq(
			'id_solicitud',
			$qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)
		));

		$this->executeStatement($qb);

		return $this->find($id);
	}

	public function cambiarEstadoSiActual(
		int $id,
		string $estadoActual,
		string $estadoNuevo,
		string $updatedBy,
		?string $fechaCampo = null
	): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->update(self::TABLE)
			->set('estado', $qb->createNamedParameter($estadoNuevo, IQueryBuilder::PARAM_STR))
			->set('updated_by', $qb->createNamedParameter($updatedBy, IQueryBuilder::PARAM_STR))
			->set('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')));

		if ($fechaCampo !== null) {
			$qb->set($fechaCampo, $qb->createNamedParameter(date('Y-m-d H:i:s')));
		}

		$qb->where($qb->expr()->eq('id_solicitud', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('estado', $qb->createNamedParameter($estadoActual, IQueryBuilder::PARAM_STR)));

		return $this->executeStatement($qb) === 1;
	}

	private function getWritableFields(): array {
		return [
			'folio',
			'id_user',
			'id_empleado',
			'id_departamento',
			'id_equipo',
			'id_cliente',
			'titulo',
			'descripcion',
			'justificacion',
			'monto_estimado',
			'monto_final',
			'moneda',
			'prioridad',
			'estado',
			'fecha_requerida',
			'fecha_envio',
			'fecha_autorizacion',
			'fecha_cierre',
			'proveedor_seleccionado',
			'created_by',
			'updated_by',

			'solicitante_nombre',
			'solicitante_depto',
			'solicitante_cargo',
			'jefe_directo_nombre',
			'tipo_compra',
			'garantia',
			'uso_compra',
			'informacion',
			'motivo',
			'proveedor_nombre',
			'atencion',
			'entrega',
			'marca_modelo',
			'especificaciones',
			'comentarios_req',
			'oficina_pct',
			'empleado_pct',
			'tipo_pago',
			'quincenas',
			'total_excl_iva',
			'iva',
			'total_incl_iva',
			'comentarios_admin',
			'pdf_file_id',
			'pdf_nombre',
			'pdf_generado_at',

			'firmado_file_id',
			'firmado_nombre',
			'firmado_mime',
			'firmado_subido_at',
			'firmado_subido_by',
		];
	}

	private function applyListFilters(IQueryBuilder $qb, ?string $idUser, ?string $estado): void {
		if ($idUser !== null) {
			$qb->andWhere($qb->expr()->eq(
				'id_user',
				$qb->createNamedParameter($idUser, IQueryBuilder::PARAM_STR)
			));
		}

		if ($estado !== null) {
			$qb->andWhere($qb->expr()->eq(
				'estado',
				$qb->createNamedParameter($estado, IQueryBuilder::PARAM_STR)
			));
		}
	}

	private function executeStatement(IQueryBuilder $qb): int {
		if (method_exists($qb, 'executeStatement')) {
			return $qb->executeStatement();
		}

		return $qb->execute();
	}
}
