<?php

declare(strict_types=1);

namespace OCA\Empleados\Controller;

use OCA\Empleados\AppInfo\Application;
use OCA\Empleados\Db\honorarios;
use OCA\Empleados\Db\honorariosMapper;
use OCA\Empleados\Db\empleadosMapper;
use OCA\Empleados\Db\configuracionesMapper;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;

use OCP\IRequest;
use OCP\IUserSession;
use OCP\IGroupManager;

class HonorariosController extends BaseController {

	protected honorariosMapper $honorariosMapper;

	public function __construct(
		IRequest $request,
		IUserSession $userSession,
		IGroupManager $groupManager,
		empleadosMapper $empleadosMapper,
		configuracionesMapper $configuracionesMapper,
		honorariosMapper $honorariosMapper
	) {

		parent::__construct(
			Application::APP_ID,
			$request,
			$userSession,
			$groupManager,
			$empleadosMapper,
			$configuracionesMapper
		);

		$this->honorariosMapper = $honorariosMapper;
	}

	#[UseSession]
	#[NoAdminRequired]
	public function getHonorarios(): DataResponse {
		$this->checkAccess(['admin', 'recursos_humanos']);

		return new DataResponse(
			$this->honorariosMapper->findAll(),
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function findByCliente(int $id_cliente): DataResponse {
		$this->checkAccess(['admin', 'recursos_humanos']);

		return new DataResponse(
			$this->honorariosMapper->findByCliente($id_cliente),
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function findById(int $id_honorario): DataResponse {
		$this->checkAccess(['admin', 'recursos_humanos']);

		return new DataResponse(
			$this->honorariosMapper->findById($id_honorario),
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function deleteById(int $id_honorario): DataResponse {
		$this->checkAccess(['admin', 'recursos_humanos']);

		$this->honorariosMapper->deleteById($id_honorario);

		return new DataResponse(
			['status' => 'ok'],
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function crearHonorario(
		int $id_cliente,
		float $importe_total,
		string $tipo_moneda,
		string $fecha_inicio,
		string $fecha_fin,
		?string $tipo_servicio
	): DataResponse {

		$this->checkAccess(['admin', 'recursos_humanos']);

		$honorario = new honorarios();

		$honorario->setId_cliente($id_cliente);

		$honorario->setImporte_total($importe_total);
		$honorario->setTipo_moneda($tipo_moneda);

		$honorario->setFecha_inicio($fecha_inicio);
		$honorario->setFecha_fin($fecha_fin);

		$honorario->setTipo_servicio($tipo_servicio);

		$honorario->setActivo(true);

		$this->honorariosMapper->crearHonorario($honorario);

		return new DataResponse(
			['status' => 'ok'],
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function modificarHonorario(
		int $id_honorario,
		int $id_cliente,
		float $importe_total,
		string $tipo_moneda,
		string $fecha_inicio,
		string $fecha_fin,
		?string $tipo_servicio,
		bool $activo
	): DataResponse {

		$this->checkAccess(['admin', 'recursos_humanos']);

		$this->honorariosMapper->updateHonorario(
			$id_honorario,
			$id_cliente,
			$importe_total,
			$tipo_moneda,
			$fecha_inicio,
			$fecha_fin,
			$tipo_servicio,
			$activo
		);

		return new DataResponse(
			['status' => 'ok'],
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function completarHonorario(): DataResponse {
		$this->checkAccess(['admin', 'recursos_humanos']);

		$idHonorario  = (int)$this->request->getParam('id_honorario');
		$idCliente    = (int)$this->request->getParam('id_cliente');
		$importeTotal = (float)$this->request->getParam('importe_total');
		$tipoMoneda   = (string)$this->request->getParam('tipo_moneda', 'MXN');
		$fechaInicio  = (string)$this->request->getParam('fecha_inicio');
		$fechaFin     = (string)$this->request->getParam('fecha_fin');
		$tipoServicio = $this->request->getParam('tipo_servicio');

		try {
			$this->honorariosMapper->updateHonorario(
				$idHonorario,
				$idCliente,
				$importeTotal,
				$tipoMoneda,
				$fechaInicio,
				$fechaFin,
				$tipoServicio !== null ? (string)$tipoServicio : null,
				true  // <- el activo que faltaba
			);
		} catch (\Exception $e) {
			return new DataResponse(
				['status' => 'error', 'message' => $e->getMessage()],
				Http::STATUS_FORBIDDEN
			);
		}

		return new DataResponse(['status' => 'ok'], Http::STATUS_OK);
	}
}