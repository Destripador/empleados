<?php

declare(strict_types=1);

namespace OCA\Empleados\Activity;

use OCP\Activity\IProvider;
use OCP\Activity\IEvent;
use OCP\Activity\Exceptions\UnknownActivityException;
use OCP\IURLGenerator;
use OCP\L10N\IFactory as L10NFactory;
use OCA\Empleados\Activity\ActivityExtension;

class ActivityProvider implements IProvider {
    public function __construct(
        private L10NFactory $l10nFactory,
        private IURLGenerator $urlGenerator,
    ) {
    }

    public function parse($language, IEvent $event, ?IEvent $previousEvent = null): IEvent {
        if ($event->getApp() !== 'empleados') {
            throw new UnknownActivityException();
        }

        $subjectID = $event->getSubject();
        $parameters = $event->getSubjectParameters();  // ✅ Ahora sí funciona

        $l10n = $this->l10nFactory->get('empleados', $language);
        $template = $l10n->t($this->getTemplateForSubject($subjectID));
        $subjectFinal = $this->render($template, $parameters);

        $event->setParsedSubject($subjectFinal);
        $event->setParsedMessage($event->getMessage() ?: $l10n->t('New activity registered in the Employees module.'));
        $event->setIcon($this->urlGenerator->getAbsoluteURL(
            $this->urlGenerator->imagePath('empleados', 'app.svg')
        ));
        $event->setLink($this->urlGenerator->linkToRouteAbsolute('empleados.page.index'));

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
			case 'parking_maintenance_activated':
				return '{nombre} activated parking maintenance mode';
			case 'parking_published':
				return '{nombre} published the parking map';
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
