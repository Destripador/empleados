<?php

declare(strict_types=1);

namespace OCA\Empleados\Service;

use OCA\Empleados\Db\clientesMapper;
use OCA\Empleados\Db\honorariosParcialidades;
use OCA\Empleados\Db\honorariosParcialidadesMapper;

/**
 * Agrega métricas de clientes y honorarios.
 *
 * No calcula cartera vencida: las parcialidades tienen periodo
 * (pfecha_inicio / pfecha_fin), no fecha de vencimiento de cobro.
 */
class ClientesDashboardService {
	/** Cobrado = PAGADA. Pendiente = PENDIENTE + FACTURADA. */
	public const ESTADOS_COBRADOS = [honorariosParcialidades::PAGADA];
	public const ESTADOS_PENDIENTES = [
		honorariosParcialidades::PENDIENTE,
		honorariosParcialidades::FACTURADA,
	];
	public const ESTADO_PENDIENTE = honorariosParcialidades::PENDIENTE;
	public const TOP_LIMIT = 10;
	public const CONCENTRACION = [5, 10];

	public const MONEDA_BASE = 'MXN';
	public const FX_DETALLE_LIMIT = 24;
	public const FX_DECIMALES = 4;

	private clientesMapper $clientesMapper;
	private honorariosParcialidadesMapper $parcialidadesMapper;

	public function __construct(
		clientesMapper $clientesMapper,
		honorariosParcialidadesMapper $parcialidadesMapper
	) {
		$this->clientesMapper = $clientesMapper;
		$this->parcialidadesMapper = $parcialidadesMapper;
	}

	public function getSummary(array $filters): array {
		$filters = $this->normalizeFilters($filters);
		$clientes = $this->clientesMapper->findDashboardCatalog($filters);
		$feeRows = $this->parcialidadesMapper->getDashboardFeeRows($filters);
		$monthlyGenerated = $this->parcialidadesMapper->getMonthlyGenerated($filters);
		$monthlyCollected = $this->parcialidadesMapper->getMonthlyCollected($filters);

		$summary = $this->buildSummary($clientes, $feeRows, $monthlyGenerated, $monthlyCollected);

		$serviceRows = $this->parcialidadesMapper->getServicioBreakdownRows($filters);
		$summary['servicios'] = $this->buildServiceBreakdown($serviceRows, $clientes);

		$fxRows = $this->parcialidadesMapper->getDashboardFxRows($filters);
		$fxByClient = $this->buildFxByClient($fxRows);

		$summary['tabla'] = array_map(
			static fn (array $row): array => $row + ['fx' => $fxByClient[(int)$row['id']] ?? []],
			$summary['tabla']
		);

		if ($filters['solo_pendientes']) {
			$summary['tabla'] = array_values(array_filter(
				$summary['tabla'],
				static fn (array $row): bool => (float)$row['pendiente'] > 0.009
			));
			$summary['ranking_pendiente'] = $this->buildPendingRanking($summary['tabla']);
			$summary['concentracion'] = $this->buildConcentration($summary['tabla']);
			$summary['monedas'] = $this->buildCurrencyTotals($summary['tabla']);
		}

		return $summary;
	}

