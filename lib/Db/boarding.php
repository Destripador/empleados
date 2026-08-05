<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\Entity;

class boarding extends Entity {
	protected ?int $idBoarding = null;
	protected string $nombre = '';
	protected int $on = 1; // 1 = onboarding, 0 = offboarding

	public function __construct() {
		$this->addType('idBoarding', 'integer');
		$this->addType('nombre', 'string');
		$this->addType('on', 'integer');
	}

	public function read(): array {
		return [
			'id_boarding' => $this->idBoarding,
			'nombre' => $this->nombre,
			'on' => $this->on,
		];
	}
}