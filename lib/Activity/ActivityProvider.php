<?php

declare(strict_types=1);

namespace OCA\Empleados\Activity;

use OCP\Activity\IProvider;
use OCP\Activity\IEvent;
use OCP\Activity\Exceptions\UnknownActivityException;
use OCA\Empleados\Activity\ActivityExtension;

class ActivityProvider implements IProvider {
    public function parse($language, IEvent $event, ?IEvent $previousEvent = null): IEvent {
        if ($event->getApp() !== 'empleados') {
            throw new UnknownActivityException();
        }

        $subjectID = $event->getSubject();
        $parameters = $event->getSubjectParameters();  // ✅ Ahora sí funciona

        $template = $this->getTemplateForSubject($subjectID);
        $subjectFinal = $this->render($template, $parameters);

        $event->setParsedSubject($subjectFinal);
        $event->setParsedMessage($event->getMessage() ?: 'Nueva actividad registrada en el módulo de empleados.');
        $event->setIcon('avatar/luis/64?v=29');
        $event->setLink('/apps/empleados/');

        return $event;
    }

    /**
     * Retorna la plantilla correspondiente según el ID del subject.
     */
    private function getTemplateForSubject(string $subjectID): string {
        switch ($subjectID) {
            case 'ausencia_registrada':
                return '{nombre} ha solicitado "{tipo_ausencia}"';
            case 'ausencia_aprobada':
                return 'La solicitud de "{tipo_ausencia}" de {nombre} fue aprobada';
            case 'ausencia_rechazada':
                return 'La solicitud de "{tipo_ausencia}" de {nombre} fue rechazada';
            case 'test':
                return '{nombre} ha realizado una prueba actualizado';
            // Puedes seguir agregando casos aquí.
            default:
                return $subjectID; // En caso de no tener plantilla, usa el ID literal como fallback.
        }
    }

    /**
     * Reemplaza los placeholders de la plantilla.
     */
    private function render(string $template, array $parameters): string {
        foreach ($parameters as $key => $value) {
            $template = str_replace(
                '{' . $key . '}',
                $value,  // Parámetro simple
                $template
            );
        }
        return $template;
    }

    public function getID(): string {
        return 'empleados';
    }

    public function getName(): string {
        return 'Gestor de Empleados';
    }

    public function getTypes(): array {
        return ['empleados'];
    }
}