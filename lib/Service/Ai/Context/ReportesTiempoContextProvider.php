<?php

declare(strict_types=1);

namespace OCA\Empleados\Service\Ai\Context;

use OCA\Empleados\Db\reportetiempoMapper;

final class ReportesTiempoContextProvider {
	private const MAX_BYTES = 524288;
	private const MAX_REPORTS = 300;
	private const MAX_EMPLOYEES = 200;
	private const MAX_ACTIVITIES = 100;
	private const MAX_PROJECTS = 100;
	private const MAX_PENDING = 200;
	private const MAX_DESCRIPTION_LENGTH = 1000;

	public function __construct(
		private reportetiempoMapper $reportMapper,
		private ReportesTiempoContextSelector $selector,
	) {
	}

	public function build(string $question, string $userId, array $parameters): array {
		$period = $this->resolvePeriod($parameters['periodo'] ?? []);
		$selection = $this->selector->select($question);
		if ($selection['restriccion_dominio'] !== null) {
			return $this->restrictedContext(
				$this->publicPeriod($period),
				$selection['restriccion_dominio'],
			);
		}
		$employee = $parameters['empleado']['id'] ?? null;
		$referenceDate = $this->referenceDate($period);
		$queryReferenceDate = $referenceDate
			?? $period['fecha_inicio_consulta'];
		$aggregateRows = $this->reportMapper->getAiAggregates(
			$userId,
			$period['fecha_inicio_consulta'],
			$period['fecha_fin_consulta'],
			$queryReferenceDate,
			$employee,
		);
		$detailRows = $this->reportMapper->getAiRecentReports(
			$userId,
			$period['fecha_inicio_consulta'],
			$period['fecha_fin_consulta'],
			$employee,
			self::MAX_REPORTS + 1,
		);

		$aggregated = $this->aggregate($aggregateRows, $referenceDate);
		$reportsTruncated = count($detailRows) > self::MAX_REPORTS;
		$reports = array_map(
			fn (array $row): array => $this->normalizeReport($row),
			array_slice($detailRows, 0, self::MAX_REPORTS),
		);
		$descriptionsTruncated = array_reduce(
			$reports,
			static fn (bool $carry, array $report): bool => $carry
				|| ($report['descripcion_truncada'] ?? false),
			false,
		);
		$employeeSummary = $this->limitSummary(
			$aggregated['empleados'],
			self::MAX_EMPLOYEES,
		);
		$activitySummary = $this->limitSummary(
			$aggregated['actividades'],
			self::MAX_ACTIVITIES,
		);
		$projectSummary = $this->limitSummary(
			$aggregated['proyectos'],
			self::MAX_PROJECTS,
		);
		$pending = array_slice(
			$aggregated['pendientes'],
			0,
			self::MAX_PENDING,
		);
		$limits = [
			'reportes' => $this->limitMetadata(
				self::MAX_REPORTS,
				$aggregated['kpis']['total_reportes'],
				$reportsTruncated,
			),
			'resumen_por_empleado' => $this->limitMetadata(
				self::MAX_EMPLOYEES,
				count($aggregated['empleados']),
			),
			'resumen_por_actividad' => $this->limitMetadata(
				self::MAX_ACTIVITIES,
				count($aggregated['actividades']),
			),
			'resumen_por_proyecto' => $this->limitMetadata(
				self::MAX_PROJECTS,
				count($aggregated['proyectos']),
			),
			'pendientes' => $this->limitMetadata(
				self::MAX_PENDING,
				count($aggregated['pendientes']),
			),
		];
		$truncated = array_reduce(
			$limits,
			static fn (bool $carry, array $limit): bool => $carry || $limit['truncado'],
			false,
		) || $aggregated['truncado'] || $descriptionsTruncated;
		$publicPeriod = $this->publicPeriod($period);
		$context = [
			'contexto' => [
				'scope' => 'reportes-tiempo-admin',
				'generado_en' => (new \DateTimeImmutable())->format(DATE_ATOM),
				'periodo' => $publicPeriod,
				'empleados_incluidos' => $aggregated['empleados_visibles'],
				'registros_incluidos' => count($reports)
					+ count($employeeSummary)
					+ count($activitySummary)
					+ count($projectSummary)
					+ count($pending),
				'truncado' => $truncated,
				'secciones' => [
					'periodo',
					'kpis',
					'criterio_cumplimiento',
					'resumen_por_empleado',
					'resumen_por_actividad',
					'resumen_por_proyecto',
					'reportes',
					'pendientes',
				],
			],
			'periodo' => $publicPeriod,
			'restriccion_dominio' => $selection['restriccion_dominio'],
			'enfoque_pregunta' => [
				'enfoque' => $selection['enfoque'],
				'secciones_prioritarias' => $selection['secciones_prioritarias'],
			],
			'kpis' => $aggregated['kpis'],
			'criterio_cumplimiento' => [
				'fecha_referencia' => $referenceDate,
				'regla' => $referenceDate === null
					? 'No aplica porque el periodo todavía no inicia.'
					: 'Cumple quien tiene al menos un reporte en la fecha de referencia.',
			],
			'resumen_por_empleado' => $employeeSummary,
			'resumen_por_actividad' => $activitySummary,
			'resumen_por_proyecto' => $projectSummary,
			'reportes' => $reports,
			'pendientes' => $pending,
			'limites' => $limits,
			'notas_calculo' => [
				'costo' => 'Estimación con la tarifa actual registrada para cada empleado; no existe costo histórico ni moneda en el reporte.',
				'cumplimiento' => 'No evalúa horas mínimas: reproduce la regla administrativa de al menos un reporte en la fecha.',
			],
		];
		if ($context['restriccion_dominio'] === null) {
			unset($context['restriccion_dominio']);
		}

		return $this->enforceLimit($context);
	}

