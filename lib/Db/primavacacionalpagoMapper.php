<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class primavacacionalpagoMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'prima_vacacional_pagos', primavacacionalpago::class);
	}

	public function getByEmpleadoYAniversario(int $idEmpleado, int $numeroAniversario): ?array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id_empleado', $qb->createNamedParameter($idEmpleado, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('numero_aniversario', $qb->createNamedParameter($numeroAniversario, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		return $row !== false ? $row : null;
	}

	public function getByEmpleado(int $idEmpleado): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id_empleado', $qb->createNamedParameter($idEmpleado, IQueryBuilder::PARAM_INT)))
			->orderBy('numero_aniversario', 'ASC');

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return $rows;
	}

	public function getAllForAiContext(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(
			'id_empleado',
			'numero_aniversario',
			'fecha_pago',
			'dias_pagados',
			'created_at',
			'updated_at'
		)
			->from($this->getTableName())
			->orderBy('id_empleado', 'ASC')
			->addOrderBy('numero_aniversario', 'DESC');

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return $rows;
	}

	/**
	 * Inserta el pago si no existe, o lo actualiza si ya estaba registrado
	 */
	public function guardar(int $idEmpleado, int $numeroAniversario, string $fechaPago, float $diasPagados): void {
		$timestamp = date('Y-m-d H:i:s');
		$existente = $this->getByEmpleadoYAniversario($idEmpleado, $numeroAniversario);

		$qb = $this->db->getQueryBuilder();

		if ($existente) {
			$qb->update($this->getTableName())
				->set('fecha_pago', $qb->createNamedParameter($fechaPago))
				->set('dias_pagados', $qb->createNamedParameter($diasPagados))
				->set('updated_at', $qb->createNamedParameter($timestamp))
				->where($qb->expr()->eq('id_empleado', $qb->createNamedParameter($idEmpleado, IQueryBuilder::PARAM_INT)))
				->andWhere($qb->expr()->eq('numero_aniversario', $qb->createNamedParameter($numeroAniversario, IQueryBuilder::PARAM_INT)));
			$qb->executeStatement();
			return;
		}

		$qb->insert($this->getTableName())
			->values([
				'id_empleado' => $qb->createNamedParameter($idEmpleado, IQueryBuilder::PARAM_INT),
				'numero_aniversario' => $qb->createNamedParameter($numeroAniversario, IQueryBuilder::PARAM_INT),
				'fecha_pago' => $qb->createNamedParameter($fechaPago),
				'dias_pagados' => $qb->createNamedParameter($diasPagados),
				'created_at' => $qb->createNamedParameter($timestamp),
				'updated_at' => $qb->createNamedParameter($timestamp),
			]);

		$qb->executeStatement();
	}

	public function eliminar(int $idEmpleado, int $numeroAniversario): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('id_empleado', $qb->createNamedParameter($idEmpleado, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('numero_aniversario', $qb->createNamedParameter($numeroAniversario, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}
}
