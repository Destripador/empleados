<?php

declare(strict_types=1);

namespace OCA\Empleados\Service\Ai;

use OCA\Empleados\Service\Ai\Scope\ContextScopeRegistry;
use OCA\Empleados\Service\Ai\Scope\ContextScopeInterface;
use OCA\Empleados\Service\Ai\Scope\ServerContextScopeInterface;

final class ContextValidator {
	private const MAX_QUESTION_LENGTH = 1000;
	private const MAX_CONTEXT_BYTES = 65536;
	private const MAX_SERVER_PARAMETERS_BYTES = 16384;
	private const MAX_HISTORY_MESSAGES = 12;
	private const MAX_HISTORY_MESSAGE_LENGTH = 2000;

	public function __construct(
		private ContextScopeRegistry $scopeRegistry,
	) {
	}

	public function validateAndSanitize(
		string $scope,
		string $question,
		array $context,
		array $history = [],
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

		$scopeDefinition = $this->scopeRegistry->get($scope);
		$isServerContext = $scopeDefinition instanceof ServerContextScopeInterface;
		$maxContextBytes = $isServerContext
			? self::MAX_SERVER_PARAMETERS_BYTES
			: self::MAX_CONTEXT_BYTES;
		if (strlen($encodedContext) > $maxContextBytes) {
			throw new \InvalidArgumentException('El contexto supera el tamaño permitido.');
		}

		$cleanHistory = $this->historyAfterDomainBoundary(
			$this->sanitizeHistory($history),
		);
		return [
			'scope' => $scopeDefinition,
			'question' => $question,
			'contextual_question' => $this->contextualizeQuestion($question, $cleanHistory),
			'context' => $context,
			'history' => $cleanHistory,
			'server_context' => $isServerContext,
		];
	}

	public function sanitizeAuthorizedContext(
		ContextScopeInterface $scope,
		array $context,
	): array {
		return $scope instanceof ServerContextScopeInterface
			? $scope->sanitizeParameters($context)
			: $scope->sanitize($context);
	}

	private function sanitizeHistory(array $history): array {
		if (!array_is_list($history) || count($history) > self::MAX_HISTORY_MESSAGES) {
			throw new \InvalidArgumentException('El historial no es válido.');
		}

		$clean = [];
		$expectedRole = 'user';
		foreach ($history as $message) {
			if (!is_array($message) || ($message !== [] && array_is_list($message))) {
				throw new \InvalidArgumentException('El historial no es válido.');
			}
			if (array_diff(array_keys($message), ['role', 'content']) !== []) {
				throw new \InvalidArgumentException('El historial contiene campos no permitidos.');
			}
			$role = $message['role'] ?? null;
			$content = $message['content'] ?? null;
			if (!in_array($role, ['user', 'assistant'], true)
				|| $role !== $expectedRole
				|| !is_string($content)) {
				throw new \InvalidArgumentException('El historial no es válido.');
			}
			$content = trim($content);
			if ($content === '' || $this->textLength($content) > self::MAX_HISTORY_MESSAGE_LENGTH) {
				throw new \InvalidArgumentException('El historial no es válido.');
			}
			$clean[] = ['role' => $role, 'content' => $content];
			$expectedRole = $role === 'user' ? 'assistant' : 'user';
		}
		if ($expectedRole === 'assistant') {
			throw new \InvalidArgumentException('El historial no es válido.');
		}
		return $clean;
	}

	private function historyAfterDomainBoundary(array $history): array {
		for ($index = count($history) - 1; $index >= 0; $index--) {
			if (($history[$index]['role'] ?? null) === 'assistant'
				&& str_starts_with(
					$history[$index]['content'] ?? '',
					'Esa información pertenece a otro submódulo:',
				)) {
				return array_slice($history, $index + 1);
			}
		}
		return $history;
	}

	private function contextualizeQuestion(string $question, array $history): string {
		if ($history === [] || !$this->looksLikeFollowUp($question)) {
			return $question;
		}

		$antecedents = [];
		for ($index = count($history) - 2; $index >= 0; $index -= 2) {
			$userMessage = $history[$index]['content'] ?? null;
			$assistantMessage = $history[$index + 1]['content'] ?? '';
			if (str_starts_with(
				$assistantMessage,
				'Esa información pertenece a otro submódulo:',
			)) {
				break;
			}
			if (!is_string($userMessage)) {
				continue;
			}
			array_unshift($antecedents, $userMessage);
			if (!$this->looksLikeFollowUp($userMessage)
				|| count($antecedents) >= 3) {
				break;
			}
		}
		if ($antecedents !== []) {
			return $question
				. "\n\nREFERENCIA DE CONTINUIDAD: La pregunta actual continúa "
				. "estas consultas anteriores del usuario:\n- "
				. implode("\n- ", $antecedents);
		}

		return $question;
	}

	private function looksLikeFollowUp(string $question): bool {
		if (preg_match(
			'/(?:^|\\s)(?:él|ella|ellos|ellas)(?:\\s|[?.!,;:]|$)/iu',
			$question,
			) === 1
			|| preg_match(
				'/^\\s*[¿?¡!]*\\s*cu[aá]l(?:es)?\\b.*'
					. '\\b(?:su|sus)(?:\\s|[?.!,;:]|$)/iu',
				$question,
			) === 1) {
			return true;
		}
		$startsAsContinuation = preg_match(
			'/^\\s*[¿?¡!]*\\s*(?:y|también|además)\\b/iu',
			$question,
		) === 1;
		if ($startsAsContinuation) {
			return preg_match('/\\bempleados?\\b/iu', $question) !== 1;
		}
		if (preg_match(
			'/\\b(?:qui[eé]n(?:es)?|empleados?|personas?|registros?|proyectos?|'
				. 'actividades|equipos?|clientes?)\\b/iu',
			$question,
		) === 1) {
			return false;
		}
		return preg_match(
			'/(?:^|\\s)(?:su|sus|le|les|él|ella|ellos|ellas|'
				. 'este|esta|ese|esa|anterior|mismo|misma)(?:\\s|[?.!,;:]|$)/iu',
			$question,
		) === 1;
	}

	private function textLength(string $value): int {
		return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
	}
}
