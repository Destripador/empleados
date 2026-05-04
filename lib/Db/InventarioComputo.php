<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\Entity;

class InventarioComputo extends Entity {

	protected ?int $id_equipo = null;
	protected ?int $id_empleado = null;
	protected ?int $id_modelo = null;
	protected ?string $nombre_dispositivo = null;
	protected ?string $nombre_sistema = null;
	protected ?string $numero_serie = null;
	protected ?string $estado = null;
	protected ?string $info = null;
	protected ?string $created_at = null;
	protected ?string $updated_at = null;

	public function __construct() {
		$this->addType('id_equipo', 'integer');
		$this->addType('id_empleado', 'integer');
		$this->addType('id_modelo', 'integer');
		$this->addType('nombre_dispositivo', 'string');
		$this->addType('nombre_sistema', 'string');
		$this->addType('numero_serie', 'string');
		$this->addType('estado', 'string');
		$this->addType('info', 'string');
		$this->addType('created_at', 'string');
		$this->addType('updated_at', 'string');
	}

	public function read(): array {
		return [
			'id_equipo' => $this->id_equipo,
			'id_empleado' => $this->id_empleado,
			'id_modelo' => $this->id_modelo,
			'nombre_dispositivo' => $this->nombre_dispositivo,
			'nombre_sistema' => $this->nombre_sistema,
			'numero_serie' => $this->numero_serie,
			'estado' => $this->estado,
			'info' => $this->info,
			'created_at' => $this->created_at,
			'updated_at' => $this->updated_at,
		];
	}
}