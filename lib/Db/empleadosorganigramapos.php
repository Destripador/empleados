<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\Entity;

class empleadosorganigramapos extends Entity {

    protected int $id_empleado = 0;
    protected float $pos_x = 0.0;
    protected float $pos_y = 0.0;

    public function __construct() {
        $this->addType('id_empleado', 'int');
        $this->addType('pos_x', 'float');
        $this->addType('pos_y', 'float');
    }

    public function read(): array {
        return [
            'id_empleado' => $this->id_empleado,
            'pos_x' => $this->pos_x,
            'pos_y' => $this->pos_y,
        ];
    }
}