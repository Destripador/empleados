<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\Entity;

class actividades extends Entity {
	public const TIPO_CLIENTE = 'cliente';
	public const TIPO_INTERNO = 'interno';
	public const TIPOS_VALIDOS = [self::TIPO_CLIENTE, self::TIPO_INTERNO];
	public const ALCANCE_GLOBAL = 'global';
	public const ALCANCE_AREAS = 'areas';
	public const ALCANCES_VALIDOS = [self::ALCANCE_GLOBAL, self::ALCANCE_AREAS];

	protected ?int $id_actividad= null;
	protected string $nombre = '';
	protected ?string $detalles = null;
	protected ?string $tiempo_estimado = null; // horas decimales
	protected ?string $tiempo_real = null; // horas decimales
	protected ?bool $cargable = false;
	protected ?string $clave_sistema = null;
	protected string $tipoActividad = self::TIPO_CLIENTE;
	protected string $alcance = self::ALCANCE_GLOBAL;

	public function __construct() {
		$this->addType('id_actividad', 'integer');
		$this->addType('nombre', 'string');
		$this->addType('detalles', 'string');
		$this->addType('tiempo_estimado', 'float');
		$this->addType('tiempo_real', 'float');
		$this->addType('cargable', 'bool');
		$this->addType('clave_sistema', 'string');
		$this->addType('tipoActividad', 'string');
		$this->addType('alcance', 'string');
	}

	public function read(): array {
		return [
			'Id_actividad' => $this->id_actividad,
			'Nombre' => $this->nombre,
			'Detalles' => $this->detalles,
			'Tiempo_estimado'=> $this->tiempo_estimado,
			'Tiempo_real' => $this->tiempo_real,
			'Cargable' => $this->cargable,
			'Clave_sistema' => $this->clave_sistema,
			'Tipo_actividad' => $this->tipoActividad,
			'Alcance' => $this->alcance,
		];
	}
}