	/**
	 * Desglose de la conversión a moneda base, por cliente y moneda de origen.
	 *
	 * Solo entran parcialidades PAGADAS de honorarios en moneda extranjera.
	 * Las que no tienen tipo de cambio registrado no suman a los totales:
	 * se cuentan aparte en 'sin_tipo_cambio' para poder avisarlo en la UI.
	 *
	 * @param list<array<string,mixed>> $rows
	 * @return array<int, list<array<string,mixed>>> indexado por id de cliente
	 */
	private function buildFxByClient(array $rows): array {
		$acc = [];

		foreach ($rows as $row) {
			$id = (int)($row['id_cliente'] ?? 0);
			$moneda = $this->normalizeCurrency($row['tipo_moneda'] ?? self::MONEDA_BASE);

			if ($id <= 0 || $moneda === self::MONEDA_BASE) {
				continue;
			}

			$importe = (float)($row['importe_parcialidad'] ?? 0);
			$estado = (int)($row['pagado'] ?? honorariosParcialidades::PENDIENTE);
			$servicio = (string)($row['tipo_servicio'] ?? '');

			$tcFactura = isset($row['cambio_moneda_factura']) && $row['cambio_moneda_factura'] !== null
				? (float)$row['cambio_moneda_factura']
				: null;
			$tcPago = isset($row['cambio_moneda']) && $row['cambio_moneda'] !== null
				? (float)$row['cambio_moneda']
				: null;

			$mxnFactura = ($tcFactura !== null && $tcFactura > 0) ? $importe * $tcFactura : null;
			$mxnPago = ($tcPago !== null && $tcPago > 0) ? $importe * $tcPago : null;

			$diferencia = ($mxnPago !== null && $mxnFactura !== null)
				? round($mxnPago - $mxnFactura, 2)
				: null;

			// "Mejor disponible" para las estadísticas agregadas del bloque.
			$tcBest = $tcPago ?? $tcFactura;
			$mxnBest = $mxnPago ?? $mxnFactura;

			$acc[$id][$moneda] ??= [
				'moneda' => $moneda,
				'moneda_destino' => self::MONEDA_BASE,
				'convertidas' => 0,
				'sin_tipo_cambio' => 0,
				'importe_origen' => 0.0,
				'importe_mxn' => 0.0,
				'tipo_cambio_min' => null,
				'tipo_cambio_max' => null,
				'tipo_cambio_promedio' => 0.0,
				'ganancia_cambiaria' => 0.0,
				'servicios' => [],
			];

			$servicioKey = $servicio !== '' ? $servicio : 'Sin especificar';

			$acc[$id][$moneda]['servicios'][$servicioKey] ??= [
				'servicio' => $servicioKey,
				'subtotal_origen' => 0.0,
				'subtotal_mxn' => 0.0,
				'subtotal_diferencia' => 0.0,
				'parcialidades' => [],
			];

			if ($tcBest !== null && $tcBest > 0) {
				$min = $acc[$id][$moneda]['tipo_cambio_min'];
				$max = $acc[$id][$moneda]['tipo_cambio_max'];

				$acc[$id][$moneda]['convertidas']++;
				$acc[$id][$moneda]['importe_origen'] += $importe;
				$acc[$id][$moneda]['importe_mxn'] += $mxnBest;
				$acc[$id][$moneda]['tipo_cambio_min'] = $min === null ? $tcBest : min($min, $tcBest);
				$acc[$id][$moneda]['tipo_cambio_max'] = $max === null ? $tcBest : max($max, $tcBest);

				$acc[$id][$moneda]['servicios'][$servicioKey]['subtotal_origen'] += $importe;
				$acc[$id][$moneda]['servicios'][$servicioKey]['subtotal_mxn'] += $mxnBest;
			} else {
				$acc[$id][$moneda]['sin_tipo_cambio']++;
			}

			if ($diferencia !== null) {
				$acc[$id][$moneda]['ganancia_cambiaria'] += $diferencia;
				$acc[$id][$moneda]['servicios'][$servicioKey]['subtotal_diferencia'] += $diferencia;
			}

			$acc[$id][$moneda]['servicios'][$servicioKey]['parcialidades'][] = [
				'id_parcialidad' => (int)($row['id_parcialidad'] ?? 0),
				'numero' => (int)($row['numero_parcialidad'] ?? 0),
				'servicio' => $servicioKey,
				'estado' => $estado,
				'fecha_factura' => $row['fecha_factura'] ?? null,
				'fecha_pago' => $row['fecha_pago'] ?? null,
				'importe' => round($importe, 2),
				'tipo_cambio_factura' => $tcFactura !== null ? round($tcFactura, self::FX_DECIMALES) : null,
				'importe_mxn_factura' => $mxnFactura !== null ? round($mxnFactura, 2) : null,
				'tipo_cambio_pago' => $tcPago !== null ? round($tcPago, self::FX_DECIMALES) : null,
				'importe_mxn_pago' => $mxnPago !== null ? round($mxnPago, 2) : null,
				'diferencia_cambiaria' => $diferencia,
			];
		}

		$result = [];

		foreach ($acc as $id => $porMoneda) {
			$lista = [];

			foreach ($porMoneda as $fx) {
				// Ordena las parcialidades de cada honorario: #1, #2, #3...
				foreach ($fx['servicios'] as &$servicioGrupo) {
					usort(
						$servicioGrupo['parcialidades'],
						static fn (array $a, array $b): int => $a['numero'] <=> $b['numero']
					);
					$servicioGrupo['subtotal_origen'] = round($servicioGrupo['subtotal_origen'], 2);
					$servicioGrupo['subtotal_mxn'] = round($servicioGrupo['subtotal_mxn'], 2);
					$servicioGrupo['subtotal_diferencia'] = round($servicioGrupo['subtotal_diferencia'], 2);
				}
				unset($servicioGrupo);

				// Honorarios entre sí: el que más convirtió a MXN primero.
				$servicios = array_values($fx['servicios']);
				usort($servicios, static fn (array $a, array $b): int => $b['subtotal_mxn'] <=> $a['subtotal_mxn']);

				$fx['servicios'] = $servicios;
				$fx['mostradas'] = array_sum(array_map(
					static fn (array $s): int => count($s['parcialidades']),
					$servicios
				));
				$fx['detalle_truncado'] = false;

				$fx['importe_origen'] = round($fx['importe_origen'], 2);
				$fx['importe_mxn'] = round($fx['importe_mxn'], 2);
				$fx['ganancia_cambiaria'] = round($fx['ganancia_cambiaria'], 2);

				$fx['tipo_cambio_promedio'] = $fx['importe_origen'] > 0.009
					? round($fx['importe_mxn'] / $fx['importe_origen'], self::FX_DECIMALES)
					: 0.0;

				$fx['tipo_cambio_min'] = $fx['tipo_cambio_min'] !== null
					? round($fx['tipo_cambio_min'], self::FX_DECIMALES)
					: null;
				$fx['tipo_cambio_max'] = $fx['tipo_cambio_max'] !== null
					? round($fx['tipo_cambio_max'], self::FX_DECIMALES)
					: null;

				$lista[] = $fx;
			}

			usort($lista, static fn (array $a, array $b): int => strcmp($a['moneda'], $b['moneda']));

			$result[$id] = $lista;
		}

		return $result;
	}

