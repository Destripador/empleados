<?php

declare(strict_types=1);

namespace OCA\Empleados\Service;

class ConfigRepairer {
	public function __construct(
		private DefaultConfigInitializer $initializer,
	) {
	}

	public function run(): void {
		$this->initializer->initialize();
	}
}
