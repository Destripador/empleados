<?php

declare(strict_types=1);

namespace OCA\Empleados\Helper;

use OCA\Empleados\AppInfo\Application;
use OCP\IURLGenerator;
use OCP\Mail\IMailer;
use Psr\Log\LoggerInterface;
use Throwable;

final class MailHelper {
	public function __construct(
		private IMailer $mailer,
		private IURLGenerator $urlGenerator,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @param array<int, string|array{html?: string, plain?: string}> $contenidoCuerpo
	 */
	public function enviarCorreo(
		string $to,
		string $subject,
		array $contenidoCuerpo,
		?string $recipientName = null,
		?string $buttonText = 'Ir al módulo de empleados',
		?string $buttonUrl = null,
		string $templateId = 'empleados.Notification',
	): bool {
		$to = trim($to);
		$subject = trim($subject);

		if ($to === '' || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
			$this->logger->warning('No se envió correo porque el destinatario no es válido.', [
				'app' => Application::APP_ID,
				'mail' => $to,
				'subject' => $subject,
			]);

			return false;
		}

		if ($subject === '') {
			$this->logger->warning('No se envió correo porque el asunto está vacío.', [
				'app' => Application::APP_ID,
				'mail' => $to,
			]);

			return false;
		}

		try {
			$template = $this->mailer->createEMailTemplate($templateId);

			$template->setSubject($subject);
			$template->addHeader();
			$template->addHeading($subject);

			foreach ($contenidoCuerpo as $linea) {
				if (is_array($linea)) {
					$html = (string)($linea['html'] ?? '');
					$plain = (string)($linea['plain'] ?? strip_tags($html));

					if ($html === '' && $plain === '') {
						continue;
					}

					if ($html === '') {
						$html = $this->textToHtml($plain);
					}

					$template->addBodyText($html, $plain);
					continue;
				}

				$texto = trim((string)$linea);

				if ($texto === '') {
					continue;
				}

				$template->addBodyText(
					$this->textToHtml($texto),
					$texto
				);
			}

			if ($buttonText !== null && trim($buttonText) !== '') {
				$buttonUrl ??= $this->urlGenerator->linkToRouteAbsolute(
					'empleados.page.index'
				);

				$template->addBodyButton($buttonText, $buttonUrl);
			}

			$template->addFooter();

			$message = $this->mailer->createMessage();

			if ($recipientName !== null && trim($recipientName) !== '') {
				$message->setTo([
					$to => trim($recipientName),
				]);
			} else {
				$message->setTo([$to]);
			}

			/*
			 * No establecer setFrom().
			 *
			 * Nextcloud utilizará mail_from_address y mail_domain
			 * configurados por el administrador.
			 */
			$message->useTemplate($template);

			$this->mailer->send($message);

			return true;
		} catch (Throwable $e) {
			$this->logger->error('No se pudo enviar un correo del módulo Empleados.', [
				'app' => Application::APP_ID,
				'mail' => $to,
				'subject' => $subject,
				'exception' => $e,
			]);

			return false;
		}
	}

	private function textToHtml(string $text): string {
		return nl2br(
			htmlspecialchars(
				$text,
				ENT_QUOTES | ENT_SUBSTITUTE,
				'UTF-8'
			)
		);
	}
}