<?php

declare(strict_types=1);
namespace OCA\Empleados\Controller;

use OCA\Empleados\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\IRequest;
use OCP\IL10N;
use OCP\IUserSession;
use OCP\IUserManager;
use OCA\Empleados\Db\empleadosMapper;
use OCA\Empleados\Db\actividadMapper;
use OCA\Empleados\Db\configuracionesMapper;
use OCA\Empleados\Db\empleados;
use OCA\Empleados\Db\actividad;
use OCA\Empleados\Db\configuraciones;
use OCA\Empleados\UploadException;
use OCP\IGroupManager;
use OCP\IConfig;

use OCP\IURLGenerator;
use OCP\Http\Client\IClientService;
use OCP\Group\ISubAdmin;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;

require_once 'SimpleXLSXGen.php';
require_once 'SimpleXLSX.php';

/**
 * Controlador para la gestión de actividad de empleados en Nextcloud.
 */
class actividadesController extends BaseController {

    /**
     * @var IUserSession
     */
    protected $userSession;

    /**
     * @var IUserManager
     */
    protected $userManager;

    /**
     * @var empleadosMapper
     */
    protected $empleadosMapper;

    /**
     * @var actividadMapper
     */
    protected $actividadMapper;

    /**
     * @var configuracionesMapper
     */
    protected $configuracionesMapper;

    /**
     * @var IL10N
     */
    protected $l10n;

    /**
     * @var IConfig
     */
    protected $config;

    /**
     * @var IGroupManager
     */
    protected $groupManager;

    /**
     * @var IURLGenerator
     */
    protected $urlGenerator;

    /**
     * @var IClientService
     */
    protected $clientService;

    /**
     * @var ISubAdmin
     */
    protected $subAdmin;   

    /**
     * Constructor del controlador.
     *
     * @param IRequest $request
     * @param IUserSession $userSession
     * @param IUserManager $userManager
     * @param empleadosMapper $empleadosMapper
     * @param actividadMapper $actividadMapper
     * @param configuracionesMapper $configuracionesMapper
     * @param IL10N $l10n
     * @param IConfig $config
     * @param IGroupManager $groupManager
     * @param IURLGenerator $urlGenerator
     * @param IClientService $clientService
     * @param ISubAdmin $subAdmin
     */
    public function __construct(
        IRequest $request,
        IUserSession $userSession,
        IUserManager $userManager,
        empleadosMapper $empleadosMapper,
        actividadMapper $actividadMapper,
        configuracionesMapper $configuracionesMapper,
        IL10N $l10n,
        IConfig $config,
		IGroupManager $groupManager,
        IURLGenerator $urlGenerator,
        IClientService $clientService,
        ISubAdmin $subAdmin,
    ) {
		parent::__construct(Application::APP_ID, $request, $userSession, $groupManager, $empleadosMapper, $configuracionesMapper);

        $this->userSession = $userSession;
        $this->userManager = $userManager;
        $this->empleadosMapper = $empleadosMapper;
        $this->actividadMapper = $actividadMapper;
        $this->configuracionesMapper = $configuracionesMapper;
        $this->l10n = $l10n;
        $this->groupManager = $groupManager;
        $this->config = $config;
        $this->urlGenerator = $urlGenerator;
        $this->clientService = $clientService;
        $this->subAdmin = $subAdmin;
    }

    /**
     * Obtiene la lista de actividad.
     *
     * @return DataResponse
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GetActividades(): DataResponse {
        $this->checkAccess(['admin', 'empleados', 'recursos_humanos']);
        return new DataResponse($this->actividadMapper->findAll(), Http::STATUS_OK);
    }

    /**
     * Obtiene la lista de actividad por id.
     *
     * @param int $id
     * @return DataResponse
     */
    #[UseSession]
    #[NoAdminRequired]
    public function findById($id): DataResponse {
        $this->checkAccess(['admin', 'recursos_humanos']);
        return new DataResponse($this->actividadMapper->findById($id), Http::STATUS_OK);
    }