	private function restrictedContext(array $period, array $restriction): array {
		$emptyLimits = [
			'reportes' => $this->limitMetadata(self::MAX_REPORTS, 0),
			'resumen_por_empleado' => $this->limitMetadata(self::MAX_EMPLOYEES, 0),
			'resumen_por_actividad' => $this->limitMetadata(self::MAX_ACTIVITIES, 0),
			'resumen_por_proyecto' => $this->limitMetadata(self::MAX_PROJECTS, 0),
			'pendientes' => $this->limitMetadata(self::MAX_PENDING, 0),
		];
		return [
			'contexto' => [
				'scope' => 'reportes-tiempo-admin',
				'generado_en' => (new \DateTimeImmutable())->format(DATE_ATOM),
				'periodo' => $period,
				'empleados_incluidos' => 0,
				'registros_incluidos' => 0,
				'truncado' => false,
				'secciones' => ['periodo', 'restriccion_dominio'],
			],
			'periodo' => $period,
			'restriccion_dominio' => $restriction,
			'kpis' => [
				'horas_reportadas' => null,
				'costo_total' => null,
				'empleados_con_reportes' => null,
				'proyectos_activos' => null,
				'actividades' => null,
				'total_reportes' => null,
				'promedio_horas_reporte' => null,
				'cumplimiento' => null,
			],
			'resumen_por_empleado' => [],
			'resumen_por_actividad' => [],
			'resumen_por_proyecto' => [],
			'reportes' => [],
			'pendientes' => [],
			'limites' => $emptyLimits,
		];
	}

