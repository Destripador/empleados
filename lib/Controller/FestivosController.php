<?php

declare(strict_types=1);

namespace OCA\Empleados\Controller;

use OCA\Empleados\AppInfo\Application;
use OCA\Empleados\Db\festivos;
use OCA\Empleados\Db\festivosMapper;
use OCA\Empleados\Db\empleadosMapper;
use OCA\Empleados\Db\configuracionesMapper;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;

use OCP\IRequest;
use OCP\IUserSession;
use OCP\IGroupManager;

class FestivosController extends BaseController {

	protected festivosMapper $festivosMapper;

	public function __construct(
		IRequest $request,
		IUserSession $userSession,
		IGroupManager $groupManager,
		empleadosMapper $empleadosMapper,
		configuracionesMapper $configuracionesMapper,
		festivosMapper $festivosMapper
	) {

		parent::__construct(
			Application::APP_ID,
			$request,
			$userSession,
			$groupManager,
			$empleadosMapper,
			$configuracionesMapper
		);

		$this->festivosMapper = $festivosMapper;
	}

	#[UseSession]
	#[NoAdminRequired]
	public function getFestivos(): DataResponse {
		$this->checkAccess(['admin', 'recursos_humanos']);

		return new DataResponse(
			$this->festivosMapper->findAll(),
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function findById(
		int $id_festivo
	): DataResponse {

		$this->checkAccess(['admin', 'recursos_humanos']);

		return new DataResponse(
			$this->festivosMapper->findById($id_festivo),
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function findByFecha(
		string $fecha
	): DataResponse {

		$this->checkAccess(['admin', 'recursos_humanos']);

		return new DataResponse(
			$this->festivosMapper->findByFecha($fecha),
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function findByYear(
		int $year
	): DataResponse {

		$this->checkAccess(['admin', 'recursos_humanos']);

		return new DataResponse(
			$this->festivosMapper->findByYear($year),
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function crearFestivo(
		string $nombre,
		string $fecha
	): DataResponse {

		$this->checkAccess(['admin', 'recursos_humanos']);

		if ($this->festivosMapper->existeFecha($fecha)) {
			return new DataResponse(
				[
					'status' => 'error',
					'message' => 'Ya existe un festivo para esa fecha.'
				],
				Http::STATUS_CONFLICT
			);
		}

		$this->festivosMapper->createFestivo(
			$nombre,
			$fecha
		);

		return new DataResponse(
			['status' => 'ok'],
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function modificarFestivo(
		int $id_festivo,
		string $nombre,
		string $fecha
	): DataResponse {

		$this->checkAccess(['admin', 'recursos_humanos']);

		$this->festivosMapper->updateFestivo(
			$id_festivo,
			$nombre,
			$fecha
		);

		return new DataResponse(
			['status' => 'ok'],
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function deleteById(
		int $id_festivo
	): DataResponse {

		$this->checkAccess(['admin', 'recursos_humanos']);

		$this->festivosMapper->deleteById(
			$id_festivo
		);

		return new DataResponse(
			['status' => 'ok'],
			Http::STATUS_OK
		);
	}
}