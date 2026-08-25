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

		$qb->select('p.*')
			->selectAlias('c.nombre', 'pagador_nombre')
			->from($this->getTableName(), 'p')
			->leftJoin(
				'p',
				'empleados_clientes',
				'c',
				$qb->expr()->eq('p.id_cliente_pagador', 'c.id')
			)
			->where(
				$qb->expr()->eq(
					'p.id_honorario',
					$qb->createNamedParameter($id_honorario, IQueryBuilder::PARAM_INT)
				)
			)
			->orderBy('p.numero_parcialidad', 'ASC');

		$result = $qb->executeQuery();
		$data = $result->fetchAll();
		$result->closeCursor();

		return $data;
	}

	/**
	 * Parcialidades de OTROS clientes que fueron pagadas por $idClientePagador.
	 */
	public function findPagadasPorCliente(int $idClientePagador): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('p.*')
			->selectAlias('h.id_cliente', 'id_cliente')
			->selectAlias('h.tipo_servicio', 'tipo_servicio')
			->selectAlias('h.tipo_moneda', 'tipo_moneda')
			->selectAlias('c.nombre', 'cliente_nombre')
			->from($this->getTableName(), 'p')
			->innerJoin(
				'p',
				'empleados_honorarios',
				'h',
				$qb->expr()->eq('p.id_honorario', 'h.id_honorario')
			)
			->innerJoin(
				'h',
				'empleados_clientes',
				'c',
				$qb->expr()->eq('h.id_cliente', 'c.id')
			)
			->where(
				$qb->expr()->eq(
					'p.id_cliente_pagador',
					$qb->createNamedParameter($idClientePagador, IQueryBuilder::PARAM_INT)
				)
			)
			->orderBy('p.pfecha_inicio', 'DESC');

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

	private function verificarHonorarioCompleto(int $id_parcialidad): ?int {
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

		$qb2 = $this->db->getQueryBuilder();
		$qb2->selectAlias($qb2->createFunction('COUNT(*)'), 'total')
			->from($this->getTableName())
			->where(
				$qb2->expr()->eq(
					'id_honorario',
					$qb2->createNamedParameter($id_honorario, IQueryBuilder::PARAM_INT)
				)
			)
			->andWhere(
				$qb2->expr()->eq(
					'pagado',
					$qb2->createNamedParameter(honorariosParcialidades::PENDIENTE, IQueryBuilder::PARAM_INT)
				)
			);
		$result = $qb2->executeQuery();
		$pendientes = (int)($result->fetch()['total'] ?? 0);
		$result->closeCursor();

		return $pendientes === 0 ? $id_honorario : null;
	}

	/**
	 * Paso 1: pendiente -> facturada.
	 */
	public function marcarFacturada(
		int $id_parcialidad,
		string $fecha_factura,
		?int $id_cliente_pagador = null
	): void {
		$qb = $this->db->getQueryBuilder();

		$qb->update($this->getTableName())
			->set('pagado', $qb->createNamedParameter(honorariosParcialidades::FACTURADA, IQueryBuilder::PARAM_INT))
			->set('fecha_factura', $qb->createNamedParameter($fecha_factura))
			->set(
				'id_cliente_pagador',
				$id_cliente_pagador !== null
					? $qb->createNamedParameter($id_cliente_pagador, IQueryBuilder::PARAM_INT)
					: $qb->createNamedParameter(null)
			)
			->where(
				$qb->expr()->eq(
					'id_parcialidad',
					$qb->createNamedParameter($id_parcialidad, IQueryBuilder::PARAM_INT)
				)
			);

		$qb->executeStatement();
	}

	/**
	 * Paso 2: facturada -> pagada.
	 * Devuelve el id_honorario si con esto el honorario quedó completo.
	 */
	public function marcarPagada(int $id_parcialidad, string $fecha_pago): ?int {
		$qb = $this->db->getQueryBuilder();

		$qb->update($this->getTableName())
			->set('pagado', $qb->createNamedParameter(honorariosParcialidades::PAGADA, IQueryBuilder::PARAM_INT))
			->set('fecha_pago', $qb->createNamedParameter($fecha_pago))
			->where(
				$qb->expr()->eq(
					'id_parcialidad',
					$qb->createNamedParameter($id_parcialidad, IQueryBuilder::PARAM_INT)
				)
			);

		$qb->executeStatement();

		return $this->verificarHonorarioCompleto($id_parcialidad);
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
			$parcialidad->setPagado(honorariosParcialidades::PENDIENTE);

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

	/**
	 * Genera parcialidades agrupando meses según la periodicidad elegida.
	 * cuadre exactamente con $importe_total.
	 */
	public function generarParcialidadesPorGrupos(
		int $id_honorario,
		array $grupos,
		float $importe_total,
		string $fecha_inicio
	): void {
		if (empty($grupos)) {
			return;
		}

		$totalMeses = array_sum($grupos);
		$importePorMes = $totalMeses > 0 ? $importe_total / $totalMeses : 0;

		$fecha = new \DateTime($fecha_inicio);
		$acumulado = 0.0;
		$numGrupos = count($grupos);

		foreach ($grupos as $i => $mesesEnGrupo) {
			$numero = $i + 1;

			$pfechaInicio = clone $fecha;
			$pfechaFin = (clone $fecha)->modify("+{$mesesEnGrupo} months")->modify('-1 day');

			if ($numero === $numGrupos) {
				// La última absorbe el residuo de centavos
				$importeParcialidad = round($importe_total - $acumulado, 2);
			} else {
				$importeParcialidad = round($importePorMes * $mesesEnGrupo, 2);
				$acumulado += $importeParcialidad;
			}

			$parcialidad = new honorariosParcialidades();
			$parcialidad->setId_honorario($id_honorario);
			$parcialidad->setNumero_parcialidad($numero);
			$parcialidad->setPfecha_inicio($pfechaInicio->format('Y-m-d'));
			$parcialidad->setPfecha_fin($pfechaFin->format('Y-m-d'));
			$parcialidad->setImporte_parcialidad($importeParcialidad);
			$parcialidad->setPagado(honorariosParcialidades::PENDIENTE);

			$this->insert($parcialidad);

			$fecha->modify("+{$mesesEnGrupo} months");
		}
	}

	public function tieneFacturacionRegistrada(int $id_honorario): bool {
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
					$qb->createNamedParameter(honorariosParcialidades::PENDIENTE, IQueryBuilder::PARAM_INT)
				)
			)
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$existe = $result->fetchOne();
		$result->closeCursor();

		return $existe !== false;
	}

	/**
	* Revertir facturación (facturada -> pendiente) 
	*/
	public function cancelarFactura(int $id_parcialidad): void {
		$qb = $this->db->getQueryBuilder();

		$qb->update($this->getTableName())
			->set('pagado', $qb->createNamedParameter(honorariosParcialidades::PENDIENTE, IQueryBuilder::PARAM_INT))
			->set('fecha_factura', $qb->createNamedParameter(null))
			->set('id_cliente_pagador', $qb->createNamedParameter(null))
			->where(
				$qb->expr()->eq(
					'id_parcialidad',
					$qb->createNamedParameter($id_parcialidad, IQueryBuilder::PARAM_INT)
				)
			);

		$qb->executeStatement();
	}

	/**
	 * Revierte pagada -> facturada
	 */
	public function cancelarPago(int $id_parcialidad): ?int {
		$idHonorario = $this->getHonorarioId($id_parcialidad);

		if ($idHonorario === null) {
			return null;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('pagado', $qb->createNamedParameter(honorariosParcialidades::FACTURADA, IQueryBuilder::PARAM_INT))
			->set('fecha_pago', $qb->createNamedParameter(null))
			->where(
				$qb->expr()->eq(
					'id_parcialidad',
					$qb->createNamedParameter($id_parcialidad, IQueryBuilder::PARAM_INT)
				)
			);

		$qb->executeStatement();

		return $idHonorario;
	}

	private function getHonorarioId(int $id_parcialidad): ?int {
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
		$row = $result->fetch();
		$result->closeCursor();

		return $row ? (int)$row['id_honorario'] : null;
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
				'pagado' => $qb3->createNamedParameter(honorariosParcialidades::PENDIENTE, IQueryBuilder::PARAM_INT),
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

	/**
	 * Totales de parcialidades agrupados por cliente y moneda.
	 *
	 * @return list<array<string,mixed>>
	 */
	public function getDashboardFeeRows(array $filters): array {
		$qb = $this->db->getQueryBuilder();
		$pagada = honorariosParcialidades::PAGADA;
		$pendiente = honorariosParcialidades::PENDIENTE;
		$facturada = honorariosParcialidades::FACTURADA;

		$qb->selectAlias('c.id', 'id_cliente')
			->selectAlias('h.tipo_moneda', 'tipo_moneda')
			->selectAlias($qb->createFunction('SUM(p.importe_parcialidad)'), 'total')
			->selectAlias($qb->createFunction("SUM(CASE WHEN p.pagado = {$pagada} THEN p.importe_parcialidad ELSE 0 END)"), 'pagado')
			->selectAlias($qb->createFunction("SUM(CASE WHEN p.pagado IN ({$pendiente}, {$facturada}) THEN p.importe_parcialidad ELSE 0 END)"), 'pendiente')
			->from($this->getTableName(), 'p')
			->innerJoin('p', 'empleados_honorarios', 'h', $qb->expr()->eq('p.id_honorario', 'h.id_honorario'))
			->innerJoin('h', 'empleados_clientes', 'c', $qb->expr()->eq('h.id_cliente', 'c.id'));

		$this->applyDashboardFeeFilters($qb, $filters);

		$qb->groupBy('c.id', 'h.tipo_moneda');

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return $rows;
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	public function getMonthlyGenerated(array $filters): array {
		$qb = $this->db->getQueryBuilder();

		$qb->selectAlias($qb->createFunction('SUBSTRING(p.pfecha_inicio, 1, 7)'), 'mes')
			->selectAlias($qb->createFunction('SUM(p.importe_parcialidad)'), 'importe')
			->from($this->getTableName(), 'p')
			->innerJoin('p', 'empleados_honorarios', 'h', $qb->expr()->eq('p.id_honorario', 'h.id_honorario'))
			->innerJoin('h', 'empleados_clientes', 'c', $qb->expr()->eq('h.id_cliente', 'c.id'))
			->where($qb->expr()->isNotNull('p.pfecha_inicio'));

		$this->applyDashboardFeeFilters($qb, $filters);
		$qb->groupBy('mes')
			->orderBy('mes', 'ASC');

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return $rows;
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	public function getMonthlyCollected(array $filters): array {
		$qb = $this->db->getQueryBuilder();

		$qb->selectAlias($qb->createFunction('SUBSTRING(p.fecha_pago, 1, 7)'), 'mes')
			->selectAlias($qb->createFunction('SUM(p.importe_parcialidad)'), 'importe')
			->from($this->getTableName(), 'p')
			->innerJoin('p', 'empleados_honorarios', 'h', $qb->expr()->eq('p.id_honorario', 'h.id_honorario'))
			->innerJoin('h', 'empleados_clientes', 'c', $qb->expr()->eq('h.id_cliente', 'c.id'))
			->where($qb->expr()->eq(
				'p.pagado',
				$qb->createNamedParameter(honorariosParcialidades::PAGADA, IQueryBuilder::PARAM_INT)
			))
			->andWhere($qb->expr()->isNotNull('p.fecha_pago'));

		$this->applyDashboardFeeFilters($qb, $filters);
		$qb->groupBy('mes')
			->orderBy('mes', 'ASC');

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return $rows;
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	public function getDashboardHonorariosByCliente(int $idCliente, array $filters): array {
		$qb = $this->db->getQueryBuilder();

		$pagada = honorariosParcialidades::PAGADA;
		$pendiente = honorariosParcialidades::PENDIENTE;
		$facturada = honorariosParcialidades::FACTURADA;

		$qb->select(
			'h.id_honorario',
			'h.id_cliente',
			'h.tipo_servicio',
			'h.tipo_honorario',
			'h.tipo_moneda',
			'h.fecha_inicio',
			'h.fecha_fin',
			'h.activo',
			'h.especial'
		)
			->selectAlias($qb->createFunction('COALESCE(SUM(p.importe_parcialidad), 0)'), 'total')
			->selectAlias($qb->createFunction("COALESCE(SUM(CASE WHEN p.pagado = {$pagada} THEN p.importe_parcialidad ELSE 0 END), 0)"), 'pagado')
			->selectAlias($qb->createFunction("COALESCE(SUM(CASE WHEN p.pagado IN ({$pendiente}, {$facturada}) THEN p.importe_parcialidad ELSE 0 END), 0)"), 'pendiente')
			->from('empleados_honorarios', 'h')
			->leftJoin('h', $this->getTableName(), 'p', $qb->expr()->eq('p.id_honorario', 'h.id_honorario'))
			->innerJoin('h', 'empleados_clientes', 'c', $qb->expr()->eq('h.id_cliente', 'c.id'))
			->where($qb->expr()->eq(
				'h.id_cliente',
				$qb->createNamedParameter($idCliente, IQueryBuilder::PARAM_INT)
			));

		$this->applyDashboardFeeFilters($qb, array_merge($filters, ['id_cliente' => $idCliente]));

		$qb->groupBy(
			'h.id_honorario',
			'h.id_cliente',
			'h.tipo_servicio',
			'h.tipo_honorario',
			'h.tipo_moneda',
			'h.fecha_inicio',
			'h.fecha_fin',
			'h.activo',
			'h.especial'
		)
			->orderBy('h.fecha_inicio', 'DESC');

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		foreach ($rows as &$row) {
			$row['id_honorario'] = (int)$row['id_honorario'];
			$row['id_cliente'] = (int)$row['id_cliente'];
			$row['activo'] = (int)($row['activo'] ?? 1);
			$row['especial'] = (int)($row['especial'] ?? 0);
			$row['total'] = round((float)$row['total'], 2);
			$row['pagado'] = round((float)$row['pagado'], 2);
			$row['pendiente'] = round((float)$row['pendiente'], 2);
		}
		unset($row);

		return $rows;
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	public function getDashboardParcialidadesPendientes(int $idCliente, array $filters): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select(
			'p.id_parcialidad',
			'p.id_honorario',
			'p.numero_parcialidad',
			'p.pfecha_inicio',
			'p.pfecha_fin',
			'p.importe_parcialidad',
			'p.pagado',
			'h.tipo_servicio',
			'h.tipo_honorario',
			'h.tipo_moneda'
		)
			->from($this->getTableName(), 'p')
			->innerJoin('p', 'empleados_honorarios', 'h', $qb->expr()->eq('p.id_honorario', 'h.id_honorario'))
			->innerJoin('h', 'empleados_clientes', 'c', $qb->expr()->eq('h.id_cliente', 'c.id'))
			->where($qb->expr()->eq(
				'h.id_cliente',
				$qb->createNamedParameter($idCliente, IQueryBuilder::PARAM_INT)
			))
			->andWhere($qb->expr()->in(
				'p.pagado',
				$qb->createNamedParameter(
					[
						honorariosParcialidades::PENDIENTE,
						honorariosParcialidades::FACTURADA,
					],
					IQueryBuilder::PARAM_INT_ARRAY
				)
			));

		$this->applyDashboardFeeFilters($qb, array_merge($filters, ['id_cliente' => $idCliente]));

		$qb->orderBy('p.pfecha_inicio', 'ASC')
			->addOrderBy('p.numero_parcialidad', 'ASC');

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		foreach ($rows as &$row) {
			$row['id_parcialidad'] = (int)$row['id_parcialidad'];
			$row['id_honorario'] = (int)$row['id_honorario'];
			$row['numero_parcialidad'] = (int)$row['numero_parcialidad'];
			$row['importe_parcialidad'] = round((float)$row['importe_parcialidad'], 2);
			$row['pagado'] = (int)$row['pagado'];
		}
		unset($row);

		return $rows;
	}

	private function applyDashboardFeeFilters($qb, array $filters): void {
		if (!empty($filters['id_cliente'])) {
			$qb->andWhere($qb->expr()->eq(
				'c.id',
				$qb->createNamedParameter((int)$filters['id_cliente'], IQueryBuilder::PARAM_INT)
			));
		}

		if (!empty($filters['cliente_padre'])) {
			$qb->andWhere($qb->expr()->eq(
				'c.cliente_padre',
				$qb->createNamedParameter((int)$filters['cliente_padre'], IQueryBuilder::PARAM_INT)
			));
		}

		if (!empty($filters['lider_proyecto'])) {
			$qb->andWhere($qb->expr()->eq(
				'c.lider_proyecto',
				$qb->createNamedParameter((int)$filters['lider_proyecto'], IQueryBuilder::PARAM_INT)
			));
		}

		if (isset($filters['estado']) && $filters['estado'] !== null) {
			$qb->andWhere($qb->expr()->eq(
				'c.estado',
				$qb->createNamedParameter((int)$filters['estado'], IQueryBuilder::PARAM_INT)
			));
		}

		if (isset($filters['especial']) && $filters['especial'] !== null) {
			$qb->andWhere($qb->expr()->eq(
				'c.especial',
				$qb->createNamedParameter((int)$filters['especial'], IQueryBuilder::PARAM_INT)
			));
		}

		if (!empty($filters['tipo_honorario'])) {
			$qb->andWhere($qb->expr()->eq(
				'h.tipo_honorario',
				$qb->createNamedParameter((string)$filters['tipo_honorario'])
			));
		}

		$fechaInicio = $filters['fecha_inicio'] ?? null;
		$fechaFin = $filters['fecha_fin'] ?? null;

		if (is_string($fechaInicio) && $fechaInicio !== '') {
			$qb->andWhere($qb->expr()->orX(
				$qb->expr()->gte('p.pfecha_fin', $qb->createNamedParameter($fechaInicio)),
				$qb->expr()->isNull('p.pfecha_fin')
			));
		}

		if (is_string($fechaFin) && $fechaFin !== '') {
			$qb->andWhere($qb->expr()->orX(
				$qb->expr()->lte('p.pfecha_inicio', $qb->createNamedParameter($fechaFin)),
				$qb->expr()->isNull('p.pfecha_inicio')
			));
		}
	}
}