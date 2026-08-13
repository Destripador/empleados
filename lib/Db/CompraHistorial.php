<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

class CompraHistorial extends Entity implements JsonSerializable {

	protected $idHistorial;
	protected $idSolicitud;
	protected $accion;
	protected $estadoAnterior;
	protected $estadoNuevo;
	protected $comentario;
	protected $metadata;
	protected $createdBy;
	protected $createdAt;

	public function __construct() {
		$this->addType('idHistorial', 'integer');
		$this->addType('idSolicitud', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id_historial' => $this->idHistorial,
			'id_solicitud' => $this->idSolicitud,
			'accion' => $this->accion,
			'estado_anterior' => $this->estadoAnterior,
			'estado_nuevo' => $this->estadoNuevo,
			'comentario' => $this->comentario,
			'metadata' => $this->metadata,
			'created_by' => $this->createdBy,
			'created_at' => $this->createdAt,
		];
	}
}
