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
	}

	/**
	 * Obtener parcialidad por ID
	 */
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

	/**
	 * Obtener parcialidades de un honorario
	 */
	public function findByHonorario(int $id_honorario): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq(
					'id_honorario',
					$qb->createNamedParameter(
						$id_honorario,
						IQueryBuilder::PARAM_INT
					)
				)
			)
			->orderBy('numero_parcialidad', 'ASC');

		$result = $qb->executeQuery();
		$data = $result->fetchAll();
		$result->closeCursor();

		return $data;
	}

	/**
	 * Eliminar parcialidades de un honorario
	 */
	public function deleteByHonorario(int $id_honorario): void {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq(
					'id_honorario',
					$qb->createNamedParameter(
						$id_honorario,
						IQueryBuilder::PARAM_INT
					)
				)
			);

		$qb->executeStatement();
	}

	/**
	 * Marcar parcialidad como pagada
	 */
	public function marcarPagada(
		int $id_parcialidad,
		string $fecha_pago
	): void {
		$qb = $this->db->getQueryBuilder();

		$qb->update($this->getTableName())
			->set(
				'pagado',
				$qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)
			)
			->set(
				'fecha_pago',
				$qb->createNamedParameter($fecha_pago)
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

	/**
	 * Generar parcialidades automáticas.
	 *
	 * @param int    $id_honorario        ID del honorario padre
	 * @param int    $numeroParcialidades Cantidad de parcialidades a generar
	 * @param float  $importeParcialidad  Importe por parcialidad
	 * @param string $fechaInicio         Fecha de la primera parcialidad (Y-m-d)
	 * @param string $frecuencia          'mensual' (default) | 'quincenal' | 'semanal'
	 */
	public function generarParcialidades(
		int $id_honorario,
		int $numeroParcialidades,
		float $importeParcialidad,
		string $fechaInicio,
		string $frecuencia = 'mensual'
	): void {

		$frecuenciasValidas = ['mensual', 'quincenal', 'semanal'];

		if (!in_array($frecuencia, $frecuenciasValidas, true)) {
			throw new \InvalidArgumentException(
				"Frecuencia inválida: '$frecuencia'. " .
				"Valores permitidos: " . implode(', ', $frecuenciasValidas)
			);
		}

		$fecha = new \DateTime($fechaInicio);

		for ($i = 1; $i <= $numeroParcialidades; $i++) {

			$parcialidad = new honorariosParcialidades();

			$parcialidad->setId_honorario($id_honorario);
			$parcialidad->setNumero_parcialidad($i);
			$parcialidad->setFecha_vencimiento($fecha->format('Y-m-d'));
			$parcialidad->setImporte($importeParcialidad);
			$parcialidad->setPagado(false);

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
}