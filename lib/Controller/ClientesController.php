<?php

declare(strict_types=1);
namespace OCA\Empleados\Controller;

use OCA\Empleados\AppInfo\Application;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\IRequest;
use OCP\IL10N;
use OCP\IUserSession;
use OCP\IUserManager;
use OCA\Empleados\Db\empleadosMapper;
use OCA\Empleados\Db\clientesMapper;
use OCA\Empleados\Db\honorariosMapper;
use OCA\Empleados\Db\honorarios;
use OCA\Empleados\Db\configuracionesMapper;
use OCA\Empleados\Db\clientes;
use OCA\Empleados\Db\configuraciones;
use OCA\Empleados\UploadException;
use OCA\Empleados\Service\PermisosService;
use OCP\IGroupManager;
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\Http\Client\IClientService;
use OCP\Group\ISubAdmin;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;

require_once 'SimpleXLSXGen.php';
require_once 'SimpleXLSX.php';

class ClientesController extends BaseController {

    protected $userSession;
    protected $userManager;
    protected $empleadosMapper;
    protected $clientesMapper;
    protected $configuracionesMapper;
    protected $honorariosMapper;
    protected $l10n;
    protected $groupManager;
    protected PermisosService $permisosService;
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
        honorariosMapper $honorariosMapper,
        configuracionesMapper $configuracionesMapper,
        IL10N $l10n,
        IConfig $config,
        IGroupManager $groupManager,
        IURLGenerator $urlGenerator,
        IClientService $clientService,
        ISubAdmin $subAdmin,
        PermisosService $permisosService,
    ) {
        parent::__construct(Application::APP_ID, $request, $userSession, $groupManager, $empleadosMapper, $configuracionesMapper);

        $this->userSession = $userSession;
        $this->userManager = $userManager;
        $this->empleadosMapper = $empleadosMapper;
        $this->clientesMapper = $clientesMapper;
        $this->configuracionesMapper = $configuracionesMapper;
        $this->honorariosMapper = $honorariosMapper;
        $this->l10n = $l10n;
        $this->groupManager = $groupManager;
        $this->config = $config;
        $this->urlGenerator = $urlGenerator;
        $this->clientService = $clientService;
        $this->subAdmin = $subAdmin;
        $this->permisosService = $permisosService;
    }

    private function requireClientesAccess(): void {
        $this->permisosService->requireCanSee('clientes');
    }

    private function requireClientesAdminAccess(): void {
        $this->permisosService->requireCanSee('clientes.admin');
    }

    #[UseSession]
    #[NoAdminRequired]
    public function GetCompaniesGroups(): DataResponse {
        $this->permisosService->requireCanSeeAny([
            'empleados',
        ]);

        $clientes = $this->clientesMapper->findAll();

        return new DataResponse($clientes, Http::STATUS_OK);
    }

    #[UseSession]
    #[NoAdminRequired]
    public function GetCompanieGroup($id): DataResponse {
        $this->requireClientesAccess();

        $cliente = $this->clientesMapper->findById((int)$id);

        return new DataResponse($cliente, Http::STATUS_OK);
    }

    #[UseSession]
    #[NoAdminRequired]
    public function deleteById($id): DataResponse {
        $this->requireClientesAdminAccess();

        $this->honorariosMapper->deleteByCliente((int)$id);
        $this->clientesMapper->deleteById((int)$id);

        return new DataResponse(['status' => 'ok'], Http::STATUS_OK);
    }

    #[UseSession]
    #[NoAdminRequired]
    public function modificarCliente(
        int $id,
        string $nombre,
        ?string $detalles = null,
        ?int $lider_proyecto = null,
        ?string $colaboradores = null,
        ?string $razon_social = null,
        ?string $nombre_contacto = null,
        ?string $telefono = null,
        ?string $correo = null,
        ?string $ubicacion = null,
        ?int $especial = null,
        ?int $cliente_padre = null,
        ?int $estado = null
    ): DataResponse {
        $this->requireClientesAdminAccess();

        $colaboradoresArr = json_decode($colaboradores ?? '[]', true) ?: [];

        $this->clientesMapper->updateClientes(
            $id,
            $nombre,
            $detalles ?: null,
            $lider_proyecto,
            $colaboradoresArr,
            $razon_social ?: null,
            $nombre_contacto ?: null,
            $telefono ?: null,
            $correo ?: null,
            $ubicacion ?: null,
            (bool)($especial ?? 0),
            $cliente_padre,
            (bool)($estado ?? 1)
        );

        return new DataResponse(['status' => 'ok'], Http::STATUS_OK);
    }

    #[UseSession]
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function crearCliente(
        string $nombre,
        ?string $detalles = null,
        ?int $lider_proyecto = null,
        ?string $colaboradores = null,
        ?string $razon_social = null,
        ?string $nombre_contacto = null,
        ?string $telefono = null,
        ?string $correo = null,
        ?string $ubicacion = null,
        ?int $especial = null,
        ?int $cliente_padre = null,
        ?int $estado = null
    ): DataResponse {
        $this->requireClientesAdminAccess();

        try {
            $colaboradoresArr = json_decode($colaboradores ?? '[]', true) ?: [];

            $cliente = new clientes();
            $cliente->setNombre($nombre);
            $cliente->setDetalles($detalles ?: null);
            $cliente->setLider_proyecto($lider_proyecto);
            $cliente->setColaboradores(json_encode($colaboradoresArr));
            $cliente->setRazon_social($razon_social ?: null);
            $cliente->setNombre_contacto($nombre_contacto ?: null);
            $cliente->setTelefono($telefono ?: null);
            $cliente->setCorreo($correo ?: null);
            $cliente->setUbicacion($ubicacion ?: null);
            $cliente->setEspecial((bool)($especial ?? 0));
            $cliente->setCliente_padre($cliente_padre);
            $cliente->setEstado((bool)($estado ?? 1));

            $cliente = $this->clientesMapper->insert($cliente);

            return new DataResponse([
                'status' => 'ok',
                'id' => (int)$cliente->getId(),
            ]);

        } catch (\Throwable $e) {
            \OC::$server->getLogger()->error(
                $e->getMessage(),
                [
                    'app' => 'empleados',
                    'exception' => $e,
                ]
            );

            return new DataResponse([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function Exportarclientes(): DataResponse {
        $this->requireClientesAdminAccess();

        $clientes = $this->clientesMapper->findAll();

        $empleados = $this->empleadosMapper->GetProjectManagers();

        $resumenHonorarios = $this->honorariosMapper->getResumenPorCliente();

        $empleadosMap = [];
        foreach ($empleados as $empleado) {
            $empleadosMap[(int)$empleado['Id_empleados']] =
                $empleado['displayname'];
        }

        $clientesMap = [];
        foreach ($clientes as $cliente) {
            $clientesMap[(int)$cliente['id']] =
                $cliente['nombre'];
        }

        $honorariosMap = [];
        foreach ($resumenHonorarios as $row) {
            $honorariosMap[(int)$row['id_cliente']] = $row;
        }

        $books[] = [
            '<style bgcolor="#DDEBF7"><b>Empresa</b></style>',
            '<style bgcolor="#DDEBF7"><b>Detalles</b></style>',
            '<style bgcolor="#DDEBF7"><b>Razón Social</b></style>',
            '<style bgcolor="#DDEBF7"><b>Total Honorarios</b></style>',
            '<style bgcolor="#DDEBF7"><b>Moneda(s)</b></style>',
            '<style bgcolor="#DDEBF7"><b>Periodo</b></style>',
            '<style bgcolor="#DDEBF7"><b>Líder Proyecto</b></style>',
            '<style bgcolor="#DDEBF7"><b>Nombre Contacto</b></style>',
            '<style bgcolor="#DDEBF7"><b>Teléfono</b></style>',
            '<style bgcolor="#DDEBF7"><b>Correo</b></style>',
            '<style bgcolor="#DDEBF7"><b>Ubicación</b></style>',
            '<style bgcolor="#DDEBF7"><b>Cliente Especial</b></style>',
            '<style bgcolor="#DDEBF7"><b>Estado</b></style>',
            '<style bgcolor="#DDEBF7"><b>Cliente Padre</b></style>',
        ];

        foreach ($clientes as $cliente) {

            $resumen = $honorariosMap[$cliente['id']] ?? null;

            $totalHonorarios = '';
            $monedas = '';
            $periodo = '';

            if ($resumen) {

                $totalHonorarios = number_format(
                    (float)$resumen['importe_total'],
                    2
                );

                $monedas = $resumen['monedas'] ?? '';

                $periodo =
                    ($resumen['fecha_inicio'] ?? '') .
                    ' - ' .
                    ($resumen['fecha_fin'] ?? '');
            }

            $books[] = [
                $cliente['nombre'] ?? '',
                $cliente['detalles'] ?? '',
                $cliente['razon_social'] ?? '',
                $totalHonorarios,
                $monedas,
                $periodo,
                $empleadosMap[(int)($cliente['lider_proyecto'] ?? 0)] ?? '',
                $cliente['nombre_contacto'] ?? '',
                $cliente['telefono'] ?? '',
                $cliente['correo'] ?? '',
                $cliente['ubicacion'] ?? '',
                ($cliente['especial'] ? 'Sí' : 'No'),
                ($cliente['estado'] ? 'Activo' : 'Inactivo'),
                $clientesMap[(int)($cliente['cliente_padre'] ?? 0)] ?? '',
            ];
        }

        $xlsx = \Shuchkin\SimpleXLSXGen::fromArray($books);

        $xlsx->setDefaultFont('Calibri');

        $xlsx->downloadAs(
            'Clientes_' . date('Y-m-d') . '.xlsx'
        );

        return new DataResponse(
            ['status' => 'ok'],
            Http::STATUS_OK
        );
    }

    public function importarClientes(): DataResponse {
        $this->requireClientesAdminAccess();

        $file = $this->getUploadedFile('clientesfileXLSX');
        $xlsx = \Shuchkin\SimpleXLSX::parse($file['tmp_name']);

        if (!$xlsx) {
            return new DataResponse(['status' => 'error'], Http::STATUS_BAD_REQUEST);
        }

        $rows = $xlsx->rows();

        if (count($rows) < 2) {
            return new DataResponse(['status' => 'error', 'message' => 'Sin datos'], Http::STATUS_BAD_REQUEST);
        }

        $rawHeaders = array_map(
            fn($h) => mb_strtolower(trim((string)$h)),
            $rows[0]
        );

        $aliases = [
            'nombre' => ['nombre', 'empresa', 'company', 'nombre_empresa', 'cliente'],
            'detalles' => ['detalles', 'descripcion', 'informacion', 'info'],
            'razon_social' => ['razon_social', 'subnombre', 'razon'],
            'nombre_contacto' => ['nombre_contacto'],
            'telefono' => ['telefono'],
            'correo' => ['correo'],
            'ubicacion' => ['ubicacion'],
            'especial' => ['especial'],
            'estado' => ['estado'],
            'grupo' => ['grupo', 'group', 'cliente_padre', 'parent', 'grupo_empresarial'],
            'importe_total' => ['importe_total', 'importe', 'honorario', 'honorarios', 'total', 'monto'],
        ];

        $colIndex = [];
        foreach ($aliases as $campo => $posiblesNombres) {
            foreach ($rawHeaders as $i => $header) {
                if (in_array($header, $posiblesNombres, true)) {
                    $colIndex[$campo] = $i;
                    break;
                }
            }
        }

        if (!isset($colIndex['nombre'])) {
            return new DataResponse(
                ['status' => 'error', 'message' => 'No se encontró columna de nombre/empresa'],
                Http::STATUS_BAD_REQUEST
            );
        }

        $dataRows = array_slice($rows, 1);

        $gruposMap = [];

        if (isset($colIndex['grupo'])) {
            $gruposEnExcel = [];
            foreach ($dataRows as $row) {
                $grupo = trim((string)($row[$colIndex['grupo']] ?? ''));
                if ($grupo !== '') {
                    $gruposEnExcel[$grupo] = true;
                }
            }

            $existentes = $this->clientesMapper->findAll();
            foreach ($existentes as $c) {
                $nombreExistente = trim((string)($c['nombre'] ?? ''));
                if (isset($gruposEnExcel[$nombreExistente])) {
                    $gruposMap[$nombreExistente] = (int)$c['id'];
                    unset($gruposEnExcel[$nombreExistente]);
                }
            }

            foreach (array_keys($gruposEnExcel) as $nombreGrupo) {
                $padre = new clientes();
                $padre->setNombre($nombreGrupo);
                $padre->setDetalles(null);
                $padre->setLider_proyecto(null);
                $padre->setColaboradores('[]');
                $padre->setRazon_social(null);
                $padre->setNombre_contacto(null);
                $padre->setTelefono(null);
                $padre->setCorreo(null);
                $padre->setUbicacion(null);
                $padre->setEspecial(false);
                $padre->setCliente_padre(null);
                $padre->setEstado(true);

                $insertedPadre = $this->clientesMapper->insert($padre);
                $gruposMap[$nombreGrupo] = (int)$insertedPadre->getId();
            }
        }

        $creados = 0;
        $errores = [];

        $get = fn(array $row, string $campo) => isset($colIndex[$campo])
            ? (trim((string)($row[$colIndex[$campo]] ?? '')) ?: null)
            : null;

        foreach ($dataRows as $lineaNum => $row) {
            $nombre = $get($row, 'nombre');
            if (!$nombre) {
                $errores[] = 'Fila ' . ($lineaNum + 2) . ': nombre vacío, se omitió.';
                continue;
            }

            $grupoNombre = $get($row, 'grupo');
            $clientePadreId = ($grupoNombre && isset($gruposMap[$grupoNombre]))
                ? $gruposMap[$grupoNombre]
                : null;

            $cliente = new clientes();
            $cliente->setNombre($nombre);
            $cliente->setDetalles($get($row, 'detalles'));
            $cliente->setLider_proyecto(null);
            $cliente->setColaboradores('[]');
            $cliente->setRazon_social($get($row, 'razon_social'));
            $cliente->setNombre_contacto($get($row, 'nombre_contacto'));
            $cliente->setTelefono($get($row, 'telefono'));
            $cliente->setCorreo($get($row, 'correo'));
            $cliente->setUbicacion($get($row, 'ubicacion'));

            $especialRaw = $get($row, 'especial');
            $cliente->setEspecial($especialRaw !== null && in_array(mb_strtolower($especialRaw), ['1', 'si', 'sí', 'yes', 'true'], true));

            $estadoRaw = $get($row, 'estado');
            $cliente->setEstado($estadoRaw === null || !in_array(mb_strtolower($estadoRaw), ['0', 'no', 'false', 'inactivo', 'disabled'], true));

            $cliente->setCliente_padre($clientePadreId);

            $inserted = $this->clientesMapper->insert($cliente);
            $idCliente = (int)$inserted->getId();

            $importeRaw = $get($row, 'importe_total');
            if ($importeRaw !== null && (float)$importeRaw > 0) {
                $honorario = new honorarios();
                $honorario->setId_cliente($idCliente);
                $honorario->setImporte_total((float)$importeRaw);
                $honorario->setTipo_moneda('MXN');
                $honorario->setFecha_inicio(null);
                $honorario->setFecha_fin(null);
                $honorario->setNumero_parcialidades(0);
                $this->honorariosMapper->insert($honorario);
            }

            $creados++;
        }

        return new DataResponse(
            ['status' => 'ok', 'creados' => $creados, 'errores' => $errores],
            Http::STATUS_OK
        );
    }

    private function getUploadedFile(string $key): array {
        $this->requireClientesAdminAccess();

        $file = $this->request->getUploadedFile($key);

        if (empty($file) || ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new UploadException($this->l10n->t('Error en la subida del archivo.'));
        }

        return $file;
    }
}
