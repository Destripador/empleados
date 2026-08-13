<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\Entity;


class historialahorro extends Entity {
    
    protected string $id_historial = '';
    protected string $id_ahorro = '';
    protected string $cantidad_solicitada = '';
    protected string $cantidad_total = '';
    protected string $fecha_solicitud = '';
    protected string $estado = '';
    protected string $nota = '';

	public function __construct() {
        $this->addType('id_historial', 'string');
		$this->addType('id_ahorro', 'string');
		$this->addType('cantidad_solicitada', 'string');
		$this->addType('cantidad_total', 'string');
		$this->addType('fecha_solicitud', 'string');
		$this->addType('estado', 'string');
		$this->addType('nota', 'string');
	}

	public function read(): array {
		return [
			'id_historial' => $this->id_historial,
			'id_ahorro' => $this->id_ahorro,
			'cantidad_solicitada' => $this->cantidad_solicitada,
			'cantidad_total' => $this->cantidad_total,
			'fecha_solicitud' => $this->fecha_solicitud,
			'estado' => $this->estado,'estado' => $this->estado,
			'nota' => $this->nota,
		];
	}
}