<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

class MantenimientoCambio extends Entity implements JsonSerializable {
	public const TIPOS_VALIDOS = [
		'created',
		'status_changed',
		'technician_changed',
		'date_changed',
		'started',
		'completed',
		'rescheduled',
		'cancelled',
		'checklist_updated',
		'result_updated',
	];

	protected $idGrupo;
	protected $idMantenimiento;
	protected $tipoCambio;
	protected $valorAnterior;
	protected $valorNuevo;
	protected $comentario;
	protected $usuarioUid;
	protected $usuarioNombre;
	protected $fecha;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('idGrupo', 'integer');
		$this->addType('idMantenimiento', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'id_grupo' => $this->idGrupo,
			'id_mantenimiento' => $this->idMantenimiento,
			'tipo_cambio' => $this->tipoCambio,
			'valor_anterior' => $this->valorAnterior,
			'valor_nuevo' => $this->valorNuevo,
			'comentario' => $this->comentario,
			'usuario_uid' => $this->usuarioUid,
			'usuario_nombre' => $this->usuarioNombre,
			'fecha' => $this->fecha,
		];
	}
}
