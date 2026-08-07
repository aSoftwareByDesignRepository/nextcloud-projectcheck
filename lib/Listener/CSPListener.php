<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Listener;

use OCA\ProjectCheck\AppInfo\Application;
use OCP\AppFramework\Http\EmptyContentSecurityPolicy;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IRequest;
use OCP\Security\CSP\AddContentSecurityPolicyEvent;

/**
 * Adds worker-src on ProjectCheck routes so service worker registration is allowed
 * under Nextcloud's nonce-only script-src CSP.
 *
 * @template-implements IEventListener<AddContentSecurityPolicyEvent>
 */
class CSPListener implements IEventListener
{
	public function __construct(
		private readonly IRequest $request,
	) {
	}

	public function handle(Event $event): void
	{
		if (!$event instanceof AddContentSecurityPolicyEvent) {
			return;
		}

		if (!$this->isProjectCheckRequest()) {
			return;
		}

		$csp = new EmptyContentSecurityPolicy();
		$csp->addAllowedWorkerSrcDomain('\'self\'');
		$event->addPolicy($csp);
	}

	private function isProjectCheckRequest(): bool
	{
		$path = $this->request->getPathInfo();
		if (str_starts_with($path, '/apps/' . Application::APP_ID)
			|| str_starts_with($path, '/index.php/apps/' . Application::APP_ID)) {
			return true;
		}

		// Service worker script route (AppFramework pathInfo may omit app prefix on some setups).
		return str_contains($path, '/' . Application::APP_ID . '/service-worker.js')
			|| str_ends_with($path, '/' . Application::APP_ID . '/sw.js');
	}
}
