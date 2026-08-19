<?php

declare(strict_types=1);

namespace OCA\Empleados\Service;

use OCA\Empleados\AppInfo\Application;
use OCP\Activity\IManager as IActivityManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

class ParkingModeService {
	public const MODE_OPERATIONAL = 'operational';
	public const MODE_MAINTENANCE = 'maintenance';

	private const KEY_MODE = 'parking_mode';
	private const KEY_REASON = 'parking_maintenance_reason';
	private const KEY_STARTED_AT = 'parking_maintenance_started_at';
	private const KEY_STARTED_BY = 'parking_maintenance_started_by';
	private const KEY_UNTIL = 'parking_maintenance_until';
	private const KEY_PUBLISHED_AT = 'parking_maintenance_published_at';
	private const KEY_PUBLISHED_BY = 'parking_maintenance_published_by';
	private const MAX_REASON_LENGTH = 500;

	public function __construct(
		private IConfig $config,
		private ITimeFactory $timeFactory,
		private PermisosService $permissions,
		private IActivityManager $activityManager,
		private LoggerInterface $logger,
	) {
	}

	public function getMode(): string {
		$mode = $this->config->getAppValue(
			Application::APP_ID,
			self::KEY_MODE,
			self::MODE_OPERATIONAL,
		);

		return $mode === self::MODE_MAINTENANCE
			? self::MODE_MAINTENANCE
			: self::MODE_OPERATIONAL;
	}

	public function isMaintenance(): bool {
		return $this->getMode() === self::MODE_MAINTENANCE;
	}

	public function canManage(?string $uid = null): bool {
		return $this->permissions->isAdmin($uid);
	}

	public function canViewSensitiveData(?string $uid = null): bool {
		return !$this->isMaintenance() || $this->canManage($uid);
	}

	/**
	 * @return array{
	 *     mode: string,
	 *     maintenance: bool,
	 *     reason: ?string,
	 *     startedAt: ?string,
	 *     until: ?string,
	 *     canManage: bool,
	 *     canViewSensitiveData: bool,
	 *     startedBy: ?string,
	 *     publishedAt: ?string,
	 *     publishedBy: ?string
	 * }
	 */
	public function getStatus(?string $uid = null): array {
		$mode = $this->getMode();
		$isMaintenance = $mode === self::MODE_MAINTENANCE;
		$canManage = $this->canManage($uid);

		return [
			'mode' => $mode,
			'maintenance' => $isMaintenance,
			'reason' => $isMaintenance ? $this->getNullableValue(self::KEY_REASON) : null,
			'startedAt' => $isMaintenance ? $this->getNullableValue(self::KEY_STARTED_AT) : null,
			'until' => $isMaintenance ? $this->getNullableValue(self::KEY_UNTIL) : null,
			'canManage' => $canManage,
			'canViewSensitiveData' => !$isMaintenance || $canManage,
			'startedBy' => $isMaintenance && $canManage
				? $this->getNullableValue(self::KEY_STARTED_BY)
				: null,
			'publishedAt' => $canManage
				? $this->getNullableValue(self::KEY_PUBLISHED_AT)
				: null,
			'publishedBy' => $canManage
				? $this->getNullableValue(self::KEY_PUBLISHED_BY)
				: null,
		];
	}

	public function activate(string $actorUid, ?string $reason = null, ?string $until = null): array {
		$this->requireManager($actorUid);

		if ($this->isMaintenance()) {
			throw new \DomainException('Parking maintenance mode is already active.');
		}

		$reason = $this->normalizeReason($reason);
		$until = $this->normalizeUntil($until);
		$startedAt = $this->timeFactory->now()->format(DATE_ATOM);

		$this->setValue(self::KEY_REASON, $reason);
		$this->setValue(self::KEY_STARTED_AT, $startedAt);
		$this->setValue(self::KEY_STARTED_BY, $actorUid);
		$this->setValue(self::KEY_UNTIL, $until);
		// Write mode last so readers never observe maintenance without metadata.
		$this->setValue(self::KEY_MODE, self::MODE_MAINTENANCE);

		$this->recordActivity('parking_maintenance_activated', $actorUid, $reason);

		return $this->getStatus($actorUid);
	}

	public function publish(string $actorUid): array {
		$this->requireManager($actorUid);

		if (!$this->isMaintenance()) {
			throw new \DomainException('Parking is already operational.');
		}

		$publishedAt = $this->timeFactory->now()->format(DATE_ATOM);
		$this->setValue(self::KEY_PUBLISHED_AT, $publishedAt);
		$this->setValue(self::KEY_PUBLISHED_BY, $actorUid);
		$this->setValue(self::KEY_MODE, self::MODE_OPERATIONAL);

		$this->recordActivity('parking_published', $actorUid, null);

		return $this->getStatus($actorUid);
	}

	private function requireManager(string $uid): void {
		if ($uid === '' || !$this->canManage($uid)) {
			throw new \RuntimeException('Only parking administrators can perform this action.');
		}
	}

	private function normalizeReason(?string $reason): ?string {
		$reason = trim((string)$reason);

		if ($reason === '') {
			return null;
		}

		if (mb_strlen($reason) > self::MAX_REASON_LENGTH) {
			throw new \InvalidArgumentException('The maintenance reason is too long.');
		}

		return $reason;
	}

	private function normalizeUntil(?string $until): ?string {
		$until = trim((string)$until);

		if ($until === '') {
			return null;
		}

		try {
			$date = new \DateTimeImmutable($until);
		} catch (\Throwable $e) {
			throw new \InvalidArgumentException('Estimated availability is not a valid date.', 0, $e);
		}

		if ($date <= $this->timeFactory->now()) {
			throw new \InvalidArgumentException('Estimated availability must be in the future.');
		}

		return $date->format(DATE_ATOM);
	}

	private function getNullableValue(string $key): ?string {
		$value = trim($this->config->getAppValue(Application::APP_ID, $key, ''));

		return $value === '' ? null : $value;
	}

	private function setValue(string $key, ?string $value): void {
		$this->config->setAppValue(Application::APP_ID, $key, $value ?? '');
	}

	private function recordActivity(string $subject, string $actorUid, ?string $reason): void {
		try {
			$event = $this->activityManager->generateEvent();
			$event->setApp(Application::APP_ID);
			$event->setType(Application::APP_ID);
			$event->setObject('parking', $this->timeFactory->getTime(), 'Estacionamiento');
			$event->setAffectedUser($actorUid);
			$event->setSubject($subject, ['nombre' => $actorUid]);
			if ($reason !== null) {
				$event->setMessage($reason);
			}
			$this->activityManager->publish($event);

			$this->logger->info('Parking publication state changed.', [
				'actorUid' => $actorUid,
				'subject' => $subject,
			]);
		} catch (\Throwable $e) {
			// The state transition is authoritative even if the auxiliary audit stream fails.
			$this->logger->warning('Could not record parking state activity.', [
				'actorUid' => $actorUid,
				'subject' => $subject,
				'exception' => $e,
			]);
		}
	}
}
