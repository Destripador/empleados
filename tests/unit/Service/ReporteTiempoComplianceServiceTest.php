<?php

declare(strict_types=1);

namespace OCA\Empleados\Tests\Unit\Service;

use OCA\Empleados\Service\ReporteTiempoComplianceService;
use PHPUnit\Framework\TestCase;

final class ReporteTiempoComplianceServiceTest extends TestCase {
	private ReporteTiempoComplianceService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->service = new ReporteTiempoComplianceService();
	}

	public function testBuildPeriodsNormalizesInvertedDates(): void {
		$periods = $this->service->buildPeriods('2026-08-20', '2026-08-03');

		$this->assertSame([
			'fecha_inicio' => '2026-08-03',
			'fecha_fin' => '2026-08-20',
		], $periods['periodo']);
		$this->assertSame([
			'fecha_inicio' => '2026-08-16',
			'fecha_fin' => '2026-08-31',
		], $periods['quincena']);
	}

	public function testBuildPeriodsUsesFirstFortnightThroughDayFifteen(): void {
		$periods = $this->service->buildPeriods('2026-08-01', '2026-08-15');

		$this->assertSame([
			'fecha_inicio' => '2026-08-01',
			'fecha_fin' => '2026-08-15',
		], $periods['quincena']);
	}

	public function testBuildPeriodsUsesSecondFortnightFromDaySixteen(): void {
		$periods = $this->service->buildPeriods('2026-08-01', '2026-08-16');

		$this->assertSame([
			'fecha_inicio' => '2026-08-16',
			'fecha_fin' => '2026-08-31',
		], $periods['quincena']);
	}

	public function testBuildPeriodsHandlesLeapYearFebruary(): void {
		$periods = $this->service->buildPeriods('2028-02-20', '2028-02-20');

		$this->assertSame([
			'fecha_inicio' => '2028-02-01',
			'fecha_fin' => '2028-02-29',
		], $periods['mes']);
		$this->assertSame([
			'fecha_inicio' => '2028-02-16',
			'fecha_fin' => '2028-02-29',
		], $periods['quincena']);
	}

	public function testSummarizeCountsOnlyWeekdays(): void {
		$summary = $this->service->summarize([
			'fecha_inicio' => '2026-08-03',
			'fecha_fin' => '2026-08-09',
		], 1, 0.0, 8.0);

		$this->assertSame(5, $summary['dias_habiles']);
		$this->assertSame(40.0, $summary['horas_esperadas']);
	}

	public function testSummarizeUsesEightHoursAsDefault(): void {
		$summary = $this->service->summarize([
			'fecha_inicio' => '2026-08-03',
			'fecha_fin' => '2026-08-03',
		], 2, 0.0, 0.0);

		$this->assertSame(ReporteTiempoComplianceService::DEFAULT_DAILY_HOURS, $summary['horas_diarias_objetivo']);
		$this->assertSame(16.0, $summary['horas_esperadas']);
	}

	public function testSummarizeCalculatesPendingHoursAndPercentage(): void {
		$summary = $this->service->summarize([
			'fecha_inicio' => '2026-08-03',
			'fecha_fin' => '2026-08-07',
		], 1, 36.0 * 60, 8.0);

		$this->assertSame(40.0, $summary['horas_esperadas']);
		$this->assertSame(36.0, $summary['horas_reportadas']);
		$this->assertSame(4.0, $summary['horas_pendientes']);
		$this->assertSame(90.0, $summary['porcentaje_cumplimiento']);
	}

	public function testSummarizeCapsComplianceAtOneHundredPercent(): void {
		$summary = $this->service->summarize([
			'fecha_inicio' => '2026-08-03',
			'fecha_fin' => '2026-08-03',
		], 1, 10.0 * 60, 8.0);

		$this->assertSame(0.0, $summary['horas_pendientes']);
		$this->assertSame(100.0, $summary['porcentaje_cumplimiento']);
	}
}
