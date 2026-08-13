<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\Entity;

class espacioObstruye extends Entity {

	protected $id_espacio_obstruye;
	protected $id_espacio;
	protected $id_obstruye;
	
	public function __construct() {
		$this->addType('id_espacio_obstruye', 'integer');
		$this->addType('id_espacio', 'integer');
		$this->addType('id_obstruye', 'integer');
	}

	public function read(): array {
		return [
			'id_espacio_obstruye'  => $this->id_espacio_obstruye,
			'id_espacio'       	   => $this->id_espacio,
			'id_obstruye'          => $this->id_obstruye,
		];
	}
}