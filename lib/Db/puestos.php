<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\Entity;


class puestos extends Entity {
    
    protected string $idpuestos = '';
    protected string $nombre = '';
    protected string $created_at = '';
    protected string $updated_at = '';

	public function __construct() {
        $this->addType('Id_puestos', 'string');
		$this->addType('Nombre', 'string');
		$this->addType('created_at', 'date');
		$this->addType('updated_at', 'date');
	}

	public function read(): array {
		return [
			'Id_puestos' => $this->idpuestos,
			'Nombre' => $this->nombre,
			'created_at' => $this->created_at,
			'updated_at' => $this->updated_at,
		];
	}
}