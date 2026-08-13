<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

class ausencias extends Entity {
	protected ?int $id_ausencias = null;
	protected ?int $id_aniversario = null;
	protected ?int $id_empleado = null;
	protected ?float $dias_disponibles = null;
	protected ?int $prima_vacacional = null;
	protected ?\DateTime $timestamp = null;

	public function __construct() {
		$this->addType('id_ausencias', Types::INTEGER);
		$this->addType('id_aniversario', Types::INTEGER);
		$this->addType('id_empleado', Types::INTEGER);
		$this->addType('dias_disponibles', Types::FLOAT);
		$this->addType('prima_vacacional', Types::INTEGER);
		$this->addType('timestamp', Types::DATETIME);
	}

	public function read(): array {
		return [
			'id_aniversario' => $this->id_aniversario,
			'id_empleado' => $this->id_empleado,
			'dias_disponibles' => $this->dias_disponibles,
			'prima_vacacional' => $this->prima_vacacional,
			'timestamp' => $this->timestamp,
		];
	}
}