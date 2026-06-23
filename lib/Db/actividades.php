<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\Entity;

class actividades extends Entity {

	protected ?int $id_actividad= null;
	protected string $nombre = '';
	protected ?string $detalles = null;
	protected ?string $tiempo_estimado = null; // horas decimales
	protected ?string $tiempo_real = null; // horas decimales
	protected ?bool $cargable = false;

	public function __construct() {
		$this->addType('id_actividad', 'integer');
		$this->addType('nombre', 'string');
		$this->addType('detalles', 'string');
		$this->addType('tiempo_estimado', 'float');
		$this->addType('tiempo_real', 'float');
		$this->addType('cargable', 'bool');
	}

	public function read(): array {
		return [
			'Id_actividad' => $this->id_actividad,
			'Nombre' => $this->nombre,
			'Detalles' => $this->detalles,
			'Tiempo_estimado'=> $this->tiempo_estimado,
			'Tiempo_real' => $this->tiempo_real,
			'Cargable' => $this->cargable,
		];
	}
}
