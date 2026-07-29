<?php

declare(strict_types=1);

namespace OCA\Empleados\Service\Ai;

use OCA\Empleados\Service\Ai\Scope\ContextScopeRegistry;

final class ContextValidator {
	private const MAX_QUESTION_LENGTH = 1000;
	private const MAX_CONTEXT_BYTES = 65536;

	public function __construct(
		private ContextScopeRegistry $scopeRegistry,
	) {
	}

	public function validateAndSanitize(
		string $scope,
		string $question,
		array $context,
	): array {
		if ($scope === '') {
			throw new \InvalidArgumentException('El alcance es obligatorio.');
		}

		$question = trim($question);
		if ($question === '') {
			throw new \InvalidArgumentException('La pregunta es obligatoria.');
		}
		if ($this->textLength($question) > self::MAX_QUESTION_LENGTH) {
			throw new \InvalidArgumentException('La pregunta es demasiado larga.');
		}

		try {
			$encodedContext = json_encode(
				$context,
				JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
			);
		} catch (\JsonException $e) {
			throw new \InvalidArgumentException('El contexto no es válido.', 0, $e);
		}

		if (strlen($encodedContext) > self::MAX_CONTEXT_BYTES) {
			throw new \InvalidArgumentException('El contexto supera el tamaño permitido.');
		}

		$scopeDefinition = $this->scopeRegistry->get($scope);
		$cleanContext = $scopeDefinition->sanitize($context);

		return [
			'scope' => $scopeDefinition,
			'question' => $question,
			'context' => $cleanContext,
		];
	}

	private function textLength(string $value): int {
		return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
	}
}
