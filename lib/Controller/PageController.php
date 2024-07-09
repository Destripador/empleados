<?php

declare(strict_types=1);
namespace OCA\Empleados\Controller;

use OCA\Empleados\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\ISession;
use OCP\Util;
use OCP\AppFramework\Http\Response;
use DateTime;
use DateTimeZone;


#dependencias agregadas
use OCP\IUserSession;
use OCP\IUserManager;

use OCA\Empleados\Db\empleadosMapper;
use OCA\Empleados\Db\empleados;

use OCA\Empleados\Db\departamentosMapper;
use OCA\Empleados\Db\departamentos;
/*
use OCA\Empleados\Db\puestosMapper;
use OCA\Empleados\Db\puestos;
*/

/**
 * @psalm-suppress UnusedClass
 */
class PageController extends Controller {

	private $userSession;
	private $userManager;
	private $empleadosMapper;
	private $departamentosMapper;

	private $session;

	public function __construct(IRequest $request, ISession $session, IUserSession $userSession, IUserManager $userManager, empleadosMapper $empleadosMapper, departamentosMapper $departamentosMapper) {
		parent::__construct(Application::APP_ID, $request);

		$this->userSession = $userSession;
		$this->userManager = $userManager;
		$this->empleadosMapper = $empleadosMapper;
		$this->departamentosMapper = $departamentosMapper;

		$this->ISession = $session;
		
	}

	#[UseSession]
	#[NoCSRFRequired]
	public function index(): TemplateResponse {
		return new TemplateResponse(
			Application::APP_ID,
			'index',
		);
	}

	#[UseSession]
	#[NoCSRFRequired]
	public function Areas(): TemplateResponse {
		return new TemplateResponse(
			Application::APP_ID,
			'areas',
		);
	}

	#[UseSession]
	public function GetUserLists(): array{
		$empleados = $this->empleadosMapper->GetUserLists();
		$users = $this->empleadosMapper->getAllUsers();
		
		$data = array(
			'Empleados' => $empleados,
			'Users' => $users,
        );

		return $data;
	}

	#[UseSession]
	public function GetEmpleadosList(): array{
		$empleados = $this->empleadosMapper->GetUserLists();
		
		$data = array(
			'Empleados' => $empleados,
        );

		return $data;
	}

	#[UseSession]
	public function ActivarEmpleado(string $id_user): string {
		try{
		
			$timestamp = date('Y-m-d');

			$user = new empleados();
			$user->setid_user($id_user);
			$user->setcreated_at($timestamp);
			$user->setupdated_at($timestamp);

			$this->empleadosMapper->insert($user);
			
			return "ok"; 
		}
		catch(Exception $e){
			return $e;
		}
	}

	#[UseSession]
	public function EliminarEmpleado(int $id_empleados): string {
		try{

			$this->empleadosMapper->deleteByIdEmpleado($id_empleados);

			
			return "ok"; 
		}
		catch(Exception $e){
			return $e;
		}
	}

	#[UseSession]
	#[NoCSRFRequired]
	public function GetAreasList(): array{
		$Areas = $this->departamentosMapper->GetAreasList();
	
		return $Areas;
	}
}
