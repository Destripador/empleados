<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\Entity;

class espacioEmpleados extends Entity {

	protected $idEspacioEmpleado;
	protected $idEspacio;
	protected $idEmpleados;
	protected $createdAt;
	protected $updatedAt;

	public function __construct() {
		$this->addType('idEspacioEmpleado', 'integer');
		$this->addType('idEspacio', 'integer');
		$this->addType('idEmpleados', 'integer');
		$this->addType('createdAt', 'datetime');
		$this->addType('updatedAt', 'datetime');
	}

	public function read(): array {
		return [
			'id_espacio_empleado'	=> $this->idEspacioEmpleado,
			'id_espacio'   			=> $this->idEspacio,
			'id_empleados'  			=> $this->idEmpleados,
			'created_at'   			=> $this->createdAt,
			'updated_at'   			=> $this->updatedAt,
		];
	}
}