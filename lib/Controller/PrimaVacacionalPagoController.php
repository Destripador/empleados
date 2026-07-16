<?php

declare(strict_types=1);

namespace OCA\Empleados\Controller;

use OCA\Empleados\Db\primavacacionalpagoMapper;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

class PrimaVacacionalPagoController extends OCSController {

	private primavacacionalpagoMapper $mapper;

	public function __construct(
		string $appName,
		IRequest $request,
		primavacacionalpagoMapper $mapper
	) {
		parent::__construct($appName, $request);
		$this->mapper = $mapper;
	}

	/**
	 * Lista todos los pagos de prima vacacional de un empleado.
	 */
    #[UseSession]
    #[NoAdminRequired]
	public function index(int $id_empleado): DataResponse {
		$pagos = $this->mapper->getByEmpleado($id_empleado);
		return new DataResponse(['message' => $pagos]);
	}

	/**
	 * Guarda (o actualiza si ya existía) el pago de un aniversario puntual.
	 */
    #[UseSession]
    #[NoAdminRequired]
	public function guardar(int $id_empleado, int $numero_aniversario, string $fecha_pago, float $dias_pagados): DataResponse {
		$this->mapper->guardar($id_empleado, $numero_aniversario, $fecha_pago, $dias_pagados);
		$pago = $this->mapper->getByEmpleadoYAniversario($id_empleado, $numero_aniversario);
		return new DataResponse(['message' => ['success' => true, 'pago' => $pago]]);
	}
}