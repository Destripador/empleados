<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

class CompraAutorizacion extends Entity implements JsonSerializable {
	protected $idAutorizacion;
	protected $idSolicitud;
	protected $idAutorizador;
	protected $idEmpleadoAutorizador;
	protected $autorizadorNombre;
	protected $rol;
	protected $nivel;
	protected $estado;
	protected $comentario;
	protected $fechaAutorizacion;
	protected $createdAt;
	protected $updatedAt;

	public function __construct() {
		$this->addType('idAutorizacion', 'integer');
		$this->addType('idSolicitud', 'integer');
		$this->addType('nivel', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id_autorizacion' => $this->idAutorizacion,
			'id_solicitud' => $this->idSolicitud,
			'id_autorizador' => $this->idAutorizador,
			'id_empleado_autorizador' => $this->idEmpleadoAutorizador,
			'autorizador_nombre' => $this->autorizadorNombre,
			'rol' => $this->rol,
			'nivel' => $this->nivel,
			'estado' => $this->estado,
			'comentario' => $this->comentario,
			'fecha_autorizacion' => $this->fechaAutorizacion,
			'created_at' => $this->createdAt,
			'updated_at' => $this->updatedAt,
		];
	}
}
