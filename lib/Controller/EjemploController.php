<?php

declare(strict_types=1);
namespace OCA\Empleados\Controller;

use OCA\Empleados\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\ForbiddenException;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\AppFramework\Http;
use OCP\ISession;
use OCP\IUserSession;
use OCP\IUserManager;
use OCP\IGroupManager;
use OCP\IL10N;
use OCA\Empleados\Db\empleadosMapper;
use OCA\Empleados\Db\configuracionesMapper;
use OCA\Empleados\Db\empleados;
use OCA\Empleados\Db\configuraciones;

use OCP\IAvatarManager;

use OCP\IDBConnection;

use OCP\Files\IRootFolder;

use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;

require_once 'SimpleXLSXGen.php';
require_once 'SimpleXLSX.php';

/**
 * Controlador principal para la gestión de empleados en Nextcloud.
 */
class EjemploController extends BaseController {

    protected $userSession;
    protected $userManager;
    protected $groupManager;
    protected $empleadosMapper;
    protected $configuracionesMapper;
    protected $session;
    protected $l10n;

    protected IRootFolder $rootFolder;

    public function __construct(
        IRequest $request,
        ISession $session,
        IUserSession $userSession,
        IUserManager $userManager,
        empleadosMapper $empleadosMapper,
        configuracionesMapper $configuracionesMapper,
        IL10N $l10n,
        IGroupManager $groupManager,
        IRootFolder $rootFolder,
        
    ) {
		parent::__construct(Application::APP_ID, $request, $userSession, $groupManager, $empleadosMapper, $configuracionesMapper);

        $this->session = $session;
        $this->userSession = $userSession;
        $this->userManager = $userManager;
        $this->groupManager = $groupManager;
        $this->empleadosMapper = $empleadosMapper;
        $this->configuracionesMapper = $configuracionesMapper;
        $this->l10n = $l10n;

        $this->rootFolder = $rootFolder;
    }

    /**
     * ejemplo
     */
    #[UseSession]
    #[NoAdminRequired]
    public function nuevafuncion(Array $empleados): DataResponse {
        return new DataResponse([
           $empleados,
        ], Http::STATUS_OK);
    }
}
