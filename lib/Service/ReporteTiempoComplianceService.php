<?php

declare(strict_types=1);

namespace OCA\Empleados\Service;

/**
 * Reglas compartidas para periodos y cumplimiento de reportes de tiempo.
 *
 * Mantiene el criterio que ya usa el reporte personal: jornada configurada,
 * lunes a viernes y quincenas 1-15 / 16-fin de mes.
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
		$inicio = $this->parseDate($period['fecha_inicio']);
		$fin = $this->parseDate($period['fecha_fin']);
		$employeeCount = max(0, $employeeCount);
		$dailyHours = $this->normalizeDailyHours($dailyHours);
		$reportedHours = max(0.0, $reportedMinutes) / 60;
		$workingDays = $this->countWorkingDays($inicio, $fin);
		$expectedHours = $workingDays * $employeeCount * $dailyHours;
		$pendingHours = max(0.0, $expectedHours - $reportedHours);
		$percentage = $expectedHours > 0
			? min(100.0, ($reportedHours / $expectedHours) * 100)
			: ($reportedHours > 0 ? 100.0 : 0.0);

		return [
			'fecha_inicio' => $inicio->format('Y-m-d'),
			'fecha_fin' => $fin->format('Y-m-d'),
			'dias_habiles' => $workingDays,
			'empleados' => $employeeCount,
			'horas_diarias_objetivo' => round($dailyHours, 2),
			'horas_esperadas' => round($expectedHours, 2),
			'horas_reportadas' => round($reportedHours, 2),
			'horas_pendientes' => round($pendingHours, 2),
			'porcentaje_cumplimiento' => round($percentage, 2),
		];
	}

	public function normalizeDailyHours(float $dailyHours): float {
		return is_finite($dailyHours) && $dailyHours > 0
			? $dailyHours
			: self::DEFAULT_DAILY_HOURS;
	}

	private function countWorkingDays(\DateTimeImmutable $inicio, \DateTimeImmutable $fin): int {
		if ($inicio > $fin) {
			return 0;
		}

		$count = 0;
		$cursor = $inicio;
		while ($cursor <= $fin) {
			if ((int)$cursor->format('N') <= 5) {
				$count++;
			}
			$cursor = $cursor->modify('+1 day');
		}

		return $count;
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
