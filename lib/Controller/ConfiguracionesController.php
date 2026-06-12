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
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\Files\IAppData;
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
    private IAppData $appData;

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
        IAppData $appData,
    ) {
        parent::__construct(Application::APP_ID, $request);

        $this->userSession = $userSession;
        $this->userManager = $userManager;
        $this->configuracionesMapper = $configuracionesMapper;
        $this->rootFolder = $rootFolder;
        $this->config = $config;
        $this->groupManager = $groupManager;
        $this->appData = $appData;
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
        $users = $this->userManager->search('');
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

        $configMap = array_column($configuraciones, 'Data', 'Nombre');

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
            ],

            'modulo_inventario' => $configMap['modulo_inventario'] ?? 'false',
            'modulo_soporte' => $configMap['modulo_soporte'] ?? 'false',
            'modulo_compras' => $configMap['modulo_compras'] ?? 'false',
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

    #[NoCSRFRequired]
    #[AdminRequired]
    public function ActualizarConfiguracion($id_configuracion, $data): DataResponse {
        $this->configuracionesMapper->ActualizarConfiguracion($id_configuracion, $data);

        return new DataResponse([
            'status' => 'ok',
            'id_configuracion' => $id_configuracion,
            'data' => $data,
        ]);
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

        $userGroupIds = $this->groupManager->getUserGroupIds($user);

        return in_array($groupId, $userGroupIds, true);
    }
    #[NoCSRFRequired]
    #[AdminRequired]
    public function uploadCompraDocumentoLogo(): DataResponse {
        try {
            $file = $this->request->getUploadedFile('logo');

            if (!is_array($file) || empty($file['tmp_name'])) {
                throw new \Exception('No se recibió ningún archivo.');
            }

            if ((int)($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                throw new \Exception('Error al subir el archivo.');
            }

            $tmpName = (string)$file['tmp_name'];
            $size = (int)($file['size'] ?? 0);

            if ($size <= 0) {
                throw new \Exception('El archivo está vacío.');
            }

            if ($size > 2 * 1024 * 1024) {
                throw new \Exception('El logo no debe pesar más de 2 MB.');
            }

            $content = file_get_contents($tmpName);

            if ($content === false || $content === '') {
                throw new \Exception('No se pudo leer el archivo.');
            }

            $mime = $this->detectCompraLogoMime($content);

            if (!in_array($mime, ['image/png', 'image/jpeg'], true)) {
                throw new \Exception('Solo se permiten logos PNG o JPG.');
            }

            $folder = $this->getOrCreateCompraLogoFolder();

            $this->deleteCompraLogoFiles($folder);

            $fileName = $mime === 'image/png'
                ? 'logo-documento.png'
                : 'logo-documento.jpg';

            if ($folder->fileExists($fileName)) {
                $folder->getFile($fileName)->putContent($content);
            } else {
                $folder->newFile($fileName, $content);
            }

            return new DataResponse([
                'success' => true,
                'message' => 'Logo guardado correctamente.',
                'data' => [
                    'file_name' => $fileName,
                    'mime' => $mime,
                ],
            ]);
        } catch (\Throwable $e) {
            return new DataResponse([
                'success' => false,
                'message' => 'No se pudo guardar el logo: ' . $e->getMessage(),
            ], Http::STATUS_BAD_REQUEST);
        }
    }

    #[NoCSRFRequired]
    #[AdminRequired]
    public function getCompraDocumentoLogo(): DataDisplayResponse {
        try {
            $logo = $this->getCompraLogoContent();

            if ($logo === null) {
                return new DataDisplayResponse(
                    'No hay logo configurado.',
                    Http::STATUS_NOT_FOUND,
                    ['Content-Type' => 'text/plain; charset=utf-8']
                );
            }

            return new DataDisplayResponse(
                $logo['content'],
                Http::STATUS_OK,
                [
                    'Content-Type' => $logo['mime'],
                    'Cache-Control' => 'no-store, no-cache, must-revalidate',
                    'Pragma' => 'no-cache',
                ]
            );
        } catch (\Throwable $e) {
            return new DataDisplayResponse(
                'No se pudo abrir el logo: ' . $e->getMessage(),
                Http::STATUS_BAD_REQUEST,
                ['Content-Type' => 'text/plain; charset=utf-8']
            );
        }
    }

    #[NoCSRFRequired]
    #[AdminRequired]
    public function deleteCompraDocumentoLogo(): DataResponse {
        try {
            $folder = $this->getOrCreateCompraLogoFolder();
            $this->deleteCompraLogoFiles($folder);

            return new DataResponse([
                'success' => true,
                'message' => 'Logo eliminado correctamente.',
            ]);
        } catch (\Throwable $e) {
            return new DataResponse([
                'success' => false,
                'message' => 'No se pudo eliminar el logo: ' . $e->getMessage(),
            ], Http::STATUS_BAD_REQUEST);
        }
    }

    private function getOrCreateCompraLogoFolder() {
        try {
            return $this->appData->getFolder('compras');
        } catch (NotFoundException $e) {
            return $this->appData->newFolder('compras');
        }
    }

    private function getCompraLogoContent(): ?array {
        try {
            $folder = $this->appData->getFolder('compras');
        } catch (NotFoundException $e) {
            return null;
        }

        foreach (['logo-documento.png', 'logo-documento.jpg'] as $fileName) {
            if (!$folder->fileExists($fileName)) {
                continue;
            }

            $file = $folder->getFile($fileName);
            $content = $file->getContent();

            if ($content === '') {
                continue;
            }

            $mime = $this->detectCompraLogoMime($content);

            if ($mime === '') {
                continue;
            }

            return [
                'content' => $content,
                'mime' => $mime,
            ];
        }

        return null;
    }

    private function deleteCompraLogoFiles($folder): void {
        foreach (['logo-documento.png', 'logo-documento.jpg'] as $fileName) {
            if ($folder->fileExists($fileName)) {
                $folder->getFile($fileName)->delete();
            }
        }
    }

    private function detectCompraLogoMime(string $content): string {
        if (strncmp($content, "\x89PNG", 4) === 0) {
            return 'image/png';
        }

        if (strncmp($content, "\xFF\xD8\xFF", 3) === 0) {
            return 'image/jpeg';
        }

        if (function_exists('finfo_buffer')) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = (string)$finfo->buffer($content);

            if (in_array($mime, ['image/png', 'image/jpeg'], true)) {
                return $mime;
            }
        }

        return '';
    }
}
