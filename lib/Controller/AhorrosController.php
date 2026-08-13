<?php

declare(strict_types=1);

namespace OCA\Empleados\Controller;

use OCA\Empleados\AppInfo\Application;
use OCA\Empleados\Db\empleadosMapper;
use OCA\Empleados\Db\configuracionesMapper;
use OCA\Empleados\Db\userahorroMapper;
use OCA\Empleados\Db\historialahorroMapper;
use OCA\Empleados\Service\PermisosService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\ISession;
use OCP\IUserSession;
use OCP\IUserManager;
use OCP\IGroupManager;

require_once 'SimpleXLSXGen.php';
require_once 'SimpleXLSX.php';

class ahorrosController extends BaseController {

	protected $empleadosMapper;
	protected $configuracionesMapper;
	protected $userahorroMapper;
	protected $historialahorroMapper;
	protected $session;
	private PermisosService $permisosService;

	public function __construct(
		IRequest $request,
		ISession $session,
		IUserSession $userSession,
		IUserManager $userManager,
		empleadosMapper $empleadosMapper,
		configuracionesMapper $configuracionesMapper,
		userahorroMapper $userahorroMapper,
		historialahorroMapper $historialahorroMapper,
		IGroupManager $groupManager,
		PermisosService $permisosService
	) {
		parent::__construct(Application::APP_ID, $request, $userSession, $groupManager, $empleadosMapper, $configuracionesMapper);

		$this->session = $session;
		$this->configuracionesMapper = $configuracionesMapper;
		$this->empleadosMapper = $empleadosMapper;
		$this->userahorroMapper = $userahorroMapper;
		$this->historialahorroMapper = $historialahorroMapper;
		$this->permisosService = $permisosService;
	}

	private function requireAhorroPersonalAccess(): void {
		if ($this->userSession->getUser() === null) {
			throw new \Exception('Usuario no autenticado.');
		}

		if (!$this->permisosService->isModuleEnabled('ahorro')) {
			throw new \Exception('El módulo de ahorro no está habilitado.');
		}
	}

	private function requireAhorroAdminAccess(): void {
		$this->permisosService->requireCanSeeAny([
			'ahorro.admin',
			'empleados.hr',
			'empleados.admin',
		]);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function GetInfoAhorro(int $Id_user): DataResponse {
		$this->requireAhorroPersonalAccess();

		try {
			$user = $this->userahorroMapper->GetInfoAhorro($Id_user);

			return new DataResponse($user, Http::STATUS_OK);
		} catch (\Exception $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_NOT_FOUND);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	public function EnviarSolicitud(int $id_ahorro, float $cantidad_solicitada, string $nota): DataResponse {
		$this->requireAhorroPersonalAccess();

		try {
			$user = $this->userSession->getUser();
			$employee = $this->empleadosMapper->GetMyEmployeeInfo($user->getUID());

			$this->historialahorroMapper->EnviarSolicitud(
				$id_ahorro,
				$cantidad_solicitada,
				$employee[0]['Fondo_ahorro'],
				$nota
			);

			$this->userahorroMapper->updatePermisionUserId($id_ahorro, '2');

			return new DataResponse('ok', Http::STATUS_OK);
		} catch (\Exception $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_NOT_FOUND);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	public function getHistorial(string $id_user): DataResponse {
		$this->requireAhorroPersonalAccess();

		try {
			$user = $this->historialahorroMapper->getahorrobyid($id_user);

			return new DataResponse($user, Http::STATUS_OK);
		} catch (\Exception $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_NOT_FOUND);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	public function GetHistorialPanel(string $options_fechas_value, string $options_estado_values): DataResponse {
		$this->requireAhorroAdminAccess();

		try {
			$user = $this->historialahorroMapper->GetHistorialPanel($options_fechas_value, $options_estado_values);

			return new DataResponse($user, Http::STATUS_OK);
		} catch (\Exception $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_NOT_FOUND);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	public function AceptarAhorro(int $id_ahorro, int $id): DataResponse {
		$this->requireAhorroAdminAccess();

		try {
			$this->historialahorroMapper->AceptarAhorro($id_ahorro);
			$this->userahorroMapper->updatePermisionUserId($id, '0');

			return new DataResponse('ok', Http::STATUS_OK);
		} catch (\Exception $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_NOT_FOUND);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	public function DenegarAhorro(int $id_ahorro, int $id): DataResponse {
		$this->requireAhorroAdminAccess();

		try {
			$this->historialahorroMapper->DenegarAhorro($id_ahorro);
			$this->userahorroMapper->updatePermisionUserId($id, '1');

			return new DataResponse('ok', Http::STATUS_OK);
		} catch (\Exception $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_NOT_FOUND);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	public function GenerateReport(string $options_fechas_value, string $options_estado_values): DataResponse {
		$this->requireAhorroAdminAccess();

		try {
			$user = $this->historialahorroMapper->GetHistorialPanel($options_fechas_value, $options_estado_values);
			$books = [['NOMBRE', 'FONDO_CLAVE', 'CUENTA_BANCARIA', 'CANTIDAD_SOLICITADA', 'AHORRO_TOTAL', 'ESTADO']];

			foreach ($user as $datas) {
				$estado = ((int)$datas['estado']) === 0 ? 'Pendiente' : 'Aprobado';

				$books[] = [
					$datas['displayname'],
					$datas['Fondo_clave'],
					$datas['Numero_cuenta'],
					'$' . $datas['cantidad_solicitada'],
					'$' . $datas['cantidad_total'],
					$estado,
				];
			}

			$xlsx = \Shuchkin\SimpleXLSXGen::fromArray($books);
			$xlsx->downloadAs('Ahorros_' . date('Y-m-d') . '.xlsx');

			return new DataResponse(['status' => 'ok'], Http::STATUS_OK);
		} catch (\Exception $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_NOT_FOUND);
		}
	}
}
