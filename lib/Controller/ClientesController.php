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
use OCA\Empleados\Db\clientesMapper;
use OCA\Empleados\Db\configuracionesMapper;
use OCA\Empleados\Db\empleados;
use OCA\Empleados\Db\clientes;
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
 * Controlador para la gestión de clientes de empleados en Nextcloud.
 */
class ClientesController extends BaseController {

    protected $userSession;
    protected $userManager;
    protected $empleadosMapper;
    protected $clientesMapper;
    protected $configuracionesMapper;
    protected $l10n;
    protected $groupManager;
    private IConfig $config;
    private IClientService $clientService;
    private ISubAdmin $subAdmin;   

    private IURLGenerator $urlGenerator;

    public function __construct(
        IRequest $request,
        IUserSession $userSession,
        IUserManager $userManager,
        empleadosMapper $empleadosMapper,
        clientesMapper $clientesMapper,
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
        $this->clientesMapper = $clientesMapper;
        $this->configuracionesMapper = $configuracionesMapper;
        $this->l10n = $l10n;
        $this->groupManager = $groupManager;
        $this->config = $config;
        $this->urlGenerator = $urlGenerator;
        $this->clientService = $clientService;
        $this->subAdmin = $subAdmin;
    }

    /**
     * Obtiene la lista de clientes.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GetCompaniesGroups(): DataResponse {
        $this->checkAccess(['admin', 'recursos_humanos', 'empleados']);
        return new DataResponse($this->clientesMapper->findAll(), Http::STATUS_OK);
    }

    /**
     * Obtiene la lista de clientes por id.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function findById($id): DataResponse {
        $this->checkAccess(['admin', 'recursos_humanos']);
        return new DataResponse($this->clientesMapper->findById($id), Http::STATUS_OK);
    }

    /**
     * eliminar clientes.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function deleteById($id): DataResponse {
        $this->checkAccess(['admin', 'recursos_humanos']);
        $this->clientesMapper->deleteById($id);
        return new DataResponse(
            ['status' => 'ok'],
            Http::STATUS_OK
        );
    }

    /**
     * Guarda cambios en los clientes.
     */
    #[UseSession]
    #[NoAdminRequired] // si aplica, cámbiala por #[AdminRequired]
    public function modificarCliente(
        int $id_cliente, 
        string $nombre, 
        ?string $razon_social,
        ?string $tipo_cliente,
        ?string $tipo_servicio,
        ?string $lider_proyecto,
        ?string $nombre_contacto,
        ?string $telefono,
        ?string $correo,
        ?string $status,
        ?string $ubicacion,
        ?string $honorarios,
        ?string $tipo_moneda, 
        ?string $detalles, 
        ?bool $especial,
        ?int $cliente_padre
    ): DataResponse {
        $this->checkAccess(['admin', 'recursos_humanos']);
        $especial = (bool)($especial ?? false);
        error_log('especial=' . var_export($especial, true));
        $this->clientesMapper->updateClientes(
            $id_cliente, 
            $nombre, 
            $razon_social,
            $tipo_cliente,
            $tipo_servicio,
            $lider_proyecto,
            $nombre_contacto,
            $telefono,
            $correo,
            $status,
            $ubicacion,
            $honorarios,
            $tipo_moneda, 
            $detalles, 
            $especial,
            $cliente_padre
        );
        return new DataResponse('ok', Http::STATUS_OK);
    }

