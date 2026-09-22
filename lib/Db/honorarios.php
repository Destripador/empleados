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
	protected ?float $cambio_moneda = null;
	protected ?float $cambio_moneda_factura = null;
	/*--------------- Periodo -----------------*/
	protected ?string $fecha_inicio = null;
	protected ?string $fecha_fin = null;
	protected int $numero_parcialidades = 0;
	/*-------------- Servicio ----------------*/
	protected ?string $tipo_servicio = null;
	protected ?string $descripcion = null;
	protected string $tipo_honorario = 'parcial';
	/*--------------- Estado ------------------*/
	protected bool $activo = true;
	protected bool $especial = false;
	protected bool $solicitud_generada = false;

	public function __construct() {

		$this->addType('id_honorario', 'integer');
		$this->addType('id_cliente', 'integer');

		$this->addType('importe_total', 'float');
		$this->addType('tipo_moneda', 'string');
		$this->addType('cambio_moneda', 'float');
		$this->addType('cambio_moneda_factura', 'float');
		$this->addType('fecha_inicio', 'string');
		$this->addType('fecha_fin', 'string');
		$this->addType('numero_parcialidades', 'integer');

		$this->addType('tipo_servicio', 'string');
		$this->addType('descripcion', 'string');
		$this->addType('tipo_honorario', 'string');

		$this->addType('activo', 'boolean');
		$this->addType('especial', 'boolean');
		$this->addType('solicitud_generada', 'boolean');
	}

	public function read(): array {
		return [
			'id_honorario' => $this->id_honorario,
			'id_cliente' => $this->id_cliente,

			'importe_total' => $this->importe_total,
			'tipo_moneda' => $this->tipo_moneda,
			'cambio_moneda' => $this->cambio_moneda,
			'cambio_moneda_factura' => $this->cambio_moneda_factura,
			'fecha_inicio' => $this->fecha_inicio,
			'fecha_fin' => $this->fecha_fin,
			'numero_parcialidades' => $this->numero_parcialidades,

			'tipo_servicio' => $this->tipo_servicio,
			'descripcion' => $this->descripcion,
			'tipo_honorario' => $this->tipo_honorario,

			'activo' => $this->activo,
			'especial' => $this->especial,
			'solicitud_generada' => $this->solicitud_generada,
		];
	}
}