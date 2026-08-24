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
use OCA\Empleados\Db\equiposMapper;
use OCA\Empleados\Db\configuracionesMapper;
use OCA\Empleados\Db\empleados;
use OCA\Empleados\Db\equipos;
use OCA\Empleados\Db\configuraciones;
use OCA\Empleados\UploadException;
use OCP\IGroupManager;
use OCA\Empleados\Service\BitacoraService;
use OCP\IConfig;

use OCP\IURLGenerator;
use OCP\Http\Client\IClientService;
use OCP\Group\ISubAdmin;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;

use OCA\Empleados\Service\PermisosService;

require_once 'SimpleXLSXGen.php';
require_once 'SimpleXLSX.php';

/**
 * Controlador para la gestión de equipos de empleados en Nextcloud.
 */
class equiposController extends BaseController {

    protected $userSession;
    protected $userManager;
    protected $empleadosMapper;
    protected $equiposMapper;
    protected $configuracionesMapper;
    protected $l10n;
    protected $groupManager;
    private IConfig $config;
    private IClientService $clientService;
    private ISubAdmin $subAdmin;
    protected PermisosService $permisosService;
    private BitacoraService $bitacoraService;

    private IURLGenerator $urlGenerator;

    public function __construct(
        IRequest $request,
        IUserSession $userSession,
        IUserManager $userManager,
        empleadosMapper $empleadosMapper,
        equiposMapper $equiposMapper,
        configuracionesMapper $configuracionesMapper,
        IL10N $l10n,
        IConfig $config,
		IGroupManager $groupManager,
        IURLGenerator $urlGenerator,
        IClientService $clientService,
        ISubAdmin $subAdmin,
        PermisosService $permisosService,
        BitacoraService $bitacoraService,
    ) {
		parent::__construct(Application::APP_ID, $request, $userSession, $groupManager, $empleadosMapper, $configuracionesMapper);

        $this->userSession = $userSession;
        $this->userManager = $userManager;
        $this->empleadosMapper = $empleadosMapper;
        $this->equiposMapper = $equiposMapper;
        $this->configuracionesMapper = $configuracionesMapper;
        $this->l10n = $l10n;
        $this->groupManager = $groupManager;
        $this->config = $config;
        $this->urlGenerator = $urlGenerator;
        $this->clientService = $clientService;
        $this->subAdmin = $subAdmin;
        $this->permisosService = $permisosService;
        $this->bitacoraService = $bitacoraService;
    }

    /**
     * Registra un movimiento del módulo "equipos" en la bitácora general.
     */
    private function registrarMovimiento(
        ?string $uidActor,
        ?int $idReferencia,
        ?string $nombreAfectado,
        string $tipo,
        string $mensaje
    ): void {
        $this->bitacoraService->registrar('equipos', $uidActor, null, $nombreAfectado, $tipo, $mensaje, $idReferencia);
    }

    /**
     * Obtiene la lista de equipos en formato clave-valor.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GetEquiposFix(): DataResponse {
        $this->checkAccess(['admin', 'recursos_humanos']);
        $response = array_map(fn($equipo) => [
            'value' => $equipo['Id_equipos'],
            'label' => $equipo['Nombre'],
        ], $this->equiposMapper->GetEquiposList());

        return new DataResponse($response, Http::STATUS_OK);
    }

    /**
     * Obtiene la lista de equipos.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GetEquiposList(): DataResponse {
        $this->checkAccess(['admin', 'recursos_humanos']);
        return new DataResponse($this->equiposMapper->GetEquiposList(), Http::STATUS_OK);
    }

    /**
     * Obtiene jefe de equipo
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GetEquipoJefe(): DataResponse {
         $this->permisosService->requireCanSeeAny([
            'empleados',
        ]);
        $id = $this->request->getParam('id');
        return new DataResponse($this->equiposMapper->GetEquipoJefe((string)$id), Http::STATUS_OK);
    }

    /**
     * Exporta la lista de equipos a un archivo XLSX.
     */
    public function ExportListEquipos(): DataResponse {
        $this->checkAccess(['admin', 'recursos_humanos']);
        $equipos = $this->equiposMapper->GetEquiposList();

        $books = [[
            'Id_equipo',
            'Nombre',
            'Id_jefe_equipo',
            'Nombre_jefe',
            'created_at',
            'updated_at',
        ]];

        foreach ($equipos as $equipo) {
            $uidJefe = $equipo['Id_jefe_equipo'] ?? null;

            $idJefeNumerico = '';
            $nombreJefe = '';

            if (!empty($uidJefe)) {
                $nombreJefe = $uidJefe; // lo que antes salía en Id_jefe_equipo

                $empleadoJefe = $this->empleadosMapper->GetMyEmployeeInfo($uidJefe);

                if (!empty($empleadoJefe)) {
                    $idJefeNumerico = $empleadoJefe[0]['Id_empleados'] ?? '';
                }
            }

            $books[] = [
                $equipo['Id_equipo'],
                $equipo['Nombre'],
                $idJefeNumerico,
                $nombreJefe,
                $equipo['created_at'],
                $equipo['updated_at'],
            ];
        }

        \Shuchkin\SimpleXLSXGen::fromArray($books)->downloadAs('equipos.xlsx');
        return new DataResponse($books, Http::STATUS_OK);
    }

