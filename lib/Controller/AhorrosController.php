<?php

declare(strict_types=1);
namespace OCA\Empleados\Controller;

use OCA\Empleados\AppInfo\Application;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\ISession;
use OCP\IUserSession;
use OCP\IUserManager;
use OCP\IGroupManager;
use OCA\Empleados\Db\empleadosMapper;
use OCA\Empleados\Db\configuracionesMapper;
use OCA\Empleados\Db\userahorroMapper;
use OCA\Empleados\Db\historialahorroMapper;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;

require_once 'SimpleXLSXGen.php';
require_once 'SimpleXLSX.php';

class ahorrosController extends BaseController {

    protected $empleadosMapper;
	protected $configuracionesMapper;
	protected $userahorroMapper;
	protected $historialahorroMapper;
	protected $session;

	public function __construct(
		IRequest $request, 
		ISession $session, 
		IUserSession $userSession, 
		IUserManager $userManager,
        empleadosMapper $empleadosMapper,
		configuracionesMapper $configuracionesMapper, 
		userahorroMapper $userahorroMapper,
		historialahorroMapper $historialahorroMapper,
		IGroupManager $groupManager
	) {
		parent::__construct(Application::APP_ID, $request, $userSession, $groupManager, $empleadosMapper, $configuracionesMapper);

		$this->session = $session;
		$this->configuracionesMapper = $configuracionesMapper;
		$this->empleadosMapper = $empleadosMapper;
		$this->userahorroMapper = $userahorroMapper;
		$this->historialahorroMapper = $historialahorroMapper;
	}

    #[UseSession]
    #[NoAdminRequired]
	public function GetInfoAhorro(int $Id_user): DataResponse {
        $this->checkAccess(['admin', 'empleados']);
		try{
			$user = $this->userahorroMapper->GetInfoAhorro($Id_user);
			
			return new DataResponse($user, Http::STATUS_OK);
		}
		catch(Exception $e){
			return new DataResponse($e, Http::STATUS_NOT_FOUND);
		}
	}

	#[UseSession]
    #[NoAdminRequired]
	public function EnviarSolicitud(int $id_ahorro, float $cantidad_solicitada, string $nota): DataResponse {
        $this->checkAccess(['admin', 'empleados']);
		try{
			# $user = $this->userahorroMapper->GetInfoByIdAhorro($id_ahorro);
			$user = $this->userSession->getUser();
			$employee = $this->empleadosMapper->GetMyEmployeeInfo($user->getUID());

			$this->historialahorroMapper->EnviarSolicitud($id_ahorro, $cantidad_solicitada, $employee[0]['Fondo_ahorro'], $nota);
			$this->userahorroMapper->updatePermisionUserId($id_ahorro, '2');
			
			return new DataResponse("ok", Http::STATUS_OK);
		}
		catch(Exception $e){
			return new DataResponse($e, Http::STATUS_NOT_FOUND);
		}
	}

	#[UseSession]
    #[NoAdminRequired]
	public function getHistorial(string $id_user) : DataResponse {
        $this->checkAccess(['admin', 'empleados']);
		try{
			$user = $this->historialahorroMapper->getahorrobyid($id_user);
			
			return new DataResponse($user, Http::STATUS_OK);
		}
		catch(Exception $e){
			return new DataResponse($e, Http::STATUS_NOT_FOUND);
		}
	}

	#[UseSession]
    #[NoAdminRequired]
	public function GetHistorialPanel(string $options_fechas_value, string $options_estado_values) : DataResponse {
        $this->checkAccess(['admin', 'recursos_humanos']);
		try{
			$user = $this->historialahorroMapper->GetHistorialPanel($options_fechas_value, $options_estado_values);
			
			return new DataResponse($user, Http::STATUS_OK);
		}
		catch(Exception $e){
			return new DataResponse($e, Http::STATUS_NOT_FOUND);
		}

	}

	#[UseSession]
    #[NoAdminRequired]
	public function AceptarAhorro(int $id_ahorro, int $id): DataResponse {
        $this->checkAccess(['admin', 'recursos_humanos']);
		try{
			$this->historialahorroMapper->AceptarAhorro($id_ahorro);	
			$this->userahorroMapper->updatePermisionUserId($id, '0');	
			
			return new DataResponse("ok", Http::STATUS_OK);
		}
		catch(Exception $e){
			return new DataResponse($e, Http::STATUS_NOT_FOUND);
		}
	}

	#[UseSession]
    #[NoAdminRequired]
	public function DenegarAhorro(int $id_ahorro, int $id): DataResponse {
		$this->checkAccess(['admin', 'recursos_humanos']);
		try{
			$this->historialahorroMapper->DenegarAhorro($id_ahorro);		
			$this->userahorroMapper->updatePermisionUserId($id, '1');	
			
			return new DataResponse("ok", Http::STATUS_OK);
		}
		catch(Exception $e){
			return new DataResponse($e, Http::STATUS_NOT_FOUND);
		}
	}

	public function GenerateReport(string $options_fechas_value, string $options_estado_values): DataResponse {
		$this->checkAccess(['admin', 'recursos_humanos']);
		try{
			
			$user = $this->historialahorroMapper->GetHistorialPanel($options_fechas_value, $options_estado_values);
			$books = [['NOMBRE', 'FONDO_CLAVE', 'CUENTA_BANCARIA', 'CANTIDAD_SOLICITADA', 'AHORRO_TOTAL', 'ESTADO']];

			foreach($user as $datas){
				if($datas['estado'] == 0){
					$estado = "Pendiente";
				}
				else{
					$estado = "Aprobado";
				}

				array_push($books, [
					$datas['displayname'],
					$datas['Fondo_clave'],
					$datas['Numero_cuenta'],
					'$' . $datas['cantidad_solicitada'], 
					'$' . $datas['cantidad_total'],
					$estado
				]);
				
			}
		
			$xlsx = \Shuchkin\SimpleXLSXGen::fromArray( $books );
			//$xlsx->saveAs('books.xlsx'); // or downloadAs('books.xlsx') or $xlsx_content = (string) $xlsx 
		
			$fileContent = $xlsx->downloadAs('php://memory');

			return new DataResponse($book, Http::STATUS_OK); 
		}
		catch(Exception $e){
			return new DataResponse($e, Http::STATUS_NOT_FOUND);
		}
	}

}
