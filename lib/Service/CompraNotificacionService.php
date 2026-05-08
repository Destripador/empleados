<?php

declare(strict_types=1);

namespace OCA\Empleados\Service;

use DateTime;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IURLGenerator;
use OCP\Mail\IMailer;
use OCP\Notification\IManager as INotificationManager;
use Psr\Log\LoggerInterface;
use Throwable;

class CompraNotificacionService {

	private CompraPermisosService $permisosService;
	private IGroupManager $groupManager;
	private INotificationManager $notificationManager;
	private IMailer $mailer;
	private IURLGenerator $urlGenerator;
	private IConfig $config;
	private LoggerInterface $logger;

	public function __construct(
		CompraPermisosService $permisosService,
		IGroupManager $groupManager,
		INotificationManager $notificationManager,
		IMailer $mailer,
		IURLGenerator $urlGenerator,
		IConfig $config,
		LoggerInterface $logger
	) {
		$this->permisosService = $permisosService;
		$this->groupManager = $groupManager;
		$this->notificationManager = $notificationManager;
		$this->mailer = $mailer;
		$this->urlGenerator = $urlGenerator;
		$this->config = $config;
		$this->logger = $logger;
	}

	public function notificarPendienteAutorizacion($solicitud, string $triggerUserId): void {
		$approvers = $this->getApproverUsers($triggerUserId);

		if (count($approvers) === 0) {
			return;
		}

		$idSolicitud = (string)$this->readGetter($solicitud, 'getIdSolicitud', '');
		$folio = (string)$this->readGetter($solicitud, 'getFolio', 'Solicitud');
		$titulo = (string)$this->readGetter($solicitud, 'getTitulo', '');
		$solicitante = (string)$this->readGetter($solicitud, 'getSolicitanteNombre', $triggerUserId);
		$monto = (string)$this->readGetter($solicitud, 'getTotalInclIva', '');

		$link = $this->urlGenerator->getAbsoluteURL('/index.php/apps/empleados/#/compras');

		foreach ($approvers as $uid => $user) {
			$this->sendNextcloudNotification(
				$uid,
				$idSolicitud,
				$folio,
				$titulo,
				$solicitante,
				$link
			);

			$this->sendMailNotification(
				$user,
				$folio,
				$titulo,
				$solicitante,
				$monto,
				$link
			);
		}
	}

	private function getApproverUsers(string $excludeUserId): array {
		$users = [];

		foreach ($this->permisosService->getApproverGroupIds() as $groupId) {
			$group = $this->groupManager->get($groupId);

			if ($group === null) {
				continue;
			}

			foreach ($group->getUsers() as $user) {
				if (!$user->isEnabled()) {
					continue;
				}

				$uid = $user->getUID();

				if ($uid === $excludeUserId) {
					continue;
				}

				$users[$uid] = $user;
			}
		}

		return $users;
	}

	private function sendNextcloudNotification(
		string $uid,
		string $idSolicitud,
		string $folio,
		string $titulo,
		string $solicitante,
		string $link
	): void {
		try {
			$notification = $this->notificationManager->createNotification();

			$notification
				->setApp('empleados')
				->setUser($uid)
				->setDateTime(new DateTime())
				->setObject('compra_solicitud', $idSolicitud)
				->setSubject('compra_pendiente_autorizacion', [
					'folio' => $folio,
					'titulo' => $titulo,
					'solicitante' => $solicitante,
				])
				->setLink($link);

			$this->notificationManager->notify($notification);
		} catch (Throwable $e) {
			$this->logger->warning('No se pudo enviar notificación de compra pendiente.', [
				'app' => 'empleados',
				'user' => $uid,
				'exception' => $e,
			]);
		}
	}

	private function sendMailNotification(
		$user,
		string $folio,
		string $titulo,
		string $solicitante,
		string $monto,
		string $link
	): void {
		$email = $user->getEMailAddress();

		if (!$email) {
			return;
		}

		try {
			$fromLocal = $this->config->getSystemValueString('mail_from_address', 'no-reply');
			$fromDomain = $this->config->getSystemValueString('mail_domain', 'localhost');
			$from = $fromLocal . '@' . $fromDomain;

			$subject = sprintf('Solicitud de compra pendiente: %s', $folio);

			$body = "Hay una solicitud de compra pendiente de autorización.\n\n"
				. "Folio: {$folio}\n"
				. "Título: {$titulo}\n"
				. "Solicitante: {$solicitante}\n"
				. ($monto !== '' ? "Monto: {$monto}\n" : '')
				. "\nRevisar en:\n{$link}\n";

			$message = $this->mailer->createMessage();
			$message->setFrom([$from => 'Empleados']);
			$message->setTo([$email => $user->getDisplayName()]);
			$message->setSubject($subject);
			$message->setPlainBody($body);

			$this->mailer->send($message);
		} catch (Throwable $e) {
			$this->logger->warning('No se pudo enviar correo de compra pendiente.', [
				'app' => 'empleados',
				'user' => $user->getUID(),
				'mail' => $email,
				'exception' => $e,
			]);
		}
	}

	private function readGetter($object, string $getter, $default = null) {
		if (is_object($object) && method_exists($object, $getter)) {
			return $object->$getter();
		}

		return $default;
	}
}