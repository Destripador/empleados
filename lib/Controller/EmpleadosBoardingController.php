<?php

declare(strict_types=1);

namespace OCA\Empleados\Controller;

use OCA\Empleados\AppInfo\Application;
use OCA\Empleados\Db\boardingMapper;
use OCA\Empleados\Db\empleadosBoardingMapper;
use OCA\Empleados\Db\empleadosMapper;
use OCA\Empleados\Db\configuracionesMapper;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;

use OCP\IRequest;
use OCP\IUserSession;
use OCP\IGroupManager;

class EmpleadosBoardingController extends BaseController {

	protected empleadosBoardingMapper $empleadosBoardingMapper;
	protected boardingMapper $boardingMapper;

	public function __construct(
		IRequest $request,
		IUserSession $userSession,
		IGroupManager $groupManager,
		empleadosMapper $empleadosMapper,
		configuracionesMapper $configuracionesMapper,
		empleadosBoardingMapper $empleadosBoardingMapper,
		boardingMapper $boardingMapper
	) {

		parent::__construct(
			Application::APP_ID,
			$request,
			$userSession,
			$groupManager,
			$empleadosMapper,
			$configuracionesMapper
		);

		$this->empleadosBoardingMapper = $empleadosBoardingMapper;
		$this->boardingMapper = $boardingMapper;
	}

	/**
	 * Checklist completo del empleado (histórico, incluye ítems ya borrados del catálogo)
	 */
	#[UseSession]
	#[NoAdminRequired]
	public function getChecklist(
		int $id_empleado
	): DataResponse {

		$this->checkAccess(['admin', 'recursos_humanos']);

		return new DataResponse(
			$this->empleadosBoardingMapper->findByEmpleado($id_empleado),
			Http::STATUS_OK
		);
	}

	/**
	 * Genera el checklist de un empleado a partir del catálogo activo.
	 * $on = 1 para alta (onboarding), $on = 0 para baja (offboarding).
	 */
	#[UseSession]
	#[NoAdminRequired]
	public function generarChecklist(
		int $id_empleado,
		int $on
	): DataResponse {

		$this->checkAccess(['admin', 'recursos_humanos']);

		$catalogo = $this->boardingMapper->findByOn($on);
		$creados = $this->empleadosBoardingMapper->generarParaEmpleado($id_empleado, $catalogo);

		return new DataResponse(
			['status' => 'ok', 'creados' => $creados],
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function marcarStatus(
		int $id_empleado_boarding,
		int $status
	): DataResponse {

		$this->checkAccess(['admin', 'recursos_humanos']);

		$this->empleadosBoardingMapper->marcarStatus($id_empleado_boarding, $status);

		return new DataResponse(
			['status' => 'ok'],
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function deleteById(
		int $id_empleado_boarding
	): DataResponse {

		$this->checkAccess(['admin', 'recursos_humanos']);

		$this->empleadosBoardingMapper->deleteById($id_empleado_boarding);

		return new DataResponse(
			['status' => 'ok'],
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function deleteByEmpleado(
		int $id_empleado
	): DataResponse {

		$this->checkAccess(['admin', 'recursos_humanos']);

		$this->empleadosBoardingMapper->deleteByEmpleado($id_empleado);

		return new DataResponse(
			['status' => 'ok'],
			Http::STATUS_OK
		);
	}
}