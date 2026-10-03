<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\Entity;

class primavacacionalpago extends Entity {

	protected string $id_empleado = '';
	protected string $numero_aniversario = '';
	protected string $fecha_pago = '';
	protected string $dias_pagados = '';
	protected string $created_at = '';
	protected string $updated_at = '';

	public function __construct() {
		$this->addType('id_empleado', 'integer');
		$this->addType('numero_aniversario', 'integer');
		$this->addType('fecha_pago', 'string');
		$this->addType('dias_pagados', 'decimal');
		$this->addType('created_at', 'string');
		$this->addType('updated_at', 'string');
	}

	public function read(): array {
		return [
			'id_empleado' => $this->id_empleado,
			'numero_aniversario' => $this->numero_aniversario,
			'fecha_pago' => $this->fecha_pago,
			'dias_pagados' => $this->dias_pagados,
			'created_at' => $this->created_at,
			'updated_at' => $this->updated_at,
		];
	}
}