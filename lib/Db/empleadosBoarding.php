<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\Entity;

class empleadosBoarding extends Entity {
	protected ?int $idEmpleadoBoarding = null;
	protected int $idEmpleado = 0;
	protected int $idBoarding = 0;
	protected string $nombre = '';
	protected int $status = 0;

	public function __construct() {
		$this->addType('idEmpleadoBoarding', 'integer');
		$this->addType('idEmpleado', 'integer');
		$this->addType('idBoarding', 'integer');
		$this->addType('nombre', 'string');
		$this->addType('status', 'integer');
	}

	public function read(): array {
		return [
			'id_empleado_boarding' => $this->idEmpleadoBoarding,
			'id_empleado' => $this->idEmpleado,
			'id_boarding' => $this->idBoarding,
			'nombre' => $this->nombre,
			'status' => $this->status,
		];
	}
}