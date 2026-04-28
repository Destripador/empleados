<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\Entity;

class ReporteTiempo extends Entity {

	protected ?int $idReporte = null;
	protected ?int $idEmpleado = null;
	protected ?int $idCliente = null;
	protected ?int $idActividad = null;
	protected ?string $descripcion = null;
	protected float $tiempoRegistrado = 0.0;
	protected string $fechaRegistro = '';
	protected string $createdAt = '';
	protected string $updatedAt = '';

	public function __construct() {
		$this->addType('idReporte', 'integer');
		$this->addType('idEmpleado', 'string');
		$this->addType('idCliente', 'integer');
		$this->addType('idActividad', 'integer');
		$this->addType('descripcion', 'string');
		$this->addType('tiempoRegistrado', 'float');
		$this->addType('fechaRegistro', 'string');
		$this->addType('createdAt', 'string');
		$this->addType('updatedAt', 'string');
	}

	public function read(): array {
		return [
			'id_reporte'        => $this->idReporte,
			'id_cliente'        => $this->idCliente,
			'id_actividad'      => $this->idActividad,
			'id_empleado'       => $this->idEmpleado,
			'descripcion'       => $this->descripcion,
			'tiempo_registrado' => $this->tiempoRegistrado,
			'fecha_registro'    => $this->fechaRegistro,
			'created_at'        => $this->createdAt,
			'updated_at'        => $this->updatedAt,
		];
	}
}
