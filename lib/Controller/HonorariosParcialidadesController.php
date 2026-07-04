<?php

declare(strict_types=1);

namespace OCA\Empleados\Controller;

use OCA\Empleados\AppInfo\Application;
use OCA\Empleados\Db\empleadosMapper;
use OCA\Empleados\Db\configuracionesMapper;
use OCA\Empleados\Db\honorariosMapper;
use OCA\Empleados\Db\honorariosParcialidadesMapper;
use OCA\Empleados\Service\PermisosService;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;

use OCP\IRequest;
use OCP\IUserSession;
use OCP\IGroupManager;

class HonorariosParcialidadesController extends BaseController {

	protected honorariosMapper $honorariosMapper;
	protected honorariosParcialidadesMapper $honorariosParcialidadesMapper;
	private PermisosService $permisosService;

	public function __construct(
		IRequest $request,
		IUserSession $userSession,
		IGroupManager $groupManager,
		empleadosMapper $empleadosMapper,
		configuracionesMapper $configuracionesMapper,
		honorariosMapper $honorariosMapper,
		honorariosParcialidadesMapper $honorariosParcialidadesMapper,
		PermisosService $permisosService
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
		$this->honorariosParcialidadesMapper = $honorariosParcialidadesMapper;
		$this->permisosService = $permisosService;
	}

	private function requireClientesAccess(): void {
		$this->permisosService->requireCanSee('clientes');
	}

	private function requireClientesAdminAccess(): void {
		$this->permisosService->requireCanSee('clientes.admin');
	}

	#[UseSession]
	#[NoAdminRequired]
	public function findById(int $id_parcialidad): DataResponse {
		$this->requireClientesAccess();

		return new DataResponse(
			$this->honorariosParcialidadesMapper->findById($id_parcialidad),
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function findByHonorario(int $id_honorario): DataResponse {
		$this->requireClientesAccess();

		return new DataResponse(
			$this->honorariosParcialidadesMapper->findByHonorario($id_honorario),
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function marcarPagada(
		int $id_parcialidad,
		string $fecha_pago
	): DataResponse {
		$this->permisosService->requireCanSee('clientes');

		$idHonorarioFinalizado = $this->honorariosParcialidadesMapper
			->marcarPagada(
				$id_parcialidad,
				$fecha_pago
			);

		if ($idHonorarioFinalizado !== null) {
			$this->honorariosMapper
				->desactivarHonorario($idHonorarioFinalizado);
		}

		return new DataResponse(
			['status' => 'ok'],
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function marcarFacturada(int $id_parcialidad): DataResponse {
		$this->requireClientesAdminAccess();

		$this->honorariosParcialidadesMapper
			->marcarFacturada($id_parcialidad);

		return new DataResponse(
			['status' => 'ok'],
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function cancelarPago(int $id_parcialidad): DataResponse {
		$this->requireClientesAdminAccess();

		$idHonorario = $this->honorariosParcialidadesMapper
			->cancelarPago($id_parcialidad);

		if ($idHonorario !== null) {
			$this->honorariosMapper
				->reactivarHonorario($idHonorario);
		}

		return new DataResponse(
			['status' => 'ok'],
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function agregarParcialidadIguala(int $id_honorario): DataResponse {
		$this->requireClientesAdminAccess();

		$this->honorariosParcialidadesMapper
			->agregarParcialidadIguala($id_honorario);

		return new DataResponse(
			['status' => 'ok'],
			Http::STATUS_OK
		);
	}
}