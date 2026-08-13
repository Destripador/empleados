<?php

declare(strict_types=1);

namespace OCA\Empleados\Service;

use DateTime;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Mail\IMailer;
use OCP\Notification\IManager as INotificationManager;
use Psr\Log\LoggerInterface;
use Throwable;
use OCP\IUserManager;

class CompraNotificacionService {

	private CompraPermisosService $permisosService;
	private IGroupManager $groupManager;
	private INotificationManager $notificationManager;
	private IMailer $mailer;
	private IURLGenerator $urlGenerator;
	private IConfig $config;
	private LoggerInterface $logger;
	private IUserManager $userManager;

	public function __construct(
		CompraPermisosService $permisosService,
		IGroupManager $groupManager,
		IUserManager $userManager,
		INotificationManager $notificationManager,
		IMailer $mailer,
		IURLGenerator $urlGenerator,
		IConfig $config,
		LoggerInterface $logger
	) {
		$this->permisosService = $permisosService;
		$this->groupManager = $groupManager;
		$this->userManager = $userManager;
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

		$idSolicitud = (string)$this->readSolicitudValue(
			$solicitud,
			['getIdSolicitud', 'getId'],
			['id_solicitud', 'id'],
			''
		);

		$folio = (string)$this->readSolicitudValue(
			$solicitud,
			['getFolio'],
			['folio'],
			''
		);

		if ($folio === '') {
			$folio = $idSolicitud !== '' ? 'Solicitud #' . $idSolicitud : 'Solicitud';
		}

		$titulo = (string)$this->readSolicitudValue(
			$solicitud,
			['getTitulo', 'getTitle'],
			['titulo', 'title'],
			''
		);

		if ($titulo === '') {
			$titulo = 'Sin título';
		}

		$solicitante = (string)$this->readSolicitudValue(
			$solicitud,
			['getSolicitanteNombre'],
			['solicitante_nombre', 'requester_name', 'displayname', 'created_by', 'id_user'],
			$triggerUserId
		);

		$monto = (string)$this->readSolicitudValue(
			$solicitud,
			['getTotalInclIva', 'getMontoFinal', 'getMontoEstimado'],
			['total_incl_iva', 'monto_final', 'monto_estimado'],
			''
		);

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

	public function notificarAprobadorActual($solicitud, string $approverUid, string $role): void {
		$user = $this->userManager->get($approverUid);
		if ($user === null || !$user->isEnabled()) {
			$this->logger->warning('No se pudo notificar al aprobador actual de la compra.', [
				'app' => 'empleados',
				'user' => $approverUid,
				'role' => $role,
			]);
			return;
		}

		$idSolicitud = (string)$this->readSolicitudValue(
			$solicitud,
			['getIdSolicitud', 'getId'],
			['id_solicitud', 'id'],
			''
		);
		$folio = (string)$this->readSolicitudValue($solicitud, ['getFolio'], ['folio'], '');
		$folio = $folio !== '' ? $folio : ($idSolicitud !== '' ? 'Solicitud #' . $idSolicitud : 'Solicitud');
		$titulo = (string)$this->readSolicitudValue($solicitud, ['getTitulo'], ['titulo'], 'Sin título');
		$solicitante = (string)$this->readSolicitudValue(
			$solicitud,
			['getSolicitanteNombre'],
			['solicitante_nombre', 'id_user'],
			'Solicitante'
		);
		$monto = (string)$this->readSolicitudValue(
			$solicitud,
			['getTotalInclIva', 'getMontoFinal', 'getMontoEstimado'],
			['total_incl_iva', 'monto_final', 'monto_estimado'],
			''
		);
		$link = $this->urlGenerator->getAbsoluteURL('/index.php/apps/empleados/#/compras');

		$this->sendNextcloudNotification(
			$approverUid,
			$idSolicitud,
			$folio,
			$titulo,
			$solicitante,
			$link,
			$role
		);
		$this->sendMailNotification($user, $folio, $titulo, $solicitante, $monto, $link);
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
		string $link,
		string $role = ''
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
					'rol' => $role,
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
		IUser $user,
		string $folio,
		string $titulo,
		string $solicitante,
		string $monto,
		string $link
	): void {
		$email = $user->getEMailAddress();

		if ($email === null || $email === '') {
			return;
		}

		try {
			$fromLocal = $this->config->getSystemValueString('mail_from_address', 'no-reply');
			$fromDomain = $this->config->getSystemValueString('mail_domain', 'localhost');
			$from = $fromLocal . '@' . $fromDomain;

			$subject = sprintf('Solicitud de compra pendiente: %s', $folio);

			$montoTexto = '';
			if ($monto !== '') {
				$montoTexto = is_numeric($monto)
					? '$' . number_format((float)$monto, 2, '.', ',') . ' MXN'
					: $monto;
			}

			$folioHtml = $this->escapeHtml($folio);
			$tituloHtml = $this->escapeHtml($titulo);
			$solicitanteHtml = $this->escapeHtml($solicitante);
			$montoHtml = $this->escapeHtml($montoTexto);
			$linkHtml = $this->escapeHtml($link);

			$detallePlain = "Folio: {$folio}\n"
				. "Título: {$titulo}\n"
				. "Solicitante: {$solicitante}\n"
				. ($montoTexto !== '' ? "Monto: {$montoTexto}\n" : '');

			$montoHtmlRow = $montoTexto !== ''
				? '<span style="display:block; margin-top:8px;">
						<strong>Monto:</strong> ' . $montoHtml . '
					</span>'
				: '';

			$detalleHtml = '
				<span style="
					display:block;
					max-width:440px;
					margin:14px auto 8px auto;
					padding:16px 18px;
					border:1px solid #e5e7eb;
					border-radius:10px;
					background:#f8f9fa;
					text-align:left;
					line-height:1.5;
					color:#222;
				">
					<span style="display:block; margin-bottom:8px;">
						<strong>Folio:</strong> ' . $folioHtml . '
					</span>

					<span style="display:block; margin-bottom:8px;">
						<strong>Título:</strong> ' . $tituloHtml . '
					</span>

					<span style="display:block; margin-bottom:8px;">
						<strong>Solicitante:</strong> ' . $solicitanteHtml . '
					</span>

					' . $montoHtmlRow . '
				</span>
			';

			$emailTemplate = $this->mailer->createEMailTemplate('empleados.CompraPendienteAutorizacion', [
				'folio' => $folio,
				'titulo' => $titulo,
				'solicitante' => $solicitante,
				'monto' => $montoTexto,
				'link' => $link,
			]);

			$emailTemplate->setSubject($subject);
			$emailTemplate->addHeader();

			$emailTemplate->addHeading('Solicitud de compra pendiente');

			$emailTemplate->addBodyText(
				'Hay una nueva solicitud de compra pendiente de autorización.',
				'Hay una nueva solicitud de compra pendiente de autorización.'
			);

			$emailTemplate->addBodyText($detalleHtml, $detallePlain);

			$emailTemplate->addBodyText(
				'Revisa la solicitud desde el módulo de compras.',
				'Revisa la solicitud desde el módulo de compras.'
			);

			$emailTemplate->addBodyButton('Revisar solicitud', $linkHtml);

			$emailTemplate->addFooter();

			$message = $this->mailer->createMessage();
			$message->setFrom([$from => 'Empleados']);
			$message->setTo([$email => $user->getDisplayName()]);
			$message->useTemplate($emailTemplate);

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
	public function notificarSolicitudAutorizada($solicitud, string $aprobadorUserId, ?string $comentario = null): void {
		$requesterUid = (string)$this->readSolicitudValue(
			$solicitud,
			['getIdUser'],
			['id_user', 'created_by', 'requester_uid'],
			''
		);

		if ($requesterUid === '') {
			$this->logger->warning('No se pudo notificar autorización de compra porque la solicitud no tiene usuario solicitante.', [
				'app' => 'empleados',
				'aprobador' => $aprobadorUserId,
			]);

			return;
		}

		$user = $this->userManager->get($requesterUid);

		if ($user === null || !$user->isEnabled()) {
			$this->logger->warning('No se pudo notificar autorización de compra porque el usuario solicitante no existe o está deshabilitado.', [
				'app' => 'empleados',
				'user' => $requesterUid,
				'aprobador' => $aprobadorUserId,
			]);

			return;
		}

		$idSolicitud = (string)$this->readSolicitudValue(
			$solicitud,
			['getIdSolicitud', 'getId'],
			['id_solicitud', 'id'],
			''
		);

		$folio = (string)$this->readSolicitudValue(
			$solicitud,
			['getFolio'],
			['folio'],
			''
		);

		if ($folio === '') {
			$folio = $idSolicitud !== '' ? 'Solicitud #' . $idSolicitud : 'Solicitud';
		}

		$titulo = (string)$this->readSolicitudValue(
			$solicitud,
			['getTitulo'],
			['titulo'],
			''
		);

		if ($titulo === '') {
			$titulo = 'Sin título';
		}

		$monto = (string)$this->readSolicitudValue(
			$solicitud,
			['getTotalInclIva', 'getMontoFinal', 'getMontoEstimado'],
			['total_incl_iva', 'monto_final', 'monto_estimado'],
			''
		);

		$link = $this->urlGenerator->getAbsoluteURL('/index.php/apps/empleados/#/compras');

		$this->sendNextcloudStatusNotification(
			$requesterUid,
			$idSolicitud,
			'compra_solicitud_autorizada',
			[
				'folio' => $folio,
				'titulo' => $titulo,
				'aprobador' => $aprobadorUserId,
				'comentario' => $comentario ?: '',
			],
			$link
		);

		$this->sendMailStatusNotification(
			$user,
			'Solicitud de compra autorizada',
			sprintf('Tu solicitud de compra fue autorizada: %s', $folio),
			$folio,
			$titulo,
			$monto,
			$aprobadorUserId,
			$comentario,
			$link,
			'Tu solicitud de compra fue autorizada.'
		);
	}

	public function notificarSolicitudRechazada($solicitud, string $aprobadorUserId, ?string $comentario = null): void {
		$requesterUid = (string)$this->readSolicitudValue(
			$solicitud,
			['getIdUser'],
			['id_user', 'created_by'],
			''
		);
		$user = $requesterUid !== '' ? $this->userManager->get($requesterUid) : null;
		if ($user === null || !$user->isEnabled()) {
			$this->logger->warning('No se pudo notificar el rechazo de la solicitud de compra.', [
				'app' => 'empleados',
				'user' => $requesterUid,
			]);
			return;
		}

		$idSolicitud = (string)$this->readSolicitudValue($solicitud, ['getIdSolicitud'], ['id_solicitud'], '');
		$folio = (string)$this->readSolicitudValue($solicitud, ['getFolio'], ['folio'], 'Solicitud');
		$titulo = (string)$this->readSolicitudValue($solicitud, ['getTitulo'], ['titulo'], 'Sin título');
		$monto = (string)$this->readSolicitudValue(
			$solicitud,
			['getTotalInclIva', 'getMontoFinal', 'getMontoEstimado'],
			['total_incl_iva', 'monto_final', 'monto_estimado'],
			''
		);
		$link = $this->urlGenerator->getAbsoluteURL('/index.php/apps/empleados/#/compras');

		$this->sendNextcloudStatusNotification(
			$requesterUid,
			$idSolicitud,
			'compra_solicitud_rechazada',
			[
				'folio' => $folio,
				'titulo' => $titulo,
				'aprobador' => $aprobadorUserId,
				'comentario' => $comentario ?: '',
			],
			$link
		);
		$this->sendMailStatusNotification(
			$user,
			'Solicitud de compra rechazada',
			sprintf('Tu solicitud de compra fue rechazada: %s', $folio),
			$folio,
			$titulo,
			$monto,
			$aprobadorUserId,
			$comentario,
			$link,
			'Tu solicitud de compra fue rechazada.'
		);
	}

	private function sendNextcloudStatusNotification(
		string $uid,
		string $idSolicitud,
		string $subject,
		array $parameters,
		string $link
	): void {
		try {
			$notification = $this->notificationManager->createNotification();

			$notification
				->setApp('empleados')
				->setUser($uid)
				->setDateTime(new DateTime())
				->setObject('compra_solicitud', $idSolicitud)
				->setSubject($subject, $parameters)
				->setLink($link);

			$this->notificationManager->notify($notification);
		} catch (Throwable $e) {
			$this->logger->warning('No se pudo enviar notificación de estado de compra.', [
				'app' => 'empleados',
				'user' => $uid,
				'subject' => $subject,
				'exception' => $e,
			]);
		}
	}

	private function sendMailStatusNotification(
		IUser $user,
		string $heading,
		string $subject,
		string $folio,
		string $titulo,
		string $monto,
		string $aprobador,
		?string $comentario,
		string $link,
		string $statusText
	): void {
		$email = $user->getEMailAddress();

		if ($email === null || $email === '') {
			return;
		}

		try {
			$fromLocal = $this->config->getSystemValueString('mail_from_address', 'no-reply');
			$fromDomain = $this->config->getSystemValueString('mail_domain', 'localhost');
			$from = $fromLocal . '@' . $fromDomain;

			$montoTexto = '';
			if ($monto !== '') {
				$montoTexto = is_numeric($monto)
					? '$' . number_format((float)$monto, 2, '.', ',') . ' MXN'
					: $monto;
			}

			$detallePlain = "Folio: {$folio}\n"
				. "Título: {$titulo}\n"
				. "Autorizó: {$aprobador}\n"
				. ($montoTexto !== '' ? "Monto: {$montoTexto}\n" : '')
				. ($comentario ? "Comentario: {$comentario}\n" : '');

			$detalleHtml = '
				<span style="
					display:block;
					max-width:440px;
					margin:14px auto 8px auto;
					padding:16px 18px;
					border:1px solid #e5e7eb;
					border-radius:10px;
					background:#f8f9fa;
					text-align:left;
					line-height:1.5;
					color:#222;
				">
					<span style="display:block; margin-bottom:8px;">
						<strong>Folio:</strong> ' . $this->escapeHtml($folio) . '
					</span>

					<span style="display:block; margin-bottom:8px;">
						<strong>Título:</strong> ' . $this->escapeHtml($titulo) . '
					</span>

					<span style="display:block; margin-bottom:8px;">
						<strong>Autorizó:</strong> ' . $this->escapeHtml($aprobador) . '
					</span>

					' . ($montoTexto !== '' ? '
						<span style="display:block; margin-bottom:8px;">
							<strong>Monto:</strong> ' . $this->escapeHtml($montoTexto) . '
						</span>
					' : '') . '

					' . ($comentario ? '
						<span style="display:block; margin-top:8px;">
							<strong>Comentario:</strong> ' . $this->escapeHtml($comentario) . '
						</span>
					' : '') . '
				</span>
			';

			$emailTemplate = $this->mailer->createEMailTemplate('empleados.CompraSolicitudAutorizada', [
				'folio' => $folio,
				'titulo' => $titulo,
				'monto' => $montoTexto,
				'aprobador' => $aprobador,
				'comentario' => $comentario,
				'link' => $link,
			]);

			$emailTemplate->setSubject($subject);
			$emailTemplate->addHeader();
			$emailTemplate->addHeading($heading);

			$emailTemplate->addBodyText(
				$statusText,
				$statusText
			);

			$emailTemplate->addBodyText($detalleHtml, $detallePlain);

			$emailTemplate->addBodyText(
				'Puedes revisar el detalle desde el módulo de compras.',
				'Puedes revisar el detalle desde el módulo de compras.'
			);

			$emailTemplate->addBodyButton('Ver solicitud', $link);
			$emailTemplate->addFooter();

			$message = $this->mailer->createMessage();
			$message->setFrom([$from => 'Empleados']);
			$message->setTo([$email => $user->getDisplayName()]);
			$message->useTemplate($emailTemplate);

			$this->mailer->send($message);
		} catch (Throwable $e) {
			$this->logger->warning('No se pudo enviar correo de estado de compra.', [
				'app' => 'empleados',
				'user' => $user->getUID(),
				'mail' => $email,
				'exception' => $e,
			]);
		}
	}
	private function readSolicitudValue($object, array $getters, array $keys, $default = null) {
		if (is_object($object)) {
			foreach ($getters as $getter) {
				if (method_exists($object, $getter)) {
					$value = $object->$getter();

					if ($value !== null && $value !== '') {
						return $value;
					}
				}
			}

			if (method_exists($object, 'jsonSerialize')) {
				$data = $object->jsonSerialize();

				if (is_array($data)) {
					foreach ($keys as $key) {
						if (array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== '') {
							return $data[$key];
						}
					}
				}
			}
		}

		if (is_array($object)) {
			foreach ($keys as $key) {
				if (array_key_exists($key, $object) && $object[$key] !== null && $object[$key] !== '') {
					return $object[$key];
				}
			}
		}

		return $default;
	}

	private function escapeHtml(string $value): string {
		return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}
