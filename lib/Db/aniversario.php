<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\Entity;


class aniversario extends Entity {
    
    protected ?int $id_aniversario = null;
    protected ?int $numero_aniversario = null;
    protected string $fecha_de = '';
    protected string $fecha_hasta = '';
	protected ?float $dias = null;

	public function __construct() {
        $this->addType('id_aniversario', 'int');
        $this->addType('numero_aniversario', 'int');
		$this->addType('fecha_de', 'string');
		$this->addType('fecha_hasta', 'string');
		$this->addType('dias', 'float');
	}

	public function read(): array {
		return [
			'id_aniversario' => $this->id_aniversario,
			'numero_aniversario' => $this->numero_aniversario,
			'fecha_de' => $this->fecha_de,
			'fecha_hasta' => $this->fecha_hasta,
			'dias' => $this->dias,
		];
	}
}