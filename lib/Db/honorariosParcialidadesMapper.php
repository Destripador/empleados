<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;

class honorariosParcialidadesMapper extends QBMapper {

	public function __construct(IDBConnection $db) {
		parent::__construct(
			$db,
			'empleados_honorarios_parcialidades',
			honorariosParcialidades::class
		);

		$this->primaryKey = 'id_parcialidad';
	}

	public function findById(int $id): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq(
					'id_parcialidad',
					$qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)
				)
			);

		$result = $qb->executeQuery();
		$data = $result->fetch();
		$result->closeCursor();

		return $data ?: [];
	}

	public function findByHonorario(int $id_honorario): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq(
					'id_honorario',
					$qb->createNamedParameter($id_honorario, IQueryBuilder::PARAM_INT)
				)
			)
			->orderBy('numero_parcialidad', 'ASC');

		$result = $qb->executeQuery();
		$data = $result->fetchAll();
		$result->closeCursor();

		return $data;
	}

	public function deleteByHonorario(int $id_honorario): void {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq(
					'id_honorario',
					$qb->createNamedParameter($id_honorario, IQueryBuilder::PARAM_INT)
				)
			);

		$qb->executeStatement();
	}

	/**
	 * Cambiar estado de una parcialidad y verificar si el honorario quedó completo.
	 * Devuelve el id_honorario si todas las parcialidades están en estado PAGADO o FACTURADO.
	 */
	private function cambiarEstado(
		int $id_parcialidad,
		int $estado,
		?string $fecha_pago = null
	): ?int {
		$qb = $this->db->getQueryBuilder();

		// 1. Actualizar estado
		$qb->update($this->getTableName())
			->set('pagado', $qb->createNamedParameter($estado, IQueryBuilder::PARAM_INT));

		if ($fecha_pago !== null) {
			$qb->set('fecha_pago', $qb->createNamedParameter($fecha_pago));
		}

		$qb->where(
			$qb->expr()->eq(
				'id_parcialidad',
				$qb->createNamedParameter($id_parcialidad, IQueryBuilder::PARAM_INT)
			)
		);

		$qb->executeStatement();

		// 2. Obtener el honorario al que pertenece
		$qb = $this->db->getQueryBuilder();
		$qb->select('id_honorario')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq(
					'id_parcialidad',
					$qb->createNamedParameter($id_parcialidad, IQueryBuilder::PARAM_INT)
				)
			);

		$result = $qb->executeQuery();
		$id_honorario = (int)$result->fetchOne();
		$result->closeCursor();

		// 3. Contar parcialidades pendientes
		$qb = $this->db->getQueryBuilder();
		$qb->selectAlias(
			$qb->createFunction('COUNT(*)'),
			'total'
		)
		->from($this->getTableName())
		->where(
			$qb->expr()->eq(
				'id_honorario',
				$qb->createNamedParameter($id_honorario, IQueryBuilder::PARAM_INT)
			)
		)
		->andWhere(
			$qb->expr()->eq(
				'pagado',
				$qb->createNamedParameter(
					honorariosParcialidades::NO_PAGADO,
					IQueryBuilder::PARAM_INT
				)
			)
		);

		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		$pendientes = (int)($row['total'] ?? 0);
		// 4. Si ya no hay pendientes, regresamos el honorario
		if ($pendientes === 0) {
			return $id_honorario;
		}

		return null;
	}

	/**
	 * Marcar parcialidad como pagada.
	 * Devuelve el id_honorario si el honorario quedó completo.
	 */
	public function marcarPagada(
		int $id_parcialidad,
		string $fecha_pago
	): ?int {
		return $this->cambiarEstado(
			$id_parcialidad,
			honorariosParcialidades::PAGADO,
			$fecha_pago
		);
	}

	/**
	 * Marcar parcialidad como facturada.
	 * Devuelve el id_honorario si el honorario quedó completo.
	 */
	public function marcarFacturada(int $id_parcialidad): void {
		$qb = $this->db->getQueryBuilder();

		$qb->update($this->getTableName())
			->set(
				'pagado',
				$qb->createNamedParameter(
					honorariosParcialidades::FACTURADO,
					IQueryBuilder::PARAM_INT
				)
			)
			->where(
				$qb->expr()->eq(
					'id_parcialidad',
					$qb->createNamedParameter(
						$id_parcialidad,
						IQueryBuilder::PARAM_INT
					)
				)
			);

		$qb->executeStatement();
	}

	public function generarParcialidades(
		int $id_honorario,
		int $numero_parcialidades,
		float $importe_parcialidad,
		string $fecha_inicio,
		string $frecuencia = 'mensual'
	): void {
		$frecuenciasValidas = ['mensual', 'semanal', 'quincenal'];

		if (!in_array($frecuencia, $frecuenciasValidas, true)) {
			throw new \InvalidArgumentException(
				"Frecuencia inválida: '$frecuencia'. " .
				"Valores permitidos: " . implode(', ', $frecuenciasValidas)
			);
		}

		$fecha = new \DateTime($fecha_inicio);

		for ($i = 1; $i <= $numero_parcialidades; $i++) {
			$pfechaInicio = clone $fecha;
			$pfechaFin = clone $fecha;

			switch ($frecuencia) {
				case 'semanal':
					$pfechaFin->modify('+1 week')->modify('-1 day');
					break;
				case 'quincenal':
					$pfechaFin->modify('+15 days')->modify('-1 day');
					break;
				default:
					$pfechaFin->modify('+1 month')->modify('-1 day');
					break;
			}

			$parcialidad = new honorariosParcialidades();
			$parcialidad->setId_honorario($id_honorario);
			$parcialidad->setNumero_parcialidad($i);
			$parcialidad->setPfecha_inicio($pfechaInicio->format('Y-m-d'));
			$parcialidad->setPfecha_fin($pfechaFin->format('Y-m-d'));
			$parcialidad->setImporte_parcialidad($importe_parcialidad);
			$parcialidad->setPagado(honorariosParcialidades::NO_PAGADO);

			$this->insert($parcialidad);

			switch ($frecuencia) {
				case 'semanal':
					$fecha->modify('+1 week');
					break;
				case 'quincenal':
					$fecha->modify('+15 days');
					break;
				default:
					$fecha->modify('+1 month');
					break;
			}
		}
	}

	public function tienePagosRegistrados(int $id_honorario): bool {
		$qb = $this->db->getQueryBuilder();

		$qb->select('id_parcialidad')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq(
					'id_honorario',
					$qb->createNamedParameter($id_honorario, IQueryBuilder::PARAM_INT)
				)
			)
			->andWhere(
				$qb->expr()->neq(
					'pagado',
					$qb->createNamedParameter(
						honorariosParcialidades::NO_PAGADO,
						IQueryBuilder::PARAM_INT
					)
				)
			)
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$existe = $result->fetchOne();
		$result->closeCursor();

		return $existe !== false;
	}
}