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
use OCA\Empleados\Service\ClienteLogoService;
use OCA\Empleados\Service\ClientesDashboardService;
use OCA\Empleados\Service\PermisosService;
use OCA\Empleados\Service\BitacoraService;
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
    protected ClientesDashboardService $dashboardService;
    protected ClienteLogoService $clienteLogoService;
    private IConfig $config;
    private IClientService $clientService;
    private ISubAdmin $subAdmin;
    private IURLGenerator $urlGenerator;
    private BitacoraService $bitacoraService;

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
        BitacoraService $bitacoraService,
        ClientesDashboardService $dashboardService,
        ClienteLogoService $clienteLogoService,
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
        $this->bitacoraService = $bitacoraService;
    }

    private function requireClientesAccess(): void {
        $this->permisosService->requireCanSee('clientes');
    }

    private function requireClientesAdminAccess(): void {
        $this->permisosService->requireCanSee('clientes.admin');
    }

    /**
     * Registra un movimiento del módulo "clientes" en la bitácora general.
     */
    private function registrarMovimiento(
        ?string $uidActor,
        ?int $idReferencia,
        ?string $nombreAfectado,
        string $tipo,
        string $mensaje
    ): void {
        $this->bitacoraService->registrar('clientes', $uidActor, null, $nombreAfectado, $tipo, $mensaje, $idReferencia);
    }

    /**
     * Devuelve [uidActor, nombreActor] del usuario en sesión, con fallback a "Sistema".
     */
    private function getActorInfo(): array {
        $actor = $this->userSession->getUser();
        $uidActor = $actor ? $actor->getUID() : null;
        $nombreActor = $actor ? $actor->getDisplayName() : 'Sistema';

        return [$uidActor, $nombreActor];
    }

    #[UseSession]
    #[NoAdminRequired]
    public function GetCompaniesGroups(): DataResponse {
        $this->requireClientesAccess();

        $clientes = $this->clientesMapper->findAll();

        return new DataResponse($clientes, Http::STATUS_OK);
    }

    #[UseSession]
    #[NoAdminRequired]
    public function GetClientesEmpleadosLookup(): DataResponse {
        $this->requireClientesAccess();

        return new DataResponse(
            $this->empleadosMapper->getClientesEmployeeLookup(),
            Http::STATUS_OK
        );
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

        $cliente = $this->clientesMapper->findById((int)$id);
        $nombreCliente = $cliente['nombre'] ?? ('Cliente ' . $id);

        $this->honorariosMapper->deleteByCliente((int)$id);
        $this->clienteLogoService->deleteLogo((int)$id);
        $this->clientesMapper->deleteById((int)$id);

        // --- Movimiento (bitácora) ---
        [$uidActor, $nombreActor] = $this->getActorInfo();

        $mensaje = sprintf(
            '%s ha eliminado el cliente "%s".',
            $nombreActor,
            $nombreCliente
        );

        $this->registrarMovimiento($uidActor, (int)$id, $nombreCliente, 'eliminacion', $mensaje);

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
        ?string $rfc = null,
        ?string $ubicacion = null,
        ?int $especial = null,
        ?int $cliente_padre = null,
        ?int $estado = null
    ): DataResponse {
        $this->requireClientesAdminAccess();

        $colaboradoresArr = json_decode($colaboradores ?? '[]', true) ?: [];

        $old = $this->clientesMapper->findById($id);

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
            $rfc ?: null,
            $ubicacion ?: null,
            (bool)($especial ?? 0),
            $cliente_padre,
            (bool)($estado ?? 1)
        );

        // --- Movimiento (bitácora) ---
        if ($old) {
            $cambios = [];

            $comparaciones = [
                'nombre' => ['label' => 'nombre', 'anterior' => $old['nombre'] ?? '', 'nuevo' => $nombre],
                'detalles' => ['label' => 'detalles', 'anterior' => $old['detalles'] ?? '', 'nuevo' => $detalles ?: ''],
                'razon_social' => ['label' => 'razón social', 'anterior' => $old['razon_social'] ?? '', 'nuevo' => $razon_social ?: ''],
                'nombre_contacto' => ['label' => 'contacto', 'anterior' => $old['nombre_contacto'] ?? '', 'nuevo' => $nombre_contacto ?: ''],
                'telefono' => ['label' => 'teléfono', 'anterior' => $old['telefono'] ?? '', 'nuevo' => $telefono ?: ''],
                'correo' => ['label' => 'correo', 'anterior' => $old['correo'] ?? '', 'nuevo' => $correo ?: ''],
                'rfc' => ['label' => 'RFC', 'anterior' => $old['rfc'] ?? '', 'nuevo' => $rfc ?: ''],
                'ubicacion' => ['label' => 'ubicación', 'anterior' => $old['ubicacion'] ?? '', 'nuevo' => $ubicacion ?: ''],
                'estado' => ['label' => 'estado', 'anterior' => ($old['estado'] ?? true) ? 'activo' : 'inactivo', 'nuevo' => ((bool)($estado ?? 1)) ? 'activo' : 'inactivo'],
            ];

            foreach ($comparaciones as $campo) {
                if ((string)$campo['anterior'] !== (string)$campo['nuevo']) {
                    $cambios[] = sprintf(
                        '%s de "%s" a **%s**',
                        $campo['label'],
                        $campo['anterior'] !== '' ? $campo['anterior'] : 'vacío',
                        $campo['nuevo'] !== '' ? $campo['nuevo'] : 'vacío'
                    );
                }
            }

            if (!empty($cambios)) {
                [$uidActor, $nombreActor] = $this->getActorInfo();

                $mensaje = sprintf(
                    '%s ha actualizado el cliente "%s": cambió %s.',
                    $nombreActor,
                    $old['nombre'] ?? $nombre,
                    implode(', ', $cambios)
                );

                $this->registrarMovimiento($uidActor, $id, $nombre, 'edicion', $mensaje);
            }
        }

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
        ?string $rfc = null,
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
            $cliente->setRfc($rfc ?: null);
            $cliente->setUbicacion($ubicacion ?: null);
            $cliente->setEspecial((bool)($especial ?? 0));
            $cliente->setCliente_padre($cliente_padre);
            $cliente->setEstado((bool)($estado ?? 1));

            $cliente = $this->clientesMapper->insert($cliente);

            // --- Movimiento (bitácora) ---
            [$uidActor, $nombreActor] = $this->getActorInfo();

            $mensaje = sprintf(
                '%s ha creado el cliente "%s".',
                $nombreActor,
                $nombre
            );

            $this->registrarMovimiento($uidActor, (int)$cliente->getId(), $nombre, 'creacion', $mensaje);

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

        $header = function (string $label): string {
            return '<style bgcolor="#DDEBF7"><b>' . $label . '</b></style>';
        };

        $books[] = [
            $header($this->l10n->t('Company')),
            $header($this->l10n->t('Details')),
            $header($this->l10n->t('Legal Business Name')),
            $header($this->l10n->t('Total fees')),
            $header($this->l10n->t('Currency')),
            $header($this->l10n->t('Period')),
            $header($this->l10n->t('Project Manager')),
            $header($this->l10n->t('Primary Contact')),
            $header($this->l10n->t('Phone Number')),
            $header($this->l10n->t('Email')),
            $header($this->l10n->t('RFC')),
            $header($this->l10n->t('Location')),
            $header($this->l10n->t('Special Client')),
            $header($this->l10n->t('Status')),
            $header($this->l10n->t('Parent group')),
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
                $cliente['rfc'] ?? '',
                $cliente['ubicacion'] ?? '',
                ($cliente['especial'] ? $this->l10n->t('Yes') : $this->l10n->t('No')),
                ($cliente['estado'] ? $this->l10n->t('Active') : $this->l10n->t('Inactive')),
                $clientesMap[(int)($cliente['cliente_padre'] ?? 0)] ?? '',
            ];
        }

        $xlsx = \Shuchkin\SimpleXLSXGen::fromArray($books);

        $xlsx->setDefaultFont('Calibri');

        $xlsx->downloadAs(
            $this->l10n->t('Customers') . '_' . date('Y-m-d') . '.xlsx'
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
            return new DataResponse(['status' => 'error', 'message' => $this->l10n->t('No data')], Http::STATUS_BAD_REQUEST);
        }

        $rawHeaders = array_map(
            fn($h) => mb_strtolower(trim((string)$h)),
            $rows[0]
        );

        $aliases = [
            'nombre' => ['nombre', 'empresa', 'company', 'nombre_empresa', 'cliente', 'client'],
            'detalles' => ['detalles', 'descripcion', 'descripción', 'informacion', 'información', 'info', 'details'],
            'razon_social' => ['razon_social', 'razón social', 'subnombre', 'razon', 'legal business name', 'business name'],
            'nombre_contacto' => ['nombre_contacto', 'nombre contacto', 'primary contact', 'contacto'],
            'telefono' => ['telefono', 'teléfono', 'phone', 'phone number'],
            'correo' => ['correo', 'email', 'email address'],
            'rfc' => ['rfc'],
            'ubicacion' => ['ubicacion', 'ubicación', 'location'],
            'especial' => ['especial', 'special client', 'cliente especial'],
            'estado' => ['estado', 'status'],
            'grupo' => ['grupo', 'group', 'cliente_padre', 'parent', 'grupo_empresarial', 'parent group'],
            'importe_total' => ['importe_total', 'importe', 'honorario', 'honorarios', 'total', 'monto', 'total fees', 'total honorarios'],
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
                ['status' => 'error', 'message' => $this->l10n->t('The company/name column was not found')],
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
                $padre->setRfc(null);
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
                $errores[] = $this->l10n->t('Row %s: empty name, skipped.', [(string)($lineaNum + 2)]);
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
            $cliente->setRfc($get($row, 'rfc'));
            $cliente->setUbicacion($get($row, 'ubicacion'));

            $especialRaw = $get($row, 'especial');
            $cliente->setEspecial($especialRaw !== null && in_array(mb_strtolower($especialRaw), ['1', 'si', 'sí', 'yes', 'true'], true));

            $estadoRaw = $get($row, 'estado');
            $cliente->setEstado($estadoRaw === null || !in_array(mb_strtolower($estadoRaw), ['0', 'no', 'false', 'inactivo', 'inactive', 'disabled'], true));

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

        // --- Movimiento (bitácora) ---
        if ($creados > 0) {
            [$uidActor, $nombreActor] = $this->getActorInfo();

            $mensaje = sprintf(
                '%s ha importado clientes desde un archivo XLSX: %d creado(s)%s.',
                $nombreActor,
                $creados,
                !empty($errores) ? sprintf(', %d con error(es)', count($errores)) : ''
            );

            $this->registrarMovimiento($uidActor, null, null, 'importacion', $mensaje);
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
            throw new UploadException($this->l10n->t('Error uploading the file.'));
        }

        return $file;
    }

    #[UseSession]
    #[NoAdminRequired]
    public function GetDashboardSummary(
        $id_cliente = null,
        $cliente_padre = null,
        $lider_proyecto = null,
        $estado = null,
        $especial = null,
        $tipo_honorario = null,
        $solo_pendientes = null,
        $fecha_inicio = null,
        $fecha_fin = null
    ): DataResponse {
        $this->requireClientesAccess();

        return new DataResponse(
            $this->dashboardService->getSummary([
                'id_cliente' => $id_cliente,
                'cliente_padre' => $cliente_padre,
                'lider_proyecto' => $lider_proyecto,
                'estado' => $estado,
                'especial' => $especial,
                'tipo_honorario' => $tipo_honorario,
                'solo_pendientes' => $solo_pendientes,
                'fecha_inicio' => $fecha_inicio,
                'fecha_fin' => $fecha_fin,
            ]),
            Http::STATUS_OK
        );
    }

    #[UseSession]
    #[NoAdminRequired]
    public function GetDashboardCliente(
        int $id,
        $tipo_honorario = null,
        $fecha_inicio = null,
        $fecha_fin = null
    ): DataResponse {
        $this->requireClientesAccess();

        $detail = $this->dashboardService->getClienteDetail($id, [
            'tipo_honorario' => $tipo_honorario,
            'fecha_inicio' => $fecha_inicio,
            'fecha_fin' => $fecha_fin,
        ]);

        if ($detail === null) {
            return new DataResponse(
                ['status' => 'error', 'message' => $this->l10n->t('Client not found')],
                Http::STATUS_NOT_FOUND
            );
        }

        return new DataResponse($detail, Http::STATUS_OK);
    }
}