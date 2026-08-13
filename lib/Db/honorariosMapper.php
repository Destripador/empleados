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
		honorariosParcialidadesMapper $parcialidadesMapper
	) {
		parent::__construct(
			$db,
			'empleados_honorarios',
			honorarios::class
		);

		$this->primaryKey = 'id_honorario';

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
	 * Obtener honorarios de un cliente, incluyendo el monto acumulado
	 */
	public function findByCliente(int $id_cliente): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq(
					'id_cliente',
					$qb->createNamedParameter($id_cliente, IQueryBuilder::PARAM_INT)
				)
			)
			->orderBy('id_honorario', 'DESC');

		$result = $qb->executeQuery();
		$data = $result->fetchAll();
		$result->closeCursor();

		if (empty($data)) {
			return $data;
		}

		$ids = array_map(static fn ($row) => (int)$row['id_honorario'], $data);
		$sums = $this->parcialidadesMapper->sumByHonorarios($ids);

		foreach ($data as &$row) {
			$row['monto_acumulado'] = $sums[(int)$row['id_honorario']] ?? 0.0;
		}
		unset($row);

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

	public function deleteByCliente(int $id_cliente): void {
		$honorarios = $this->findByCliente($id_cliente);

		foreach ($honorarios as $honorario) {
			$this->parcialidadesMapper->deleteByHonorario(
				(int)$honorario['id_honorario']
			);
		}

		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq(
					'id_cliente',
					$qb->createNamedParameter(
						$id_cliente,
						IQueryBuilder::PARAM_INT
					)
				)
			);

		$qb->executeStatement();
	}

	/**
	 * Crear honorario y generar parcialidades
	 */
	public function crearHonorario(honorarios $honorario): honorarios {
		$tipoHonorario = $honorario->getTipo_honorario() ?: 'parcial';

		$parcialidades = $this->calcularNumeroParcialidades(
			$honorario->getFecha_inicio(),
			$honorario->getFecha_fin(),
			$tipoHonorario
		);

		$honorario->setNumero_parcialidades($parcialidades);

		$this->insert($honorario);

		// Obtener el ID recién insertado
		$id = (int)$this->db->lastInsertId('*PREFIX*empleados_honorarios');

		$importeParcialidad = round(
			$honorario->getImporte_total() / $parcialidades,
			2
		);

		$this->parcialidadesMapper->generarParcialidades(
			$id,
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
		?int $id_honorario,
		int $id_cliente,
		float $importe_total,
		string $tipo_moneda,
		?string $fecha_inicio,
		?string $fecha_fin,
		?string $tipo_servicio,
		bool $especial,
		string $tipo_honorario = 'parcial'
	): void {
		$parcialidades = $this->calcularNumeroParcialidades(
			$fecha_inicio,
			$fecha_fin,
			$tipo_honorario
		);

		$qb = $this->db->getQueryBuilder();

		$qb->update($this->getTableName())
			->set(
				'id_cliente',
				$qb->createNamedParameter($id_cliente)
			)
			->set(
				'importe_total',
				$qb->createNamedParameter($importe_total)
			)
			->set(
				'tipo_moneda',
				$qb->createNamedParameter($tipo_moneda)
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
				'numero_parcialidades',
				$qb->createNamedParameter($parcialidades)
			)
			->set(
				'tipo_servicio',
				$qb->createNamedParameter($tipo_servicio)
			)
			->set(
				'tipo_honorario',
				$qb->createNamedParameter($tipo_honorario)
			)
			->set(
				'especial',
				$qb->createNamedParameter(
					$especial,
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

		if (
			$this->parcialidadesMapper->tienePagosRegistrados(
				$id_honorario
			)
		) {
			throw new \Exception(
				'No se puede modificar un honorario con pagos registrados.'
			);
		}
		
		$qb->executeStatement();

		$this->parcialidadesMapper->deleteByHonorario(
			$id_honorario
		);

		$importeParcialidad =
			round(
				$importe_total / $parcialidades,
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
	 * Calcular cantidad de parcialidades.
	 * Solo el tipo 'parcial' calcula por rango de fechas; 'iguala' y
	 * 'eventual' siempre arrancan con una sola parcialidad inicial.
	 */
	private function calcularNumeroParcialidades(
		?string $fecha_inicio,
		?string $fecha_fin,
		string $tipo_honorario = 'parcial'
	): int {

		if ($tipo_honorario !== 'parcial') {
			return 1;
		}

		if (
			empty($fecha_inicio)
			|| empty($fecha_fin)
		) {
			return 1;
		}
		
		$inicio = new DateTime($fecha_inicio);
		$fin = new DateTime($fecha_fin);

		$diferencia = $inicio->diff($fin);

		$meses =
			($diferencia->y * 12)
			+ $diferencia->m
			+ 1;

		return max($meses, 1);
	}

	public function desactivarHonorario(
		int $id_honorario
	): void {

		$qb = $this->db->getQueryBuilder();

		$qb->update($this->getTableName())
			->set(
				'activo',
				$qb->createNamedParameter(
					0,
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
	}

	public function reactivarHonorario(int $idHonorario): void {
		$qb = $this->db->getQueryBuilder();

		$qb->update($this->getTableName())
			->set('activo', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq(
				'id_honorario',
				$qb->createNamedParameter($idHonorario, IQueryBuilder::PARAM_INT)
			));

		$qb->executeStatement();
	}

	public function getResumenPorCliente(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select(
				'id_cliente',
				$qb->createFunction('SUM(importe_total) AS importe_total'),
				$qb->createFunction('MIN(fecha_inicio) AS fecha_inicio'),
				$qb->createFunction('MAX(fecha_fin) AS fecha_fin'),
				$qb->createFunction('GROUP_CONCAT(DISTINCT tipo_moneda) AS monedas')
			)
			->from($this->getTableName())
			->groupBy('id_cliente');

		$result = $qb->executeQuery();
		$data = $result->fetchAll();
		$result->closeCursor();

		return $data;
	}

	/**
	 * Actualiza solo tipo_servicio, tipo_moneda y especial.
	 * No modifica fechas, importes ni regenera parcialidades.
	 */
	public function actualizarMetadatos(
		int $id_honorario,
		?string $tipo_servicio,
		string $tipo_moneda,
		bool $especial
	): void {
		$qb = $this->db->getQueryBuilder();

		$qb->update($this->getTableName())
			->set('tipo_servicio', $qb->createNamedParameter($tipo_servicio))
			->set('tipo_moneda', $qb->createNamedParameter($tipo_moneda))
			->set('especial', $qb->createNamedParameter($especial, IQueryBuilder::PARAM_INT))
			->where(
				$qb->expr()->eq(
					'id_honorario',
					$qb->createNamedParameter($id_honorario, IQueryBuilder::PARAM_INT)
				)
			);

		$qb->executeStatement();
	}
}