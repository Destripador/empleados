<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\Entity;

class clientes extends Entity {

	protected ?int $id_cliente = null;
	protected string $nombre = '';
	protected ?string $razon_social = null;
	protected ?string $tipo_cliente = null;
	protected ?string $tipo_servicio = null;
	protected ?string $lider_proyecto = null;
	protected ?string $nombre_contacto = null;
	protected ?string $telefono = null;
	protected ?string $correo = null;
	protected ?string $status = null;
	protected ?string $ubicacion = null;
	protected ?string $honorarios = null;
	protected ?string $tipo_moneda = null;
	protected ?string $detalles = null;
	protected ?bool $especial = false;
	protected ?int $cliente_padre = null;

	public function __construct() {
		// Tipos para (de)serialización
		$this->addType('id_cliente', 'integer');
		$this->addType('nombre', 'string');
		$this->addType('razon_social', 'string');
		$this->addType('tipo_cliente', 'string');
		$this->addType('tipo_servicio', 'string');
		$this->addType('lider_proyecto', 'string');
		$this->addType('nombre_contacto', 'string');
		$this->addType('telefono', 'string');
		$this->addType('correo', 'string');
		$this->addType('status', 'string');
		$this->addType('ubicacion', 'string');
		$this->addType('honorarios', 'string');
		$this->addType('tipo_moneda', 'string');
		$this->addType('detalles', 'string');
		$this->addType('especial', 'boolean');
		$this->addType('cliente_padre', 'integer');
	}

	public function read(): array {
		return [
			'id_cliente' => $this->id_cliente,
			'nombre' => $this->nombre,
			'razon_social' => $this->razon_social,
			'tipo_cliente' => $this->tipo_cliente,
			'tipo_servicio' => $this->tipo_servicio,
			'lider_proyecto' => $this->lider_proyecto,
			'nombre_contacto' => $this->nombre_contacto,
			'telefono' => $this->telefono,
			'correo' => $this->correo,
			'status' => $this->status,
			'ubicacion' => $this->ubicacion,
			'honorarios' => $this->honorarios,
			'tipo_moneda' => $this->tipo_moneda,
			'detalles' => $this->detalles,
			'especial' => $this->especial,
			'cliente_padre' => $this->cliente_padre,
		];
	}
}
