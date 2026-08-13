<?php

declare(strict_types=1);

namespace OCA\Empleados\Notification;

use OCA\Empleados\AppInfo\Application;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;

class ReportesNotifier implements INotifier {

	public function __construct(
		private IFactory $l10nFactory,
		private IURLGenerator $urlGenerator,
	) {
	}

	public function getID(): string {
		return Application::APP_ID;
	}

	public function getName(): string {
		return $this->l10nFactory
			->get(Application::APP_ID)
			->t('Empleados');
	}

	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== Application::APP_ID) {
			throw new UnknownNotificationException();
		}

		$l = $this->l10nFactory->get(Application::APP_ID, $languageCode);

		switch ($notification->getSubject()) {
			case 'tiempo_pendiente':
				$params = $notification->getSubjectParameters();

				$horasReportadas = (float)($params['horas_reportadas'] ?? 0);
				$horasMinimas = (float)($params['horas_minimas'] ?? 0);

				if ($horasMinimas > 0) {
					$subject = $l->t('Tienes pendiente completar tu reporte de tiempo');
					$message = $l->t(
						'Llevas %s horas reportadas. La meta mínima configurada es de %s horas.',
						[
							number_format($horasReportadas, 2),
							number_format($horasMinimas, 2),
						]
					);
				} else {
					$subject = $l->t('Tienes pendiente registrar tu tiempo de hoy');
					$message = $l->t('Aún no tienes reportes de tiempo registrados el día de hoy.');
				}

				$notification
					->setParsedSubject($subject)
					->setParsedMessage($message)
					->setIcon(
						$this->urlGenerator->getAbsoluteURL(
							$this->urlGenerator->imagePath(Application::APP_ID, 'app.svg')
						)
					)
					->setLink(
						$this->urlGenerator->linkToRouteAbsolute('empleados.page.index') . '#/quick-report'
					);

				return $notification;

			default:
				throw new UnknownNotificationException();
		}
	}
}