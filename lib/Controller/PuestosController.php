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
use OCA\Empleados\Db\puestosMapper;
use OCA\Empleados\Db\configuracionesMapper;
use OCA\Empleados\Db\empleados;
use OCA\Empleados\Db\puestos;
use OCA\Empleados\Db\configuraciones;
use OCA\Empleados\UploadException;
use OCP\IGroupManager;
use OCA\Empleados\Service\BitacoraService;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;

use OCA\Empleados\Service\PermisosService;
use OCA\Empleados\Service\CompraPermisosService;

require_once 'SimpleXLSXGen.php';
require_once 'SimpleXLSX.php';

/**
 * Controlador para la gestión de puestos de empleados en Nextcloud.
 */
class PuestosController extends BaseController {

    protected $userSession;
    protected $userManager;
    protected $empleadosMapper;
    protected $puestosMapper;
    protected $configuracionesMapper;
    protected $l10n;
    protected PermisosService $permisosService;
    protected CompraPermisosService $compraPermisosService;
    private BitacoraService $bitacoraService;

    public function __construct(
        IRequest $request,
        IUserSession $userSession,
        IUserManager $userManager,
        empleadosMapper $empleadosMapper,
        puestosMapper $puestosMapper,
        configuracionesMapper $configuracionesMapper,
        IL10N $l10n,
		IGroupManager $groupManager,
        PermisosService $permisosService,
        CompraPermisosService $compraPermisosService,
        BitacoraService $bitacoraService
    ) {
		parent::__construct(Application::APP_ID, $request, $userSession, $groupManager, $empleadosMapper, $configuracionesMapper);

        $this->userSession = $userSession;
        $this->userManager = $userManager;
        $this->empleadosMapper = $empleadosMapper;
        $this->puestosMapper = $puestosMapper;
        $this->configuracionesMapper = $configuracionesMapper;
        $this->l10n = $l10n;
        $this->permisosService = $permisosService;
        $this->compraPermisosService = $compraPermisosService;
        $this->bitacoraService = $bitacoraService;
    }

    /**
     * Registra un movimiento del módulo "puestos" en la bitácora general.
     */
    private function registrarMovimiento(
        ?string $uidActor,
        ?int $idReferencia,
        ?string $nombreAfectado,
        string $tipo,
        string $mensaje
    ): void {
        $this->bitacoraService->registrar('puestos', $uidActor, null, $nombreAfectado, $tipo, $mensaje, $idReferencia);
    }

    /**
     * Obtiene la lista de puestos en formato clave-valor.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GetPuestosFix(): DataResponse {
        $this->requireCatalogReadAccess();
        $result = array_map(fn($puesto) => [
            'value' => $puesto['Id_puestos'],
            'label' => $puesto['Nombre'],
        ], $this->puestosMapper->GetPuestosList());

        return new Dataresponse($result, Http::STATUS_OK);
    }

    /**
     * Obtiene la lista de puestos.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GetPuestosList(): DataResponse {
        $this->requireHumanResourcesAccess();
        return new DataResponse($this->puestosMapper->GetPuestosList(), Http::STATUS_OK);
    }

    /**
     * Exporta la lista de puestos a un archivo XLSX.
     */
    public function ExportListPuestos(): DataResponse {
        $this->requireHumanResourcesAccess();
        $puestos = $this->puestosMapper->GetPuestosList();
        $books = [['Id_puesto', 'Nombre', 'Nivel', 'created_at', 'updated_at']];

        foreach ($puestos as $puesto) {
            $books[] = [
                $puesto['Id_puestos'],
                $puesto['Nombre'],
                $puesto['Nivel'],
                $puesto['created_at'],
                $puesto['updated_at'],
            ];
        }

        \Shuchkin\SimpleXLSXGen::fromArray($books)->downloadAs('puestos.xlsx');
        return new DataResponse($books, Http::STATUS_OK);
    }

    /**
     * Importa la lista de puestos desde un archivo XLSX.
     */
    public function ImportListPuestos(): DataResponse {
        $this->requireHumanResourcesAccess();
        $file = $this->getUploadedFile('puestofileXLSX');

        $creados = 0;
        $actualizados = 0;

        if ($xlsx = \Shuchkin\SimpleXLSX::parse($file['tmp_name'])) {
            foreach ($xlsx->rows() as $row) {
                $nivel = isset($row[2]) && $row[2] !== '' ? (int) $row[2] : null;

                if (!empty($row[0])) {
                    $this->puestosMapper->updatePuestos((string) $row[0], (string) $row[1], $nivel);
                    $actualizados++;
                } else {
                    $timestamp = date('Y-m-d');
                    $puesto = new puestos();
                    $puesto->setnombre((string) $row[1]);
                    $puesto->setnivel($nivel);
                    $puesto->setcreated_at($timestamp);
                    $puesto->setupdated_at($timestamp);
                    $this->puestosMapper->insert($puesto);
                    $creados++;
                }
            }

            // --- Movimiento (bitácora) ---
            if ($creados > 0 || $actualizados > 0) {
                $actor = $this->userSession->getUser();
                $uidActor = $actor ? $actor->getUID() : null;
                $nombreActor = $actor ? $actor->getDisplayName() : 'Sistema';

                $mensaje = sprintf(
                    '%s ha importado puestos desde un archivo XLSX: %d creado(s), %d actualizado(s).',
                    $nombreActor, $creados, $actualizados
                );

                $this->registrarMovimiento($uidActor, null, null, 'importacion', $mensaje);
            }

            return new DataResponse(Http::STATUS_INTERNAL_SERVER_ERROR);
        }
        return new DataResponse(Http::STATUS_OK);
    }

