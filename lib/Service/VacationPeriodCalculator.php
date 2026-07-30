<?php

declare(strict_types=1);

namespace OCA\Empleados\Service;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Pure vacation-period calendar and balance calculations.
 *
 * Dates are treated as civil dates. Time-of-day and timezone offsets do not
 * affect comparisons. Employees hired on February 29 celebrate their
 * anniversary on February 28 in non-leap years.
 */
final class VacationPeriodCalculator {
	private const DAY_SCALE = 2;

	public function parseDate(string $date): DateTimeImmutable {
		$value = trim($date);
		$parsed = DateTimeImmutable::createFromFormat(
			'!Y-m-d',
			$value,
			new DateTimeZone('UTC'),
		);
		$errors = DateTimeImmutable::getLastErrors();

		if (
			$parsed === false
			|| $value !== $parsed->format('Y-m-d')
			|| ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
		) {
			throw new InvalidArgumentException('Invalid date; expected YYYY-MM-DD.');
		}

		return $parsed;
	}

	public function anniversaryDate(DateTimeImmutable $hireDate, int $anniversary): DateTimeImmutable {
		if ($anniversary < 0) {
			throw new InvalidArgumentException('Anniversary number cannot be negative.');
		}

		$targetYear = (int)$hireDate->format('Y') + $anniversary;
		$month = (int)$hireDate->format('n');
		$day = (int)$hireDate->format('j');

		if ($month === 2 && $day === 29 && !checkdate($month, $day, $targetYear)) {
			$day = 28;
		}

		return $hireDate
			->setDate($targetYear, $month, $day)
			->setTime(0, 0, 0);
	}

	public function completedAnniversaries(
		DateTimeImmutable $hireDate,
		DateTimeImmutable $today,
	): int {
		if ($this->dateKey($today) < $this->dateKey($hireDate)) {
			return 0;
		}

		$completed = (int)$today->format('Y') - (int)$hireDate->format('Y');

		if (
			$completed > 0
			&& $this->dateKey($today) < $this->dateKey($this->anniversaryDate($hireDate, $completed))
		) {
			$completed--;
		}

		return max(0, $completed);
	}

	/**
	 * @return array{
	 *     number: int,
	 *     start: DateTimeImmutable,
	 *     end: DateTimeImmutable,
	 *     carry_expires: DateTimeImmutable
	 * }
	 */
	public function period(DateTimeImmutable $hireDate, int $anniversary): array {
		$start = $this->anniversaryDate($hireDate, $anniversary);

		return [
			'number' => $anniversary,
			'start' => $start,
			'end' => $this->anniversaryDate($hireDate, $anniversary + 1),
			'carry_expires' => $this->addMonthsClamped($start, 6),
		];
	}

	public function isCarryValid(
		DateTimeImmutable $expiryDate,
		DateTimeImmutable $today,
	): bool {
		return $this->dateKey($today) <= $this->dateKey($expiryDate);
	}

	/**
	 * Allocates requested days to carry-over first, then to the current period.
	 *
	 * @return array{
	 *     requested: float,
	 *     carry_used: float,
	 *     current_used: float,
	 *     carry_remaining: float
	 * }
	 */
	public function allocate(float $days, float $carryAvailable): array {
		$requested = $this->nonNegativeDays($days);
		$availableCarry = $this->nonNegativeDays($carryAvailable);
		$carryUsed = $this->days(min($requested, $availableCarry));

		return [
			'requested' => $requested,
			'carry_used' => $carryUsed,
			'current_used' => $this->days($requested - $carryUsed),
			'carry_remaining' => $this->days($availableCarry - $carryUsed),
		];
	}

	/**
	 * Produces a non-negative balance while retaining audit amounts for excess
	 * usage and expired carry-over.
	 *
	 * @return array{
	 *     current_grant: float,
	 *     current_used: float,
	 *     current_used_applied: float,
	 *     current_remaining: float,
	 *     current_excess: float,
	 *     carry_grant: float,
	 *     carry_used: float,
	 *     carry_used_applied: float,
	 *     carry_unused: float,
	 *     carry_remaining: float,
	 *     carry_expired: float,
	 *     carry_excess: float,
	 *     carry_valid: bool,
	 *     available: float,
	 *     excess: float,
	 *     expired: float
	 * }
	 */
	public function balance(
		float $grant,
		float $currentUsed,
		float $carryGrant,
		float $carryUsed,
		?DateTimeImmutable $carryExpiry,
		DateTimeImmutable $today,
	): array {
		$currentGrant = $this->nonNegativeDays($grant);
		$totalCurrentUsed = $this->nonNegativeDays($currentUsed);
		$currentUsedApplied = $this->days(min($currentGrant, $totalCurrentUsed));
		$currentExcess = $this->days(max(0.0, $totalCurrentUsed - $currentGrant));
		$currentRemaining = $this->days($currentGrant - $currentUsedApplied);

		$totalCarryGrant = $this->nonNegativeDays($carryGrant);
		$totalCarryUsed = $this->nonNegativeDays($carryUsed);
		$carryUsedApplied = $this->days(min($totalCarryGrant, $totalCarryUsed));
		$carryExcess = $this->days(max(0.0, $totalCarryUsed - $totalCarryGrant));
		$carryUnused = $this->days($totalCarryGrant - $carryUsedApplied);
		$carryValid = $carryExpiry !== null && $this->isCarryValid($carryExpiry, $today);
		$carryRemaining = $carryValid ? $carryUnused : 0.0;
		$carryExpired = $carryValid ? 0.0 : $carryUnused;

		return [
			'current_grant' => $currentGrant,
			'current_used' => $totalCurrentUsed,
			'current_used_applied' => $currentUsedApplied,
			'current_remaining' => $currentRemaining,
			'current_excess' => $currentExcess,
			'carry_grant' => $totalCarryGrant,
			'carry_used' => $totalCarryUsed,
			'carry_used_applied' => $carryUsedApplied,
			'carry_unused' => $carryUnused,
			'carry_remaining' => $carryRemaining,
			'carry_expired' => $carryExpired,
			'carry_excess' => $carryExcess,
			'carry_valid' => $carryValid,
			'available' => $this->days($currentRemaining + $carryRemaining),
			'excess' => $this->days($currentExcess + $carryExcess),
			'expired' => $carryExpired,
		];
	}

	private function addMonthsClamped(DateTimeImmutable $date, int $months): DateTimeImmutable {
		$monthIndex = ((int)$date->format('Y') * 12) + ((int)$date->format('n') - 1) + $months;
		$targetYear = intdiv($monthIndex, 12);
		$targetMonth = ($monthIndex % 12) + 1;
		$lastDay = (int)$date
			->setDate($targetYear, $targetMonth, 1)
			->format('t');
		$targetDay = min((int)$date->format('j'), $lastDay);

		return $date
			->setDate($targetYear, $targetMonth, $targetDay)
			->setTime(0, 0, 0);
	}

	private function dateKey(DateTimeImmutable $date): string {
		return $date->format('Y-m-d');
	}

	private function nonNegativeDays(float $days): float {
		if (!is_finite($days)) {
			throw new InvalidArgumentException('Days must be a finite number.');
		}

		return $this->days(max(0.0, $days));
	}

	private function days(float $days): float {
		$rounded = round($days, self::DAY_SCALE);

		return abs($rounded) < 0.01 ? 0.0 : $rounded;
	}
}
