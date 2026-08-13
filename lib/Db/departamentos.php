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
	protected int $mostrarClientes = 1;
	protected int $mostrarAusencias = 1;

	public function __construct() {
        $this->addType('Id_departamentos', 'string');
        $this->addType('Id_padre', 'string');
		$this->addType('Nombre', 'string');
		$this->addType('created_at', 'string');
		$this->addType('updated_at', 'string');
		$this->addType('mostrarClientes', 'integer');
		$this->addType('mostrarAusencias', 'integer');
	}

	public function read(): array {
		return [
			'Id_departamentos' => $this->id_departamentos,
			'Id_padre' => $this->id_padre,
			'Nombre' => $this->nombre,
			'created_at' => $this->created_at,
			'updated_at' => $this->updated_at,
			'mostrar_clientes' => (int)$this->mostrarClientes === 1,
			'mostrar_ausencias' => (int)$this->mostrarAusencias === 1,
		];
	}
}