    /**
     * Elimina un puesto por ID.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function EliminarPuesto(int $id_puesto): DataResponse {
        $this->requireHumanResourcesAccess();
        try {
            $puesto = $this->puestosMapper->getById((string) $id_puesto);
            $nombrePuesto = $puesto['Nombre'] ?? $puesto['nombre'] ?? ('Puesto ' . $id_puesto);

            $this->puestosMapper->EliminarPuesto((string) $id_puesto);

            // --- Movimiento (bitácora) ---
            $actor = $this->userSession->getUser();
            $uidActor = $actor ? $actor->getUID() : null;
            $nombreActor = $actor ? $actor->getDisplayName() : 'Sistema';

            $mensaje = sprintf(
                '%s ha eliminado el puesto "%s".',
                $nombreActor,
                $nombrePuesto
            );

            $this->registrarMovimiento($uidActor, $id_puesto, $nombrePuesto, 'eliminacion', $mensaje);

            return new DataResponse(Http::STATUS_OK);
        } catch (\Exception $e) {
            return new DataResponse($e->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Guarda cambios en los puestos.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GuardarCambioPuestos(int $id_puestos, string $nombre, ?int $nivel = null): DataResponse {
        $this->requireHumanResourcesAccess();

        $old = $this->puestosMapper->getById((string) $id_puestos);

        $this->puestosMapper->updatePuestos((string) $id_puestos, $nombre, $nivel);

        if ($old) {
            $nombreAnterior = $old['Nombre'] ?? $old['nombre'] ?? '';
            $nivelAnterior = $old['Nivel'] ?? $old['nivel'] ?? null;

            $cambioNombre = $nombreAnterior !== $nombre;
            $cambioNivel = $nivelAnterior !== $nivel;

            if ($cambioNombre || $cambioNivel) {
                $actor = $this->userSession->getUser();
                $uidActor = $actor ? $actor->getUID() : null;
                $nombreActor = $actor ? $actor->getDisplayName() : 'Sistema';

                if ($cambioNombre && $cambioNivel) {
                    $mensaje = sprintf(
                        '%s ha actualizado el puesto "%s": cambió el nombre a **%s** y el nivel de %s a **%s**.',
                        $nombreActor,
                        $nombreAnterior,
                        $nombre,
                        $nivelAnterior ?? 'sin nivel',
                        $nivel ?? 'sin nivel'
                    );
                } elseif ($cambioNombre) {
                    $mensaje = sprintf(
                        '%s ha cambiado el nombre del puesto de "%s" a "%s".',
                        $nombreActor,
                        $nombreAnterior,
                        $nombre
                    );
                } else {
                    $mensaje = sprintf(
                        '%s ha cambiado el nivel del puesto "%s" de %s a %s.',
                        $nombreActor,
                        $nombreAnterior,
                        $nivelAnterior ?? 'sin nivel',
                        $nivel ?? 'sin nivel'
                    );
                }

                $this->registrarMovimiento($uidActor, $id_puestos, $nombre, 'edicion', $mensaje);
            }
        }

        return new DataResponse(Http::STATUS_OK);
    }

    /**
     * Crea un nuevo puesto.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function crearPuesto(string $nombre, ?int $nivel = null): DataResponse {
        $this->requireHumanResourcesAccess();
        $timestamp = date('Y-m-d');
        $puesto = new puestos();
        $puesto->setnombre($nombre);
        $puesto->setnivel($nivel);
        $puesto->setcreated_at($timestamp);
        $puesto->setupdated_at($timestamp);
        $this->puestosMapper->insert($puesto);

        // --- Movimiento (bitácora) ---
        $actor = $this->userSession->getUser();
        $uidActor = $actor ? $actor->getUID() : null;
        $nombreActor = $actor ? $actor->getDisplayName() : 'Sistema';

        $mensaje = sprintf(
            '%s ha creado el puesto "%s"%s.',
            $nombreActor,
            $nombre,
            $nivel !== null ? sprintf(' con nivel %d', $nivel) : ''
        );

        $this->registrarMovimiento($uidActor, $puesto->getId(), $nombre, 'creacion', $mensaje);

        return new DataResponse(Http::STATUS_OK);
    }

    /**
     * Obtiene un archivo subido y maneja posibles errores.
     */
    private function getUploadedFile(string $key): array {
        $this->requireHumanResourcesAccess();
        $file = $this->request->getUploadedFile($key);
        if (empty($file) || ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new UploadException($this->l10n->t('Error en la subida del archivo.'));
        }
        return $file;
    }

    private function requireHumanResourcesAccess(): void {
        $this->permisosService->requireCanSeeAny([
            'empleados.hr',
            'empleados.admin',
        ]);
    }

    /**
     * Catálogo de solo lectura (id/nombre) para etiquetas en módulos
     * como compras, sin conceder gestión de RRHH.
     */
    private function requireCatalogReadAccess(): void {
        $user = $this->userSession->getUser();
        $uid = $user?->getUID();

        if ($uid !== null && $uid !== '' && (
            $this->permisosService->canManageHumanResources($uid)
            || $this->compraPermisosService->canAccessModule($uid)
            || $this->permisosService->canSeeAny([
                'compras.admin',
                'compras.approve',
                'compras.request',
                'compras.accounting',
            ], $uid)
        )) {
            return;
        }

        $this->requireHumanResourcesAccess();
    }
}