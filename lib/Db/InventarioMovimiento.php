<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\Entity;

class InventarioMovimiento extends Entity {
	public const TIPO_ALTA = 'alta';
	public const TIPO_ASIGNACION = 'asignacion';
	public const TIPO_REASIGNACION = 'reasignacion';
	public const TIPO_DESASIGNACION = 'desasignacion';
	public const TIPO_CAMBIO_ESTADO = 'cambio_estado';
	public const TIPO_ACTUALIZACION = 'actualizacion';
	public const TIPO_MANTENIMIENTO = 'mantenimiento';
	public const TIPO_REPARACION = 'reparacion';
	public const TIPO_BAJA = 'baja';
	public const TIPO_NOTA = 'nota';
	public const TIPOS_VALIDOS = [
		self::TIPO_ALTA,
		self::TIPO_ASIGNACION,
		self::TIPO_REASIGNACION,
		self::TIPO_DESASIGNACION,
		self::TIPO_CAMBIO_ESTADO,
		self::TIPO_ACTUALIZACION,
		self::TIPO_MANTENIMIENTO,
		self::TIPO_REPARACION,
		self::TIPO_BAJA,
		self::TIPO_NOTA,
	];

	protected $idEquipo;
	protected $tipoMovimiento;
	protected $actorUid;
	protected $actorNombre;
	protected $empleadoAnteriorUid;
	protected $empleadoAnteriorNombre;
	protected $empleadoNuevoUid;
	protected $empleadoNuevoNombre;
	protected $estadoAnterior;
	protected $estadoNuevo;
	protected $descripcion;
	protected $cambios;
	protected $fecha;

	public function __construct() {
		$this->addType('idEquipo', 'integer');
		$this->addType('tipoMovimiento', 'string');
		$this->addType('actorUid', 'string');
		$this->addType('actorNombre', 'string');
		$this->addType('empleadoAnteriorUid', 'string');
		$this->addType('empleadoAnteriorNombre', 'string');
		$this->addType('empleadoNuevoUid', 'string');
		$this->addType('empleadoNuevoNombre', 'string');
		$this->addType('estadoAnterior', 'string');
		$this->addType('estadoNuevo', 'string');
		$this->addType('descripcion', 'string');
		$this->addType('cambios', 'string');
		$this->addType('fecha', 'string');
	}
}