	public function getClienteDetail(int $idCliente, array $filters = []): ?array {
		$cliente = $this->clientesMapper->findById($idCliente);
		if ($cliente === []) {
			return null;
		}

		$filters = $this->normalizeFilters($filters);
		$honorarios = $this->parcialidadesMapper->getDashboardHonorariosByCliente($idCliente, $filters);
		$pendientes = $this->parcialidadesMapper->getDashboardParcialidadesPendientes($idCliente, $filters);

		$porMoneda = [];
		foreach ($honorarios as $honorario) {
			$moneda = $this->normalizeCurrency($honorario['tipo_moneda'] ?? self::MONEDA_BASE);
			if (!isset($porMoneda[$moneda])) {
				$porMoneda[$moneda] = $this->emptyMoney();
			}
			$porMoneda[$moneda]['total'] += (float)($honorario['total'] ?? 0);
			$porMoneda[$moneda]['pagado'] += (float)($honorario['pagado'] ?? 0);
			$porMoneda[$moneda]['pendiente'] += (float)($honorario['pendiente'] ?? 0);
		}

		foreach ($porMoneda as &$row) {
			$row = $this->withPendingRatio($row);
		}
		unset($row);

		$fxRows = $this->parcialidadesMapper->getDashboardFxRows(
			array_merge($filters, ['id_cliente' => $idCliente])
		);
		$fx = $this->buildFxByClient($fxRows)[$idCliente] ?? [];

		return [
			'cliente' => [
				'id' => (int)$cliente['id'],
				'nombre' => (string)($cliente['nombre'] ?? ''),
				'razon_social' => $cliente['razon_social'] ?? null,
				'logo' => $cliente['logo'] ?? null,
				'estado' => (int)($cliente['estado'] ?? 1),
				'especial' => (int)($cliente['especial'] ?? 0),
				'cliente_padre' => $cliente['cliente_padre'] ?? null,
				'lider_proyecto' => $cliente['lider_proyecto'] ?? null,
			],
			'totales' => array_values($porMoneda),
			'fx' => $fx,
			'honorarios' => $honorarios,
			'parcialidades_pendientes' => $pendientes,
			'analisis' => $this->capabilities(),
		];
	}

