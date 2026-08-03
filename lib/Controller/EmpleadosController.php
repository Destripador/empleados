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
use OCA\Empleados\Db\departamentosMapper;
use OCA\Empleados\Db\configuracionesMapper;
use OCA\Empleados\Db\historialvacacionesMapper;
use OCA\Empleados\Db\ausenciasMapper;
use OCA\Empleados\Db\userahorroMapper;
use OCA\Empleados\Db\ausencias;
use OCA\Empleados\Db\empleados;
use OCA\Empleados\Db\contactoemergencia;
use OCA\Empleados\Db\contactoemergenciaMapper;
use OCA\Empleados\Db\departamentos;
use OCA\Empleados\Db\configuraciones;
use OCA\Empleados\Db\userahorro;

use OCA\Empleados\Db\equiposMapper;

use OCP\IAvatarManager;

use OCP\IDBConnection;

use OCP\Files\IRootFolder;

use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;

use OCA\Empleados\Service\PermisosService;
use OCA\Empleados\Service\AniversarioSyncService;
use OCA\Empleados\Service\InventarioMovimientoService;

require_once 'SimpleXLSXGen.php';
require_once 'SimpleXLSX.php';

/**
 * Controlador principal para la gestión de empleados en Nextcloud.
 */
class EmpleadosController extends BaseController {

    protected $userSession;
    protected $userManager;
    protected $groupManager;
    protected $empleadosMapper;
    protected $ausenciasMapper;
    protected $departamentosMapper;
    protected $configuracionesMapper;
    protected $userahorroMapper;
    protected $session;
    protected $l10n;
    protected $equiposMapper;
    protected $historialvacacionesMapper;
    protected contactoemergenciaMapper $contactoemergenciaMapper;
    protected PermisosService $permisosService;
    private AniversarioSyncService $aniversarioSyncService;
    private InventarioMovimientoService $inventarioMovimientoService;

    protected IRootFolder $rootFolder;

    public function __construct(
        IRequest $request,
        ISession $session,
        IUserSession $userSession,
        IUserManager $userManager,
        empleadosMapper $empleadosMapper,
        ausenciasMapper $ausenciasMapper,
        departamentosMapper $departamentosMapper,
        configuracionesMapper $configuracionesMapper,
        userahorroMapper $userahorroMapper,
        IL10N $l10n,
        IGroupManager $groupManager,
        IRootFolder $rootFolder,
        IAvatarManager $avatarManager,
        equiposMapper $equiposMapper,
        historialvacacionesMapper $historialvacacionesMapper,
        contactoemergenciaMapper $contactoemergenciaMapper,
        PermisosService $permisosService,
        AniversarioSyncService $aniversarioSyncService,
        InventarioMovimientoService $inventarioMovimientoService
    ) {
		parent::__construct(Application::APP_ID, $request, $userSession, $groupManager, $empleadosMapper, $configuracionesMapper);

        $this->session = $session;
        $this->userSession = $userSession;
        $this->userManager = $userManager;
        $this->groupManager = $groupManager;
        $this->empleadosMapper = $empleadosMapper;
        $this->ausenciasMapper = $ausenciasMapper;
        $this->userahorroMapper = $userahorroMapper;
        $this->departamentosMapper = $departamentosMapper;
        $this->configuracionesMapper = $configuracionesMapper;
        $this->l10n = $l10n;
        $this->avatarManager = $avatarManager;

        $this->rootFolder = $rootFolder;

        $this->equiposMapper = $equiposMapper;
        $this->contactoemergenciaMapper = $contactoemergenciaMapper;

        $this->permisosService = $permisosService;
        $this->aniversarioSyncService = $aniversarioSyncService;
        $this->inventarioMovimientoService = $inventarioMovimientoService;
    }

