<?php

declare(strict_types=1);

namespace OCA\Empleados\Service\Ai;

final class ContextValidator {
	private const SCOPE = 'vacaciones-empleado';
	private const MAX_QUESTION_LENGTH = 1000;
	private const MAX_CONTEXT_BYTES = 65536;
	private const MAX_RECORDS = 50;
	private const MAX_TEXT_LENGTH = 200;
	private const MAX_DEPTH = 4;

	private const CONTEXT_FIELDS = [
		'empleado' => ['nombre'],
		'periodo' => [
			'numero_aniversario',
			'inicio',
			'fin',
			'dias_derecho',
			'dias_disfrutados',
			'dias_restantes',
			'dias_acumulados',
			'fecha_expiracion_acumulados',
		],
		'prima_vacacional' => ['solicitada', 'fecha'],
	];

	private const RECORD_FIELDS = [
		'tipo',
		'fecha_inicio',
		'fecha_fin',
		'dias',
		'estado',
	];

	public function validateAndSanitize(
		string $scope,
		string $question,
		array $context,
	): array {
		if ($scope !== self::SCOPE) {
			throw new \InvalidArgumentException('invalid_scope');
		}

		$question = trim($question);
		if ($question === '') {
			throw new \InvalidArgumentException('empty_question');
		}
		if ($this->textLength($question) > self::MAX_QUESTION_LENGTH) {
			throw new \InvalidArgumentException('question_too_long');
		}

		try {
			$encodedContext = json_encode(
				$context,
				JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
			);
		} catch (\JsonException $e) {
			throw new \InvalidArgumentException('invalid_context', 0, $e);
		}

		if (strlen($encodedContext) > self::MAX_CONTEXT_BYTES) {
			throw new \InvalidArgumentException('context_too_large');
		}

		$this->validateValue($context, 1, true);

		$cleanContext = [];
		$allowedSections = array_merge(array_keys(self::CONTEXT_FIELDS), ['registros_visibles']);
		foreach ($context as $section => $value) {
			if (!in_array($section, $allowedSections, true) && is_array($value)) {
				throw new \InvalidArgumentException('invalid_context');
			}
		}
		foreach (self::CONTEXT_FIELDS as $section => $fields) {
			if (!isset($context[$section])) {
				continue;
			}
			if (!is_array($context[$section]) || array_is_list($context[$section])) {
				throw new \InvalidArgumentException('invalid_context');
			}
			$cleanContext[$section] = $this->sanitizeFields($context[$section], $fields);
		}

		if (isset($context['registros_visibles'])) {
			if (!is_array($context['registros_visibles']) || !array_is_list($context['registros_visibles'])) {
				throw new \InvalidArgumentException('invalid_context');
			}
			if (count($context['registros_visibles']) > self::MAX_RECORDS) {
				throw new \InvalidArgumentException('too_many_records');
			}
			$cleanContext['registros_visibles'] = [];
			foreach ($context['registros_visibles'] as $record) {
				if (!is_array($record) || array_is_list($record)) {
					throw new \InvalidArgumentException('invalid_context');
				}
				$cleanContext['registros_visibles'][] = $this->sanitizeFields($record, self::RECORD_FIELDS);
			}
		}

		return [
			'scope' => $scope,
			'question' => $question,
			'context' => $cleanContext,
		];
	}

	private function sanitizeFields(array $data, array $allowedFields): array {
		$sanitized = [];
		foreach ($data as $field => $value) {
			if (!in_array($field, $allowedFields, true) && is_array($value)) {
				throw new \InvalidArgumentException('invalid_context');
			}
		}
		foreach ($allowedFields as $field) {
			if (!array_key_exists($field, $data)) {
				continue;
			}
			$value = $data[$field];
			if (!is_string($value) && !is_int($value) && !is_float($value) && !is_bool($value) && $value !== null) {
				throw new \InvalidArgumentException('invalid_context');
			}
			if (is_string($value) && $this->textLength($value) > self::MAX_TEXT_LENGTH) {
				throw new \InvalidArgumentException('text_too_long');
			}
			$sanitized[$field] = $value;
		}
		return $sanitized;
	}

	private function validateValue(mixed $value, int $depth, bool $arraysAllowed = false): void {
		if ($depth > self::MAX_DEPTH) {
			throw new \InvalidArgumentException('context_too_deep');
		}
		if (is_object($value) || is_resource($value)) {
			throw new \InvalidArgumentException('invalid_context');
		}
		if (is_array($value)) {
			if (!$arraysAllowed && $depth >= self::MAX_DEPTH) {
				throw new \InvalidArgumentException('invalid_context');
			}
			foreach ($value as $child) {
				$this->validateValue($child, $depth + 1, true);
			}
			return;
		}
		if (!is_string($value) && !is_int($value) && !is_float($value) && !is_bool($value) && $value !== null) {
			throw new \InvalidArgumentException('invalid_context');
		}
	}

	private function textLength(string $value): int {
		return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
	}
}
