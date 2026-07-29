<?php

declare(strict_types=1);

namespace OCA\Empleados\Service\Ai\Scope;

abstract class AbstractContextScope implements ContextScopeInterface {
	protected function stringOrNull(
		mixed $value,
		int $maxLength = 200,
	): ?string {
		if ($value === null) {
			return null;
		}
		if (!is_string($value)) {
			throw new \InvalidArgumentException('El contexto contiene un valor de texto inválido.');
		}

		return mb_substr(trim($value), 0, $maxLength, 'UTF-8');
	}

	protected function numberOrNull(
		mixed $value,
	): int|float|null {
		if ($value === null || is_int($value) || is_float($value)) {
			return $value;
		}

		throw new \InvalidArgumentException('El contexto contiene un valor numérico inválido.');
	}

	protected function boolOrNull(
		mixed $value,
	): ?bool {
		if ($value === null || is_bool($value)) {
			return $value;
		}

		throw new \InvalidArgumentException('El contexto contiene un valor booleano inválido.');
	}

	protected function arrayOrEmpty(
		mixed $value,
	): array {
		if ($value === null) {
			return [];
		}
		if (!is_array($value)) {
			throw new \InvalidArgumentException('El contexto contiene una estructura inválida.');
		}

		return $value;
	}

	protected function displayValueOrNull(
		mixed $value,
		int $maxLength = 200,
	): ?string {
		if ($value === null || is_string($value)) {
			return $this->stringOrNull($value, $maxLength);
		}
		if (!is_array($value)) {
			throw new \InvalidArgumentException('El contexto contiene un valor visible inválido.');
		}

		foreach (['displayName', 'displayname', 'label', 'name', 'nombre', 'user'] as $key) {
			if (!array_key_exists($key, $value) || $value[$key] === null) {
				continue;
			}

			return $this->stringOrNull($value[$key], $maxLength);
		}

		return null;
	}
}