	/**
	 * @param list<array<string,mixed>> $clientes
	 * @param list<array<string,mixed>> $feeRows
	 * @param list<array<string,mixed>> $monthlyGenerated
	 * @param list<array<string,mixed>> $monthlyCollected
	 */
	public function buildSummary(
		array $clientes,
		array $feeRows,
		array $monthlyGenerated = [],
		array $monthlyCollected = []
	): array {
		$catalogo = $this->buildCatalog($clientes);
		$tabla = $this->mergeClientFees($clientes, $feeRows);
		$monedas = $this->buildCurrencyTotals($tabla);
		$ranking = $this->buildPendingRanking($tabla);
		$concentracion = $this->buildConcentration($tabla);

		return [
			'catalogo' => $catalogo,
			'monedas' => $monedas,
			'ranking_pendiente' => $ranking,
			'concentracion' => $concentracion,
			'tabla' => $tabla,
			'evolucion' => $this->mergeMonthly($monthlyGenerated, $monthlyCollected),
			'analisis' => $this->capabilities(),
		];
	}

	public function capabilities(): array {
		return [
			'honorarios_pendientes' => true,
			'cartera_vencida' => false,
			'antiguedad' => false,
			'pagos_parciales_por_parcialidad' => true,
			'pagos_fraccionados_en_parcialidad' => false,
			'desglose_tipo_cambio' => true,
		];
	}

	public function normalizeFilters(array $filters): array {
		$idCliente = $this->positiveInt($filters['id_cliente'] ?? null);
		$clientePadre = $this->positiveInt($filters['cliente_padre'] ?? null);
		$lider = $this->positiveInt($filters['lider_proyecto'] ?? null);
		$estado = $this->nullableInt($filters['estado'] ?? null);
		$especial = $this->nullableInt($filters['especial'] ?? null);
		$tipo = strtolower(trim((string)($filters['tipo_honorario'] ?? '')));
		if (!in_array($tipo, ['parcial', 'iguala', 'eventual'], true)) {
			$tipo = null;
		}

		return [
			'id_cliente' => $idCliente,
			'cliente_padre' => $clientePadre,
			'lider_proyecto' => $lider,
			'estado' => $estado,
			'especial' => $especial,
			'tipo_honorario' => $tipo,
			'solo_pendientes' => $this->isTruthy($filters['solo_pendientes'] ?? null),
			'fecha_inicio' => $this->normalizeDate($filters['fecha_inicio'] ?? null),
			'fecha_fin' => $this->normalizeDate($filters['fecha_fin'] ?? null),
		];
	}

	private function buildCatalog(array $clientes): array {
		$total = 0;
		$activos = 0;
		$grupos = 0;
		$subempresas = 0;
		$especiales = 0;

		foreach ($clientes as $cliente) {
			$total++;
			$activo = (int)($cliente['estado'] ?? 1) === 1;
			if ($activo) {
				$activos++;
			}
			if ((int)($cliente['cliente_padre'] ?? 0) === 0) {
				$grupos++;
			} else {
				$subempresas++;
			}
			if ((int)($cliente['especial'] ?? 0) === 1) {
				$especiales++;
			}
		}

		return [
			'total' => $total,
			'activos' => $activos,
			'inactivos' => $total - $activos,
			'grupos' => $grupos,
			'subempresas' => $subempresas,
			'especiales' => $especiales,
		];
	}

