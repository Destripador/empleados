<?php

declare(strict_types=1);

namespace OCA\Empleados\Exception;

class MantenimientoConflictException extends \RuntimeException {
	public function __construct(
		string $message,
		private array $conflicts = [],
		?\Throwable $previous = null,
	) {
		parent::__construct($message, 0, $previous);
	}

	public function getConflicts(): array {
		return $this->conflicts;
	}
}
