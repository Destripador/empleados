<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

class CompraDetalle extends Entity implements JsonSerializable {

	protected $idDetalle;
	protected $idSolicitud;
	protected $descripcion;
	protected $cantidad;
	protected $unidad;
	protected $precioEstimado;
	protected $subtotal;
	protected $notas;
	protected $createdAt;
	protected $updatedAt;

	public function __construct() {
		$this->addType('idDetalle', 'integer');
		$this->addType('idSolicitud', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id_detalle' => $this->idDetalle,
			'id_solicitud' => $this->idSolicitud,
			'descripcion' => $this->descripcion,
			'cantidad' => $this->cantidad,
			'unidad' => $this->unidad,
			'precio_estimado' => $this->precioEstimado,
			'subtotal' => $this->subtotal,
			'notas' => $this->notas,
			'created_at' => $this->createdAt,
			'updated_at' => $this->updatedAt,
		];
	}
}
