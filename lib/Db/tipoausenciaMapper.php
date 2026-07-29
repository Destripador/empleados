<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class tipoausenciaMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'tipo_ausencia', tipoausencia::class);
	}

	/**
	 * Devuelve TODOS los tipos de ausencia, incluidos los privados.
	 * Usar solo en contextos de administración (admin/RH), nunca para llenar
	 * el selector de solicitud de un empleado normal.
	 */
	public function getTipo(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName());

		$result = $qb->executeQuery();
		$tipo_ausencia = $result->fetchAll();
		$result->closeCursor();

		return $tipo_ausencia;
	}

	/**
	 * Devuelve los tipos de ausencia visibles para el usuario actual.
	 * Si $isPrivileged es false, excluye los marcados como privados (privado > 0).
	 */
	public function getTipoVisible(bool $isPrivileged): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName());

		if (!$isPrivileged) {
			$qb->where($qb->expr()->eq('privado', $qb->createNamedParameter(0, \PDO::PARAM_INT)));
		}

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

	/**
	 * Crea un nuevo tipo de ausencia.
	 */
	public function insertTipoAusencia(string $nombre, string $descripcion, int $solicitar_archivo, int $solicitar_prima_vacacional, int $cargable, int $privado): tipoausencia {
		$entidad = new tipoausencia();
		$entidad->setnombre($nombre);
		$entidad->setdescripcion($descripcion);
		$entidad->setsolicitar_archivo((bool) $solicitar_archivo);
		$entidad->setsolicitar_prima_vacacional((bool) $solicitar_prima_vacacional);
		$entidad->setcargable((bool) $cargable);
		$entidad->setprivado($privado);

		return $this->insert($entidad);
	}

	public function updateTipoAusencias(int $id_tipo_ausencia, string $nombre, string $descripcion, int $solicitar_archivo, int $solicitar_prima_vacacional, int $cargable, int $privado): void {
		$query = $this->db->getQueryBuilder();
		$query->update($this->getTableName())
			->set('nombre', $query->createNamedParameter($nombre))
			->set('descripcion', $query->createNamedParameter($descripcion))
			->set('solicitar_archivo', $query->createNamedParameter($solicitar_archivo))
			->set('solicitar_prima_vacacional', $query->createNamedParameter($solicitar_prima_vacacional))
			->set('cargable', $query->createNamedParameter($cargable))
			->set('privado', $query->createNamedParameter($privado))
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