	private function mergeClientFees(array $clientes, array $feeRows): array {
		$byClient = [];
		foreach ($clientes as $cliente) {
			$id = (int)($cliente['id'] ?? 0);
			if ($id <= 0) {
				continue;
			}
			$byClient[$id] = [
				'id' => $id,
				'nombre' => (string)($cliente['nombre'] ?? ''),
				'logo' => $cliente['logo'] ?? null,
				'estado' => (int)($cliente['estado'] ?? 1),
				'especial' => (int)($cliente['especial'] ?? 0),
				'cliente_padre' => $cliente['cliente_padre'] !== null ? (int)$cliente['cliente_padre'] : null,
				'lider_proyecto' => $cliente['lider_proyecto'] !== null ? (int)$cliente['lider_proyecto'] : null,
				'monedas' => [],
				'total' => 0.0,
				'pagado' => 0.0,
				'pendiente' => 0.0,
			];
		}

		foreach ($feeRows as $row) {
			$id = (int)($row['id_cliente'] ?? $row['id'] ?? 0);
			if ($id <= 0 || !isset($byClient[$id])) {
				continue;
			}
			$moneda = $this->normalizeCurrency($row['tipo_moneda'] ?? self::MONEDA_BASE);
			$total = (float)($row['total'] ?? 0);
			$pagado = (float)($row['pagado'] ?? 0);
			$pendiente = (float)($row['pendiente'] ?? 0);

			if (!isset($byClient[$id]['monedas'][$moneda])) {
				$byClient[$id]['monedas'][$moneda] = $this->emptyMoney();
			}
			$byClient[$id]['monedas'][$moneda]['total'] += $total;
			$byClient[$id]['monedas'][$moneda]['pagado'] += $pagado;
			$byClient[$id]['monedas'][$moneda]['pendiente'] += $pendiente;
			$byClient[$id]['total'] += $total;
			$byClient[$id]['pagado'] += $pagado;
			$byClient[$id]['pendiente'] += $pendiente;
		}

		$tabla = [];
		foreach ($byClient as $row) {
			$row['monedas'] = array_values(array_map(
				fn (array $money, string $moneda): array => $this->withPendingRatio($money + ['moneda' => $moneda]),
				$row['monedas'],
				array_keys($row['monedas'])
			));
			$row = $this->withPendingRatio($row);
			$tabla[] = $row;
		}

		usort($tabla, static function (array $a, array $b): int {
			$cmp = $b['pendiente'] <=> $a['pendiente'];
			if ($cmp !== 0) {
				return $cmp;
			}
			return strcasecmp((string)$a['nombre'], (string)$b['nombre']);
		});

		return $tabla;
	}

	private function buildCurrencyTotals(array $tabla): array {
		$totales = [];
		$clientesPendientes = [];

		foreach ($tabla as $row) {
			foreach ($row['monedas'] as $money) {
				$moneda = $money['moneda'];
				if (!isset($totales[$moneda])) {
					$totales[$moneda] = $this->emptyMoney() + [
						'moneda' => $moneda,
						'clientes_con_pendiente' => 0,
					];
					$clientesPendientes[$moneda] = [];
				}
				$totales[$moneda]['total'] += (float)$money['total'];
				$totales[$moneda]['pagado'] += (float)$money['pagado'];
				$totales[$moneda]['pendiente'] += (float)$money['pendiente'];
				if ((float)$money['pendiente'] > 0.009) {
					$clientesPendientes[$moneda][(int)$row['id']] = true;
				}
			}
		}

		$result = [];
		foreach ($totales as $moneda => $row) {
			$row['clientes_con_pendiente'] = count($clientesPendientes[$moneda] ?? []);
			$result[] = $this->withPendingRatio($row);
		}

		usort($result, static fn (array $a, array $b): int => $b['pendiente'] <=> $a['pendiente']);

		return $result;
	}

	private function buildPendingRanking(array $tabla): array {
		$ranked = array_values(array_filter(
			$tabla,
			static fn (array $row): bool => (float)$row['pendiente'] > 0.009
		));

		return array_slice($ranked, 0, self::TOP_LIMIT);
	}

	private function buildConcentration(array $tabla): array {
		$totalPendiente = 0.0;
		foreach ($tabla as $row) {
			$totalPendiente += (float)$row['pendiente'];
		}

		$result = [
			'total_pendiente' => round($totalPendiente, 2),
		];

		foreach (self::CONCENTRACION as $n) {
			$suma = 0.0;
			$slice = array_slice($tabla, 0, $n);
			foreach ($slice as $row) {
				$suma += (float)$row['pendiente'];
			}
			$result['top' . $n] = [
				'clientes' => $n,
				'importe' => round($suma, 2),
				'porcentaje' => $totalPendiente > 0.009
					? round(($suma / $totalPendiente) * 100, 2)
					: 0.0,
			];
		}

		return $result;
	}

