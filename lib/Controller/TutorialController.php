<?php

declare(strict_types=1);

namespace OCA\Empleados\Controller;

use OCA\Empleados\Exception\TutorialLessonNotFoundException;
use OCA\Empleados\Service\TutorialProgressService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;
use Throwable;

class TutorialController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private IUserSession $userSession,
		private TutorialProgressService $progressService,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[UseSession]
	public function status(string $lessonId): DataResponse {
		$userId = $this->requireUserId();
		if ($userId === null) {
			return $this->unauthorizedResponse();
		}

		try {
			$completed = $this->progressService->isCompleted($userId, $lessonId);

			return new DataResponse([
				'lesson' => $lessonId,
				'completed' => $completed,
			]);
		} catch (TutorialLessonNotFoundException $e) {
			return $this->notFoundResponse($e->getMessage());
		} catch (Throwable $e) {
			return $this->internalErrorResponse($e);
		}
	}

	#[NoAdminRequired]
	#[UseSession]
	public function complete(string $lessonId): DataResponse {
		$userId = $this->requireUserId();
		if ($userId === null) {
			return $this->unauthorizedResponse();
		}

		try {
			$this->progressService->complete($userId, $lessonId);

			return new DataResponse([
				'success' => true,
				'lesson' => $lessonId,
				'completed' => true,
			]);
		} catch (TutorialLessonNotFoundException $e) {
			return $this->notFoundResponse($e->getMessage());
		} catch (Throwable $e) {
			return $this->internalErrorResponse($e);
		}
	}

	#[NoAdminRequired]
	#[UseSession]
	public function reset(string $lessonId): DataResponse {
		$userId = $this->requireUserId();
		if ($userId === null) {
			return $this->unauthorizedResponse();
		}

		try {
			$this->progressService->reset($userId, $lessonId);

			return new DataResponse([
				'success' => true,
				'lesson' => $lessonId,
				'completed' => false,
			]);
		} catch (TutorialLessonNotFoundException $e) {
			return $this->notFoundResponse($e->getMessage());
		} catch (Throwable $e) {
			return $this->internalErrorResponse($e);
		}
	}

	#[NoAdminRequired]
	#[UseSession]
	public function resetAll(): DataResponse {
		$userId = $this->requireUserId();
		if ($userId === null) {
			return $this->unauthorizedResponse();
		}

		try {
			$reset = $this->progressService->resetAll($userId);

			return new DataResponse([
				'success' => true,
				'reset' => $reset,
			]);
		} catch (Throwable $e) {
			return $this->internalErrorResponse($e);
		}
	}

	private function requireUserId(): ?string {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return null;
		}

		$uid = $user->getUID();
		return $uid !== '' ? $uid : null;
	}

	private function unauthorizedResponse(): DataResponse {
		return new DataResponse([
			'success' => false,
			'error' => 'No autenticado.',
		], Http::STATUS_UNAUTHORIZED);
	}

	private function notFoundResponse(string $message): DataResponse {
		return new DataResponse([
			'success' => false,
			'error' => $message !== '' ? $message : 'Lección no encontrada.',
		], Http::STATUS_NOT_FOUND);
	}

	private function internalErrorResponse(Throwable $e): DataResponse {
		$this->logger->error('Tutorial controller error: ' . $e->getMessage(), [
			'exception' => $e,
			'app' => 'empleados',
		]);

		return new DataResponse([
			'success' => false,
			'error' => 'Error interno.',
		], Http::STATUS_INTERNAL_SERVER_ERROR);
	}
}
