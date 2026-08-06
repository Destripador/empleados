<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\Entity;

class MovimientoArchivo extends Entity {
	protected $idEmpleado;
	protected $uidActor;
	protected $tipoEvento;
	protected $fileId;
	protected $storageId;
	protected $rutaAnterior;
	protected $rutaActual;
	protected $nombreArchivo;
	protected $mimeType;
	protected $tamanio;
	protected $esCarpeta;
	protected $fechaEvento;
	protected $remoteAddr;
	protected $userAgent;

	public function __construct() {
		$this->addType('idEmpleado', 'integer');
		$this->addType('uidActor', 'string');
		$this->addType('tipoEvento', 'string');
		$this->addType('fileId', 'integer');
		$this->addType('storageId', 'string');
		$this->addType('rutaAnterior', 'string');
		$this->addType('rutaActual', 'string');
		$this->addType('nombreArchivo', 'string');
		$this->addType('mimeType', 'string');
		$this->addType('tamanio', 'integer');
		$this->addType('esCarpeta', 'boolean');
		$this->addType('fechaEvento', 'string');
		$this->addType('remoteAddr', 'string');
		$this->addType('userAgent', 'string');
	}
}
