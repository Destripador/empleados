<?php
namespace OCA\Empleados\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\Settings\ISettings;


class settingsAdmin implements ISettings {
    private IL10N $l;
    private IConfig $config;

    public function __construct(IConfig $config, IL10N $l) {
        $this->config = $config;
        $this->l = $l;
    }

    /**
     * @return TemplateResponse
     */
    public function getForm() {
        
        $parameters = [
            'mySetting' => $this->config->getSystemValue('empleados', true),
        ];

        return new TemplateResponse('empleados', 'settings/admin', $parameters, '');
    }

    public function getSection() {
        return 'empleados'; // Name of the previously created section.
    }

    public function getPriority() {
        return  1;
    }
}