	/**
	 * Agrupa las filas de honorarios por servicio
	 *
	 * @param list<array<string,mixed>> $rows
	 * @return list<array{moneda:string, items:list<array<string,mixed>>}>
	 */
	private function buildServiceBreakdown(array $rows, array $clientes = []): array {
		$catalogo = [];
		foreach ($clientes as $cliente) {
			$id = (int)($cliente['id'] ?? 0);
			if ($id > 0) {
				$catalogo[$id] = [
					'nombre' => (string)($cliente['nombre'] ?? ''),
					'logo' => $cliente['logo'] ?? null,
				];
			}
		}

		$porMoneda = [];

		foreach ($rows as $row) {
			$moneda = $this->normalizeCurrency($row['tipo_moneda'] ?? self::MONEDA_BASE);
			$especial = (int)($row['especial'] ?? 0) === 1;
			$importe = (float)($row['total'] ?? 0);
			$idCliente = (int)($row['id_cliente'] ?? 0);

			[$servicio, $anio, $esFijo] = $this->normalizarServicio(
				(string)($row['tipo_servicio'] ?? ''),
				$especial
			);

			$porMoneda[$moneda] ??= [];
			$key = mb_strtolower($servicio);

			$porMoneda[$moneda][$key] ??= [
				'servicio' => $servicio,
				'es_fijo' => $esFijo,
				'total' => 0.0,
				'anios' => [],
				'clientes' => [],
			];

			$anioKey = $anio !== null ? (string)$anio : 'sin_anio';

			$porMoneda[$moneda][$key]['total'] += $importe;
			$porMoneda[$moneda][$key]['anios'][$anioKey] ??= ['anio' => $anio, 'total' => 0.0];
			$porMoneda[$moneda][$key]['anios'][$anioKey]['total'] += $importe;

			if ($idCliente > 0) {
				$porMoneda[$moneda][$key]['clientes'][$idCliente] ??= [
					'id' => $idCliente,
					'nombre' => $catalogo[$idCliente]['nombre'] ?? ('Cliente #' . $idCliente),
					'logo' => $catalogo[$idCliente]['logo'] ?? null,
					'total' => 0.0,
					'anios' => [],
				];

				$porMoneda[$moneda][$key]['clientes'][$idCliente]['total'] += $importe;
				$porMoneda[$moneda][$key]['clientes'][$idCliente]['anios'][$anioKey] ??= [
					'anio' => $anio,
					'total' => 0.0,
				];
				$porMoneda[$moneda][$key]['clientes'][$idCliente]['anios'][$anioKey]['total'] += $importe;
			}
		}

		$ordenFijos = [
			'auditoria fiscal y financiera' => 0,
			'auditoria financiera' => 1,
			'auditoria fiscal' => 2,
			'procedimientos convenidos' => 3,
			'trabajos especiales' => 4,
		];

		$result = [];

		foreach ($porMoneda as $moneda => $items) {
			$lista = [];

			foreach ($items as $item) {
				$item['total'] = round($item['total'], 2);
				$item['anios'] = $this->finalizeAnios($item['anios']);

				$clientesItem = [];
				foreach ($item['clientes'] as $c) {
					$c['total'] = round($c['total'], 2);
					$c['anios'] = $this->finalizeAnios($c['anios']);
					$clientesItem[] = $c;
				}

				usort($clientesItem, static function (array $a, array $b): int {
					$cmp = $b['total'] <=> $a['total'];
					return $cmp !== 0 ? $cmp : strcasecmp($a['nombre'], $b['nombre']);
				});

				$item['clientes'] = $clientesItem;
				$lista[] = $item;
			}

			usort($lista, static function (array $a, array $b) use ($ordenFijos): int {
				$oa = $ordenFijos[mb_strtolower($a['servicio'])] ?? 99;
				$ob = $ordenFijos[mb_strtolower($b['servicio'])] ?? 99;

				if ($oa !== $ob) {
					return $oa <=> $ob;
				}

				return strcasecmp($a['servicio'], $b['servicio']);
			});

			$result[] = ['moneda' => $moneda, 'items' => $lista];
		}

		usort($result, static fn (array $a, array $b): int => strcmp($a['moneda'], $b['moneda']));

		return $result;
	}

	/**
	 * Redondea y ordena los totales por año
	 *
	 * @param array<string, array{anio:?int,total:float}> $anios
	 * @return list<array{anio:?int,total:float}>
	 */
	private function finalizeAnios(array $anios): array {
		$lista = array_values($anios);

		foreach ($lista as &$a) {
			$a['total'] = round($a['total'], 2);
		}
		unset($a);

		usort($lista, static function (array $a, array $b): int {
			if ($a['anio'] === null) {
				return 1;
			}
			if ($b['anio'] === null) {
				return -1;
			}
			return $b['anio'] <=> $a['anio'];
		});

		return $lista;
	}

