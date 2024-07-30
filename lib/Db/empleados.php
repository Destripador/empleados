<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\Entity;


class empleados extends Entity {

	protected string $id_empleados = '';
	protected string $id_user = '';
	protected string $numeroempleado = '';
	protected string $ingreso = '';
	protected string $correocontacto = '';
	protected string $iddepartamento = '';
	protected string $idpuesto = '';
	protected string $idgerente = '';
	protected string $idsocio = '';
	protected string $fondoclave = '';
	protected string $numerocuenta = '';
	protected string $equipoasignado = '';
	protected string $sueldo = '';
	protected string $fechanacimiento = '';
	protected string $estado = '';
	protected string $created_at = '';
	protected string $updated_at = '';
	protected string $direccion = '';
	protected string $estadocivil = '';
	protected string $telefonocontacto = '';
	protected string $curp = '';
	protected string $rfc = '';
	protected string $imss = '';
	protected string $genero = '';
	protected string $contactoemergencia = '';
	protected string $numeroemergencia = '';
	protected string $notas = '';

	public function __construct() {
		$this->addType('Id_empleados', 'integer');
		$this->addType('Id_user', 'string');
		$this->addType('Numero_empleado', 'string');
		$this->addType('Ingreso', 'date');
		$this->addType('Correo_contacto', 'string');
		$this->addType('Id_departamento', 'integer');
		$this->addType('Id_puesto', 'integer');
		$this->addType('Id_gerente', 'integer');
		$this->addType('Id_socio', 'integer');
		$this->addType('Fondo_clave', 'string');
		$this->addType('Numero_cuenta', 'string');
		$this->addType('Equipo_asignado', 'string');
		$this->addType('Sueldo', 'decimal');
		$this->addType('Fecha_nacimiento', 'date');
		$this->addType('Estado', 'string');
		$this->addType('created_at', 'string');
		$this->addType('updated_at', 'string');
		$this->addType('Direccion', 'string');
		$this->addType('Estado_civil', 'string');
		$this->addType('Telefono_contacto', 'string');
		$this->addType('Curp', 'string');
		$this->addType('Rfc', 'string');
		$this->addType('Imss', 'string');
		$this->addType('Genero', 'string');
		$this->addType('Contacto_emergencia', 'string');
		$this->addType('Numero_emergencia', 'string');
		$this->addType('Notas', 'string');
	}

	public function read(): array {
		return [
			'Id_empleados' => $this->id_empleados,
			'Id_user' => $this->id_user,
			'Numero_empleado' => $this->numeroempleado,
			'Ingreso' => $this->ingreso,
			'Correo_contacto' => $this->correocontacto,
			'Id_departamento' => $this->iddepartamento,
			'Id_puesto' => $this->idpuesto,
			'Id_gerente' => $this->idgerente,
			'Id_socio' => $this->idsocio,
			'Fondo_clave' => $this->fondoclave,
			'Numero_cuenta' => $this->numerocuenta,
			'Equipo_asignado' => $this->equipoasignado,
			'Sueldo' => $this->sueldo,
			'Fecha_nacimiento' => $this->fechanacimiento,
			'Estado' => $this->estado,
			'created_at' => $this->created_at,
			'updated_at' => $this->updated_at,
			'Direccion' => $this->direccion,
			'Estado_civil' => $this->estadocivil,
			'Telefono_contacto' => $this->telefonocontacto,
			'Curp' => $this->curp,
			'Rfc' => $this->rfc,
			'Imss' => $this->imss,
			'Genero' => $this->genero,
			'Contacto_emergencia' => $this->contactoemergencia,
			'Numero_emergencia' => $this->numeroemergencia,
			'Notas' => $this->notas,
		];





	}
}