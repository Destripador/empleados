<?php

namespace OCA\Empleados\Activity;

use OCP\Activity\IExtension;

class ActivityExtension implements IExtension {

    public function getNotificationTypes(string $language): array {
        return [
            [
                'id' => 'empleados',
                'desc' => 'Notificaciones del módulo Empleados',
            ],
        ];
    }

    public function getDefaultTypes(): array {
        // Indica que por defecto las notificaciones de este tipo deben enviarse por correo
        return ['stream', 'email'];
    }
}
