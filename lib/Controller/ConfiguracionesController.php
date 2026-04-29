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

use OCP\IConfig;
use OCP\AppFramework\Http\DataResponse;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AdminRequired;

use OCP\IGroupManager;

/**
 * @psalm-suppress UnusedClass
 */
class ConfiguracionesController extends Controller {

	private $userSession;
	private $configuracionesMapper;

	
	protected IRootFolder $rootFolder;

	private $session;
	private IL10N $l10n;
    private IConfig $config;
    
    private IUserManager $userManager;
    private IGroupManager $groupManager;

	public function __construct(
            IRequest $request,
            ISession $session,
            IUserSession $userSession,
            IUserManager $userManager,
            IL10N $l10n,
            IRootFolder $rootFolder,
            configuracionesMapper $configuracionesMapper, 
            IConfig $config,
            IGroupManager $groupManager,
        ) {
		parent::__construct(Application::APP_ID, $request);

		$this->userSession = $userSession;
		$this->userManager = $userManager;
		$this->configuracionesMapper = $configuracionesMapper;
		$this->rootFolder = $rootFolder;
        $this->config = $config;
        $this->groupManager = $groupManager;

	}
    
    /**
     * Obtiene las configuraciones actuales del módulo, incluyendo:
     * - El listado de usuarios disponibles en Nextcloud para selección.
     * - El usuario gestor de datos actual, si existe.
     * - Configuraciones adicionales como guardado de notas, acumulación de vacaciones,
     *   módulo de ahorro y módulo de ausencias.
     *
     * @return array Arreglo asociativo con las siguientes claves:
     *               - 'Gestor_actual': Información del usuario gestor actual o null.
     *               - 'Users': Listado de usuarios disponibles.
     *               - 'Guardado_notas': Configuración de guardado de notas.
     *               - 'Acumular_vacaciones': Configuración de acumulación de vacaciones.
     *               - 'modulo_ahorro': Configuración del módulo de ahorro.
     *               - 'modulo_ausencias': Configuración del módulo de ausencias.
     */
	#[NoCSRFRequired]
	#[NoAdminRequired]    
	public function GetConfigurations(): array {

        /**
         *  este apartado funciona para obtener el listado
         *  usuarios disponibles en nextcloud
         *  
         *  Esto para rellenar el NcSelect y poder seleccionar
         *  algun nuevo gestor de datos
        */
       $groups = $this->groupManager->search('');

        $groupList = [];

        foreach ($groups as $group) {
            $gid = $group->getGID();

            $groupList[] = [
                'id' => $gid,
                'label' => $gid,
            ];
        }

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
         *  NcSelect
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
            'Acumular_vacaciones' => $configuraciones[2]['Data'],
            'modulo_ahorro' => $configuraciones[3]['Data'],
            'modulo_ausencias' => $configuraciones[4]['Data'],
            'modulo_ausencias_readonly' => $configuraciones[5]['Data'],
            'modulo_clientes' => $configuraciones[6]['Data'],
            'modulo_reporte_tiempos' => $configuraciones[7]['Data'],
            'Groups' => $groupList,
            'CanAdminReports' => $this->canAccessAdminReports(),

            'Reportes' => [
                'recordatorios_enabled' => $this->config->getAppValue(Application::APP_ID, 'reportes_recordatorios_enabled', 'true'),
                'recordatorios_grupo' => $this->config->getAppValue(Application::APP_ID, 'reportes_recordatorios_grupo', 'empleados'),
                'recordatorios_hora' => $this->config->getAppValue(Application::APP_ID, 'reportes_recordatorios_hora', '17'),
                'recordatorios_zona_horaria' => $this->config->getAppValue(Application::APP_ID, 'reportes_recordatorios_zona_horaria', 'America/Mexico_City'),
                'recordatorios_email' => $this->config->getAppValue(Application::APP_ID, 'reportes_recordatorios_email', 'true'),
                'horas_minimas' => $this->config->getAppValue(Application::APP_ID, 'reportes_horas_minimas', '0'),
                'admin_reports_group' => $this->config->getAppValue(
                    Application::APP_ID,
                    'reportes_admin_reports_group',
                    'recursos_humanos'
                ),
                'admin_reports_group' => $this->config->getAppValue(Application::APP_ID, 'reportes_admin_reports_group', 'recursos_humanos'),
            ],
        );

