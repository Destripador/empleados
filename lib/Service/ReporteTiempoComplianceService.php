<?php

declare(strict_types=1);

namespace OCA\Empleados\Service;

/**
 * Reglas compartidas para periodos y cumplimiento de reportes de tiempo.
 *
 * Separa la jornada esperada del umbral minimo de cumplimiento diario.
 */
final class ReporteTiempoComplianceService {
	public const DEFAULT_DAILY_HOURS = 8.0;

	/**
	 * @return array{
	 *   periodo:array{fecha_inicio:string,fecha_fin:string},
	 *   quincena:array{fecha_inicio:string,fecha_fin:string},
	 *   mes:array{fecha_inicio:string,fecha_fin:string}
	 * }
	 */
	public function buildPeriods(string $fechaInicio, string $fechaFin): array {
		$inicio = $this->parseDate($fechaInicio);
		$fin = $this->parseDate($fechaFin);

		if ($inicio > $fin) {
			[$inicio, $fin] = [$fin, $inicio];
		}

		// La fecha final representa el contexto administrativo seleccionado.
		$reference = $fin;
		$monthStart = $reference->modify('first day of this month');
		$monthEnd = $reference->modify('last day of this month');
		if ((int)$reference->format('j') <= 15) {
			$fortnightStart = $monthStart;
			$fortnightEnd = $reference->setDate(
				(int)$reference->format('Y'),
				(int)$reference->format('n'),
				15,
			);
		} else {
			$fortnightStart = $reference->setDate(
				(int)$reference->format('Y'),
				(int)$reference->format('n'),
				16,
			);
			$fortnightEnd = $monthEnd;
		}

		return [
			'periodo' => $this->periodArray($inicio, $fin),
			'quincena' => $this->periodArray($fortnightStart, $fortnightEnd),
			'mes' => $this->periodArray($monthStart, $monthEnd),
		];
	}

	/**
	 * @param array{fecha_inicio:string,fecha_fin:string} $period
	 * @return array<string,int|float|string>
	 */
	public function summarize(array $period, int $employeeCount, float $reportedMinutes, float $dailyHours): array {
		$employeeCount = max(0, $employeeCount);
		$employees = array_fill(0, $employeeCount, ['Estado' => 1]);
		$minutesByEmployee = array_fill(0, $employeeCount, [
			'minutos_cliente' => max(0.0, $reportedMinutes) / max(1, $employeeCount),
			'minutos_internos' => 0.0,
			'minutos_ausencia' => 0.0,
		]);

		return $this->summarizeEmployees($period, $employees, $minutesByEmployee, $dailyHours, []);
	}

	/**
	 * @param array{fecha_inicio:string,fecha_fin:string} $period
	 * @param array<string,mixed> $employee
	 * @param array<string,mixed> $minutes
	 * @param array<int,array<string,mixed>> $holidays
	 * @return array<string,int|float|string>
	 */
	public function summarizeEmployee(
		array $period,
		array $employee,
		array $minutes,
		float $dailyHours,
		array $holidays = [],
	): array {
		$inicio = $this->parseDate($period['fecha_inicio']);
		$fin = $this->parseDate($period['fecha_fin']);
		if ($inicio > $fin) {
			[$inicio, $fin] = [$fin, $inicio];
		}
		$dailyHours = $this->normalizeDailyHours($dailyHours);
		$workingDays = $this->countEmployeeWorkingDays($inicio, $fin, $employee, $holidays);
		$expectedHours = $workingDays * $dailyHours;
		$clientHours = max(0.0, (float)($minutes['minutos_cliente'] ?? 0)) / 60;
		$internalHours = max(0.0, (float)($minutes['minutos_internos'] ?? 0)) / 60;
		$absenceHours = max(0.0, (float)($minutes['minutos_ausencia'] ?? 0)) / 60;
		$reportedWorkHours = $clientHours + $internalHours;
		$accountedHours = $reportedWorkHours + $absenceHours;
		$pendingHours = max(0.0, $expectedHours - $accountedHours);
		$percentage = $expectedHours > 0
			? min(100.0, ($accountedHours / $expectedHours) * 100)
			: ($accountedHours > 0 ? 100.0 : 0.0);

		return [
			'fecha_inicio' => $inicio->format('Y-m-d'),
			'fecha_fin' => $fin->format('Y-m-d'),
			'dias_habiles' => $workingDays,
			'empleados' => 1,
			'horas_diarias_esperadas' => round($dailyHours, 2),
			// Alias temporal para consumidores anteriores del contrato.
			'horas_diarias_objetivo' => round($dailyHours, 2),
			'horas_esperadas' => round($expectedHours, 2),
			'horas_reportadas' => round($reportedWorkHours, 2),
			'horas_reportadas_trabajo' => round($reportedWorkHours, 2),
			'horas_cliente' => round($clientHours, 2),
			'horas_internas' => round($internalHours, 2),
			'horas_ausencia' => round($absenceHours, 2),
			'horas_contabilizadas' => round($accountedHours, 2),
			'horas_pendientes' => round($pendingHours, 2),
			'porcentaje_cumplimiento' => round($percentage, 2),
		];
	}

