<?php

declare(strict_types=1);

namespace OCA\Empleados\Service\Ai\Scope;

interface ContextScopeInterface {
	public function getId(): string;

	public function getDescription(): string;

	public function getInstructions(): string;

	public function sanitize(array $context): array;
}
