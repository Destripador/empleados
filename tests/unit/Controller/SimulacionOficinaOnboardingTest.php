<?php

declare(strict_types=1);

namespace OCA\Empleados\Tests\Unit\Controller;

use OCA\Empleados\Controller\SimulacionOficinaController;
use OCA\Empleados\Service\TutorialProgressService;
use OCA\Empleados\Tutorial\TutorialCatalog;
use OCP\AppFramework\Http;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

class SimulacionOficinaOnboardingTest extends TestCase {
	public function testStatusUsesAuthenticatedUserId(): void {
		$progress = $this->createMock(TutorialProgressService::class);
		$progress->expects($this->once())
			->method('getVersionStatus')
			->with('alice', TutorialCatalog::OFFICE_SIMULATION_INTRO)
			->willReturn([
				'completed' => false,
				'completedVersion' => 0,
				'requiredVersion' => 1,
			]);

		$response = $this->controller($progress)->getOnboarding();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertFalse($response->getData()['completed']);
	}

	public function testCompleteUsesAuthenticatedUserIdAndRequestedVersion(): void {
		$progress = $this->createMock(TutorialProgressService::class);
		$progress->expects($this->once())
			->method('completeVersion')
			->with('alice', TutorialCatalog::OFFICE_SIMULATION_INTRO, 1)
			->willReturn([
				'completed' => true,
				'completedVersion' => 1,
				'requiredVersion' => 1,
			]);

		$response = $this->controller($progress)->completeOnboarding(1);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertTrue($response->getData()['success']);
	}

	public function testCompleteRejectsInvalidVersionWithoutWriting(): void {
		$progress = $this->createMock(TutorialProgressService::class);
		$progress->expects($this->never())->method('completeVersion');

		$response = $this->controller($progress)->completeOnboarding('invalid');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testStatusRequiresAuthenticatedUser(): void {
		$progress = $this->createMock(TutorialProgressService::class);
		$progress->expects($this->never())->method('getVersionStatus');

		$this->expectException(OCSForbiddenException::class);
		$this->controller($progress, false)->getOnboarding();
	}

	private function controller(TutorialProgressService $progress, bool $authenticated = true): SimulacionOficinaController {
		$user = $authenticated ? $this->createMock(IUser::class) : null;
		if ($user !== null) {
			$user->method('getUID')->willReturn('alice');
		}

		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);
		$groupManager = $this->createMock(IGroupManager::class);
		if ($user !== null) {
			$groupManager->method('getUserGroupIds')->with($user)->willReturn(['empleados']);
		}

		$reflection = new \ReflectionClass(SimulacionOficinaController::class);
		$controller = $reflection->newInstanceWithoutConstructor();
		foreach ([
			'userSession' => $userSession,
			'groupManager' => $groupManager,
			'tutorialProgressService' => $progress,
		] as $property => $value) {
			$target = $reflection->getProperty($property);
			$target->setValue($controller, $value);
		}

		return $controller;
	}
}