	/**
	 * Extrae el nombre base y el año
	 *
	 * @return array{0:string, 1:?int, 2:bool} [servicio, anio, es_fijo]
	 */
	private function normalizarServicio(string $tipoServicio, bool $especial): array {
		$nombreCompleto = trim($tipoServicio);
		$nombreBase = $nombreCompleto;
		$anio = null;

		if (preg_match('/^(.*?)(?:\s-\s(\d{4}))?$/', $nombreCompleto, $m) === 1) {
			$nombreBase = trim($m[1]);
			if (!empty($m[2])) {
				$anio = (int)$m[2];
			}
		}

		if ($especial) {
			return ['Trabajos Especiales', $anio, true];
		}

		$fijos = [
			'auditoria fiscal y financiera' => 'Auditoria Fiscal y Financiera',
			'auditoria financiera' => 'Auditoria Financiera',
			'auditoria fiscal' => 'Auditoria Fiscal',
			'procedimientos convenidos' => 'Procedimientos Convenidos',
			'trabajos especiales' => 'Trabajos Especiales',
		];

		$key = mb_strtolower($nombreBase);

		if (isset($fijos[$key])) {
			return [$fijos[$key], $anio, true];
		}

		return [$nombreBase !== '' ? $nombreBase : 'Sin especificar', $anio, false];
	}

	private function mergeMonthly(array $generated, array $collected): array {
		$byMonth = [];
		foreach ($generated as $row) {
			$mes = $this->normalizeMonth($row['mes'] ?? null);
			if ($mes === null) {
				continue;
			}
			$byMonth[$mes] ??= ['mes' => $mes, 'generado' => 0.0, 'cobrado' => 0.0];
			$byMonth[$mes]['generado'] += (float)($row['importe'] ?? $row['generado'] ?? 0);
		}
		foreach ($collected as $row) {
			$mes = $this->normalizeMonth($row['mes'] ?? null);
			if ($mes === null) {
				continue;
			}
			$byMonth[$mes] ??= ['mes' => $mes, 'generado' => 0.0, 'cobrado' => 0.0];
			$byMonth[$mes]['cobrado'] += (float)($row['importe'] ?? $row['cobrado'] ?? 0);
		}

		ksort($byMonth);
		$months = array_values($byMonth);
		if (count($months) > 12) {
			$months = array_slice($months, -12);
		}

		return $months;
	}

	private function emptyMoney(): array {
		return [
			'total' => 0.0,
			'pagado' => 0.0,
			'pendiente' => 0.0,
			'porcentaje_pendiente' => 0.0,
		];
	}

	private function withPendingRatio(array $row): array {
		$total = (float)($row['total'] ?? 0);
		$pendiente = (float)($row['pendiente'] ?? 0);
		$row['total'] = round($total, 2);
		$row['pagado'] = round((float)($row['pagado'] ?? 0), 2);
		$row['pendiente'] = round($pendiente, 2);
		$row['porcentaje_pendiente'] = $total > 0.009
			? round(($pendiente / $total) * 100, 2)
			: 0.0;
		return $row;
	}

	private function normalizeCurrency(?string $value): string {
		$moneda = strtoupper(trim((string)$value));
		return $moneda !== '' ? $moneda : self::MONEDA_BASE;
	}

	private function normalizeDate(mixed $value): ?string {
		$value = trim((string)$value);
		if ($value === '') {
			return null;
		}
		$date = \DateTimeImmutable::createFromFormat('Y-m-d', substr($value, 0, 10));
		return $date instanceof \DateTimeImmutable ? $date->format('Y-m-d') : null;
	}

	private function normalizeMonth(mixed $value): ?string {
		$value = trim((string)$value);
		if (preg_match('/^\d{4}-\d{2}/', $value, $match) === 1) {
			return substr($match[0], 0, 7);
		}
		return null;
	}

	private function positiveInt(mixed $value): ?int {
		$id = $this->nullableInt($value);
		return ($id !== null && $id > 0) ? $id : null;
	}

	private function nullableInt(mixed $value): ?int {
		if ($value === null || $value === '') {
			return null;
		}
		if (is_numeric($value)) {
			return (int)$value;
		}
		return null;
	}

	private function isTruthy(mixed $value): bool {
		return $value === true
			|| $value === 1
			|| $value === '1'
			|| $value === 'true';
	}
}