<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\Entity;


class tipoausencia extends Entity {
    
    protected ?int $id_tipo_ausencia = null;
    protected ?string $nombre = null;
    protected string $descripcion = '';
    protected ?bool $solicitar_archivo = null;
    protected ?bool $solicitar_prima_vacacional = null;
	protected ?bool $cargable = null;
	protected ?int $privado = null;

	public function __construct() {
        $this->addType('id_tipo_ausencia', 'int');
        $this->addType('nombre', 'string');
		$this->addType('descripcion', 'string');
		$this->addType('solicitar_archivo', 'bool');
		$this->addType('solicitar_prima_vacacional', 'bool');
		$this->addType('cargable', 'bool');
		$this->addType('privado', 'int');
	}

	public function read(): array {
		return [
			'id_tipo_ausencia' => $this->id_tipo_ausencia,
			'nombre' => $this->nombre,
			'descripcion' => $this->descripcion,
			'solicitar_archivo' => $this->solicitar_archivo,
			'solicitar_prima_vacacional' => $this->solicitar_prima_vacacional,
			'cargable' => $this->cargable,
			'privado' => $this->privado,
		];
	}
}