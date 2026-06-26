<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class tipoausenciaMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'tipo_ausencia', tipo_ausencia::class);
	}

	public function getTipo(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName());

		$result = $qb->executeQuery();
		$tipo_ausencia = $result->fetchAll();
		$result->closeCursor();

		return $tipo_ausencia;
	}

	public function getTipoById($id): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id_tipo_ausencia', $qb->createNamedParameter($id)));

		$result = $qb->executeQuery();
		$tipo_ausencia = $result->fetchAll();
		$result->closeCursor();

		return $tipo_ausencia;
	}

	public function updateTipoAusencias(int $id_tipo_ausencia, string $nombre, string $descripcion, int $solicitar_archivo, int $solicitar_prima_vacacional): void {
		$query = $this->db->getQueryBuilder();
		$query->update($this->getTableName())
			->set('nombre', $query->createNamedParameter($nombre))
			->set('descripcion', $query->createNamedParameter($descripcion))
			->set('solicitar_archivo', $query->createNamedParameter((int) $solicitar_archivo))
			->set('solicitar_prima_vacacional', $query->createNamedParameter((int) $solicitar_prima_vacacional))
			->where($query->expr()->eq('id_tipo_ausencia', $query->createNamedParameter($id_tipo_ausencia)));

		$query->executeStatement();
	}

	public function VaciarTipo(): void {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName());

		$qb->executeStatement();
	}

	public function deleteById(int $id): void {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('id_tipo_ausencia', $qb->createNamedParameter($id)));

		$qb->executeStatement();
	}
}