<?php

declare(strict_types=1);

namespace OCA\Empleados\Tests\Unit\Service;

use OCA\Empleados\AppInfo\Application;
use OCA\Empleados\Service\ParkingModeService;
use OCA\Empleados\Service\PermisosService;
use OCP\Activity\IEvent;
use OCP\Activity\IManager as IActivityManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ParkingModeServiceTest extends TestCase {
	public function testOperationalIsTheSafeDefault(): void {
		[$service] = $this->service([], false);

		$status = $service->getStatus('alice');

		$this->assertSame(ParkingModeService::MODE_OPERATIONAL, $status['mode']);
		$this->assertFalse($status['maintenance']);
		$this->assertFalse($status['canManage']);
		$this->assertTrue($status['canViewSensitiveData']);
	}

	public function testMaintenanceHidesSensitiveDataAndActorFromRegularUser(): void {
		[$service] = $this->service([
			'parking_mode' => ParkingModeService::MODE_MAINTENANCE,
			'parking_maintenance_reason' => 'Reasignación trimestral',
			'parking_maintenance_started_at' => '2026-08-18T10:00:00-06:00',
			'parking_maintenance_started_by' => 'admin',
		], false);

		$status = $service->getStatus('alice');

		$this->assertTrue($status['maintenance']);
		$this->assertFalse($status['canViewSensitiveData']);
		$this->assertSame('Reasignación trimestral', $status['reason']);
		$this->assertNull($status['startedBy']);
	}

	public function testMaintenanceRemainsVisibleToAdministrator(): void {
		[$service] = $this->service([
			'parking_mode' => ParkingModeService::MODE_MAINTENANCE,
			'parking_maintenance_started_by' => 'admin',
		], true);

		$status = $service->getStatus('admin');

		$this->assertTrue($status['canManage']);
		$this->assertTrue($status['canViewSensitiveData']);
		$this->assertSame('admin', $status['startedBy']);
	}

	public function testActivatePersistsMetadataAndPublishesActivity(): void {
		$activityManager = $this->createMock(IActivityManager::class);
		$event = $this->createMock(IEvent::class);
		$activityManager->method('generateEvent')->willReturn($event);
		$activityManager->expects($this->once())->method('publish')->with($event);
		[$service, $state] = $this->service([], true, $activityManager);

		$status = $service->activate(
			'admin',
			'Reordenamiento',
			'2026-08-19T12:00:00-06:00',
		);

		$this->assertTrue($status['maintenance']);
		$this->assertSame(ParkingModeService::MODE_MAINTENANCE, $state->values['parking_mode']);
		$this->assertSame('Reordenamiento', $state->values['parking_maintenance_reason']);
		$this->assertSame('admin', $state->values['parking_maintenance_started_by']);
	}

	public function testPublishReturnsToOperationalAndAuditsTransition(): void {
		$activityManager = $this->createMock(IActivityManager::class);
		$event = $this->createMock(IEvent::class);
		$activityManager->method('generateEvent')->willReturn($event);
		$activityManager->expects($this->once())->method('publish')->with($event);
		[$service, $state] = $this->service([
			'parking_mode' => ParkingModeService::MODE_MAINTENANCE,
		], true, $activityManager);

		$status = $service->publish('admin');

		$this->assertFalse($status['maintenance']);
		$this->assertSame(ParkingModeService::MODE_OPERATIONAL, $state->values['parking_mode']);
		$this->assertSame('admin', $state->values['parking_maintenance_published_by']);
	}

	public function testActivateRejectsPastEstimatedDateWithoutChangingState(): void {
		[$service, $state] = $this->service([], true);

		try {
			$service->activate('admin', null, '2026-08-17T12:00:00-06:00');
			$this->fail('A past estimated date should have been rejected.');
		} catch (\InvalidArgumentException $e) {
			$this->assertSame([], $state->values);
		}
	}

	/**
	 * @return array{ParkingModeService, object{values: array<string, string>}, IConfig&MockObject}
	 */
	private function service(
		array $initialValues,
		bool $isAdmin,
		?IActivityManager $activityManager = null,
	): array {
		$state = (object)['values' => $initialValues];
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')
			->willReturnCallback(static function(string $appId, string $key, string $default) use ($state): string {
				return $state->values[$key] ?? $default;
			});
		$config->method('setAppValue')
			->willReturnCallback(static function(string $appId, string $key, string $value) use ($state): void {
				$state->values[$key] = $value;
			});

		$timeFactory = $this->createMock(ITimeFactory::class);
		$now = new \DateTimeImmutable('2026-08-18T10:00:00-06:00');
		$timeFactory->method('now')->willReturn($now);
		$timeFactory->method('getTime')->willReturn($now->getTimestamp());

		$permissions = $this->createMock(PermisosService::class);
		$permissions->method('isAdmin')->willReturn($isAdmin);

		return [
			new ParkingModeService(
				$config,
				$timeFactory,
				$permissions,
				$activityManager ?? $this->createMock(IActivityManager::class),
				$this->createMock(LoggerInterface::class),
			),
			$state,
			$config,
		];
	}
}
