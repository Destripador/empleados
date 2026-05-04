<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\Entity;

class InventarioModelo extends Entity {

	protected ?int $id_modelo = null;
	protected ?string $marca = null;
	protected ?string $modelo = null;
	protected ?string $procesador = null;
	protected ?string $ram = null;
	protected ?string $disco_duro = null;
	protected ?string $tipo = null;
	protected bool $touch = false;
	protected ?string $created_at = null;
	protected ?string $updated_at = null;

	public function __construct() {
		$this->addType('id_modelo', 'integer');
		$this->addType('marca', 'string');
		$this->addType('modelo', 'string');
		$this->addType('procesador', 'string');
		$this->addType('ram', 'string');
		$this->addType('disco_duro', 'string');
		$this->addType('tipo', 'string');
		$this->addType('touch', 'boolean');
		$this->addType('created_at', 'string');
		$this->addType('updated_at', 'string');
	}

	public function read(): array {
		return [
			'id_modelo' => $this->id_modelo,
			'marca' => $this->marca,
			'modelo' => $this->modelo,
			'procesador' => $this->procesador,
			'ram' => $this->ram,
			'disco_duro' => $this->disco_duro,
			'tipo' => $this->tipo,
			'touch' => $this->touch,
			'created_at' => $this->created_at,
			'updated_at' => $this->updated_at,
		];
	}
}