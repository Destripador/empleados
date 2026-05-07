<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

class CompraSolicitud extends Entity implements JsonSerializable {

	protected $idSolicitud;
	protected $folio;
	protected $idUser;
	protected $idEmpleado;
	protected $idDepartamento;
	protected $idEquipo;
	protected $idCliente;
	protected $titulo;
	protected $descripcion;
	protected $justificacion;
	protected $montoEstimado;
	protected $montoFinal;
	protected $moneda;
	protected $prioridad;
	protected $estado;
	protected $fechaRequerida;
	protected $fechaEnvio;
	protected $fechaAutorizacion;
	protected $fechaCierre;
	protected $proveedorSeleccionado;
	protected $createdAt;
	protected $updatedAt;
	protected $createdBy;
	protected $updatedBy;

	public function __construct() {
		$this->addType('idSolicitud', 'integer');
		$this->addType('idCliente', 'integer');
		$this->addType('proveedorSeleccionado', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id_solicitud' => $this->idSolicitud,
			'folio' => $this->folio,
			'id_user' => $this->idUser,
			'id_empleado' => $this->idEmpleado,
			'id_departamento' => $this->idDepartamento,
			'id_equipo' => $this->idEquipo,
			'id_cliente' => $this->idCliente,
			'titulo' => $this->titulo,
			'descripcion' => $this->descripcion,
			'justificacion' => $this->justificacion,
			'monto_estimado' => $this->montoEstimado,
			'monto_final' => $this->montoFinal,
			'moneda' => $this->moneda,
			'prioridad' => $this->prioridad,
			'estado' => $this->estado,
			'fecha_requerida' => $this->fechaRequerida,
			'fecha_envio' => $this->fechaEnvio,
			'fecha_autorizacion' => $this->fechaAutorizacion,
			'fecha_cierre' => $this->fechaCierre,
			'proveedor_seleccionado' => $this->proveedorSeleccionado,
			'created_at' => $this->createdAt,
			'updated_at' => $this->updatedAt,
			'created_by' => $this->createdBy,
			'updated_by' => $this->updatedBy,
		];
	}
}