	/**
	 * @param array{fecha_inicio:string,fecha_fin:string} $period
	 * @param array<int,array<string,mixed>> $employees
	 * @param array<int,array<string,mixed>> $minutesByEmployee
	 * @param array<int,array<string,mixed>> $holidays
	 * @return array<string,int|float|string>
	 */
	public function summarizeEmployees(
		array $period,
		array $employees,
		array $minutesByEmployee,
		float $dailyHours,
		array $holidays = [],
	): array {
		$inicio = $this->parseDate($period['fecha_inicio']);
		$fin = $this->parseDate($period['fecha_fin']);
		if ($inicio > $fin) {
			[$inicio, $fin] = [$fin, $inicio];
		}

		$totals = [
			'dias_habiles' => 0,
			'horas_esperadas' => 0.0,
			'horas_reportadas' => 0.0,
			'horas_cliente' => 0.0,
			'horas_internas' => 0.0,
			'horas_ausencia' => 0.0,
			'horas_contabilizadas' => 0.0,
		];
		foreach (array_values($employees) as $index => $employee) {
			$id = (int)($employee['id_empleado'] ?? $employee['Id_empleados'] ?? $index);
			$minutes = $minutesByEmployee[$id] ?? $minutesByEmployee[$index] ?? [];
			$summary = $this->summarizeEmployee(
				['fecha_inicio' => $inicio->format('Y-m-d'), 'fecha_fin' => $fin->format('Y-m-d')],
				$employee,
				$minutes,
				$dailyHours,
				$holidays,
			);
			foreach (array_keys($totals) as $key) {
				$totals[$key] += (float)$summary[$key];
			}
		}

		$pendingHours = max(0.0, $totals['horas_esperadas'] - $totals['horas_contabilizadas']);
		$percentage = $totals['horas_esperadas'] > 0
			? min(100.0, ($totals['horas_contabilizadas'] / $totals['horas_esperadas']) * 100)
			: ($totals['horas_contabilizadas'] > 0 ? 100.0 : 0.0);
		$dailyHours = $this->normalizeDailyHours($dailyHours);

		return [
			'fecha_inicio' => $inicio->format('Y-m-d'),
			'fecha_fin' => $fin->format('Y-m-d'),
			'dias_habiles' => (int)$totals['dias_habiles'],
			'empleados' => count($employees),
			'horas_diarias_esperadas' => round($dailyHours, 2),
			'horas_diarias_objetivo' => round($dailyHours, 2),
			'horas_esperadas' => round($totals['horas_esperadas'], 2),
			'horas_reportadas' => round($totals['horas_reportadas'], 2),
			'horas_reportadas_trabajo' => round($totals['horas_reportadas'], 2),
			'horas_cliente' => round($totals['horas_cliente'], 2),
			'horas_internas' => round($totals['horas_internas'], 2),
			'horas_ausencia' => round($totals['horas_ausencia'], 2),
			'horas_contabilizadas' => round($totals['horas_contabilizadas'], 2),
			'horas_pendientes' => round($pendingHours, 2),
			'porcentaje_cumplimiento' => round($percentage, 2),
		];
	}

	/** @return array{estado:string,cumple:bool,porcentaje:float} */
	public function evaluateDailyCompliance(float $reportedMinutes, float $minimumDailyHours): array {
		$reportedMinutes = max(0.0, $reportedMinutes);
		$minimumMinutes = max(0.0, $minimumDailyHours) * 60;
		$hasReport = $reportedMinutes > 0;
		$complies = $minimumMinutes > 0 ? $reportedMinutes >= $minimumMinutes : $hasReport;
		$percentage = $minimumMinutes > 0
			? min(100.0, ($reportedMinutes / $minimumMinutes) * 100)
			: ($hasReport ? 100.0 : 0.0);

		return [
			'estado' => $complies ? 'cumplido' : ($hasReport ? 'incompleto' : 'sin_reportar'),
			'cumple' => $complies,
			'porcentaje' => round($percentage, 2),
		];
	}

	public function normalizeDailyHours(float $dailyHours): float {
		return is_finite($dailyHours) && $dailyHours > 0
			? $dailyHours
			: self::DEFAULT_DAILY_HOURS;
	}

