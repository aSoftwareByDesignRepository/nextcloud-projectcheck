<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Controller;

use OCA\ProjectCheck\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\IRequest;
use OCP\IURLGenerator;

/**
 * Serves the ProjectCheck service worker with correct MIME and scope headers.
 */
class ServiceWorkerController extends Controller
{
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly IURLGenerator $urlGenerator,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function script(): DataDisplayResponse
	{
		$path = dirname(__DIR__, 2) . '/sw.js';
		if (!is_readable($path)) {
			return new DataDisplayResponse('// Service worker missing', 404, [
				'Content-Type' => 'text/plain; charset=UTF-8',
			]);
		}

		$content = file_get_contents($path);
		if ($content === false) {
			return new DataDisplayResponse('// Service worker unreadable', 500, [
				'Content-Type' => 'text/plain; charset=UTF-8',
			]);
		}

		$scopeBase = rtrim($this->urlGenerator->linkTo(Application::APP_ID, ''), '/') . '/';

		$response = new DataDisplayResponse($content, 200, [
			'Content-Type' => 'application/javascript; charset=UTF-8',
			'Service-Worker-Allowed' => $scopeBase,
			'Cache-Control' => 'no-cache, no-store, must-revalidate',
		]);

		$policy = new ContentSecurityPolicy();
		$policy->addAllowedWorkerSrcDomain('\'self\'');
		$policy->addAllowedScriptDomain('\'self\'');
		$policy->addAllowedConnectDomain('\'self\'');
		$response->setContentSecurityPolicy($policy);

		return $response;
	}
}
