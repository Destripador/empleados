<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\Entity;

class honorariosParcialidades extends Entity {
	public const PENDIENTE = 0;
	public const FACTURADA = 1;
	public const PAGADA = 2;

	/*-------------- Relación ---------------*/
	protected ?int $id_parcialidad = null;
	protected int $id_honorario = 0;
	/*------------- Parcialidad ------------*/
	protected int $numero_parcialidad = 0;
	protected ?string $pfecha_inicio = null;
	protected ?string $pfecha_fin = null;
	protected float $importe_parcialidad = 0;
	protected ?float $cambio_moneda = null;
	/*--------------- Facturación / Pago -----------------*/
	protected int $pagado = 0;
	protected ?string $fecha_factura = null;
	protected ?string $fecha_pago = null;
	protected ?int $id_cliente_pagador = null;

	public function __construct() {
		$this->addType('id_parcialidad', 'integer');
		$this->addType('id_honorario', 'integer');

		$this->addType('numero_parcialidad', 'integer');
		$this->addType('pfecha_inicio', 'string');
		$this->addType('pfecha_fin', 'string');
		$this->addType('importe_parcialidad', 'float');
		$this->addType('cambio_moneda', 'float');

		$this->addType('pagado', 'integer');
		$this->addType('fecha_factura', 'string');
		$this->addType('fecha_pago', 'string');
		$this->addType('id_cliente_pagador', 'integer');
	}

	public function read(): array {
		return [
			'id_parcialidad' => $this->id_parcialidad,
			'id_honorario' => $this->id_honorario,

			'numero_parcialidad' => $this->numero_parcialidad,
			'pfecha_inicio' => $this->pfecha_inicio,
			'pfecha_fin' => $this->pfecha_fin,
			'importe_parcialidad' => $this->importe_parcialidad,
			'cambio_moneda' => $this->cambio_moneda,

			'pagado' => $this->pagado,
			'fecha_factura' => $this->fecha_factura,
			'fecha_pago' => $this->fecha_pago,
			'id_cliente_pagador' => $this->id_cliente_pagador,
		];
	}
}