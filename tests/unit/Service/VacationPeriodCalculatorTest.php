<?php

declare(strict_types=1);

namespace OCA\Empleados\Tests\Unit\Service;

use DateTimeImmutable;
use InvalidArgumentException;
use OCA\Empleados\Service\VacationPeriodCalculator;
use PHPUnit\Framework\TestCase;

final class VacationPeriodCalculatorTest extends TestCase {
	private VacationPeriodCalculator $calculator;

	protected function setUp(): void {
		parent::setUp();
		$this->calculator = new VacationPeriodCalculator();
	}

	public function testParseDateAcceptsOnlyRealIsoDates(): void {
		self::assertSame('2026-07-29', $this->calculator->parseDate('2026-07-29')->format('Y-m-d'));

		$this->expectException(InvalidArgumentException::class);
		$this->calculator->parseDate('2026-02-30');
	}

	public function testEmployeeWithLessThanOneYearHasNoCompletedAnniversary(): void {
		self::assertSame(
			0,
			$this->calculator->completedAnniversaries(
				$this->date('2025-10-10'),
				$this->date('2026-07-29'),
			),
		);
	}

	public function testFirstAnniversaryIsDetectedOnTheDateAndAfterALateRun(): void {
		$hireDate = $this->date('2025-07-29');

		self::assertSame(0, $this->calculator->completedAnniversaries($hireDate, $this->date('2026-07-28')));
		self::assertSame(1, $this->calculator->completedAnniversaries($hireDate, $this->date('2026-07-29')));
		self::assertSame(1, $this->calculator->completedAnniversaries($hireDate, $this->date('2026-08-15')));
	}

	public function testSecondAndLaterAnniversariesAreCalculatedFromHireDate(): void {
		$hireDate = $this->date('2020-03-10');

		self::assertSame(5, $this->calculator->completedAnniversaries($hireDate, $this->date('2026-03-09')));
		self::assertSame(6, $this->calculator->completedAnniversaries($hireDate, $this->date('2026-03-10')));
	}

	public function testFutureHireDateReturnsZeroCompletedAnniversaries(): void {
		self::assertSame(
			0,
			$this->calculator->completedAnniversaries(
				$this->date('2030-01-01'),
				$this->date('2026-07-29'),
			),
		);
	}

	public function testLeapDayAnniversaryUsesFebruaryTwentyEightInNonLeapYears(): void {
		$hireDate = $this->date('2020-02-29');

		self::assertSame('2021-02-28', $this->calculator->anniversaryDate($hireDate, 1)->format('Y-m-d'));
		self::assertSame('2024-02-29', $this->calculator->anniversaryDate($hireDate, 4)->format('Y-m-d'));
		self::assertSame(0, $this->calculator->completedAnniversaries($hireDate, $this->date('2021-02-27')));
		self::assertSame(1, $this->calculator->completedAnniversaries($hireDate, $this->date('2021-02-28')));
	}

	public function testPeriodContainsStartEndAndSixMonthCarryExpiry(): void {
		$period = $this->calculator->period($this->date('2020-03-10'), 2);

		self::assertSame(2, $period['number']);
		self::assertSame('2022-03-10', $period['start']->format('Y-m-d'));
		self::assertSame('2023-03-10', $period['end']->format('Y-m-d'));
		self::assertSame('2022-09-10', $period['carry_expires']->format('Y-m-d'));
	}

	public function testCarryExpiryClampsToTheLastDayOfShorterMonth(): void {
		$period = $this->calculator->period($this->date('2023-08-31'), 0);

		self::assertSame('2024-02-29', $period['carry_expires']->format('Y-m-d'));
	}

	public function testCarryIsValidThroughExpiryDateButNotTheNextDay(): void {
		$expiry = $this->date('2026-09-10');

		self::assertTrue($this->calculator->isCarryValid($expiry, $this->date('2026-09-10')));
		self::assertFalse($this->calculator->isCarryValid($expiry, $this->date('2026-09-11')));
	}

	public function testAllocationConsumesDecimalCarryBeforeCurrentDays(): void {
		$allocation = $this->calculator->allocate(1.5, 0.5);

		self::assertSame(1.5, $allocation['requested']);
		self::assertSame(0.5, $allocation['carry_used']);
		self::assertSame(1.0, $allocation['current_used']);
		self::assertSame(0.0, $allocation['carry_remaining']);

		$halfDay = $this->calculator->allocate(0.5, 2.0);
		self::assertSame(0.5, $halfDay['carry_used']);
		self::assertSame(0.0, $halfDay['current_used']);
		self::assertSame(1.5, $halfDay['carry_remaining']);
	}

	public function testBalanceCombinesCurrentAndValidCarryWithoutNegativeValues(): void {
		$balance = $this->calculator->balance(
			14.0,
			3.0,
			4.0,
			1.5,
			$this->date('2026-09-10'),
			$this->date('2026-09-10'),
		);

		self::assertSame(11.0, $balance['current_remaining']);
		self::assertSame(2.5, $balance['carry_remaining']);
		self::assertSame(13.5, $balance['available']);
		self::assertSame(0.0, $balance['excess']);
		self::assertSame(0.0, $balance['expired']);
		self::assertTrue($balance['carry_valid']);
	}

	public function testExpiredCarryIsReportedAndCurrentBalanceIsPreserved(): void {
		$balance = $this->calculator->balance(
			14.0,
			3.0,
			4.0,
			1.5,
			$this->date('2026-09-10'),
			$this->date('2026-09-11'),
		);

		self::assertSame(11.0, $balance['current_remaining']);
		self::assertSame(0.0, $balance['carry_remaining']);
		self::assertSame(2.5, $balance['expired']);
		self::assertSame(11.0, $balance['available']);
		self::assertFalse($balance['carry_valid']);
	}

	public function testBalanceClampsOveruseAndReportsExcess(): void {
		$balance = $this->calculator->balance(
			2.0,
			3.5,
			1.0,
			1.5,
			$this->date('2026-09-10'),
			$this->date('2026-08-01'),
		);

		self::assertSame(0.0, $balance['current_remaining']);
		self::assertSame(0.0, $balance['carry_remaining']);
		self::assertSame(1.5, $balance['current_excess']);
		self::assertSame(0.5, $balance['carry_excess']);
		self::assertSame(2.0, $balance['excess']);
		self::assertSame(0.0, $balance['available']);
	}

	public function testNegativeBalanceInputsAreClampedToZero(): void {
		$balance = $this->calculator->balance(
			-10.0,
			-2.0,
			-5.0,
			-1.0,
			null,
			$this->date('2026-07-29'),
		);

		self::assertSame(0.0, $balance['current_grant']);
		self::assertSame(0.0, $balance['current_used']);
		self::assertSame(0.0, $balance['carry_grant']);
		self::assertSame(0.0, $balance['carry_used']);
		self::assertSame(0.0, $balance['available']);
		self::assertSame(0.0, $balance['excess']);
		self::assertSame(0.0, $balance['expired']);
	}

	private function date(string $date): DateTimeImmutable {
		return $this->calculator->parseDate($date);
	}
}
