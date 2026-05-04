<?php
declare(strict_types=1);

namespace OCA\Empleados\Controller;

use OCP\AppFramework\OCSController;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\IGroupManager;
use OCA\Empleados\Db\empleadosMapper;
use OCA\Empleados\Db\configuracionesMapper;

abstract class BaseController extends OCSController {

    protected $userSession;
    protected $groupManager;
    protected $configuracionesMapper;
    protected $empleadosMapper;
    protected $configParams = [];

    public function __construct(
        string $appName,
        IRequest $request,
        IUserSession $userSession,
        IGroupManager $groupManager,
        empleadosMapper $empleadosMapper,
        configuracionesMapper $configuracionesMapper
    ) {
        parent::__construct($appName, $request);
        $this->userSession = $userSession;
        $this->groupManager = $groupManager;
        $this->empleadosMapper = $empleadosMapper;
        $this->configuracionesMapper = $configuracionesMapper;
        $this->loadConfigParams(); // 🖥️ Cargar configuración automáticamente
    }

    public function checkAccess($allowedGroups): void {
        $user = $this->userSession->getUser();
        if (!$user) {
            throw new OCSForbiddenException("❌ Debes estar autenticado para acceder a este módulo.");
        }

        // Recomendado: IDs directos para evitar firmas antiguas
        $allowed = is_array($allowedGroups) ? $allowedGroups : [$allowedGroups];
        $userGroupIds = $this->groupManager->getUserGroupIds($user);

        if (!array_intersect($userGroupIds, $allowed)) {
            throw new OCSForbiddenException("🚫 No tienes permiso para acceder a este apartado. Contacta al administrador.");
        }
    }


    public function AdminCheckAccess($flag = true): void {
        if (!$flag) {
            $allowedGroups = ['admin', 'recursos_humanos'];
            $user = $this->userSession->getUser();

            if (!$user) {
                throw new OCSForbiddenException("❌ Debes estar autenticado para acceder a este módulo.");
            }

            $userGroups = $this->groupManager->getUserGroups($user);
            if (!$userGroups || count($userGroups) === 0) {
                throw new OCSForbiddenException("⚠️ No perteneces a ningún grupo permitido para acceder.");
            }

            foreach ($userGroups as $group) {
                $groupId = $group->getGID();
                if ($groupId && in_array($groupId, $allowedGroups)) {
                    return; // ✅ Acceso permitido
                }
            }

            throw new OCSForbiddenException("🚫 No tienes permiso para acceder a este módulo. Contacta al administrador.");
        }
    }

    
    public function GroupCheckAccess(): array {
        $user = $this->userSession->getUser();
        return $this->groupManager->getUserGroups($user);
    }

    /**
     * 🔥 Carga la configuración automáticamente para todos los controladores.
     */
    private function loadConfigParams(): void {
        $configMap = array_column($this->configuracionesMapper->GetConfig(), 'Data', 'Nombre');

        $this->configParams = [
            "usuario_almacenamiento" => $configMap['usuario_almacenamiento'] ?? null,
            "automatic_save_note" => $configMap['automatic_save_note'] ?? null,
            "acumular_vacaciones" => $configMap['acumular_vacaciones'] ?? null,
            "modulo_ahorro" => $configMap['modulo_ahorro'] ?? null,
            "modulo_ausencias" => $configMap['modulo_ausencias'] ?? null,
            "modulo_ausencias_readonly" => $configMap['ausencias_readonly'] ?? null,
            "modulo_clientes" => $configMap['modulo_clientes'] ?? null,
            "modulo_reporte_tiempos" => $configMap['modulo_reporte_tiempos'] ?? null,
            "modulo_inventario" => $configMap['modulo_inventario'] ?? null,
            "modulo_soporte" => $configMap['modulo_soporte'] ?? null,
        ];
    }

    /**
     * 📌 Permite que los controladores accedan a la configuración cargada.
     */
    public function getConfigParams(): array {
        return $this->configParams;
    }

    /**
     * 📌 Permite que los controladores accedan los datos del empleado actual.
     */
    public function getEmployeeInfo(): array {
        $user = $this->userSession->getUser();
        return $this->empleadosMapper->GetMyEmployeeInfo($user->getUID());
    }

    /**
     * 📌 Permite busacr si el empleado actual cuenta tiene subordinados
     */
    public function GetSubordinates(): array {
        $user = $this->userSession->getUser();
        return $this->empleadosMapper->GetSubordinates($user->getUID());
    }
}
