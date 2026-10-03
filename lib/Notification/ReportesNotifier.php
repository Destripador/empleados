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

			case 'ausencia_solicitada':
				$params = $notification->getSubjectParameters();

				$subject = $l->t('Nueva solicitud de ausencia por aprobar');
				$message = $l->t(
					'%1$s solicitó "%2$s" del %3$s al %4$s.',
					[
						$params['nombre_empleado'] ?? '',
						$params['tipo_ausencia'] ?? '',
						$params['fecha_de'] ?? '',
						$params['fecha_hasta'] ?? '',
					]
				);

				$notification
					->setParsedSubject($subject)
					->setParsedMessage($message)
					->setIcon(
						$this->urlGenerator->getAbsoluteURL(
							$this->urlGenerator->imagePath(Application::APP_ID, 'app.svg')
						)
					)
					->setLink(
						$this->urlGenerator->linkToRouteAbsolute('empleados.page.index') . '#/ausencias-pendientes'
					);

				return $notification;

			case 'ausencia_aprobada_parcial':
				$params = $notification->getSubjectParameters();

				$rolTexto = match ($params['rol'] ?? '') {
					'gerente' => $l->t('tu gerente'),
					'socio' => $l->t('el socio'),
					'supervisor' => $l->t('tu supervisor'),
					'capital_humano_como_socio' => $l->t('recursos humanos'),
					default => $l->t('un aprobador'),
				};

				$subject = $l->t('Tu solicitud avanzó de aprobación');
				$message = $l->t(
					'%1$s aprobó tu solicitud de "%2$s" del %3$s al %4$s. Aún falta la aprobación de los demás.',
					[
						ucfirst($rolTexto),
						$params['tipo_ausencia'] ?? '',
						$params['fecha_de'] ?? '',
						$params['fecha_hasta'] ?? '',
					]
				);

				$notification
					->setParsedSubject($subject)
					->setParsedMessage($message)
					->setIcon(
						$this->urlGenerator->getAbsoluteURL(
							$this->urlGenerator->imagePath(Application::APP_ID, 'app.svg')
						)
					)
					->setLink(
						$this->urlGenerator->linkToRouteAbsolute('empleados.page.index') . '#/mis-ausencias'
					);

				return $notification;

			case 'ausencia_aprobada_completa':
				$params = $notification->getSubjectParameters();

				$subject = $l->t('Tu solicitud fue aprobada por completo');
				$message = $l->t(
					'Tu solicitud de "%1$s" del %2$s al %3$s ha sido aprobada por todos.',
					[
						$params['tipo_ausencia'] ?? '',
						$params['fecha_de'] ?? '',
						$params['fecha_hasta'] ?? '',
					]
				);

				$notification
					->setParsedSubject($subject)
					->setParsedMessage($message)
					->setIcon(
						$this->urlGenerator->getAbsoluteURL(
							$this->urlGenerator->imagePath(Application::APP_ID, 'app.svg')
						)
					)
					->setLink(
						$this->urlGenerator->linkToRouteAbsolute('empleados.page.index') . '#/mis-ausencias'
					);

				return $notification;

			case 'ausencia_rechazada':
				$params = $notification->getSubjectParameters();

				$subject = $l->t('Tu solicitud fue rechazada');
				$motivo = trim((string) ($params['motivo'] ?? ''));

				$message = $motivo !== ''
					? $l->t(
						'Tu solicitud de "%1$s" del %2$s al %3$s fue rechazada. Motivo: %4$s',
						[
							$params['tipo_ausencia'] ?? '',
							$params['fecha_de'] ?? '',
							$params['fecha_hasta'] ?? '',
							$motivo,
						]
					)
					: $l->t(
						'Tu solicitud de "%1$s" del %2$s al %3$s fue rechazada.',
						[
							$params['tipo_ausencia'] ?? '',
							$params['fecha_de'] ?? '',
							$params['fecha_hasta'] ?? '',
						]
					);

				$notification
					->setParsedSubject($subject)
					->setParsedMessage($message)
					->setIcon(
						$this->urlGenerator->getAbsoluteURL(
							$this->urlGenerator->imagePath(Application::APP_ID, 'app.svg')
						)
					)
					->setLink(
						$this->urlGenerator->linkToRouteAbsolute('empleados.page.index') . '#/mis-ausencias'
					);

				return $notification;

			case 'ausencia_cancelada':
				$params = $notification->getSubjectParameters();

				$subject = $l->t('Una solicitud de ausencia fue cancelada');
				$message = $l->t(
					'%1$s canceló su solicitud de "%2$s" del %3$s al %4$s.',
					[
						$params['nombre_empleado'] ?? '',
						$params['tipo_ausencia'] ?? '',
						$params['fecha_de'] ?? '',
						$params['fecha_hasta'] ?? '',
					]
				);

				$notification
					->setParsedSubject($subject)
					->setParsedMessage($message)
					->setIcon(
						$this->urlGenerator->getAbsoluteURL(
							$this->urlGenerator->imagePath(Application::APP_ID, 'app.svg')
						)
					)
					->setLink(
						$this->urlGenerator->linkToRouteAbsolute('empleados.page.index') . '#/ausencias-pendientes'
					);

				return $notification;

			case 'ausencia_recordatorio_aprobacion':
				$params = $notification->getSubjectParameters();

				$subject = $l->t('Recordatorio: solicitud de ausencia pendiente');
				$message = $l->t(
					'%1$s tiene una solicitud de "%2$s" del %3$s al %4$s esperando tu aprobación.',
					[
						$params['nombre_empleado'] ?? '',
						$params['tipo_ausencia'] ?? '',
						$params['fecha_de'] ?? '',
						$params['fecha_hasta'] ?? '',
					]
				);

				$notification
					->setParsedSubject($subject)
					->setParsedMessage($message)
					->setIcon(
						$this->urlGenerator->getAbsoluteURL(
							$this->urlGenerator->imagePath(Application::APP_ID, 'app.svg')
						)
					)
					->setLink(
						$this->urlGenerator->linkToRouteAbsolute('empleados.page.index') . '#/ausencias-pendientes'
					);

				return $notification;

			default:
				throw new UnknownNotificationException();
		}
	}
}