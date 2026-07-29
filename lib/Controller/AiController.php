<?php

declare(strict_types=1);

namespace OCA\Empleados\Controller;

use OCA\Empleados\Service\Ai\ContextAiService;
use OCA\Empleados\Service\Ai\ContextValidator;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class AiController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private IUserSession $userSession,
		private ContextAiService $aiService,
		private ContextValidator $validator,
	) {
		parent::__construct($appName, $request);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function capabilities(): DataResponse {
		if ($this->userSession->getUser() === null) {
			return new DataResponse(['available' => false, 'scopes' => []], Http::STATUS_UNAUTHORIZED);
		}

		return new DataResponse([
			'available' => $this->aiService->isAvailable(),
			'scopes' => ['vacaciones-empleado'],
		]);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function ask(
		string $scope,
		string $question,
		array $context = [],
	): DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new DataResponse(['message' => 'No autenticado.'], Http::STATUS_UNAUTHORIZED);
		}

		try {
			$validated = $this->validator->validateAndSanitize($scope, $question, $context);
		} catch (\InvalidArgumentException $e) {
			$message = $e->getMessage() === 'question_too_long'
				? 'La pregunta es demasiado larga.'
				: 'El contexto de esta vista no es válido.';
			return new DataResponse(['message' => $message], Http::STATUS_BAD_REQUEST);
		}

		if (!$this->aiService->isAvailable()) {
			return new DataResponse(
				['message' => 'La IA no está disponible en esta instancia.'],
				Http::STATUS_PRECONDITION_FAILED
			);
		}

		try {
			$answer = $this->aiService->ask(
				$validated['scope'],
				$validated['question'],
				$validated['context'],
				$user->getUID()
			);
			return new DataResponse(['answer' => $answer]);
		} catch (\Throwable) {
			return new DataResponse(
				['message' => 'No fue posible obtener una respuesta.'],
				Http::STATUS_INTERNAL_SERVER_ERROR
			);
		}
	}
}
