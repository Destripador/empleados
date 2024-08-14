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

use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\StorageInvalidException;
use OCP\Files\StorageNotAvailableException;

use OCP\IRequest;
use OCP\ISession;
use OCP\Util;
use OCP\AppFramework\Http\Response;
use DateTime;
use DateTimeZone;

use OCP\IL10N;
use OCA\Empleados\UploadException;


#dependencias agregadas
use OCP\IUserSession;
use OCP\IUserManager;

use OCA\Empleados\Db\empleadosMapper;
use OCA\Empleados\Db\empleados;

use OCA\Empleados\Db\departamentosMapper;
use OCA\Empleados\Db\departamentos;

use OCA\Empleados\Db\configuracionesMapper;
use OCA\Empleados\Db\configuraciones;
/*
use OCA\Empleados\Db\puestosMapper;
use OCA\Empleados\Db\puestos;
*/

require_once 'SimpleXLSXGen.php';
use Shuchkin\SimpleXLSXGen;

require_once 'SimpleXLSX.php';
use Shuchkin\SimpleXLSX;

/**
 * @psalm-suppress UnusedClass
 */
class empleadosController extends Controller {

	private $userSession;
	private $userManager;
	private $empleadosMapper;
	private $departamentosMapper;
	private $configuracionesMapper;

	protected IRootFolder $rootFolder;

	private $session;
	private IL10N $l10n;

	public function __construct(IRequest $request, ISession $session, IUserSession $userSession, IUserManager $userManager, empleadosMapper $empleadosMapper, departamentosMapper $departamentosMapper, IL10N $l10n, IRootFolder $rootFolder, configuracionesMapper $configuracionesMapper) {
		parent::__construct(Application::APP_ID, $request);

		$this->userSession = $userSession;
		$this->userManager = $userManager;
		$this->empleadosMapper = $empleadosMapper;
		$this->departamentosMapper = $departamentosMapper;
		$this->configuracionesMapper = $configuracionesMapper;
		
		$this->rootFolder = $rootFolder;

		$this->ISession = $session;
		
		$this->l10n = $l10n;
		
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
	public function GetEmpleadosArea(string $id_area): array{
		$area = $this->empleadosMapper->GetEmpleadosArea($id_area);
		
		$data = array(
			'area' => $area,
        );

		return $data;
	}

	#[UseSession]
	public function GetEmpleadosPuesto(string $id_puesto): array{
		$puesto = $this->empleadosMapper->GetEmpleadosPuesto($id_puesto);
		
		$data = array(
			'puesto' => $puesto,
        );

		return $data;
	}

	#[UseSession]
	public function GetEmpleadosListFix(): array{
		$empleados = $this->empleadosMapper->GetUserLists();
		$data = [];
		foreach($empleados as $empleado){
			if($empleado['displayname'] == null || $empleado['displayname'] == ""){
				$empleado['displayname'] = $empleado['uid'];
			}
			$datas = array(
				'id' => $empleado['uid'],
				'displayName' => $empleado['displayname'],
				'user' => $empleado['uid'],
                'showUserStatus' => false,
			);
			$data[] = $datas;
		}

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

	public function ExportListEmpleados(): array{
		$empleados = $this->empleadosMapper->GetUserLists();
		
		$books = [[
		'Id_empleados', 
		'Id_user', 
		'Numero_empleado', 
		'Ingreso', 
		'Correo_contacto', 
		'Id_departamento', 
		'Id_puesto', 
		'Id_gerente', 
		'Id_socio', 
		'Fondo_clave', 
		'Numero_cuenta', 
		'Equipo_asignado', 
		'Sueldo', 
		'Fecha_nacimiento', 
		'Estado',
		'Direccion',
		'Estado_civil',
		'Telefono_contacto',
		'Curp',
		'Rfc',
		'Imss',
		'Genero',
		'Contacto_emergencia',
		'Numero_emergencia',
		'created_at', 
		'updated_at', 
		]];

		foreach($empleados as $datas){
			array_push(
				$books, 
				[
					$datas['Id_empleados'], 
					$datas['Id_user'], 
					$datas['Numero_empleado'], 
					$datas['Ingreso'], 
					$datas['Correo_contacto'], 
					$datas['Id_departamento'], 
					$datas['Id_puesto'], 
					$datas['Id_gerente'], 
					$datas['Id_socio'], 
					$datas['Fondo_clave'], 
					$datas['Numero_cuenta'], 
					$datas['Equipo_asignado'], 
					$datas['Sueldo'], 
					$datas['Fecha_nacimiento'], 
					$datas['Estado'],
					$datas['Direccion'],
					$datas['Estado_civil'],
					$datas['Telefono_contacto'],
					$datas['Curp'],
					$datas['Rfc'],
					$datas['Imss'],
					$datas['Genero'],
					$datas['Contacto_emergencia'],
					$datas['Numero_emergencia'],
					$datas['created_at'], 
					$datas['updated_at'], 
				]);
		}

		$xlsx = \Shuchkin\SimpleXLSXGen::fromArray( $books );
		//$xlsx->saveAs('books.xlsx'); // or downloadAs('books.xlsx') or $xlsx_content = (string) $xlsx 
	
		$fileContent = $xlsx->downloadAs('php://memory');

		return $books; 
	}
	
	public function CambiosEmpleado($id_empleados, $numeroempleado, $ingreso, $area, $puesto, $socio, $gerente, $fondoclave, $numerocuenta, $equipoasignado, $sueldo): void {
		$this->empleadosMapper->CambiosEmpleado($id_empleados, $numeroempleado, $ingreso, $area, $puesto, $socio, $gerente, $fondoclave, $numerocuenta, $equipoasignado, $sueldo);
	}

}