    /**
     * Actualizar imagen de perfil de nextcloud.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function uploadAvatar(): DataResponse {
        $this->requireHumanResourcesAccess();

        $uid = $this->request->getParam('uid');
        $file = $this->request->getUploadedFile('avatar');

        if (!$uid || !$file || !$file['tmp_name']) {
            throw new \Exception('Faltan datos o archivo inválido');
        }

        $content = file_get_contents($file['tmp_name']);
        $avatar = $this->avatarManager->getAvatar($uid);
        $avatar->set($content);

        // Generar una versión basada en la marca de tiempo actual
        $version = time();

        return new DataResponse(['status' => 'success', 'version' => $version]);
    }

    /**
     * Obtiene la lista de empleados, usuarios y desactivados.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GetUserLists(): DataResponse {
        $this->requireHumanResourcesAccess();
        return new DataResponse([
            'Empleados' => $this->empleadosMapper->GetUserLists(),
            'Users' => $this->empleadosMapper->getAllUsers(),
            'Desactivados' => $this->empleadosMapper->GetUserListsDeactive()
        ], Http::STATUS_OK);
    }

    /**
     * Obtiene la lista de empleados con validación de acceso.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GetEmpleadosList(): DataResponse {
         $this->permisosService->requireCanSeeAny([
            'empleados.hr',
            'empleados.admin',
        ]);
        return new DataResponse([
            'Empleados'    => $this->empleadosMapper->GetUserLists()
        ], Http::STATUS_OK);
    }

    /**
     * Obtiene empleados de un área específica.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GetEmpleadosArea(string $id_area): DataResponse {
        $this->requireHumanResourcesAccess();
        return new DataResponse([
            'area' => $this->empleadosMapper->GetEmpleadosArea($id_area)
        ], Http::STATUS_OK);
    }

    /**
     * Obtiene empleados de un puesto específico.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GetEmpleadosPuesto(string $id_puesto): DataResponse {
        $this->requireHumanResourcesAccess();
        return new DataResponse([
            'puesto' => $this->empleadosMapper->GetEmpleadosPuesto($id_puesto)
        ], Http::STATUS_OK);
    }
    
    /**
     * Obtiene empleados de un equipo específico.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GetEmpleadosEquipo(string $id_equipo): DataResponse {
        $this->requireHumanResourcesAccess();
        return new DataResponse([
            'equipo' => $this->empleadosMapper->GetEmpleadosEquipo($id_equipo)
        ], Http::STATUS_OK);
    }

    /**
     * Obtiene empleados de un equipo específico.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GetMyEquipo(): DataResponse {
        $this->permisosService->requireCanSeeAny([
            'empleados',
        ]);
        $empleado = $this->empleadosMapper->GetMyEmployeeInfo($this->userSession->getUser()->getUID());

        if (empty($empleado) || empty($empleado[0]['Id_equipo'])) {
            return new DataResponse(['equipo' => []], Http::STATUS_OK);
        }

        $people = $this->empleadosMapper->GetMyEquipo($empleado[0]['Id_equipo']);

        return new DataResponse([
            'equipo' => $people,
        ], Http::STATUS_OK);
    }

    /**
     * Activa un empleado y crea sus carpetas en Nextcloud.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function ActivarEmpleado(string $id_user): DataResponse {
        $this->requireHumanResourcesAccess();
        try {
            // Verificar si el grupo "empleados" existe
            $group = $this->groupManager->get("empleados");
            if (!$group) {
                $this->groupManager->createGroup("empleados");
                $group = $this->groupManager->get("empleados");
            }

            // verificar que el usuario exista en nextcloud
            $user = $this->userManager->get($id_user);
        
            // Verificar si el usuario ya pertenece al grupo
            if (!$group->inGroup($user)) {
                $group->addUser($user);
            }
            
            $gestor = $this->configuracionesMapper->GetGestor()[0]['Data'] ?? null;

            if ($gestor) {
                $userFolder = $this->rootFolder->getUserFolder($gestor);
                $folderPath = "EMPLEADOS/" . $id_user . " - " . mb_strtoupper($user->getDisplayName(), 'UTF-8');

                if (!$userFolder->nodeExists($folderPath)) {
                    foreach (["", "/CAPACITACIONES", "/DOCUMENTOS OFICIALES", "/DOCUMENTOS DE IDENTIFICACION", "/MEMORANDUMS", "/JUSTIFICANTES"] as $subFolder) {
                        $userFolder->newFolder($folderPath . $subFolder);
                    }
                }

                $timestamp = date('Y-m-d');
                $empleado = new empleados();
                $empleado->setid_user($id_user);
                $empleado->setestado('1');
                $empleado->setcreated_at($timestamp);
                $empleado->setupdated_at($timestamp);

                $this->empleadosMapper->insert($empleado);
                                
                // Obtén la conexión a través del contenedor de Nextcloud
                $connection = \OC::$server->get(IDBConnection::class);
                $idEmpleado = $connection->lastInsertId('empleados');

                // Generar un nuevo registro de ausencias
                // y asociarlo al empleado recién creado
                $ausencias = new ausencias();
                $ausencias->setid_empleado((int)$idEmpleado);
                $ausencias->setTimestamp(new \DateTime());
                $this->ausenciasMapper->insert($ausencias);

                // Generar un nuevo registro de ahorro
                // y asociarlo al empleado recién creado
                $userahorro = new userahorro();
                $userahorro->setid_user($idEmpleado);
                $userahorro->setid_permision('0');
                $userahorro->setstate('0');
                $userahorro->setlast_modified($timestamp);
                $this->userahorroMapper->insert($userahorro);

                return new DataResponse(Http::STATUS_OK);
            } else {
                return new DataResponse("No existe usuario gestor", Http::STATUS_OK);
            }
            
        } catch (\Exception $e) {
             return new DataResponse($e->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Desactiva un empleado.
     */
    #[UseSession]
    #[NoAdminRequired]
	public function DesactivarEmpleado(int $id_empleados): DataResponse {
        $this->requireHumanResourcesAccess();
		try{
			$this->empleadosMapper->DesactivarByIdEmpleado($id_empleados);
			return new DataResponse(Http::STATUS_OK);
		}
		catch(Exception $e){
            return new DataResponse($e->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

    /**
     * Activa un empleado.
     */
    #[UseSession]
    #[NoAdminRequired]
	public function ActivarUsuario(int $id_empleados): DataResponse {
        $this->requireHumanResourcesAccess();
		try{
			$this->empleadosMapper->ActivarByIdEmpleado($id_empleados);
            
            return new DataResponse(Http::STATUS_OK);
		}
		catch(Exception $e){
            return new DataResponse($e ,Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

    #[UseSession]
    #[NoAdminRequired]
	public function EliminarEmpleado(int $id_empleados, string $id_user): DataResponse {
        $this->requireHumanResourcesAccess();
		try{
            // verificar que el usuario exista en nextcloud
            $user = $this->userManager->get($id_user);
            // Verificar si el grupo "empleados" existe
            $group = $this->groupManager->get("empleados");

            // Verificar si el usuario ya pertenece al grupo
            if ($group->inGroup($user)) {
                $group->removeUser($user);
            }
            
			$this->contactoemergenciaMapper->deleteByEmpleado($id_empleados);
			$this->empleadosMapper->deleteByIdEmpleado($id_empleados);
            $this->ausenciasMapper->deleteByIdEmpleado($id_empleados);
            $this->organigramaMapper->EliminarPorEmpleado($id_empleados);

			return new DataResponse(Http::STATUS_OK);
		}
		catch(Exception $e){
            return new DataResponse($e,Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

    /**
     * Importa lista de empleados desde un archivo XLSX.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function ImportListEmpleados(): DataResponse {
        $this->requireHumanResourcesAccess();
        $file = $this->getUploadedFile('fileXLSX');
        if ($xlsx = \Shuchkin\SimpleXLSX::parse($file['tmp_name'])) {
            $rows_info = $xlsx->rows();
            foreach (array_slice($rows_info, 1) as $row) { // Omitir encabezado
                $this->empleadosMapper->updateEmpleado(
                    (string) $row[0], (string) $row[2], $this->convertExcelDate($row[3]),
                    (string) $row[4], (string) $row[5], (string) $row[6], (string) $row[7],
                    (string) $row[8], (string) $row[9], (string) $row[10], (string) $row[12],
                    (string) $row[13], (string) $row[14], $this->convertExcelDate($row[15]),
                    (string) $row[16], (string) $row[17], (string) $row[18], (string) $row[19],
                    (string) $row[20], (string) $row[21], (string) $row[22], (string) $row[23],
                    (string) $row[24], (string) $row[25],
                );
                
                $this->ausenciasMapper->updateAusenciasById(
                    (int)$row[0], 
                    (int)$row[25], 
                    (float)$row[26],
                );

                $this->userahorroMapper->updatePermisionByEmpleadoId(
                    (int) $row[0], 
                    (string) $row[11],
                );
            }

            return new DataResponse(Http::STATUS_OK);
        }
        
        return new DataResponse("Error al procesar el archivo XLSX", Http::STATUS_INTERNAL_SERVER_ERROR);
    }

    #[UseSession]
    #[NoAdminRequired]
    public function GuardarNota(int $id_empleados, string $nota): DataResponse {
        $this->requireHumanResourcesAccess();
		$this->empleadosMapper->GuardarNota(strval($id_empleados), $nota);
        return new DataResponse(Http::STATUS_OK);
	}

    #[UseSession]
    #[NoAdminRequired]
    public function CambiosEmpleado(
        $id_empleados, 
        $numeroempleado, 
        $ingreso, 
        $area, 
        $puesto, 
        $socio, 
        $gerente, 
        $fondoclave, 
        $fondoahorro, 
        $numerocuenta, 
        $equipoasignado, // equipo de cómputo
        $equipo,         // grupo de trabajo
        $sueldo,
        $id_aniversario,
        $dias_disponibles
    ): DataResponse {
        $this->requireHumanResourcesAccess();
        $empBefore = $this->empleadosMapper->GetMyEmployeeInfoByIdEmpleado((string)$id_empleados);
        if (!$empBefore) {
            throw new \RuntimeException("Empleado $id_empleados no existe");
        }

        $ingresoAnterior = $empBefore[0]['Ingreso'] ?? null;
        $oldUid    = $empBefore[0]['Id_user'] ?? null;
        $oldEquipo = $empBefore[0]['Id_equipo'] ?? null;
		$equipoComputoAnterior = $this->normalizarEquipoComputoId($empBefore[0]['Equipo_asignado'] ?? null);
		$equipoComputoNuevo = $this->normalizarEquipoComputoId($equipoasignado);

        // 1) Aplica cambios en BD (empleado + ausencias)
		$this->inventarioMovimientoService->ejecutarCambioAsignacion(
			(int)$id_empleados,
			$equipoComputoAnterior,
			$equipoComputoNuevo,
			function () use ($id_empleados, $numeroempleado, $ingreso, $area, $puesto, $socio, $gerente, $fondoclave, $fondoahorro, $numerocuenta, $equipoasignado, $equipo, $sueldo, $id_aniversario, $dias_disponibles): void {
				$this->empleadosMapper->CambiosEmpleado(
					$id_empleados, $numeroempleado, $ingreso, $area, $puesto, $socio,
					$gerente, $fondoclave, $fondoahorro, $numerocuenta, $equipoasignado,
					$equipo, $sueldo
				);
				$this->ausenciasMapper->updateAusenciasById(
					(int)$id_empleados, (int)$id_aniversario, (float)$dias_disponibles
				);
			}
		);

        // 1.5) Sincroniza periodos SOLO si cambió el ingreso, y una sola vez
        if (!empty($ingreso) && $ingresoAnterior !== $ingreso) {
            try {
                $this->aniversarioSyncService->sincronizarPeriodos($id_empleados, $ingreso);
            } catch (\Exception $e) {
                return new DataResponse("Error al sincronizar periodos: " . $e->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
            }
        }

        // 2) Si no cambió el equipo o está vacío → no tocar grupos
        if (empty($equipo) || (string)$oldEquipo === (string)$equipo) {
            return new DataResponse(Http::STATUS_OK);
        }

        // 3) Obtiene datos del equipo destino
        $team = $this->equiposMapper->getById((string)$equipo);
        $groupName = $team['Nombre'] ?? $team['nombre'] ?? null;
        if (!$groupName) {
            throw new \RuntimeException("El equipo $equipo no tiene nombre de grupo");
        }

        $group = $this->groupManager->get($groupName);
        if (!$group) {
            throw new \RuntimeException("El grupo '$groupName' no existe");
        }

        // 4) Busca el usuario
        $uid = $oldUid;
        if (empty($uid)) {
            return new DataResponse(Http::STATUS_OK);
        }

        $user = $this->userManager->get($uid);
        if (!$user) {
            throw new \RuntimeException("Usuario '$uid' no existe en Nextcloud");
        }

        // 5) Quita del grupo anterior si cambió
        if (!empty($oldEquipo) && (string)$oldEquipo !== (string)$equipo) {
            $oldTeam = $this->equiposMapper->getById((string)$oldEquipo);
            $oldGroupName = $oldTeam['Nombre'] ?? $oldTeam['nombre'] ?? null;
            if ($oldGroupName) {
                $oldGroup = $this->groupManager->get($oldGroupName);
                if ($oldGroup && $oldGroup->inGroup($user)) {
                    $oldGroup->removeUser($user);
                }
            }
        }

        // 6) Añade al nuevo grupo
        $userGroups = $this->groupManager->getUserGroupIds($user);
        if (!in_array($groupName, $userGroups, true)) {
            $group->addUser($user);
        }

        return new DataResponse(Http::STATUS_OK);
    }


    #[UseSession]
    #[NoAdminRequired]
	public function CambiosPersonal($Id_empleados, $Direccion, $Estado_civil, $Telefono_contacto, $Rfc, $Imss, $Contacto_emergencia, $Numero_emergencia, $Curp, $Fecha_nacimiento, $Correo_contacto, $Genero): DataResponse {
        $this->requireHumanResourcesAccess();
		$this->empleadosMapper->CambiosPersonal($Id_empleados, $Direccion, $Estado_civil, $Telefono_contacto, $Rfc, $Imss, $Contacto_emergencia, $Numero_emergencia, $Curp, $Fecha_nacimiento, $Correo_contacto, $Genero);
        return new DataResponse(Http::STATUS_OK);
    }

    #[UseSession]
    #[NoAdminRequired]
    public function listarContactosEmergencia(int $id_empleado): DataResponse {
        $this->requireHumanResourcesAccess();
		if ($id_empleado <= 0) {
			return new DataResponse(['message' => 'Identificador de empleado inválido'], Http::STATUS_BAD_REQUEST);
		}
        if (!$this->empleadoExiste($id_empleado)) {
            return new DataResponse(['message' => 'Empleado no encontrado'], Http::STATUS_NOT_FOUND);
        }
        return new DataResponse(['contactos' => array_map([$this, 'contactoToArray'], $this->contactoemergenciaMapper->findByEmpleado($id_empleado))]);
    }

    #[UseSession]
    #[NoAdminRequired]
    public function crearContactoEmergencia(int $id_empleado, string $nombre, string $relacion, string $numero_contacto, string $medio_alternativo = '', string $tipo_ayuda = '', string $notas = '', bool $es_principal = false): DataResponse {
        $this->requireHumanResourcesAccess();
		if ($id_empleado <= 0) {
			return new DataResponse(['message' => 'Identificador de empleado inválido'], Http::STATUS_BAD_REQUEST);
		}
        if (!$this->empleadoExiste($id_empleado)) {
            return new DataResponse(['message' => 'Empleado no encontrado'], Http::STATUS_NOT_FOUND);
        }
        $values = $this->validarContacto($nombre, $relacion, $numero_contacto, $medio_alternativo, $tipo_ayuda, $notas);
        if (isset($values['error'])) return new DataResponse(['message' => $values['error']], Http::STATUS_BAD_REQUEST);
		$contact = new contactoemergencia();
		$contact->setIdEmpleado($id_empleado);
		$this->aplicarContacto($contact, $values, $es_principal);
		$contact->setOrden(count($this->contactoemergenciaMapper->findByEmpleado($id_empleado)));
		$contact->setCreatedAt(date('Y-m-d H:i:s'));
		$saved = $this->contactoemergenciaMapper->saveContact($contact);

		return new DataResponse(['contacto' => $this->contactoToArray($saved)], Http::STATUS_CREATED);
    }

    #[UseSession]
    #[NoAdminRequired]
    public function actualizarContactoEmergencia(int $id, int $id_empleado, string $nombre, string $relacion, string $numero_contacto, string $medio_alternativo = '', string $tipo_ayuda = '', string $notas = '', bool $es_principal = false): DataResponse {
        $this->requireHumanResourcesAccess();
		if ($id <= 0 || $id_empleado <= 0) {
			return new DataResponse(['message' => 'Identificador inválido'], Http::STATUS_BAD_REQUEST);
		}
        $values = $this->validarContacto($nombre, $relacion, $numero_contacto, $medio_alternativo, $tipo_ayuda, $notas);
        if (isset($values['error'])) return new DataResponse(['message' => $values['error']], Http::STATUS_BAD_REQUEST);
        try {
            $contact = $this->contactoemergenciaMapper->findForEmpleado($id, $id_empleado);
            $this->aplicarContacto($contact, $values, $es_principal);
            return new DataResponse(['contacto' => $this->contactoToArray($this->contactoemergenciaMapper->saveContact($contact))]);
        } catch (\OCP\AppFramework\Db\DoesNotExistException) {
            return new DataResponse(['message' => 'Contacto no encontrado'], Http::STATUS_NOT_FOUND);
        }
    }

    #[UseSession]
    #[NoAdminRequired]
    public function eliminarContactoEmergencia(int $id, int $id_empleado): DataResponse {
        $this->requireHumanResourcesAccess();
		if ($id <= 0 || $id_empleado <= 0) {
			return new DataResponse(['message' => 'Identificador inválido'], Http::STATUS_BAD_REQUEST);
		}
        try {
            $this->contactoemergenciaMapper->delete($this->contactoemergenciaMapper->findForEmpleado($id, $id_empleado));
            return new DataResponse([], Http::STATUS_OK);
        } catch (\OCP\AppFramework\Db\DoesNotExistException) {
            return new DataResponse(['message' => 'Contacto no encontrado'], Http::STATUS_NOT_FOUND);
        }
    }

    #[UseSession]
    #[NoAdminRequired]
    public function marcarContactoEmergenciaPrincipal(int $id, int $id_empleado): DataResponse {
        $this->requireHumanResourcesAccess();
		if ($id <= 0 || $id_empleado <= 0) {
			return new DataResponse(['message' => 'Identificador inválido'], Http::STATUS_BAD_REQUEST);
		}
        try {
            return new DataResponse(['contacto' => $this->contactoToArray($this->contactoemergenciaMapper->setPrincipal($id, $id_empleado))]);
        } catch (\OCP\AppFramework\Db\DoesNotExistException) {
            return new DataResponse(['message' => 'Contacto no encontrado'], Http::STATUS_NOT_FOUND);
        }
    }

    private function empleadoExiste(int $idEmpleado): bool {
        return !empty($this->empleadosMapper->GetMyEmployeeInfoByIdEmpleado((string)$idEmpleado));
    }

    private function validarContacto(string ...$fields): array {
        [$nombre, $relacion, $numero, $alternativo, $ayuda, $notas] = array_map('trim', $fields);
        if ($nombre === '' || $relacion === '' || $numero === '') return ['error' => 'Nombre, relación y número son obligatorios'];
        $limits = [200, 120, 80, 255, 255, 2000];
        foreach ([$nombre, $relacion, $numero, $alternativo, $ayuda, $notas] as $i => $value) {
            if (mb_strlen($value) > $limits[$i]) return ['error' => 'Uno de los campos excede la longitud permitida'];
        }
        return compact('nombre', 'relacion', 'numero', 'alternativo', 'ayuda', 'notas');
    }

    private function aplicarContacto(contactoemergencia $contact, array $values, bool $principal): void {
        $contact->setNombre($values['nombre']);
        $contact->setRelacion($values['relacion']);
        $contact->setNumeroContacto($values['numero']);
        $contact->setMedioAlternativo($values['alternativo'] ?: null);
        $contact->setTipoAyuda($values['ayuda'] ?: null);
        $contact->setNotas($values['notas'] ?: null);
        $contact->setEsPrincipal($principal ? 1 : 0);
        $contact->setUpdatedAt(date('Y-m-d H:i:s'));
    }

    private function contactoToArray(contactoemergencia $contact): array {
        return [
            'id' => $contact->getId(), 'id_empleado' => $contact->getIdEmpleado(),
            'nombre' => $contact->getNombre(), 'relacion' => $contact->getRelacion(),
            'numero_contacto' => $contact->getNumeroContacto(), 'medio_alternativo' => $contact->getMedioAlternativo(),
            'tipo_ayuda' => $contact->getTipoAyuda(), 'notas' => $contact->getNotas(),
            'es_principal' => (bool)$contact->getEsPrincipal(), 'orden' => $contact->getOrden(),
        ];
    }

    private function requireHumanResourcesAccess(): void {
        $this->permisosService->requireCanSeeAny([
            'empleados.hr',
            'empleados.admin',
        ]);
    }

	private function normalizarEquipoComputoId(mixed $value): ?int {
		if ($value === null || $value === '') return null;
		if (!is_scalar($value)) return null;
		$id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
		return $id === false ? null : (int)$id;
	}

    /**
     * Convierte fechas de Excel a formato `Y-m-d`.
     */
    private function convertExcelDate($excelDate): string {
        if (empty($excelDate) || trim($excelDate) == 'dd/mm/aaaa') return '';

        return is_numeric($excelDate)
            ? date('Y-m-d', \PhpOffice\PhpSpreadsheet\Shared\Date::excelToTimestamp($excelDate))
            : (strtotime($excelDate) !== false ? date('Y-m-d', strtotime($excelDate)) : '');
    }

    #[UseSession]
    #[NoAdminRequired]
    public function ExportListEmpleados(): DataResponse {
        $this->requireHumanResourcesAccess();
		$empleados = $this->empleadosMapper->GetUserLists();
		
		$books = [[
		'Id_empleados', 
		'Id_user', 
		'Numero_empleado', 
		'Ingreso', 
		'Correo_contacto', 
		'Id_departamento', 
		'Id_puesto', 
		'Id_gerente', 
		'Id_socio', 
		'Fondo_clave',
		'Fondo_ahorro',
        'estado_ahorro',
		'Numero_cuenta', 
		'Equipo_asignado', 
		'Sueldo', 
		'Fecha_nacimiento', 
		'Estado',
		'Direccion',
		'Estado_civil',
		'Telefono_contacto',
		'Curp',
		'Rfc',
		'Imss',
		'Genero',
		'Contacto_emergencia',
		'Numero_emergencia',
        'id_aniversario',
        'dias_disponibles',
		'created_at', 
		'updated_at', 
		]];

		foreach($empleados as $datas){
			array_push(
				$books, 
				[
					$datas['Id_empleados'], 
					$datas['Id_user'], 
					$datas['Numero_empleado'], 
					$datas['Ingreso'], 
					$datas['Correo_contacto'], 
					$datas['Id_departamento'], 
					$datas['Id_puesto'], 
					$datas['Id_gerente'], 
					$datas['Id_socio'], 
					$datas['Fondo_clave'], 
					$datas['Fondo_ahorro'], 
					$datas['state'], 
					$datas['Numero_cuenta'], 
					$datas['Equipo_asignado'], 
					$datas['Sueldo'], 
					$datas['Fecha_nacimiento'], 
					$datas['Estado'],
					$datas['Direccion'],
					$datas['Estado_civil'],
					$datas['Telefono_contacto'],
					$datas['Curp'],
					$datas['Rfc'],
					$datas['Imss'],
					$datas['Genero'],
					$datas['Contacto_emergencia'],
					$datas['Numero_emergencia'],
                    $datas['id_aniversario'],
                    $datas['dias_disponibles'],
					$datas['created_at'], 
					$datas['updated_at'], 
				]);
		}

		$xlsx = \Shuchkin\SimpleXLSXGen::fromArray( $books );
		//$xlsx->saveAs('books.xlsx'); // or downloadAs('books.xlsx') or $xlsx_content = (string) $xlsx 
	
		$fileContent = $xlsx->downloadAs('php://memory');

        return new DataResponse($books ,Http::STATUS_OK);
	}

    /**
     * Obtiene el archivo subido y maneja errores.
     */
    #[UseSession]
    #[NoAdminRequired]
    private function getUploadedFile(string $key): array {
        $file = $this->request->getUploadedFile($key);
        if (empty($file) || ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new \Exception("Error en la subida del archivo.");
        }
        return $file;
    }
    
    /** 
     * Obtiene todos los usuarios de Nextcloud
     */
	#[NoCSRFRequired]
	#[NoAdminRequired]    
	public function GetUsers(): DataResponse {
        $this->requireHumanResourcesAccess();
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

        return new DataResponse([
            'Users' => $userList
        ], Http::STATUS_OK);
	}

    /** 
     * Obtiene la información del empleado actual
     */
    #[NoCSRFRequired]
	#[NoAdminRequired]    
	public function GetMyEmployeeInfo(): DataResponse {
        $this->requireHumanResourcesAccess();
        $user = $this->userSession->getUser();

        return new DataResponse([
            'Empleado' => $this->empleadosMapper->GetMyEmployeeInfo($user->getUID())
        ], Http::STATUS_OK);
	}

    #[UseSession]
    #[NoAdminRequired]
    public function ActualizarEstadoAhorro($id_ahorro, $state): DataResponse {
        $this->requireHumanResourcesAccess();
        $this->userahorroMapper->updatePermisionUserId(
            $id_ahorro, 
            $state,
        );
        return new DataResponse("ok", Http::STATUS_OK);
    }

    #[UseSession]
    #[NoAdminRequired]
    public function GuardarDiasDerecho(int $id_empleado, int $anio, float $dias_derecho): DataResponse {
        $this->checkAccess(['admin', 'recursos_humanos']);
        $this->historialvacacionesMapper->guardar($id_empleado, $anio, $dias_derecho);
        return new DataResponse(Http::STATUS_OK);
    }

    #[UseSession]
    #[NoAdminRequired]
    public function GetDiasDerecho(int $id_empleado, int $anio): DataResponse {
        $this->checkAccess(['admin', 'recursos_humanos']);
        $registro = $this->historialvacacionesMapper->getByEmpleadoYAnio($id_empleado, $anio);
        return new DataResponse(['dias_derecho' => $registro], Http::STATUS_OK);
    }
}
