<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use DateTime;
use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;

class honorariosMapper extends QBMapper {

	private honorariosParcialidadesMapper $parcialidadesMapper;

	public function __construct(
		IDBConnection $db,
		HonorariosParcialidadesMapper $parcialidadesMapper
	) {
		parent::__construct(
			$db,
			'empleados_honorarios',
			Honorario::class
		);

		$this->parcialidadesMapper = $parcialidadesMapper;
	}

	/**
	 * Obtener honorario por ID
	 */
	public function findById(int $id): array {

		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq(
					'id_honorario',
					$qb->createNamedParameter(
						$id,
						IQueryBuilder::PARAM_INT
					)
				)
			);

		$result = $qb->executeQuery();
		$data = $result->fetch();
		$result->closeCursor();

		return $data ?: [];
	}

	/**
	 * Obtener todos los honorarios
	 */
	public function findAll(
		?int $limit = null,
		int $offset = 0
	): array {

		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->orderBy('id_honorario', 'DESC')
			->setMaxResults($limit)
			->setFirstResult($offset);

		$result = $qb->executeQuery();
		$data = $result->fetchAll();
		$result->closeCursor();

		return $data;
	}

	/**
	 * Eliminar honorario
	 */
	public function deleteById(int $id): void {

		$this->parcialidadesMapper->deleteByHonorario($id);

		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq(
					'id_honorario',
					$qb->createNamedParameter(
						$id,
						IQueryBuilder::PARAM_INT
					)
				)
			);

		$qb->executeStatement();
	}

	/**
	 * Crear honorario y generar parcialidades
	 */
	public function crearHonorario(
		honorario $honorario
	): honorario {

		$honorario = $this->insert($honorario);

		$parcialidades = $this->calcularNumeroParcialidades(
			$honorario->getFecha_inicio(),
			$honorario->getFecha_fin()
		);

		$importeParcialidad =
			round(
				$honorario->getHonorario_total() / $parcialidades,
				2
			);

		$this->parcialidadesMapper->generarParcialidades(
			$honorario->getId_honorario(),
			$parcialidades,
			$importeParcialidad,
			$honorario->getFecha_inicio()
		);

		return $honorario;
	}

	/**
	 * Actualizar honorario y regenerar parcialidades
	 */
	public function updateHonorario(
		int $id_honorario,
		int $id_cliente,
		?string $tipo_servicio,
		?int $ejercicio,
		?string $fecha_inicio,
		?string $fecha_fin,
		float $honorario_total,
		?string $tipo_moneda,
		bool $activo
	): void {

		$qb = $this->db->getQueryBuilder();

		$qb->update($this->getTableName())
			->set(
				'id_cliente',
				$qb->createNamedParameter($id_cliente)
			)
			->set(
				'tipo_servicio',
				$qb->createNamedParameter($tipo_servicio)
			)
			->set(
				'ejercicio',
				$qb->createNamedParameter($ejercicio)
			)
			->set(
				'fecha_inicio',
				$qb->createNamedParameter($fecha_inicio)
			)
			->set(
				'fecha_fin',
				$qb->createNamedParameter($fecha_fin)
			)
			->set(
				'honorario_total',
				$qb->createNamedParameter($honorario_total)
			)
			->set(
				'tipo_moneda',
				$qb->createNamedParameter($tipo_moneda)
			)
			->set(
				'activo',
				$qb->createNamedParameter(
					(int)$activo,
					IQueryBuilder::PARAM_INT
				)
			)
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

		$this->parcialidadesMapper->deleteByHonorario(
			$id_honorario
		);

		$parcialidades = $this->calcularNumeroParcialidades(
			$fecha_inicio,
			$fecha_fin
		);

		$importeParcialidad =
			round(
				$honorario_total / $parcialidades,
				2
			);

		$this->parcialidadesMapper->generarParcialidades(
			$id_honorario,
			$parcialidades,
			$importeParcialidad,
			$fecha_inicio
		);
	}

	/**
	 * Calcular cantidad de parcialidades
	 */
	private function calcularNumeroParcialidades(
		?string $fechaInicio,
		?string $fechaFin
	): int {

		$inicio = new DateTime($fechaInicio);
		$fin = new DateTime($fechaFin);

		$diferencia = $inicio->diff($fin);

		$meses =
			($diferencia->y * 12)
			+ $diferencia->m
			+ 1;

		return max($meses, 1);
	}
}