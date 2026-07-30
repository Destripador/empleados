<?php

declare(strict_types=1);

namespace OCA\Empleados\Service\Ai\Scope;

interface ServerContextScopeInterface extends ContextScopeInterface {
	public function sanitizeParameters(array $parameters): array;

	public function buildServerContext(string $question, string $userId, array $parameters): array;
}