        return $data;
	}

    #[NoCSRFRequired]
    #[AdminRequired]
    public function ActualizarConfiguracionReportes(): DataResponse {
        $adminReportsGroup = trim((string)$this->request->getParam(
            'admin_reports_group',
            'recursos_humanos'
        ));

        if ($adminReportsGroup === '') {
            $adminReportsGroup = 'recursos_humanos';
        }

        if ($this->groupManager->get($adminReportsGroup) === null) {
            return new DataResponse([
                'status' => 'error',
                'message' => 'El grupo configurado para reportes administrativos no existe.',
            ], Http::STATUS_BAD_REQUEST);
        }

        $recordatoriosEnabled = filter_var(
            $this->request->getParam('recordatorios_enabled', 'true'),
            FILTER_VALIDATE_BOOLEAN
        );

        $recordatoriosEmail = filter_var(
            $this->request->getParam('recordatorios_email', 'true'),
            FILTER_VALIDATE_BOOLEAN
        );

        $grupo = trim((string)$this->request->getParam('recordatorios_grupo', 'empleados'));

        if ($grupo === '') {
            $grupo = 'empleados';
        }

        $hora = (int)$this->request->getParam('recordatorios_hora', 17);
        $hora = max(0, min(23, $hora));

        $zonaHoraria = trim((string)$this->request->getParam('recordatorios_zona_horaria', 'America/Mexico_City'));

        try {
            new \DateTimeZone($zonaHoraria);
        } catch (\Throwable $e) {
            return new DataResponse([
                'status' => 'error',
                'message' => 'Zona horaria inválida',
            ], Http::STATUS_BAD_REQUEST);
        }

        $horasMinimas = (float)$this->request->getParam('horas_minimas', 0);

        if ($horasMinimas < 0) {
            $horasMinimas = 0;
        }

        $this->config->setAppValue(Application::APP_ID, 'reportes_recordatorios_enabled', $recordatoriosEnabled ? 'true' : 'false');
        $this->config->setAppValue(Application::APP_ID, 'reportes_recordatorios_grupo', $grupo);
        $this->config->setAppValue(Application::APP_ID, 'reportes_recordatorios_hora', (string)$hora);
        $this->config->setAppValue(Application::APP_ID, 'reportes_recordatorios_zona_horaria', $zonaHoraria);
        $this->config->setAppValue(Application::APP_ID, 'reportes_recordatorios_email', $recordatoriosEmail ? 'true' : 'false');
        $this->config->setAppValue(Application::APP_ID, 'reportes_horas_minimas', (string)$horasMinimas);
        $this->config->setAppValue(
            Application::APP_ID,
            'reportes_admin_reports_group',
            $adminReportsGroup
        );

        return new DataResponse([
            'status' => 'ok',
            'data' => [
                'recordatorios_enabled' => $recordatoriosEnabled,
                'recordatorios_grupo' => $grupo,
                'recordatorios_hora' => $hora,
                'recordatorios_zona_horaria' => $zonaHoraria,
                'recordatorios_email' => $recordatoriosEmail,
                'horas_minimas' => $horasMinimas,
                'admin_reports_group' => $adminReportsGroup,
            ],
        ], Http::STATUS_OK);
    }

    #[NoCSRFRequired]
	#[NoAdminRequired]    
	public function GetDataManager(): array {
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
            $gestor = [null];
        }

        return $gestor;
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

    #[NoCSRFRequired]
    #[AdminRequired]
    public function provisioning(): DataResponse {
        $password = $this->request->getParam('secret');
        $user = $this->userSession->getUser();
        $username = $user->getUID();

        if (!$password) {
            return new DataResponse(['status' => 'error', 'message' => 'Faltan parámetros'], Http::STATUS_BAD_REQUEST);
        }

        // Guardar los valores (equivalente a occ config:app:set)
        $this->config->setAppValue('empleados', 'provisioning_admin_user', $username);
        $this->config->setAppValue('empleados', 'provisioning_admin_pass', $password);

        return new DataResponse(['status' => 'ok']);
    }

    private function canAccessAdminReports(): bool {
        $user = $this->userSession->getUser();

        if ($user === null) {
            return false;
        }

        $uid = $user->getUID();

        if ($this->groupManager->isAdmin($uid)) {
            return true;
        }

        $groupId = trim($this->config->getAppValue(
            Application::APP_ID,
            'reportes_admin_reports_group',
            'recursos_humanos'
        ));

        if ($groupId === '') {
            return false;
        }

        $group = $this->groupManager->get($groupId);

        if ($group === null) {
            return false;
        }

        return $group->inGroup($user);
    }
}
