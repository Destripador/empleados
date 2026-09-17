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
		$summary['servicios'] = $this->buildServiceBreakdown($serviceRows);

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
			$moneda = $this->normalizeCurrency($honorario['tipo_moneda'] ?? 'MXN');
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
			$moneda = $this->normalizeCurrency($row['tipo_moneda'] ?? 'MXN');
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
	private function buildServiceBreakdown(array $rows): array {
		$porMoneda = [];

		foreach ($rows as $row) {
			$moneda = $this->normalizeCurrency($row['tipo_moneda'] ?? 'MXN');
			$especial = (int)($row['especial'] ?? 0) === 1;
			$importe = (float)($row['total'] ?? 0);

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
			];

			$porMoneda[$moneda][$key]['total'] += $importe;

			$anioKey = $anio !== null ? (string)$anio : 'sin_anio';
			$porMoneda[$moneda][$key]['anios'][$anioKey] ??= [
				'anio' => $anio,
				'total' => 0.0,
			];
			$porMoneda[$moneda][$key]['anios'][$anioKey]['total'] += $importe;
		}

		$ordenFijos = [
			'auditoria financiera y fiscal' => 0,
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

				$anios = array_values($item['anios']);
				foreach ($anios as &$a) {
					$a['total'] = round($a['total'], 2);
				}
				unset($a);

				usort($anios, static function (array $a, array $b): int {
					if ($a['anio'] === null) {
						return 1;
					}
					if ($b['anio'] === null) {
						return -1;
					}
					return $b['anio'] <=> $a['anio'];
				});

				$item['anios'] = $anios;
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
			'auditoria financiera y fiscal' => 'Auditoria Financiera y Fiscal',
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
		return $moneda !== '' ? $moneda : 'MXN';
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