    /**
     * Elimina actividad.
     *
     * @param int $id
     * @return DataResponse
     */
    #[UseSession]
    #[NoAdminRequired]
    public function deleteById($id): DataResponse {
        $this->checkAccess(['admin', 'recursos_humanos']);
        return new DataResponse($this->actividadMapper->deleteById($id), Http::STATUS_OK);
    }

    /**
     * Guarda cambios en los actividad.
     *
     * @param int $id_actividad
     * @param string $nombre
     * @param string $detalles
     * @param float $tiempoestimado
     * @param string $tipo
     * @return DataResponse
     */
    #[UseSession]
    #[NoAdminRequired] // si aplica, cámbiala por #[AdminRequired]
    public function modificarActividad(int $id_actividad, string $nombre, string $detalles, float $tiempoestimado, string $tipo): DataResponse {
        $this->checkAccess(['admin', 'recursos_humanos']);
        $tipo = strtolower(trim($tipo));
        if ($tipo === 'horas') {
            $tiempoestimado *= 60; // <-- usa la MISMA variable
        }
        $this->actividadMapper->updateActividad($id_actividad, $nombre, $detalles, $tiempoestimado);
        return new DataResponse('ok', Http::STATUS_OK);
    }

    /**
     * Crea un nuevo actividad.
     *
     * @param string $nombre
     * @param ?string $detalles
     * @param float $tiempoestimado
     * @param string $tipo
     * @return DataResponse
     */
    #[UseSession]
    #[NoAdminRequired]
    public function crearActividad(string $nombre, ?string $detalles, float $tiempoestimado, string $tipo): DataResponse {
        $this->checkAccess(['admin', 'recursos_humanos']);

        $tipo = strtolower(trim($tipo));
        if ($tipo === 'horas') {
            $tiempoestimado *= 60; // <-- usa la MISMA variable
        }
        
        $actividad = new actividad();
        $actividad->setnombre($nombre);
        $actividad->setdetalles($detalles);
        $actividad->settiempo_estimado($tiempoestimado);
        $this->actividadMapper->insert($actividad);

        return new DataResponse(Http::STATUS_OK);
    }

    /**
     * Exporta la lista de actividad a un archivo XLSX.
     *
     * @return DataResponse
     */
    public function ExportarActividades(): DataResponse {
        $this->checkAccess(['admin', 'recursos_humanos']);
        $actividad = $this->actividadMapper->findAll();
        $books = [['id_actividad', 'nombre', 'detalles', 'tiempo_estimado', 'tiempo_real']];

        foreach ($actividad as $actividad) {
            $books[] = [
                $actividad['id_actividad'],
                $actividad['nombre'],
                $actividad['tiempo_estimado'],
                $actividad['tiempo_real'],
            ];
        }

        \Shuchkin\SimpleXLSXGen::fromArray($books)->downloadAs('actividades.xlsx');
        return new DataResponse($books, Http::STATUS_OK);
    }

    /**
     * Importa la lista de actividad desde un archivo XLSX.
     *
     * @return DataResponse
     */
    public function ImportarActividades(): DataResponse {
        $file = $this->getUploadedFile('ActividadesfileXLSX');
        if ($xlsx = \Shuchkin\SimpleXLSX::parse($file['tmp_name'])) {
            foreach ($xlsx->rows() as $row) {
                if (!empty($row[0])) {
                    $this->actividadMapper->updateActividad((int) $row[0], (string) $row[1], (string) $row[2], (float) $row[3]);
                } else {
                    $actividad = new actividad();
                    $actividad->setnombre($row[1]);
                    $actividad->setdetalles($row[2]);
                    $actividad->settiempo_estimado($row[3]);
                    $this->actividadMapper->insert($actividad);
                }
            }
        return new DataResponse(['status' => 'error'], Http::STATUS_BAD_REQUEST);
        }
        return new DataResponse(Http::STATUS_OK);
    }

    /**
     * Obtiene un archivo subido y maneja posibles errores.
     *
     * @param string $key
     * @return array
     */
    private function getUploadedFile(string $key): array {
        $file = $this->request->getUploadedFile($key);
        if (empty($file) || ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new UploadException($this->l10n->t('Error en la subida del archivo.'));
        }
        return $file;
    }
}
