<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\Entity;

class puestos extends Entity {

    protected string $id_puestos = '';
    protected string $nombre = '';
    protected ?int $nivel = null;
    protected string $created_at = '';
    protected string $updated_at = '';

    public function __construct() {
        $this->addType('Id_puestos', 'string');
        $this->addType('Nombre', 'string');
        $this->addType('Nivel', 'int');
        $this->addType('created_at', 'string');
        $this->addType('updated_at', 'string');
    }

    public function read(): array {
        return [
            'Id_puestos' => $this->id_puestos,
            'Nombre' => $this->nombre,
            'Nivel' => $this->nivel,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}