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

use OCA\Empleados\Db\puestosMapper;
use OCA\Empleados\Db\puestos;


require_once 'SimpleXLSXGen.php';
use Shuchkin\SimpleXLSXGen;

require_once 'SimpleXLSX.php';
use Shuchkin\SimpleXLSX;

/**
 * @psalm-suppress UnusedClass
 */
class PuestosController extends Controller {

	private $userSession;
	private $userManager;
	private $empleadosMapper;
	private $puestosMapper;
	private $configuracionesMapper;

	protected IRootFolder $rootFolder;

	private $session;
	private IL10N $l10n;

	public function __construct(IRequest $request, ISession $session, IUserSession $userSession, IUserManager $userManager, empleadosMapper $empleadosMapper, puestosMapper $puestosMapper, IL10N $l10n, IRootFolder $rootFolder, configuracionesMapper $configuracionesMapper) {
		parent::__construct(Application::APP_ID, $request);

		$this->userSession = $userSession;
		$this->userManager = $userManager;
		$this->empleadosMapper = $empleadosMapper;
		$this->puestosMapper = $puestosMapper;
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
	public function GetPuestosFix(): array{
		$Puestos = $this->puestosMapper->GetPuestosList();
		$data = [];
		foreach($Puestos as $puesto){
			$datas = array(
				'value' => $puesto['Id_puesto'],
				'label' => $puesto['Nombre'],
			);
			$data[] = $datas;
		}

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
	public function GetPuestosList(): array{
		$Puestos = $this->puestosMapper->GetPuestosList();
	
		return $Puestos;
	}

	public function ExportListPuestos(): array{
		$empleados = $this->puestosMapper->GetPuestosList();
		
		$books = [[
		'Id_puesto',
		'Nombre',
		'created_at', 
		'updated_at', 
		]];

		foreach($empleados as $datas){
			array_push(
				$books, 
				[
					$datas['Id_puesto'],
					$datas['Nombre'], 
					$datas['created_at'], 
					$datas['updated_at'],
				]);
		}

		$xlsx = \Shuchkin\SimpleXLSXGen::fromArray( $books );
		//$xlsx->saveAs('books.xlsx'); // or downloadAs('books.xlsx') or $xlsx_content = (string) $xlsx 
	
		$fileContent = $xlsx->downloadAs('php://memory');

		return $books; 
	}
	
	public function ImportListPuestos(): void {
		$file = $this->getUploadedFile('puestofileXLSX');
		if ( $xlsx = SimpleXLSX::parse($file['tmp_name']) ) {

			$rows_info = $xlsx->rows();

			foreach($rows_info as $row){
                if(strval($row[0])){
                    $this->puestosMapper->updatePuestos(strval($row[0]), strval($row[1]));
                }
                else{
                    $timestamp = date('Y-m-d');

                    $puesto = new puestos();
                    $puesto->setnombre(strval($row[1]));
                    $puesto->setcreated_at($timestamp);
                    $puesto->setupdated_at($timestamp);

                    $this->puestosMapper->insert($puesto);
                }
			}
		}
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

	
	#[UseSession]
	public function EliminarPuesto(int $id_puesto): string {
		try{

			$this->puestosMapper->EliminarPuesto(strval($id_puesto));

			
			return "ok"; 
		}
		catch(Exception $e){
			return $e;
		}
	}

	public function GuardarCambioPuestos(int $id_puestos, string $nombre): void {
		$this->puestosMapper->updatePuestos(strval($id_puestos), $nombre);
	}

	
	public function crearPuesto(string $nombre): void {
		$timestamp = date('Y-m-d');

		$puesto = new puestos();
		$puesto->setnombre($nombre);
		$puesto->setcreated_at($timestamp);
		$puesto->setupdated_at($timestamp);

		$this->puestosMapper->insert($puesto);
	}
}
