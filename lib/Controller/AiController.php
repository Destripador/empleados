<?php

declare(strict_types=1);

namespace OCA\Empleados\Controller;

use OCA\Empleados\AppInfo\Application;
use OCA\Empleados\Service\Ai\ContextAiService;
use OCA\Empleados\Service\Ai\ContextValidator;
use OCA\Empleados\Service\Ai\Scope\ContextScopeRegistry;
use OCA\Empleados\Service\Ai\Scope\ServerContextScopeInterface;
use OCA\Empleados\Service\PermisosService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class AiController extends Controller {
	public function __construct(
		IRequest $request,
		private IUserSession $userSession,
		private ContextValidator $validator,
		private ContextAiService $aiService,
		private ContextScopeRegistry $scopeRegistry,
		private PermisosService $permisosService,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function capabilities(): DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new DataResponse(['available' => false, 'scopes' => []], Http::STATUS_UNAUTHORIZED);
		}

		$available = $this->aiService->isAvailable($user->getUID());
		return new DataResponse([
			'available' => $available,
			'scopes' => $available ? $this->getAuthorizedScopeIds($user->getUID()) : [],
		]);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function ask(
		string $scope = '',
		string $question = '',
		array $context = [],
		array $history = [],
	): DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new DataResponse(['message' => 'No autenticado.'], Http::STATUS_UNAUTHORIZED);
		}

		try {
			$validated = $this->validator->validateAndSanitize($scope, $question, $context, $history);
		} catch (\InvalidArgumentException $e) {
			$message = $e->getMessage() === 'La pregunta es demasiado larga.'
				? 'La pregunta es demasiado larga.'
				: 'El contexto de esta vista no es válido.';
			return new DataResponse(['message' => $message], Http::STATUS_BAD_REQUEST);
		}

		try {
			$scopeDefinition = $validated['scope'];
			$this->permisosService->requireCanSeeAny(
				$scopeDefinition->getRequiredPermissions(),
			);
		} catch (\Throwable) {
			return new DataResponse(
				['message' => 'No tienes permiso para usar este asistente.'],
				Http::STATUS_FORBIDDEN
			);
		}

		if (!$this->aiService->isAvailable($user->getUID())) {
			return new DataResponse(
				['message' => 'La IA no está disponible en esta instancia.'],
				Http::STATUS_PRECONDITION_FAILED
			);
		}

		try {
			$sanitizedContext = $this->validator->sanitizeAuthorizedContext(
				$scopeDefinition,
				$validated['context'],
			);
		} catch (\InvalidArgumentException) {
			return new DataResponse(
				['message' => 'El contexto de esta vista no es válido.'],
				Http::STATUS_BAD_REQUEST
			);
		}

		try {
			$resolvedContext = $scopeDefinition instanceof ServerContextScopeInterface
				? $scopeDefinition->buildServerContext(
					$validated['contextual_question'],
					$user->getUID(),
					$sanitizedContext,
				)
				: $sanitizedContext;
			$answer = $this->aiService->ask(
				$scopeDefinition,
				$validated['contextual_question'],
				$resolvedContext,
				$user->getUID(),
				$validated['history'],
			);
			return new DataResponse(['answer' => $answer]);
		} catch (\Throwable) {
			if (!$this->aiService->isAvailable($user->getUID())) {
				return new DataResponse(
					['message' => 'La IA no está disponible en esta instancia.'],
					Http::STATUS_PRECONDITION_FAILED
				);
			}
			return new DataResponse(
				['message' => 'No fue posible obtener una respuesta.'],
				Http::STATUS_INTERNAL_SERVER_ERROR
			);
		}
	}

	private function getAuthorizedScopeIds(string $userId): array {
		$scopeIds = [];

		foreach ($this->scopeRegistry->getAvailableScopeIds() as $scopeId) {
			$scope = $this->scopeRegistry->get($scopeId);
			if ($this->permisosService->canSeeAny(
				$scope->getRequiredPermissions(),
				$userId,
			)) {
				$scopeIds[] = $scopeId;
			}
		}

		return $scopeIds;
	}
}
