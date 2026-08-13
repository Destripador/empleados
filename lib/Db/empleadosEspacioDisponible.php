<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\Entity;

class empleadosEspacioDisponible extends Entity {

    // Nextcloud mapeará id_emp_esp_disp a esta variable
    protected $idEmpEspDisp;
    protected $idEspacioEmpleado;
    protected $fecha;
    protected $todoDia;
    protected $horaInicial;
    protected $horaFinal;
    protected $createdAt;
    protected $updatedAt;

    public function __construct() {
        // Los nombres aquí deben coincidir con las variables declaradas arriba
        $this->addType('idEmpEspDisp', 'integer');
        $this->addType('idEspacioEmpleado', 'integer');
        $this->addType('fecha', 'datetime');
        $this->addType('todoDia', 'boolean');
        $this->addType('horaInicial', 'datetime');
        $this->addType('horaFinal', 'datetime');
        $this->addType('createdAt', 'datetime');
        $this->addType('updatedAt', 'datetime');
    }

    public function read(): array {
        return [
            'id_emp_esp_disp' => $this->idEmpEspDisp,
            'id_espacio_empleado'            => $this->idEspacioEmpleado,
            'fecha'                          => $this->fecha,
            'todo_dia'                       => $this->todoDia,
            'hora_inicial'                   => $this->horaInicial,
            'hora_final'                     => $this->horaFinal,
            'created_at'                     => $this->createdAt,
            'updated_at'                     => $this->updatedAt,
        ];
    }
}