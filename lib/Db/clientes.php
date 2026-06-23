<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\Entity;

class clientes extends Entity {
	/* EMPRESA */
	protected string $nombre = '';
	protected ?string $detalles = null;
	/* EMPLEADOS */
	protected ?int $lider_proyecto = null;
	protected ?string $colaboradores = null;
	/* INFORMACIÓN */
	protected ?string $razon_social = null;
	protected ?string $nombre_contacto = null;
	protected ?string $telefono = null;
	protected ?string $correo = null;
	protected ?string $ubicacion = null;
	/* BANDERAS / GRUPOS */
	protected bool $especial = false;
	protected ?int $cliente_padre = null;
	protected bool $estado = true;

	public function __construct() {
		$this->addType('id', 'integer');

		$this->addType('nombre', 'string');
		$this->addType('detalles', 'string');

		$this->addType('lider_proyecto', 'integer');
		$this->addType('colaboradores', 'string');

		$this->addType('razon_social', 'string');
		$this->addType('nombre_contacto', 'string');
		$this->addType('telefono', 'string');
		$this->addType('correo', 'string');
		$this->addType('ubicacion', 'string');

		$this->addType('especial', 'boolean');
		$this->addType('cliente_padre', 'integer');
		$this->addType('estado', 'boolean');
	}

	public function read(): array {
		return [
			'id' => $this->id,

			'nombre' => $this->nombre,
			'detalles' => $this->detalles,

			'lider_proyecto' => $this->lider_proyecto,
			'colaboradores' => $this->colaboradores,

			'razon_social' => $this->razon_social,
			'nombre_contacto' => $this->nombre_contacto,
			'telefono' => $this->telefono,
			'correo' => $this->correo,
			'ubicacion' => $this->ubicacion,

			'especial' => $this->especial,
			'cliente_padre' => $this->cliente_padre,
			'estado' => $this->estado,
		];
	}
}