    /**
     * Crea un nuevo clientes.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function crearCliente(
        string $nombre, 
        ?string $razon_social,
        ?string $tipo_cliente,
        ?string $tipo_servicio,
        ?string $lider_proyecto,
        ?string $nombre_contacto,
        ?string $telefono,
        ?string $correo,
        ?string $status,
        ?string $ubicacion,
        ?string $honorarios,
        ?string $tipo_moneda, 
        ?string $detalles, 
        ?bool $especial,
        ?int $cliente_padre
    ): DataResponse {
        $this->checkAccess(['admin', 'recursos_humanos']);
        
        $clientes = new clientes();
        $clientes->setnombre($nombre);
        $clientes->setrazon_social($razon_social);
        $clientes->settipo_cliente($tipo_cliente);
        $clientes->settipo_servicio($tipo_servicio);
        $clientes->setlider_proyecto($lider_proyecto);
        $clientes->setnombre_contacto($nombre_contacto);
        $clientes->settelefono($telefono);
        $clientes->setcorreo($correo);
        $clientes->setstatus($status);
        $clientes->setubicacion($ubicacion);
        $clientes->sethonorarios($honorarios);
        $clientes->settipo_moneda($tipo_moneda);
        $clientes->setdetalles($detalles);
        $clientes->setespecial($especial);
        $clientes->setcliente_padre($cliente_padre);
        $this->clientesMapper->insert($clientes);

        return new DataResponse(
            ['status' => 'ok'],
            Http::STATUS_OK
        );
    }


    /**
     * Exporta la lista de clientes a un archivo XLSX.
     */
    public function Exportarclientes(): DataResponse {
        $this->checkAccess(['admin', 'recursos_humanos']);

        $clientes = $this->clientesMapper->findAll();

        $books = [[
            'id_cliente',
            'nombre',
            'razon_social',
            'tipo_cliente',
            'tipo_servicio',
            'lider_proyecto',
            'nombre_contacto',
            'telefono',
            'correo',
            'status',
            'ubicacion',
            'honorarios',
            'tipo_moneda',
            'detalles',
            'especial',
            'cliente_padre'
        ]];

        foreach ($clientes as $cliente) {
            $books[] = [
                $cliente['id_cliente'],
                $cliente['nombre'],
                $cliente['razon_social'],
                $cliente['tipo_cliente'],
                $cliente['tipo_servicio'],
                $cliente['lider_proyecto'],
                $cliente['nombre_contacto'],
                $cliente['telefono'],
                $cliente['correo'],
                $cliente['status'],
                $cliente['ubicacion'],
                $cliente['honorarios'],
                $cliente['tipo_moneda'],
                $cliente['detalles'],
                $cliente['especial'],
                $cliente['cliente_padre'],
            ];
        }

        \Shuchkin\SimpleXLSXGen::fromArray($books)->downloadAs('clientes.xlsx');

        return new DataResponse(
            ['status' => 'ok'],
            Http::STATUS_OK
        );
    }

    /**
     * Importa la lista de clientes desde un archivo XLSX.
     */
    public function importarClientes(): DataResponse {
        $this->checkAccess(['admin', 'recursos_humanos']);

        $file = $this->getUploadedFile('clientesfileXLSX');

        $xlsx = \Shuchkin\SimpleXLSX::parse($file['tmp_name']);

        if (!$xlsx) {
            return new DataResponse(
                ['status' => 'error'],
                Http::STATUS_BAD_REQUEST
            );
        }

        $rows = $xlsx->rows();

        // Saltar encabezado
        foreach (array_slice($rows, 1) as $row) {

            if (!empty($row[0])) {

                $this->clientesMapper->updateClientes(
                    (int)$row[0],
                    (string)($row[1] ?? ''),
                    $row[2] ?? null,
                    $row[3] ?? null,
                    $row[4] ?? null,
                    $row[5] ?? null,
                    $row[6] ?? null,
                    $row[7] ?? null,
                    $row[8] ?? null,
                    $row[9] ?? null,
                    $row[10] ?? null,
                    $row[11] ?? null,
                    $row[12] ?? null,
                    $row[13] ?? null,
                    !empty($row[14]) ? (bool)$row[14] : null,
                    !empty($row[15]) ? (int)$row[15] : null
                );

            } else {

                $cliente = new clientes();

                $cliente->setnombre($row[1] ?? '');
                $cliente->setrazon_social($row[2] ?? null);
                $cliente->settipo_cliente($row[3] ?? null);
                $cliente->settipo_servicio($row[4] ?? null);
                $cliente->setlider_proyecto($row[5] ?? null);
                $cliente->setnombre_contacto($row[6] ?? null);
                $cliente->settelefono($row[7] ?? null);
                $cliente->setcorreo($row[8] ?? null);
                $cliente->setstatus($row[9] ?? null);
                $cliente->setubicacion($row[10] ?? null);
                $cliente->sethonorarios($row[11] ?? null);
                $cliente->settipo_moneda($row[12] ?? null);
                $cliente->setdetalles($row[13] ?? null);
                $cliente->setespecial(
                    !empty($row[14]) ? (bool)$row[14] : null
                );
                $cliente->setcliente_padre(
                    !empty($row[15]) ? (int)$row[15] : null
                );

                $this->clientesMapper->insert($cliente);
            }
        }

        return new DataResponse(
            ['status' => 'ok'],
            Http::STATUS_OK
        );
    }

    /**
     * Obtiene un archivo subido y maneja posibles errores.
     */
    private function getUploadedFile(string $key): array {
        $file = $this->request->getUploadedFile($key);
        if (empty($file) || ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new UploadException($this->l10n->t('Error en la subida del archivo.'));
        }
        return $file;
    }
}
