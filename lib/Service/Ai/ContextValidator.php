<?php

declare(strict_types=1);

namespace OCA\Empleados\Service\Ai;

final class ContextValidator {
	private const SCOPE = 'vacaciones-empleado';
	private const MAX_QUESTION_LENGTH = 1000;
	private const MAX_CONTEXT_BYTES = 65536;
	private const MAX_RECORDS = 50;
	private const MAX_TEXT_LENGTH = 200;

	public function validateAndSanitize(
		string $scope,
		string $question,
		array $context,
	): array {
		if ($scope !== self::SCOPE) {
			throw new \InvalidArgumentException('El alcance solicitado no es válido.');
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
		if ($context !== [] && array_is_list($context)) {
			throw new \InvalidArgumentException('El contexto contiene una estructura no permitida.');
		}

		$this->rejectUnexpectedStructures(
			$context,
			['empleado', 'periodo', 'prima_vacacional', 'registros_visibles']
		);

		$empleado = $this->getSection($context, 'empleado');
		$periodo = $this->getSection($context, 'periodo');
		$primaVacacional = $this->getSection($context, 'prima_vacacional');
		$registros = $this->sanitizeRecords($context['registros_visibles'] ?? []);

		return [
			'scope' => self::SCOPE,
			'question' => $question,
			'context' => [
				'empleado' => [
					'nombre' => $this->getScalar($empleado, 'nombre'),
				],
				'periodo' => [
					'numero_aniversario' => $this->getScalar($periodo, 'numero_aniversario'),
					'inicio' => $this->getScalar($periodo, 'inicio'),
					'fin' => $this->getScalar($periodo, 'fin'),
					'dias_derecho' => $this->getScalar($periodo, 'dias_derecho'),
					'dias_disfrutados' => $this->getScalar($periodo, 'dias_disfrutados'),
					'dias_restantes' => $this->getScalar($periodo, 'dias_restantes'),
					'dias_acumulados' => $this->getScalar($periodo, 'dias_acumulados'),
					'fecha_expiracion_acumulados' => $this->getScalar($periodo, 'fecha_expiracion_acumulados'),
				],
				'prima_vacacional' => [
					'solicitada' => $this->getScalar($primaVacacional, 'solicitada'),
					'fecha' => $this->getScalar($primaVacacional, 'fecha'),
				],
				'registros_visibles' => $registros,
			],
		];
	}

	private function getSection(array $context, string $key): array {
		if (!array_key_exists($key, $context)) {
			return [];
		}
		if (!is_array($context[$key]) || ($context[$key] !== [] && array_is_list($context[$key]))) {
			throw new \InvalidArgumentException('El contexto contiene una sección inválida.');
		}
		return $context[$key];
	}

	private function sanitizeRecords(mixed $records): array {
		if (!is_array($records) || !array_is_list($records)) {
			throw new \InvalidArgumentException('Los registros visibles no son válidos.');
		}
		if (count($records) > self::MAX_RECORDS) {
			throw new \InvalidArgumentException('Hay demasiados registros visibles.');
		}

		$sanitized = [];
		foreach ($records as $record) {
			if (!is_array($record) || ($record !== [] && array_is_list($record))) {
				throw new \InvalidArgumentException('Un registro visible no es válido.');
			}
			$this->rejectUnexpectedStructures(
				$record,
				['tipo', 'fecha_inicio', 'fecha_fin', 'dias', 'estado']
			);
			$sanitized[] = [
				'tipo' => $this->getScalar($record, 'tipo'),
				'fecha_inicio' => $this->getScalar($record, 'fecha_inicio'),
				'fecha_fin' => $this->getScalar($record, 'fecha_fin'),
				'dias' => $this->getScalar($record, 'dias'),
				'estado' => $this->getScalar($record, 'estado'),
			];
		}
		return $sanitized;
	}

	private function rejectUnexpectedStructures(array $data, array $allowedKeys): void {
		foreach ($data as $key => $value) {
			if (!in_array($key, $allowedKeys, true) && (is_array($value) || is_object($value) || is_resource($value))) {
				throw new \InvalidArgumentException('El contexto contiene una estructura no permitida.');
			}
		}
	}

	private function getScalar(array $data, string $key): string|int|float|bool|null {
		if (!array_key_exists($key, $data) || $data[$key] === null) {
			return null;
		}
		$value = $data[$key];
		if (!is_string($value) && !is_int($value) && !is_float($value) && !is_bool($value)) {
			throw new \InvalidArgumentException('El contexto contiene un valor no permitido.');
		}
		if (is_string($value) && $this->textLength($value) > self::MAX_TEXT_LENGTH) {
			throw new \InvalidArgumentException('Un campo de texto supera la longitud permitida.');
		}
		return $value;
	}

	private function textLength(string $value): int {
		return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
	}
}
