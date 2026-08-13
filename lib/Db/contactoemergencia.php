<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\Entity;

class contactoemergencia extends Entity {
	protected $idEmpleado = 0;
	protected $nombre = '';
	protected $relacion = '';
	protected $numeroContacto = '';
	protected $medioAlternativo = null;
	protected $tipoAyuda = null;
	protected $notas = null;
	protected $esPrincipal = 0;
	protected $principalEmpleado = null;
	protected $orden = 0;
	protected $createdAt = '';
	protected $updatedAt = '';

	public function __construct() {
		$this->addType('idEmpleado', 'integer');
		$this->addType('nombre', 'string');
		$this->addType('relacion', 'string');
		$this->addType('numeroContacto', 'string');
		$this->addType('medioAlternativo', 'string');
		$this->addType('tipoAyuda', 'string');
		$this->addType('notas', 'string');
		$this->addType('esPrincipal', 'integer');
		$this->addType('principalEmpleado', 'integer');
		$this->addType('orden', 'integer');
		$this->addType('createdAt', 'string');
		$this->addType('updatedAt', 'string');
	}
}
