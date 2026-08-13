<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\Entity;

class espacio extends Entity {

	protected $id_espacio;
	protected $numero;
	protected $disponible;
	protected $created_at;
	protected $updated_at;
	
	public function __construct() {
		$this->addType('id_espacio', 'integer');
		$this->addType('numero', 'integer');
		$this->addType('disponible', 'boolean');
		$this->addType('created_at', 'datetime');
		$this->addType('updated_at', 'datetime');
	}

	public function read(): array {
		return [
			'id_espacio'   => $this->id_espacio,
			'numero'       => $this->numero,
			'disponible'   => $this->disponible,
			'created_at'   => $this->created_at,
			'updated_at'   => $this->updated_at,
		];
	}
}