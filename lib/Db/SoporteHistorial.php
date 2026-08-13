<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\Entity;

class SoporteHistorial extends Entity {

	protected ?int $id_soporte = null;
	protected ?int $id_equipo = null;
	protected ?string $accion = null;
	protected ?string $detalles = null;
	protected ?string $fecha = null;
	protected ?string $usuario_actual = null;
	protected ?string $usuario_soporte = null;
	protected ?string $created_at = null;
	protected ?string $updated_at = null;
	protected ?int $duracion_minutos = null;

	public function __construct() {
		$this->addType('id_soporte', 'integer');
		$this->addType('id_equipo', 'integer');
		$this->addType('accion', 'string');
		$this->addType('detalles', 'string');
		$this->addType('fecha', 'string');
		$this->addType('usuario_actual', 'string');
		$this->addType('usuario_soporte', 'string');
		$this->addType('created_at', 'string');
		$this->addType('updated_at', 'string');
		$this->addType('duracion_minutos', 'integer');
	}

	public function read(): array {
		return [
			'id_soporte' => $this->id_soporte,
			'id_equipo' => $this->id_equipo,
			'accion' => $this->accion,
			'detalles' => $this->detalles,
			'fecha' => $this->fecha,
			'usuario_actual' => $this->usuario_actual,
			'usuario_soporte' => $this->usuario_soporte,
			'created_at' => $this->created_at,
			'updated_at' => $this->updated_at,
			'duracion_minutos' => $this->duracion_minutos,
		];
	}
}
