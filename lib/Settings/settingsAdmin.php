<?php
declare(strict_types=1);

namespace OCA\Empleados\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\Settings\ISettings;
use OCA\Empleados\Db\configuracionesMapper;
use OCA\Empleados\Service\ConfigRepairer;

class settingsAdmin implements ISettings {
    private IL10N $l;
    private IConfig $config;
    private configuracionesMapper $configuracionesMapper;
    private ConfigRepairer $repairer;

    public function __construct(
        IConfig $config,
        IL10N $l,
        configuracionesMapper $configuracionesMapper,
        ConfigRepairer $repairer
    ) {
        $this->config = $config;
        $this->l = $l;
        $this->configuracionesMapper = $configuracionesMapper;
        $this->repairer = $repairer;
    }

    /**
     * @return TemplateResponse
     */
    public function getForm(): TemplateResponse {
        $rows = $this->configuracionesMapper->GetConfig();
        $params = is_array($rows) ? array_column($rows, 'Data', 'Nombre') : [];

        // Verifica si faltan claves, si sí → ejecuta reparación
        $requiredKeys = [
            'usuario_almacenamiento',
            'automatic_save_note',
            'acumular_vacaciones',
            'modulo_ahorro',
            'modulo_ausencias',
            'ausencias_readonly',
        ];
        $missing = array_diff($requiredKeys, array_keys($params));

        if (!empty($missing)) {
            $this->repairer->run();
            // Recarga la configuración tras reparar
            $rows = $this->configuracionesMapper->GetConfig();
            $params = is_array($rows) ? array_column($rows, 'Data', 'Nombre') : [];
        }

        return new TemplateResponse('empleados', 'settings/admin', [
            'config' => $params,
        ], '');
    }

    public function getSection(): string {
        return 'empleados'; // Nombre de la sección creada
    }

    public function getPriority(): int {
        return 1;
    }
}
