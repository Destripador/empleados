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

use OCA\Empleados\Db\configuracionesMapper;
use OCA\Empleados\Db\configuraciones;


/**
 * @psalm-suppress UnusedClass
 */
class ConfiguracionesController extends Controller {

	private $userSession;
	private $configuracionesMapper;

	
	protected IRootFolder $rootFolder;

	private $session;
	private IL10N $l10n;

    
    private IUserManager $userManager;


	public function __construct(IRequest $request, ISession $session, IUserSession $userSession, IUserManager $userManager, IL10N $l10n, IRootFolder $rootFolder, configuracionesMapper $configuracionesMapper ) {
		parent::__construct(Application::APP_ID, $request);

		$this->userSession = $userSession;
		$this->userManager = $userManager;
		$this->configuracionesMapper = $configuracionesMapper;
		$this->rootFolder = $rootFolder;

	}

	#[NoCSRFRequired]
	#[NoAdminRequired]    
	public function GetConfigurations(): array {

        /**
         *  este apartado funciona para obtener el listadod
         *  usuarios disponibles en nextcloud
         *  
         *  Esto para rellenar el NcSelect y poder seleccionar
         *  algyn nuevo gesto de datos
        */
        $users = $this->userManager->search('');

        $userList = [];
        foreach ($users as $user) {
            $userList[] = [
                'id' => $user->getUID(),
                'displayName' => $user->getDisplayName(),
                'icon' => $user->getUID(),
                'user' => $user->getUID(),
                'showUserStatus' => false,
            ];
        }

        /**
         *  Esto funciona para obtener el usuario gestor
         *  de datos, en caso de que exista, regresa el
         *  usuario seleccionado para darle el valor al
         *  NcSeleclt
         */
        $configuraciones = $this->configuracionesMapper->GetConfig();
        if($configuraciones[0]['Data']) {
            $gestor_datos = $this->userManager->get($configuraciones[0]['Data']);
            $gestor[] = [
                'id' => $gestor_datos->getUID(),
                'displayName' => $gestor_datos->getDisplayName(),
                'icon' => $gestor_datos->getUID(),
                'user' => $gestor_datos->getUID(),
                'showUserStatus' => false,
            ];
        }
        else{
            $gestor = null;
        }

        
		$data = array(
			'Gestor_actual' => $gestor,
			'Users' => $userList,
            'Guardado_notas' => $configuraciones[1]['Data'],
        );


        return $data;
	}

    #[NoCSRFRequired]
	#[NoAdminRequired]    
	public function ActualizarGestor(string $id_gestor): void{
        $gestor = $this->configuracionesMapper->GetGestor();

        if ($gestor[0]['Data'] == null || $gestor[0]['Data'] == null) {
            $this->configuracionesMapper->ActualizarGestor($id_gestor);
            $userFolder = $this->rootFolder->getUserFolder($id_gestor);
            if (!$userFolder->nodeExists("EMPLEADOS")) {
                $userFolder->newFolder("EMPLEADOS");
            } 
        } else {
            $currentUser = $gestor[0]['Data'];
            $this->configuracionesMapper->ActualizarGestor($id_gestor);
            $userFolder = $this->rootFolder->getUserFolder($currentUser);
            if ($userFolder->nodeExists("EMPLEADOS")) {
                $sourceNode = $userFolder->get("EMPLEADOS");
                if ($sourceNode->getType() === \OCP\Files\FileInfo::TYPE_FOLDER) {
                    $targetUserObject = $this->userManager->get($id_gestor);
                    if ($targetUserObject) {
                        $targetUserFolder = $this->rootFolder->getUserFolder($id_gestor);
                        $sourceNode->move($targetUserFolder->getPath() . '/' . $sourceNode->getName());
                    } 
                }
            }
        }
       
	}

    public function ActualizarConfiguracion($id_configuracion, $data){
        $this->configuracionesMapper->ActualizarConfiguracion($id_configuracion, $data);
    }
}
