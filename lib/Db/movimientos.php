<?php
declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\Entity;

class movimientos extends Entity {
    protected $modulo;
	protected $fecha;
	protected $idEmpleadoActor;
	protected $nombreActor;
	protected $idEmpleadoAfectado;
	protected $nombreAfectado;
	protected $tipoMovimiento;
	protected $idReferencia;
	protected $mensaje;

	public function __construct() {
		$this->addType('fecha', 'string');
		$this->addType('idEmpleadoActor', 'integer');
		$this->addType('idEmpleadoAfectado', 'integer');
		$this->addType('idReferencia', 'integer');
	}
}