<?php

declare(strict_types=1);

namespace OCA\Empleados\Controller;

use OCA\Empleados\AppInfo\Application;
use OCA\Empleados\Db\boardingMapper;
use OCA\Empleados\Db\empleadosMapper;
use OCA\Empleados\Db\configuracionesMapper;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;

use OCP\IRequest;
use OCP\IUserSession;
use OCP\IGroupManager;

class BoardingController extends BaseController {

	protected boardingMapper $boardingMapper;

	public function __construct(
		IRequest $request,
		IUserSession $userSession,
		IGroupManager $groupManager,
		empleadosMapper $empleadosMapper,
		configuracionesMapper $configuracionesMapper,
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

		$this->boardingMapper = $boardingMapper;
	}

	#[UseSession]
	#[NoAdminRequired]
	public function getBoarding(): DataResponse {
		$this->checkAccess(['admin', 'recursos_humanos']);

		return new DataResponse(
			$this->boardingMapper->findAll(),
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function findById(
		int $id_boarding
	): DataResponse {

		$this->checkAccess(['admin', 'recursos_humanos']);

		return new DataResponse(
			$this->boardingMapper->findById($id_boarding),
			Http::STATUS_OK
		);
	}

	/**
	 * on = 1  catálogo de onboarding, on = 0 catálogo de offboarding
	 */
	#[UseSession]
	#[NoAdminRequired]
	public function findByOn(
		int $on
	): DataResponse {

		$this->checkAccess(['admin', 'recursos_humanos']);

		return new DataResponse(
			$this->boardingMapper->findByOn($on),
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function crearBoarding(
		string $nombre,
		int $on
	): DataResponse {

		$this->checkAccess(['admin', 'recursos_humanos']);

		if ($this->boardingMapper->existeNombre($nombre, $on)) {
			return new DataResponse(
				[
					'status' => 'error',
					'message' => 'Ya existe un ítem con ese nombre para este tipo (on/off).'
				],
				Http::STATUS_CONFLICT
			);
		}

		$this->boardingMapper->createBoarding($nombre, $on);

		return new DataResponse(
			['status' => 'ok'],
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function modificarBoarding(
		int $id_boarding,
		string $nombre,
		int $on
	): DataResponse {

		$this->checkAccess(['admin', 'recursos_humanos']);

		$this->boardingMapper->updateBoarding($id_boarding, $nombre, $on);

		return new DataResponse(
			['status' => 'ok'],
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function deleteById(
		int $id_boarding
	): DataResponse {

		$this->checkAccess(['admin', 'recursos_humanos']);

		$this->boardingMapper->deleteById($id_boarding);

		return new DataResponse(
			['status' => 'ok'],
			Http::STATUS_OK
		);
	}
}