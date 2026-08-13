<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

class MantenimientoEquipo extends Entity implements JsonSerializable {
	public const ESTADO_PENDING = 'pending';
	public const ESTADO_SCHEDULED = 'scheduled';
	public const ESTADO_IN_PROGRESS = 'in_progress';
	public const ESTADO_COMPLETED = 'completed';
	public const ESTADO_RESCHEDULED = 'rescheduled';
	public const ESTADO_CANCELLED = 'cancelled';
	public const ESTADO_NOT_APPLICABLE = 'not_applicable';
	public const ESTADOS_VALIDOS = [
		self::ESTADO_PENDING,
		self::ESTADO_SCHEDULED,
		self::ESTADO_IN_PROGRESS,
		self::ESTADO_COMPLETED,
		self::ESTADO_RESCHEDULED,
		self::ESTADO_CANCELLED,
		self::ESTADO_NOT_APPLICABLE,
	];
	public const ESTADOS_ACTIVOS = [
		self::ESTADO_PENDING,
		self::ESTADO_SCHEDULED,
		self::ESTADO_IN_PROGRESS,
		self::ESTADO_RESCHEDULED,
	];

	protected $idGrupo;
	protected $idEquipo;
	protected $equipoNombre;
	protected $equipoIdentificador;
	protected $idModelo;
	protected $modeloNombre;
	protected $numeroSerie;
	protected $idEmpleado;
	protected $empleadoUid;
	protected $empleadoNombre;
	protected $idDepartamento;
	protected $departamentoNombre;
	protected $tecnicoUid;
	protected $tecnicoNombre;
	protected $tipo;
	protected $fechaProgramada;
	protected $horaInicioProgramada;
	protected $horaFinProgramada;
	protected $fechaInicioReal;
	protected $fechaFinReal;
	protected $estado;
	protected $resultado;
	protected $accionesRealizadas;
	protected $incidencias;
	protected $repuestos;
	protected $observaciones;
	protected $proximaFecha;
	protected $creadoPor;
	protected $actualizadoPor;
	protected $fechaCreacion;
	protected $fechaActualizacion;

	public function __construct() {
		foreach (['id', 'idGrupo', 'idEquipo', 'idModelo', 'idEmpleado', 'idDepartamento'] as $field) {
			$this->addType($field, 'integer');
		}
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'id_grupo' => $this->idGrupo,
			'id_equipo' => $this->idEquipo,
			'equipo_nombre' => $this->equipoNombre,
			'equipo_identificador' => $this->equipoIdentificador,
			'id_modelo' => $this->idModelo,
			'modelo_nombre' => $this->modeloNombre,
			'numero_serie' => $this->numeroSerie,
			'id_empleado' => $this->idEmpleado,
			'empleado_uid' => $this->empleadoUid,
			'empleado_nombre' => $this->empleadoNombre,
			'id_departamento' => $this->idDepartamento,
			'departamento_nombre' => $this->departamentoNombre,
			'tecnico_uid' => $this->tecnicoUid,
			'tecnico_nombre' => $this->tecnicoNombre,
			'tipo' => $this->tipo,
			'fecha_programada' => $this->fechaProgramada,
			'hora_inicio_programada' => $this->horaInicioProgramada,
			'hora_fin_programada' => $this->horaFinProgramada,
			'fecha_inicio_real' => $this->fechaInicioReal,
			'fecha_fin_real' => $this->fechaFinReal,
			'estado' => $this->estado,
			'resultado' => $this->resultado,
			'acciones_realizadas' => $this->accionesRealizadas,
			'incidencias' => $this->incidencias,
			'repuestos' => $this->repuestos,
			'observaciones' => $this->observaciones,
			'proxima_fecha' => $this->proximaFecha,
			'creado_por' => $this->creadoPor,
			'actualizado_por' => $this->actualizadoPor,
			'fecha_creacion' => $this->fechaCreacion,
			'fecha_actualizacion' => $this->fechaActualizacion,
		];
	}
}
