<?php

declare(strict_types=1);

namespace OCA\Empleados\Cron;

use OCA\Empleados\Db\empleadosMapper;
use OCA\Empleados\Db\historialausenciasMapper;
use OCA\Empleados\Db\tipoausenciaMapper;
use OCA\Empleados\Helper\MailHelper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IGroupManager;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

/**
 * Recordatorio diario a los aprobadores (gerente / socio / supervisor / RH)
 * que AÚN NO han confirmado una solicitud de ausencia. Solo se notifica al
 * rol que falta por aprobar; si alguno ya aprobó, no vuelve a recibir correo.
 */
class RecordatorioAprobadoresPendientes extends TimedJob {

    private historialausenciasMapper $historialausenciasMapper;
    private empleadosMapper $empleadosMapper;
    private tipoausenciaMapper $tipoausenciaMapper;
    private IUserManager $userManager;
    private IGroupManager $groupManager;
    private MailHelper $mailHelper;
    private LoggerInterface $logger;

    public function __construct(
        ITimeFactory $time,
        historialausenciasMapper $historialausenciasMapper,
        empleadosMapper $empleadosMapper,
        tipoausenciaMapper $tipoausenciaMapper,
        IUserManager $userManager,
        IGroupManager $groupManager,
        MailHelper $mailHelper,
        LoggerInterface $logger
    ) {
        parent::__construct($time);

        // Se ejecuta 1 vez al día.
        $this->setInterval(24 * 60 * 60);

        $this->historialausenciasMapper = $historialausenciasMapper;
        $this->empleadosMapper = $empleadosMapper;
        $this->tipoausenciaMapper = $tipoausenciaMapper;
        $this->userManager = $userManager;
        $this->groupManager = $groupManager;
        $this->mailHelper = $mailHelper;
        $this->logger = $logger;
    }

    protected function run($argument): void {
        $this->logger->warning('RecordatorioAprobadoresPendientes iniciado.', ['app' => 'empleados']);

        // Reutilizamos el método existente del mapper: ya filtra
        // "pendientes" (no rechazadas/canceladas y no 100% aprobadas)
        // y trae id_empleado por el JOIN con la tabla empleados.
        $pendientes = $this->historialausenciasMapper->GetAusenciasHistorialCapitalHumano();

        $this->logger->warning('Solicitudes pendientes encontradas.', [
            'app' => 'empleados',
            'total' => count($pendientes),
        ]);

        // Cache de correos de RH para no repetir la consulta en cada vuelta.
        $usuariosRH = null;

        foreach ($pendientes as $ausencia) {
            $idEmpleado = $ausencia['id_empleado'] ?? null;
            if (empty($idEmpleado)) {
                continue;
            }

            $empleadoInfo = $this->empleadosMapper->GetMyEmployeeInfoByIdEmpleado((string) $idEmpleado);
            if (empty($empleadoInfo)) {
                continue;
            }

            $tipo = $this->tipoausenciaMapper->getTipoById($ausencia['id_tipo_ausencia']);
            $nombreTipo = $tipo[0]['nombre'] ?? 'Ausencia';

            // Nombre del empleado que solicitó (para mostrarlo en el correo)
            $uidEmpleadoSolicitante = $ausencia['nombre_empleado'] ?? ($empleadoInfo[0]['Id_user'] ?? null);
            $userEmpleadoSolicitante = $uidEmpleadoSolicitante ? $this->userManager->get($uidEmpleadoSolicitante) : null;
            $nombreEmpleadoSolicitante = $userEmpleadoSolicitante
                ? $userEmpleadoSolicitante->getDisplayName()
                : ($empleadoInfo[0]['Nombre'] ?? ($uidEmpleadoSolicitante ?? 'Empleado'));

            $idGerente = $empleadoInfo[0]['Id_gerente'] ?? null;
            $idSocio = $empleadoInfo[0]['Id_socio'] ?? null;
            $idSupervisor = $empleadoInfo[0]['Id_supervisor'] ?? null;
            $tieneSupervisor = !empty($idSupervisor);

            $gerenteOk = (int) ($ausencia['a_gerente'] ?? 0) === 1;
            $socioOk = (int) ($ausencia['a_socio'] ?? 0) === 1;
            $supervisorOk = !$tieneSupervisor || (int) ($ausencia['a_supervisor'] ?? 0) === 1;
            $capitalHumanoOk = (int) ($ausencia['a_capital_humano'] ?? 0) === 1;

            // Destinatarios pendientes: uid => rol (solo para el log)
            $pendientesPorRol = [];

            if (!$gerenteOk && !empty($idGerente)) {
                $pendientesPorRol[$idGerente] = 'gerente';
            }

            if (!$socioOk && !empty($idSocio) && $idSocio !== $idGerente) {
                $pendientesPorRol[$idSocio] = $pendientesPorRol[$idSocio] ?? 'socio';
            }

            if ($tieneSupervisor && !$supervisorOk && $idSupervisor !== $idGerente && $idSupervisor !== $idSocio) {
                $pendientesPorRol[$idSupervisor] = $pendientesPorRol[$idSupervisor] ?? 'supervisor';
            }

            if (!$capitalHumanoOk) {
                if ($usuariosRH === null) {
                    $usuariosRH = $this->obtenerUsuariosRH();
                }
                foreach ($usuariosRH as $uidRH) {
                    // Si ya recibe recordatorio por ser gerente/socio/supervisor,
                    // no lo duplicamos como RH.
                    if (!isset($pendientesPorRol[$uidRH])) {
                        $pendientesPorRol[$uidRH] = 'recursos_humanos';
                    }
                }
            }

            foreach ($pendientesPorRol as $uidAprobador => $rol) {
                $this->enviarRecordatorio($uidAprobador, $rol, $ausencia, $nombreTipo, $nombreEmpleadoSolicitante);
            }
        }

        $this->logger->warning('RecordatorioAprobadoresPendientes finalizado.', ['app' => 'empleados']);
    }

    /**
     * @return string[] UIDs de todos los usuarios del grupo recursos_humanos
     */
    private function obtenerUsuariosRH(): array {
        $grupo = $this->groupManager->get('recursos_humanos');
        if (!$grupo) {
            return [];
        }
        return array_map(fn($u) => $u->getUID(), $grupo->getUsers());
    }

    private function enviarRecordatorio(string $uid, string $rol, array $ausencia, string $nombreTipo, string $nombreEmpleado): void {
        $user = $this->userManager->get($uid);
        if (!$user) {
            return;
        }

        $mail = $user->getEMailAddress();
        if (!$mail) {
            return;
        }

        $this->logger->warning('Enviando recordatorio de aprobación pendiente.', [
            'app' => 'empleados',
            'uid_aprobador' => $uid,
            'rol' => $rol,
            'id_historial_ausencias' => $ausencia['id_historial_ausencias'] ?? null,
        ]);

        $this->mailHelper->enviarCorreo(
            $mail,
            'Recordatorio: solicitud de ausencia pendiente',
            [
                'Hola ' . $user->getDisplayName(),
                'El empleado ' . $nombreEmpleado . ' tiene una solicitud de "' . $nombreTipo . '" pendiente de tu aprobación.',
                'Fecha de inicio: ' . $ausencia['fecha_de'] . '  - Fecha de finalización: ' . $ausencia['fecha_hasta'] . '',
                '',
            ]
        );
    }
}