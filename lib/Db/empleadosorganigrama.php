<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\Entity;

class empleadosorganigrama extends Entity {

    protected int $id_empleado = 0;
    protected int $id_dependiente = 0;
    protected string $created_at = '';

    public function __construct() {
        $this->addType('id_empleado', 'int');
        $this->addType('id_dependiente', 'int');
        $this->addType('created_at', 'string');
    }

    public function read(): array {
        return [
            'id' => $this->id,
            'id_empleado' => $this->id_empleado,
            'id_dependiente' => $this->id_dependiente,
            'created_at' => $this->created_at,
        ];
    }
}