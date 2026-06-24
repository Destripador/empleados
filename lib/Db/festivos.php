<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\Entity;

class festivos extends Entity {
	protected ?int $id_festivo = null;
	protected string $nombre = '';
	protected string $fecha = '';

	public function __construct() {
		$this->addType('id_festivo', 'integer');
		$this->addType('nombre', 'string');
		$this->addType('fecha', 'string');
	}

	public function read(): array {
		return [
			'id_festivo' => $this->id_festivo,
			'nombre' => $this->nombre,
			'fecha' => $this->fecha,
		];
	}
}