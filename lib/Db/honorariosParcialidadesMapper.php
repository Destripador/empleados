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
			'empleados_honorarios_p',
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

	public function cancelarPago(int $idParcialidad): ?int {
		$qb = $this->db->getQueryBuilder();

		$qb->select('id_honorario')
			->from($this->getTableName())
			->where($qb->expr()->eq(
				'id_parcialidad',
				$qb->createNamedParameter($idParcialidad, IQueryBuilder::PARAM_INT)
			));

		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		if (!$row) {
			return null;
		}

		$idHonorario = (int)$row['id_honorario'];

		$qb2 = $this->db->getQueryBuilder();
		$qb2->update($this->getTableName())
			->set('pagado', $qb2->createNamedParameter(0, IQueryBuilder::PARAM_INT))
			->set('fecha_pago', $qb2->createNamedParameter(null))
			->where($qb2->expr()->eq(
				'id_parcialidad',
				$qb2->createNamedParameter($idParcialidad, IQueryBuilder::PARAM_INT)
			));

		$qb2->executeStatement();

		return $idHonorario;
	}

	public function agregarParcialidadIguala(int $idHonorario): void {
		// Busca la última parcialidad para saber qué número y qué mes sigue
		$qb = $this->db->getQueryBuilder();
		$qb->select('numero_parcialidad', 'pfecha_fin')
			->from($this->getTableName())
			->where($qb->expr()->eq(
				'id_honorario',
				$qb->createNamedParameter($idHonorario, IQueryBuilder::PARAM_INT)
			))
			->orderBy('numero_parcialidad', 'DESC')
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$last = $result->fetch();
		$result->closeCursor();

		$nextNum = $last ? (int)$last['numero_parcialidad'] + 1 : 1;

		// Obtener datos del honorario padre (fecha_inicio e importe_total)
		$qb2 = $this->db->getQueryBuilder();
		$qb2->select('fecha_inicio', 'importe_total')
			->from('empleados_honorarios')
			->where($qb2->expr()->eq(
				'id_honorario',
				$qb2->createNamedParameter($idHonorario, IQueryBuilder::PARAM_INT)
			));
		$r2 = $qb2->executeQuery();
		$hon = $r2->fetch();
		$r2->closeCursor();

		$importeMensual = $hon ? (float)$hon['importe_total'] : 0;

		// Calcular siguiente mes
		if ($last && $last['pfecha_fin']) {
			$fecha = new \DateTime($last['pfecha_fin']);
			$fecha->modify('+1 day'); // primer día del siguiente mes
		} else {
			$fecha = new \DateTime($hon['fecha_inicio']);
		}

		$inicioMes = (clone $fecha)->modify('first day of this month');
		$finMes    = (clone $fecha)->modify('last day of this month');

		$qb3 = $this->db->getQueryBuilder();
		$qb3->insert($this->getTableName())
			->values([
				'id_honorario'        => $qb3->createNamedParameter($idHonorario, IQueryBuilder::PARAM_INT),
				'numero_parcialidad'  => $qb3->createNamedParameter($nextNum, IQueryBuilder::PARAM_INT),
				'pfecha_inicio'       => $qb3->createNamedParameter($inicioMes->format('Y-m-d')),
				'pfecha_fin'          => $qb3->createNamedParameter($finMes->format('Y-m-d')),
				'importe_parcialidad' => $qb3->createNamedParameter($importeMensual),
				'pagado'              => $qb3->createNamedParameter(0, IQueryBuilder::PARAM_INT),
			]);
		$qb3->executeStatement();
	}

	/**
	 * Suma el importe de las parcialidades agrupado por id_honorario
	 */
	public function sumByHonorarios(array $idsHonorarios): array {
		if (empty($idsHonorarios)) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();

		$qb->select('id_honorario')
			->selectAlias($qb->createFunction('SUM(importe_parcialidad)'), 'total')
			->from($this->getTableName())
			->where(
				$qb->expr()->in(
					'id_honorario',
					$qb->createNamedParameter($idsHonorarios, IQueryBuilder::PARAM_INT_ARRAY)
				)
			)
			->groupBy('id_honorario');

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		$sums = [];
		foreach ($rows as $row) {
			$sums[(int)$row['id_honorario']] = (float)$row['total'];
		}

		return $sums;
	}
}