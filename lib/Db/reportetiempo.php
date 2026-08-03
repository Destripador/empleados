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
	protected ?string $origen = null;
	protected ?int $origenId = null;
	protected ?string $actividadNombre = null;
	protected ?bool $cargable = null;
	protected ?int $idEquipo = null;
	protected ?string $nombreDispositivo = null;

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
		$this->addType('origen', 'string');
		$this->addType('origenId', 'integer');
		$this->addType('actividadNombre', 'string');
		$this->addType('cargable', 'bool');
		$this->addType('idEquipo', 'integer');
		$this->addType('nombreDispositivo', 'string');
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
			'origen'            => $this->origen,
			'origen_id'         => $this->origenId,
			'actividad_nombre'  => $this->actividadNombre,
			'cargable'          => $this->cargable,
			'id_equipo'         => $this->idEquipo,
			'nombre_dispositivo' => $this->nombreDispositivo,
		];
	}
}
