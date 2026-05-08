<?php

declare(strict_types=1);

namespace OCA\Empleados\Notification;

use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;

class ComprasNotifier implements INotifier {

	private IFactory $l10nFactory;
	private IURLGenerator $urlGenerator;

	public function __construct(
		IFactory $l10nFactory,
		IURLGenerator $urlGenerator
	) {
		$this->l10nFactory = $l10nFactory;
		$this->urlGenerator = $urlGenerator;
	}

	public function getID(): string {
		return 'empleados';
	}

	public function getName(): string {
		return 'Empleados';
	}

	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== 'empleados') {
			throw new UnknownNotificationException();
		}

		$l = $this->l10nFactory->get('empleados', $languageCode);

		if ($notification->getSubject() === 'compra_pendiente_autorizacion') {
			return $this->prepareCompraPendiente($notification, $l);
		}

		throw new UnknownNotificationException();
	}

	private function prepareCompraPendiente(INotification $notification, IL10N $l): INotification {
		$params = $notification->getSubjectParameters();

		$folio = (string)($params['folio'] ?? '');
		$titulo = (string)($params['titulo'] ?? '');
		$solicitante = (string)($params['solicitante'] ?? '');

		$notification->setParsedSubject(
			$l->t('Purchase request pending approval')
		);

		$notification->setParsedMessage(
			$l->t('%s - %s was sent by %s and is waiting for approval.', [
				$folio,
				$titulo,
				$solicitante,
			])
		);

		$notification->setIcon(
			$this->urlGenerator->imagePath('empleados', 'app.svg')
		);

		return $notification;
	}
}