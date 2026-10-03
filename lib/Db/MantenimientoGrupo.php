<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

class MantenimientoGrupo extends Entity implements JsonSerializable {
	public const ESTADO_ACTIVE = 'active';
	public const ESTADO_CANCELLED = 'cancelled';
	public const ESTADOS_VALIDOS = [self::ESTADO_ACTIVE, self::ESTADO_CANCELLED];

	protected $titulo;
	protected $idDepartamento;
	protected $departamentoNombre;
	protected $tipo;
	protected $fechaProgramada;
	protected $fechaInicio;
	protected $fechaFin;
	protected $horaInicio;
	protected $horaFin;
	protected $tecnicoUid;
	protected $tecnicoNombre;
	protected $estadoAdmin;
	protected $descripcion;
	protected $creadoPor;
	protected $fechaCreacion;
	protected $fechaActualizacion;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('idDepartamento', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'titulo' => $this->titulo,
			'id_departamento' => $this->idDepartamento,
			'departamento_nombre' => $this->departamentoNombre,
			'tipo' => $this->tipo,
			'fecha_programada' => $this->fechaProgramada,
			'fecha_inicio' => $this->fechaInicio,
			'fecha_fin' => $this->fechaFin,
			'periodStart' => $this->fechaInicio ?? $this->fechaProgramada,
			'periodEnd' => $this->fechaFin ?? $this->fechaProgramada,
			'startTime' => $this->horaInicio,
			'endTime' => $this->horaFin,
			'hora_inicio' => $this->horaInicio,
			'hora_fin' => $this->horaFin,
			'tecnico_uid' => $this->tecnicoUid,
			'tecnico_nombre' => $this->tecnicoNombre,
			'estado_admin' => $this->estadoAdmin,
			'descripcion' => $this->descripcion,
			'creado_por' => $this->creadoPor,
			'fecha_creacion' => $this->fechaCreacion,
			'fecha_actualizacion' => $this->fechaActualizacion,
		];
	}
}
