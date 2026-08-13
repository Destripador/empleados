<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\Entity;


class capitalhumano extends Entity {
    
    protected string $idch = '';
    protected string $idempleado = '';
    protected string $createdat = '';
    protected string $updatedat = '';

	public function __construct() {
        $this->addType('Id_ch', 'string');
        $this->addType('Id_empleado', 'string');
		$this->addType('Created_at', 'string');
		$this->addType('Updated_at', 'string');
	}

	public function read(): array {
		return [
			'Id_ch' => $this->idch,
			'Id_empleado' => $this->idempleado,
			'Created_at' => $this->createdat,
			'Updated_at' => $this->updatedat,
		];
	}
}