	private function resolvePeriod(array $period): array {
		$startMonth = $period['mes_inicio'] ?? null;
		$endMonth = $period['mes_fin'] ?? null;
		$year = $period['anio'] ?? null;
		if (is_int($startMonth) && is_int($endMonth) && is_int($year)) {
			$start = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $startMonth));
			$end = (new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $endMonth)))
				->modify('last day of this month');
			return [
				'modo' => 'periodo_seleccionado',
				'mes_inicio' => $startMonth,
				'mes_fin' => $endMonth,
				'anio' => $year,
				'fecha_inicio' => $start->format('Y-m-d'),
				'fecha_fin' => $end->format('Y-m-d'),
				'fecha_inicio_consulta' => $start->format('Y-m-d'),
				'fecha_fin_consulta' => $end->format('Y-m-d'),
			];
		}

		return [
			'modo' => 'todo_el_historial_visible',
			'mes_inicio' => null,
			'mes_fin' => null,
			'anio' => null,
			'fecha_inicio' => null,
			'fecha_fin' => null,
			'fecha_inicio_consulta' => '1000-01-01',
			'fecha_fin_consulta' => '9999-12-31',
		];
	}

	private function publicPeriod(array $period): array {
		return [
			'modo' => $period['modo'],
			'mes_inicio' => $period['mes_inicio'],
			'mes_fin' => $period['mes_fin'],
			'anio' => $period['anio'],
			'fecha_inicio' => $period['fecha_inicio'],
			'fecha_fin' => $period['fecha_fin'],
		];
	}

	private function referenceDate(array $period): ?string {
		$today = new \DateTimeImmutable('today', new \DateTimeZone('America/Mexico_City'));
		if ($period['modo'] === 'todo_el_historial_visible') {
			return $today->format('Y-m-d');
		}
		$start = new \DateTimeImmutable($period['fecha_inicio']);
		$end = new \DateTimeImmutable($period['fecha_fin']);
		if ($today < $start) {
			return null;
		}
		return ($today > $end ? $end : $today)->format('Y-m-d');
	}

	private function aggregate(array $rows, ?string $referenceDate): array {
		$employees = [];
		$activities = [];
		$projects = [];
		$totalMinutes = 0.0;
		$totalReports = 0;
		$totalCost = 0.0;
		$projectIds = [];
		$activityIds = [];
		$missingRates = [];

		foreach ($rows as $row) {
			$employeeId = (int)($row['id_empleado'] ?? 0);
			if ($employeeId <= 0) {
				continue;
			}
			$minutes = (float)($row['total_minutos'] ?? 0);
			$reports = (int)($row['total_reportes'] ?? 0);
			$rateAvailable = is_numeric($row['costo_hora'] ?? null);
			$rate = $rateAvailable ? (float)$row['costo_hora'] : 0.0;
			$cost = ($minutes / 60) * $rate;
			$referenceReports = (int)($row['reportes_fecha_referencia'] ?? 0);
			if (!isset($employees[$employeeId])) {
				$employees[$employeeId] = [
					'id_empleado' => $employeeId,
					'usuario' => $row['uid'] ?? null,
					'nombre' => $this->visibleName(
						$row['nombre_empleado'] ?? null,
						$row['uid'] ?? null,
						'Empleado sin nombre visible',
					),
					'total_minutos' => 0.0,
					'horas_reportadas' => 0.0,
					'total_reportes' => 0,
					'costo_estimado_tarifa_actual' => 0.0,
					'reportes_fecha_referencia' => 0,
				];
			}
			$employees[$employeeId]['total_minutos'] += $minutes;
			$employees[$employeeId]['total_reportes'] += $reports;
			$employees[$employeeId]['costo_estimado_tarifa_actual'] += $cost;
			$employees[$employeeId]['reportes_fecha_referencia'] += $referenceReports;
			if (!$rateAvailable && $minutes > 0) {
				$missingRates[$employeeId] = true;
			}
			if ($reports <= 0) {
				continue;
			}

			$totalMinutes += $minutes;
			$totalReports += $reports;
			$totalCost += $cost;
			$projectId = $this->nullableInt($row['id_proyecto'] ?? null);
			$activityId = $this->nullableInt($row['id_actividad'] ?? null);
			if ($projectId !== null && $projectId !== 99999) {
				$projectIds[$projectId] = true;
			}
			if ($activityId !== null && $activityId !== 99999) {
				$activityIds[$activityId] = true;
			}
			$activityKey = $activityId === null ? 'sin-actividad' : 'actividad-' . $activityId;
			if ($projectId !== 99999) {
				$projectKey = $projectId === null ? 'sin-proyecto' : 'proyecto-' . $projectId;
				if (!isset($projects[$projectKey])) {
					$projects[$projectKey] = [
						'id_funcional' => $projectId,
						'nombre' => $this->projectName($projectId, $row['nombre_proyecto'] ?? null),
						'total_minutos' => 0.0,
						'horas_reportadas' => 0.0,
						'total_reportes' => 0,
						'costo_estimado_tarifa_actual' => 0.0,
						'actividades' => [],
					];
				}
				$projects[$projectKey]['total_minutos'] += $minutes;
				$projects[$projectKey]['total_reportes'] += $reports;
				$projects[$projectKey]['costo_estimado_tarifa_actual'] += $cost;
				if ($activityId !== 99999) {
					if (!isset($projects[$projectKey]['actividades'][$activityKey])) {
						$projects[$projectKey]['actividades'][$activityKey] = [
							'id_funcional' => $activityId,
							'nombre' => $this->activityName($activityId, $row['nombre_actividad'] ?? null),
							'total_minutos' => 0.0,
							'horas_reportadas' => 0.0,
							'costo_estimado_tarifa_actual' => 0.0,
						];
					}
					$projects[$projectKey]['actividades'][$activityKey]['total_minutos'] += $minutes;
					$projects[$projectKey]['actividades'][$activityKey]['costo_estimado_tarifa_actual'] += $cost;
				}
			}

			if ($activityId !== 99999) {
				if (!isset($activities[$activityKey])) {
					$activities[$activityKey] = [
						'id_funcional' => $activityId,
						'nombre' => $this->activityName($activityId, $row['nombre_actividad'] ?? null),
						'cargable' => isset($row['actividad_cargable'])
							? (bool)$row['actividad_cargable']
							: null,
						'total_minutos' => 0.0,
						'horas_reportadas' => 0.0,
						'total_reportes' => 0,
						'costo_estimado_tarifa_actual' => 0.0,
					];
				}
				$activities[$activityKey]['total_minutos'] += $minutes;
				$activities[$activityKey]['total_reportes'] += $reports;
				$activities[$activityKey]['costo_estimado_tarifa_actual'] += $cost;
			}
		}

		foreach ($employees as &$employee) {
			$employee['horas_reportadas'] = $this->hours($employee['total_minutos']);
			$employee['costo_estimado_tarifa_actual'] = round(
				$employee['costo_estimado_tarifa_actual'],
				2,
			);
			if (isset($missingRates[$employee['id_empleado']])) {
				$employee['tarifa_actual_faltante'] = true;
			}
		}
		unset($employee);
		foreach ($activities as &$activity) {
			$activity['horas_reportadas'] = $this->hours($activity['total_minutos']);
			$activity['costo_estimado_tarifa_actual'] = round(
				$activity['costo_estimado_tarifa_actual'],
				2,
			);
		}
		unset($activity);
		$summariesTruncated = false;
		foreach ($projects as &$project) {
			$project['horas_reportadas'] = $this->hours($project['total_minutos']);
			$project['costo_estimado_tarifa_actual'] = round(
				$project['costo_estimado_tarifa_actual'],
				2,
			);
			$projectActivities = array_values($project['actividades']);
			foreach ($projectActivities as &$projectActivity) {
				$projectActivity['horas_reportadas'] = $this->hours(
					$projectActivity['total_minutos'],
				);
				$projectActivity['costo_estimado_tarifa_actual'] = round(
					$projectActivity['costo_estimado_tarifa_actual'],
					2,
				);
			}
			unset($projectActivity);
			$this->sortByHours($projectActivities);
			$project['actividades_principales'] = array_slice($projectActivities, 0, 10);
			$project['actividades_truncadas'] = count($projectActivities) > 10;
			$summariesTruncated = $summariesTruncated
				|| $project['actividades_truncadas'];
			unset($project['actividades']);
		}
		unset($project);

		$employees = array_values($employees);
		$activities = array_values($activities);
		$projects = array_values($projects);
		$this->sortByHours($employees);
		$this->sortByHours($activities);
		$this->sortByHours($projects);
		$employeesWithReports = count(array_filter(
			$employees,
			static fn (array $employee): bool => $employee['total_reportes'] > 0,
		));
		$pending = [];
		if ($referenceDate !== null) {
			foreach ($employees as $employee) {
				if ($employee['reportes_fecha_referencia'] === 0) {
					$pending[] = [
						'id_empleado' => $employee['id_empleado'],
						'usuario' => $employee['usuario'],
						'nombre' => $employee['nombre'],
						'fecha_referencia' => $referenceDate,
						'estado' => 'pendiente',
					];
				}
			}
			usort(
				$pending,
				static fn (array $a, array $b): int => strcasecmp($a['nombre'], $b['nombre']),
			);
		}
		$visibleEmployees = count($employees);
		$reportedOnReference = $referenceDate === null
			? 0
			: $visibleEmployees - count($pending);

		return [
			'kpis' => [
				'horas_reportadas' => $this->hours($totalMinutes),
				'costo_total' => round($totalCost, 2),
				'empleados_con_reportes' => $employeesWithReports,
				'proyectos_activos' => count($projectIds),
				'actividades' => count($activityIds),
				'total_reportes' => $totalReports,
				'promedio_horas_reporte' => $totalReports > 0
					? round(($totalMinutes / 60) / $totalReports, 4)
					: 0.0,
				'cumplimiento' => $referenceDate !== null && $visibleEmployees > 0
					? round(($reportedOnReference / $visibleEmployees) * 100, 2)
					: null,
				'empleados_sin_tarifa_actual' => count($missingRates),
			],
			'empleados' => $employees,
			'actividades' => $activities,
			'proyectos' => $projects,
			'pendientes' => $pending,
			'empleados_visibles' => $visibleEmployees,
			'truncado' => $summariesTruncated,
		];
	}

	private function normalizeReport(array $row): array {
		$minutes = (float)($row['minutos'] ?? 0);
		$rateAvailable = is_numeric($row['costo_hora'] ?? null);
		$rate = $rateAvailable ? (float)$row['costo_hora'] : 0.0;
		$projectId = $this->nullableInt($row['id_proyecto'] ?? null);
		$activityId = $this->nullableInt($row['id_actividad'] ?? null);
		$description = $this->plainText($row['descripcion'] ?? null);
		return $this->removeNulls([
			'fecha' => $row['fecha'] ?? null,
			'empleado' => [
				'id_empleado' => $this->nullableInt($row['id_empleado'] ?? null),
				'usuario' => $row['uid'] ?? null,
				'nombre' => $this->visibleName(
					$row['nombre_empleado'] ?? null,
					$row['uid'] ?? null,
					'Empleado sin nombre visible',
				),
			],
			'proyecto' => [
				'id_funcional' => $projectId,
				'nombre' => $this->projectName($projectId, $row['nombre_proyecto'] ?? null),
			],
			'actividad' => [
				'id_funcional' => $activityId,
				'nombre' => $this->activityName($activityId, $row['nombre_actividad'] ?? null),
				'cargable' => isset($row['actividad_cargable'])
					? (bool)$row['actividad_cargable']
					: null,
			],
			'descripcion' => $description['texto'],
			'descripcion_truncada' => $description['truncado'] ? true : null,
			'minutos_reportados' => $minutes,
			'horas_reportadas' => $this->hours($minutes),
			'costo_estimado_tarifa_actual' => round(($minutes / 60) * $rate, 2),
			'tarifa_actual_faltante' => !$rateAvailable ? true : null,
		]);
	}

	private function projectName(?int $id, mixed $name): string {
		if ($id === 99999) {
			return 'Ausencia';
		}
		return $this->visibleName($name, null, 'Sin proyecto asociado');
	}

	private function activityName(?int $id, mixed $name): string {
		if ($id === 99999) {
			return 'Ausencia';
		}
		return $this->visibleName($name, null, 'Sin actividad asociada');
	}

	private function visibleName(mixed $primary, mixed $fallback, string $empty): string {
		foreach ([$primary, $fallback] as $value) {
			if (is_string($value) && trim($value) !== '') {
				return trim($value);
			}
		}
		return $empty;
	}

	private function nullableInt(mixed $value): ?int {
		return is_numeric($value) ? (int)$value : null;
	}

	private function hours(float $minutes): float {
		return round($minutes / 60, 4);
	}

	private function sortByHours(array &$rows): void {
		usort($rows, static function (array $a, array $b): int {
			$hours = ($b['horas_reportadas'] ?? 0) <=> ($a['horas_reportadas'] ?? 0);
			return $hours !== 0
				? $hours
				: strcasecmp((string)($a['nombre'] ?? ''), (string)($b['nombre'] ?? ''));
		});
	}

	private function limitSummary(array $summary, int $limit): array {
		return array_slice($summary, 0, $limit);
	}

	private function limitMetadata(int $limit, int $total, ?bool $truncated = null): array {
		return [
			'limite' => $limit,
			'total' => $total,
			'truncado' => $truncated ?? $total > $limit,
		];
	}

	private function plainText(mixed $value): array {
		if (!is_string($value) || trim($value) === '') {
			return ['texto' => null, 'truncado' => false];
		}
		$text = trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
		return [
			'texto' => mb_substr(
				$text,
				0,
				self::MAX_DESCRIPTION_LENGTH,
				'UTF-8',
			),
			'truncado' => mb_strlen($text, 'UTF-8') > self::MAX_DESCRIPTION_LENGTH,
		];
	}

	private function removeNulls(array $value): array {
		foreach ($value as $key => $item) {
			if (is_array($item)) {
				$item = $this->removeNulls($item);
			}
			if ($item === null || $item === []) {
				unset($value[$key]);
			} else {
				$value[$key] = $item;
			}
		}
		return $value;
	}

	private function enforceLimit(array $context): array {
		while ($this->size($context) > self::MAX_BYTES && $context['reportes'] !== []) {
			array_pop($context['reportes']);
			$context['contexto']['truncado'] = true;
			$context['limites']['reportes']['truncado'] = true;
		}
		$context['contexto']['registros_incluidos'] = count($context['reportes'])
			+ count($context['resumen_por_empleado'])
			+ count($context['resumen_por_actividad'])
			+ count($context['resumen_por_proyecto'])
			+ count($context['pendientes']);
		if ($this->size($context) > self::MAX_BYTES) {
			throw new \RuntimeException('El contexto generado supera el tamaño permitido.');
		}
		return $context;
	}

	private function size(array $context): int {
		return strlen(json_encode(
			$context,
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
		));
	}
}