	/**
	 * @param array<string,mixed> $employee
	 * @param array<int,array<string,mixed>> $holidays
	 */
	private function countEmployeeWorkingDays(
		\DateTimeImmutable $inicio,
		\DateTimeImmutable $fin,
		array $employee,
		array $holidays,
	): int {
		if ($inicio > $fin) {
			return 0;
		}
		$status = $employee['Estado'] ?? $employee['estado'] ?? 1;
		$termination = $this->employeeTerminationDate($employee);
		if (($status === false || $status === 0 || $status === '0') && $termination === null) {
			return 0;
		}

		$hireDate = $this->employeeDate($employee['Ingreso'] ?? $employee['ingreso'] ?? null);
		if ($hireDate !== null && $hireDate > $inicio) {
			$inicio = $hireDate;
		}
		if ($termination !== null && $termination < $fin) {
			$fin = $termination;
		}
		if ($inicio > $fin) {
			return 0;
		}
		$holidayDates = $this->buildHolidayDateSet($holidays, (int)$inicio->format('Y'), (int)$fin->format('Y'));

		$count = 0;
		$cursor = $inicio;
		while ($cursor <= $fin) {
			if ((int)$cursor->format('N') <= 5 && !isset($holidayDates[$cursor->format('Y-m-d')])) {
				$count++;
			}
			$cursor = $cursor->modify('+1 day');
		}

		return $count;
	}

	/** @param array<string,mixed> $employee */
	private function employeeTerminationDate(array $employee): ?\DateTimeImmutable {
		foreach (['fecha_baja', 'Fecha_baja', 'baja', 'Baja', 'fecha_inactividad', 'Fecha_inactividad'] as $key) {
			$date = $this->employeeDate($employee[$key] ?? null);
			if ($date !== null) {
				return $date;
			}
		}
		return null;
	}

	private function employeeDate(mixed $value): ?\DateTimeImmutable {
		if ($value instanceof \DateTimeInterface) {
			return new \DateTimeImmutable($value->format('Y-m-d'));
		}
		if (!is_string($value) || strlen(trim($value)) < 10) {
			return null;
		}
		$dateValue = substr(trim($value), 0, 10);
		$date = \DateTimeImmutable::createFromFormat('!Y-m-d', $dateValue);
		$errors = \DateTimeImmutable::getLastErrors();
		return $date !== false
			&& $date->format('Y-m-d') === $dateValue
			&& (!is_array($errors) || ((int)$errors['warning_count'] === 0 && (int)$errors['error_count'] === 0))
			? $date
			: null;
	}

	/**
	 * @param array<int,array<string,mixed>> $holidays
	 * @return array<string,bool>
	 */
	private function buildHolidayDateSet(array $holidays, int $startYear, int $endYear): array {
		$dates = [];
		foreach ($holidays as $holiday) {
			$type = strtolower(trim((string)($holiday['tipo'] ?? 'fijo')));
			for ($year = $startYear; $year <= $endYear; $year++) {
				if (
					$type === 'variable'
					&& (int)($holiday['regla_mes'] ?? 0) >= 1
					&& (int)($holiday['regla_dia_semana'] ?? 0) >= 1
					&& (int)($holiday['regla_semana'] ?? 0) !== 0
				) {
					$date = FestivosCalculator::nthWeekday(
						$year,
						(int)$holiday['regla_mes'],
						(int)$holiday['regla_dia_semana'],
						(int)$holiday['regla_semana'],
					)->format('Y-m-d');
					$dates[$date] = true;
					continue;
				}

				$raw = trim((string)($holiday['fecha'] ?? ''));
				if (preg_match('/^\d{2}-\d{2}$/', $raw) === 1) {
					$dates[sprintf('%04d-%s', $year, $raw)] = true;
				} elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
					$dates[$raw] = true;
				}
			}
		}

		return $dates;
	}

	private function parseDate(string $value): \DateTimeImmutable {
		$date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
		$errors = \DateTimeImmutable::getLastErrors();
		if (
			$date === false
			|| $date->format('Y-m-d') !== $value
			|| (is_array($errors) && ((int)$errors['warning_count'] > 0 || (int)$errors['error_count'] > 0))
		) {
			throw new \InvalidArgumentException('La fecha del reporte no es válida.');
		}

		return $date;
	}

	/** @return array{fecha_inicio:string,fecha_fin:string} */
	private function periodArray(\DateTimeImmutable $inicio, \DateTimeImmutable $fin): array {
		return [
			'fecha_inicio' => $inicio->format('Y-m-d'),
			'fecha_fin' => $fin->format('Y-m-d'),
		];
	}
}