    /**
     * Importa la lista de equipos desde un archivo XLSX.
     */
    public function ImportListEquipos(): DataResponse {
        $this->checkAccess(['admin', 'recursos_humanos']);
        $file = $this->getUploadedFile('equipofileXLSX');

        $creados = 0;
        $actualizados = 0;

        if ($xlsx = \Shuchkin\SimpleXLSX::parse($file['tmp_name'])) {
            foreach ($xlsx->rows() as $row) {
                if (!empty($row[0])) {
                    $this->equiposMapper->updateEquipos((string) $row[0], (string) $row[1]);
                    $actualizados++;
                } else {
                    $timestamp = date('Y-m-d');
                    $equipo = new equipos();
                    $equipo->setnombre((string) $row[1]);
                    $equipo->setcreated_at($timestamp);
                    $equipo->setupdated_at($timestamp);
                    $this->equiposMapper->insert($equipo);
                    $creados++;
                }
            }

            // --- Movimiento (bitácora) ---
            if ($creados > 0 || $actualizados > 0) {
                $actor = $this->userSession->getUser();
                $uidActor = $actor ? $actor->getUID() : null;
                $nombreActor = $actor ? $actor->getDisplayName() : 'Sistema';

                $mensaje = sprintf(
                    '%s ha importado equipos desde un archivo XLSX: %d creado(s), %d actualizado(s).',
                    $nombreActor, $creados, $actualizados
                );

                $this->registrarMovimiento($uidActor, null, null, 'importacion', $mensaje);
            }

        return new DataResponse(['status' => 'error'], Http::STATUS_BAD_REQUEST);
        }
        return new DataResponse(Http::STATUS_OK);
    }

