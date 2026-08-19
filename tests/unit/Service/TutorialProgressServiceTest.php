<?php

declare(strict_types=1);

namespace OCA\Empleados\Tests\Unit\Service;

use OCA\Empleados\AppInfo\Application;
use OCA\Empleados\Service\TutorialProgressService;
use OCA\Empleados\Tutorial\TutorialCatalog;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;

class TutorialProgressServiceTest extends TestCase {
	public function testVersionStatusIsPendingWhenPreferenceIsMissing(): void {
		$config = $this->createMock(IConfig::class);
		$config->expects($this->once())
			->method('getUserValue')
			->with('alice', Application::APP_ID, 'office_simulation_intro_version', '')
			->willReturn('');

		$status = (new TutorialProgressService($config))->getVersionStatus(
			'alice',
			TutorialCatalog::OFFICE_SIMULATION_INTRO
		);

		$this->assertSame([
			'completed' => false,
			'completedVersion' => 0,
			'requiredVersion' => TutorialCatalog::OFFICE_SIMULATION_INTRO_VERSION,
		], $status);
	}

	public function testCompleteVersionPersistsCurrentVersionForUser(): void {
		$config = $this->createMock(IConfig::class);
		$config->method('getUserValue')->willReturn('');
		$config->expects($this->once())
			->method('setUserValue')
			->with('alice', Application::APP_ID, 'office_simulation_intro_version', '1');

		$status = (new TutorialProgressService($config))->completeVersion(
			'alice',
			TutorialCatalog::OFFICE_SIMULATION_INTRO,
			TutorialCatalog::OFFICE_SIMULATION_INTRO_VERSION
		);

		$this->assertTrue($status['completed']);
		$this->assertSame(1, $status['completedVersion']);
	}

	public function testVersionStatusIsCompleteAtRequiredVersion(): void {
		$config = $this->createMock(IConfig::class);
		$config->method('getUserValue')->willReturn('1');

		$status = (new TutorialProgressService($config))->getVersionStatus(
			'alice',
			TutorialCatalog::OFFICE_SIMULATION_INTRO
		);

		$this->assertTrue($status['completed']);
		$this->assertSame(1, $status['completedVersion']);
	}

	public function testCompleteVersionRejectsUnexpectedVersion(): void {
		$config = $this->createMock(IConfig::class);
		$config->method('getUserValue')->willReturn('');
		$config->expects($this->never())->method('setUserValue');
		$service = new TutorialProgressService($config);

		$this->expectException(\InvalidArgumentException::class);
		$service->completeVersion('alice', TutorialCatalog::OFFICE_SIMULATION_INTRO, 99);
	}

	public function testCompleteVersionDoesNotDowngradeFutureStoredVersion(): void {
		$config = $this->createMock(IConfig::class);
		$config->method('getUserValue')->willReturn('2');
		$config->expects($this->never())->method('setUserValue');

		$status = (new TutorialProgressService($config))->completeVersion(
			'alice',
			TutorialCatalog::OFFICE_SIMULATION_INTRO,
			TutorialCatalog::OFFICE_SIMULATION_INTRO_VERSION
		);

		$this->assertTrue($status['completed']);
		$this->assertSame(2, $status['completedVersion']);
	}

	public function testResetAllClearsVersionedPreference(): void {
		$config = $this->createMock(IConfig::class);
		$config->method('getUserValue')->willReturnCallback(
			static fn(string $userId, string $appId, string $key): string => $key === 'office_simulation_intro_version' ? '1' : ''
		);
		$config->expects($this->once())
			->method('deleteUserValue')
			->with('alice', Application::APP_ID, 'office_simulation_intro_version');

		$reset = (new TutorialProgressService($config))->resetAll('alice');

		$this->assertSame(1, $reset);
	}
}
