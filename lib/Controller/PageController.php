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
class PageController extends Controller {

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
	#[NoCSRFRequired]
	public function Puestos(): TemplateResponse {
		return new TemplateResponse(
			Application::APP_ID,
			'puestos',
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
	
	public function ImportListEmpleados(): void {
		$file = $this->getUploadedFile('fileXLSX');
		if ( $xlsx = SimpleXLSX::parse($file['tmp_name']) ) {

			$rows_info = $xlsx->rows();

			foreach($rows_info as $row){
				$this->empleadosMapper->updateEmpleado(	strval($row[0]), strval($row[2]), strval($row[3]), strval($row[4]), strval($row[5]), strval($row[6]), strval($row[7]), strval($row[8]), strval($row[9]), strval($row[10]), strval($row[11]), strval($row[12]), strval($row[13]), strval($row[14]), 
				strval($row[15]), strval($row[16]) , strval($row[17]) , strval($row[18]) , strval($row[19]) , strval($row[20]) , strval($row[21]) , strval($row[22]) , strval($row[23]));
			}
		}
	}

	public function GuardarNota(int $id_empleados, string $nota): void {
		$this->empleadosMapper->GuardarNota(strval($id_empleados), $nota);
	}

	private function getUploadedFile(string $key): array {
		$file = $this->request->getUploadedFile($key);
		$error = null;
		$phpFileUploadErrors = [
			UPLOAD_ERR_OK => $this->l10n->t('The file was uploaded'),
			UPLOAD_ERR_INI_SIZE => $this->l10n->t('The uploaded file exceeds the upload_max_filesize directive in php.ini'),
			UPLOAD_ERR_FORM_SIZE => $this->l10n->t('The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form'),
			UPLOAD_ERR_PARTIAL => $this->l10n->t('The file was only partially uploaded'),
			UPLOAD_ERR_NO_FILE => $this->l10n->t('No file was uploaded'),
			UPLOAD_ERR_NO_TMP_DIR => $this->l10n->t('Missing a temporary folder'),
			UPLOAD_ERR_CANT_WRITE => $this->l10n->t('Could not write file to disk'),
			UPLOAD_ERR_EXTENSION => $this->l10n->t('A PHP extension stopped the file upload'),
		];

		if (empty($file)) {
			throw new UploadException($this->l10n->t('No file uploaded or file size exceeds maximum of %s', [Util::humanFileSize(Util::uploadLimit())]));
		}
		if (array_key_exists('error', $file) && $file['error'] !== UPLOAD_ERR_OK) {
			throw new UploadException($phpFileUploadErrors[$file['error']]);
		}
		return $file;
	}

	public function getAdminUser() {
        $adminGroup = 'admin';  // Nombre del grupo de administradores
        $adminUsers = $this->userManager->search('admin', $adminGroup, 10, 0);

        // Devuelve el primer administrador encontrado
        if (!empty($adminUsers)) {
            return $adminUsers;
        }

        return null;
    }
}
