<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\Entity;


class departamentos extends Entity {
    
    protected string $id_deparamentos = '';
    protected string $id_padre = '';
    protected string $nombre = '';
    protected string $created_at = '';
    protected string $updated_at = '';

	public function __construct() {
        $this->addType('Id_departamentos', 'string');
        $this->addType('Id_padre', 'string');
		$this->addType('Nombre', 'string');
		$this->addType('created_at', 'string');
		$this->addType('updated_at', 'string');
	}

	public function read(): array {
		return [
			'Id_departamentos' => $this->id_departamentos,
			'Id_padre' => $this->id_padre,
			'Nombre' => $this->nombre,
			'created_at' => $this->created_at,
			'updated_at' => $this->updated_at,
		];
	}
}