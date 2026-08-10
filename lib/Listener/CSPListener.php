<?php

declare(strict_types=1);

namespace OCA\Empleados\Listener;

use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Security\CSP\AddContentSecurityPolicyEvent;

/**
 * Allow YouTube embeds used by in-app tutorials.
 *
 * @template-implements IEventListener<AddContentSecurityPolicyEvent>
 */
class CSPListener implements IEventListener {
	public function handle(Event $event): void {
		if (!($event instanceof AddContentSecurityPolicyEvent)) {
			return;
		}

		$csp = new ContentSecurityPolicy();
		$csp->addAllowedFrameDomain('https://www.youtube.com');
		$csp->addAllowedFrameDomain('https://youtube.com');
		$csp->addAllowedFrameDomain('https://www.youtube-nocookie.com');
		$csp->addAllowedFrameDomain('https://youtube-nocookie.com');
		$event->addPolicy($csp);
	}
}
