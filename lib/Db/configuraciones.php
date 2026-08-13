<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\Entity;


class configuraciones extends Entity {
    
    protected string $idconf = '';
    protected string $nombre = '';
    protected string $data = '';

	public function __construct() {
        $this->addType('Id_conf', 'string');
        $this->addType('Nombre', 'string');
		$this->addType('Data', 'string');
	}

	public function read(): array {
		return [
			'Id_conf' => $this->idconf,
			'Nombre' => $this->nombre,
			'Data' => $this->data,
		];
	}
}