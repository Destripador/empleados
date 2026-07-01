<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\Entity;

class honorarios extends Entity {

	/*---------------- Cliente ----------------*/
	protected ?int $id_honorario = null;
	protected int $id_cliente = 0;
	/*-------------- Honorarios ---------------*/
	protected float $importe_total = 0;
	protected string $tipo_moneda = 'MXN';
	/*--------------- Periodo -----------------*/
	protected ?string $fecha_inicio = null;
	protected ?string $fecha_fin = null;
	protected int $numero_parcialidades = 0;
	/*-------------- Servicio ----------------*/
	protected ?string $tipo_servicio = null;
	protected string $tipo_honorario = 'parcial';
	/*--------------- Estado ------------------*/
	protected bool $activo = true;
	protected bool $especial = false;

	public function __construct() {

		$this->addType('id_honorario', 'integer');
		$this->addType('id_cliente', 'integer');

		$this->addType('importe_total', 'float');
		$this->addType('tipo_moneda', 'string');

		$this->addType('fecha_inicio', 'string');
		$this->addType('fecha_fin', 'string');
		$this->addType('numero_parcialidades', 'integer');

		$this->addType('tipo_servicio', 'string');
		$this->addType('tipo_honorario', 'string');

		$this->addType('activo', 'boolean');
		$this->addType('especial', 'boolean');
	}

	public function read(): array {
		return [
			'id_honorario' => $this->id_honorario,
			'id_cliente' => $this->id_cliente,
			
			'importe_total' => $this->importe_total,
			'tipo_moneda' => $this->tipo_moneda,

			'fecha_inicio' => $this->fecha_inicio,
			'fecha_fin' => $this->fecha_fin,
			'numero_parcialidades' => $this->numero_parcialidades,

			'tipo_servicio' => $this->tipo_servicio,
			'tipo_honorario' => $this->tipo_honorario,

			'activo' => $this->activo,
			'especial' => $this->especial,
		];
	}
}