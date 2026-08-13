<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

class MantenimientoChecklist extends Entity implements JsonSerializable {
	public const RESULTADO_OK = 'ok';
	public const RESULTADO_ATTENTION = 'attention';
	public const RESULTADO_NOT_APPLICABLE = 'not_applicable';
	public const RESULTADO_PENDING = 'pending';
	public const RESULTADOS_VALIDOS = [
		self::RESULTADO_OK,
		self::RESULTADO_ATTENTION,
		self::RESULTADO_NOT_APPLICABLE,
		self::RESULTADO_PENDING,
	];

	protected $idMantenimiento;
	protected $clave;
	protected $etiqueta;
	protected $orden;
	protected $resultado;
	protected $observacion;
	protected $actualizadoPor;
	protected $fechaActualizacion;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('idMantenimiento', 'integer');
		$this->addType('orden', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'id_mantenimiento' => $this->idMantenimiento,
			'clave' => $this->clave,
			'etiqueta' => $this->etiqueta,
			'orden' => $this->orden,
			'resultado' => $this->resultado,
			'observacion' => $this->observacion,
			'actualizado_por' => $this->actualizadoPor,
			'fecha_actualizacion' => $this->fechaActualizacion,
		];
	}
}