    /**
     * Elimina un equipo por ID.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function EliminarEquipo(int $id_equipo): DataResponse {
        $this->checkAccess(['admin', 'recursos_humanos']);

        try {
            $row = $this->equiposMapper->deleteByIdReturningRow((string)$id_equipo);

            if (!$row) {
                return new DataResponse([
                    'status' => 'error',
                    'message' => 'Equipo no encontrado.',
                ], Http::STATUS_NOT_FOUND);
            }

            $nombreGrupo = $row['Nombre'] ?? $row['nombre'] ?? null;

            if (!empty($nombreGrupo)) {
                $group = $this->groupManager->get($nombreGrupo);

                if ($group !== null) {
                    $group->delete();
                }
            }

            // --- Movimiento (bitácora) ---
            $actor = $this->userSession->getUser();
            $uidActor = $actor ? $actor->getUID() : null;
            $nombreActor = $actor ? $actor->getDisplayName() : 'Sistema';

            $mensaje = sprintf(
                '%s ha eliminado el equipo "%s".',
                $nombreActor,
                $nombreGrupo ?? ('Equipo ' . $id_equipo)
            );

            $this->registrarMovimiento($uidActor, $id_equipo, $nombreGrupo, 'eliminacion', $mensaje);

            return new DataResponse([
                'status' => 'ok',
                'message' => 'Equipo eliminado correctamente.',
            ], Http::STATUS_OK);
        } catch (\Throwable $e) {
            return new DataResponse([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }


    /**
     * Guarda cambios en los equipos.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GuardarCambioEquipo(
        int $Id_Equipo,
        string $nombre,
        string $Id_jefe_equipo
    ): DataResponse {
        $this->checkAccess(['admin', 'recursos_humanos']);

        try {
            // 1) Leer estado actual
            $old = $this->equiposMapper->getById((string)$Id_Equipo);

            if (!$old) {
                return new DataResponse([
                    'status' => 'error',
                    'message' => "Equipo $Id_Equipo no existe",
                ], Http::STATUS_NOT_FOUND);
            }

            $groupName = $old['Nombre'] ?? $old['nombre'] ?? null;

            if (!$groupName) {
                return new DataResponse([
                    'status' => 'error',
                    'message' => 'Equipo sin nombre',
                ], Http::STATUS_BAD_REQUEST);
            }

            $oldJefe = $old['Id_jefe_equipo'] ?? $old['id_jefe_equipo'] ?? null;

            // 2) Actualizar jefe en BD
            $this->equiposMapper->updateEquipos(
                (string)$Id_Equipo,
                $nombre,
                $Id_jefe_equipo
            );

            // 3) Asegurar grupo
            $group = $this->groupManager->get($groupName);

            if (!$group) {
                $this->groupManager->createGroup($groupName);
                $group = $this->groupManager->get($groupName);
            }

            if (!$group) {
                return new DataResponse([
                    'status' => 'error',
                    'message' => "No se pudo crear/obtener el grupo '$groupName'",
                ], Http::STATUS_INTERNAL_SERVER_ERROR);
            }

            // 4) Nuevo jefe
            $newBoss = $this->userManager->get($Id_jefe_equipo);

            if (!$newBoss) {
                return new DataResponse([
                    'status' => 'error',
                    'message' => "Usuario '$Id_jefe_equipo' no existe",
                ], Http::STATUS_BAD_REQUEST);
            }

            // 5) Asegurar que el jefe sea miembro del grupo
            if (!$group->inGroup($newBoss)) {
                $group->addUser($newBoss);
            }

            // 6) Promover solo si todavía no es subadmin
            if (!$this->isSubAdminOfGroupSafe($newBoss, $group)) {
                $this->subAdmin->createSubAdmin($newBoss, $group);
            }

            // 7) Quitar subadmin anterior si cambió
            if ($oldJefe && $oldJefe !== $Id_jefe_equipo) {
                $oldUser = $this->userManager->get($oldJefe);

                if ($oldUser && $this->isSubAdminOfGroupSafe($oldUser, $group)) {
                    $this->subAdmin->deleteSubAdmin($oldUser, $group);
                }
            }

            // --- Movimiento (bitácora) ---
            $actor = $this->userSession->getUser();
            $uidActor = $actor ? $actor->getUID() : null;
            $nombreActor = $actor ? $actor->getDisplayName() : 'Sistema';

            $nombreAnterior = $old['Nombre'] ?? $old['nombre'] ?? '';
            $cambioNombre = $nombreAnterior !== $nombre;
            $cambioJefe = $oldJefe !== $Id_jefe_equipo;

            // Solo registrar si realmente hubo algún cambio
            if ($cambioNombre || $cambioJefe) {

                if ($cambioNombre && $cambioJefe) {
                    $nombreJefeAnterior = $oldJefe
                        ? ($this->userManager->get($oldJefe)?->getDisplayName() ?? $oldJefe)
                        : 'ninguno';

                    $mensaje = sprintf(
                        '%s ha actualizado el equipo "%s": cambió el nombre de "%s" a **%s** y el jefe de %s a **%s**.',
                        $nombreActor,
                        $nombreAnterior,
                        $nombreAnterior,
                        $nombre,
                        $nombreJefeAnterior,
                        $newBoss->getDisplayName()
                    );
                } elseif ($cambioNombre) {
                    // Solo cambió nombre
                    $mensaje = sprintf(
                        '%s ha cambiado el nombre del equipo de "%s" a "%s".',
                        $nombreActor,
                        $nombreAnterior,
                        $nombre
                    );

                } else {
                    // Solo cambió jefe — conserva tu mensaje actual
                    $nombreJefeAnterior = $oldJefe
                        ? ($this->userManager->get($oldJefe)?->getDisplayName() ?? $oldJefe)
                        : 'ninguno';

                    $mensaje = sprintf(
                        '%s ha cambiado el jefe del equipo "%s": de %s a %s.',
                        $nombreActor,
                        $nombreAnterior,
                        $nombreJefeAnterior,
                        $newBoss->getDisplayName()
                    );
                }

                $this->registrarMovimiento(
                    $uidActor,
                    $Id_Equipo,
                    $nombre,
                    'edicion',
                    $mensaje
                );
            }

            return new DataResponse([
                'status' => 'ok',
                'message' => 'Equipo actualizado correctamente.',
            ], Http::STATUS_OK);
        } catch (\Throwable $e) {
            return new DataResponse([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Crea un nuevo equipo.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function crearEquipo(string $nombre, string $jefe): DataResponse {
        $this->checkAccess(['admin', 'recursos_humanos']);
        $timestamp = date('Y-m-d');
        $equipo = new equipos();
        $equipo->setnombre($nombre);
        $equipo->setid_jefe_equipo($jefe);
        $equipo->setcreated_at($timestamp);
        $equipo->setupdated_at($timestamp);
        $this->equiposMapper->insert($equipo);

        $group = $this->groupManager->get($nombre);
            if (!$group) {
                $this->groupManager->createGroup($nombre);
                $group = $this->groupManager->get($nombre);
            }

        // después de crear equipo y grupo
        $user = $this->userManager->get($jefe);
        if (!$user) {
            throw new \RuntimeException("Usuario $jefe no existe");
        }

        $group = $this->groupManager->get($nombre);
        if (!$group) {
            throw new \RuntimeException("No se pudo crear/obtener el grupo '$nombre'");
        }

        // 1) asegurar que sea miembro del grupo
        if (!$group->inGroup($user)) {
            $group->addUser($user);
        }

        $this->subAdmin->createSubAdmin($user, $group);

        // --- Movimiento (bitácora) ---
        $actor = $this->userSession->getUser();
        $uidActor = $actor ? $actor->getUID() : null;
        $nombreActor = $actor ? $actor->getDisplayName() : 'Sistema';

        $mensaje = sprintf(
            '%s ha creado el equipo "%s" con %s como jefe.',
            $nombreActor,
            $nombre,
            $user->getDisplayName()
        );

        $this->registrarMovimiento($uidActor, $equipo->getId(), $nombre, 'creacion', $mensaje);

        return new DataResponse(Http::STATUS_OK);
    }

    #[UseSession]
    #[NoAdminRequired]
    public function promoverJefeDeEquipo(string $uid, string $gid): DataResponse {
        $this->checkAccess(['admin', 'recursos_humanos']);
        try {
            $user  = $this->userManager->get($uid);
            $group = $this->groupManager->get($gid);
            if (!$user || !$group) {
                return DataResponse('error: usuario o grupo no existen', Http::STATUS_BAD_REQUEST);
            }

            // Asegurar pertenencia al grupo (evita fallo de subadmin si no es miembro)
            if (!$group->inGroup($user)) {
                $group->addUser($user);
            }

            $yaEraSubAdmin = $this->isSubAdminOfGroupSafe($user, $group);

            // Promover a subadmin
            if (!$yaEraSubAdmin) {
                $this->subAdmin->createSubAdmin($user, $group);
            }

            // --- Movimiento (bitácora) ---
            if (!$yaEraSubAdmin) {
                $actor = $this->userSession->getUser();
                $uidActor = $actor ? $actor->getUID() : null;
                $nombreActor = $actor ? $actor->getDisplayName() : 'Sistema';

                $mensaje = sprintf(
                    '%s ha promovido a %s como jefe/subadmin del equipo "%s".',
                    $nombreActor, $user->getDisplayName(), $gid
                );

                $this->registrarMovimiento($uidActor, null, $gid, 'promocion_jefe', $mensaje);
            }

            return new DataResponse('ok', Http::STATUS_OK);
        } catch (\Throwable $e) {
            return new DataResponse('error: ' . $e->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtiene un archivo subido y maneja posibles errores.
     */
    private function getUploadedFile(string $key): array {
        $this->checkAccess(['admin', 'recursos_humanos']);
        $file = $this->request->getUploadedFile($key);
        if (empty($file) || ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new UploadException($this->l10n->t('Error en la subida del archivo.'));
        }
        return $file;
    }

    private function isSubAdminOfGroupSafe($user, $group): bool {
        if (method_exists($this->subAdmin, 'isSubAdminOfGroup')) {
            return $this->subAdmin->isSubAdminOfGroup($user, $group);
        }

        if (method_exists($this->subAdmin, 'isSubAdminofGroup')) {
            return $this->subAdmin->isSubAdminofGroup($user, $group);
        }

        if (method_exists($this->subAdmin, 'getSubAdminsGroups')) {
            $groups = $this->subAdmin->getSubAdminsGroups($user);

            foreach ($groups as $subAdminGroup) {
                if ($subAdminGroup->getGID() === $group->getGID()) {
                    return true;
                }
            }
        }

        return false;
    }
    private function requireHumanResourcesAccess(): void {
        $this->permisosService->requireCanSeeAny([
            'empleados.hr',
            'empleados.admin',
        ]);
    }
}
