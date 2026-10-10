<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\IRequest;

/**
 * Serves the ProjectCheck service worker with correct MIME and scope headers.
 */
class ServiceWorkerController extends Controller
{
	public function __construct(
		string $appName,
		IRequest $request,
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

		// The allowed scope must cover the path the worker is registered under.
		// The registration script uses the script's own directory as its scope,
		// so emit exactly that directory — derived from the incoming request
		// URI so it matches whichever route shape served this script
		// (/index.php/apps/<id>/ vs /apps/<id>/, with or without a webroot
		// prefix). linkTo(APP_ID,'') is wrong here: it resolves to the app's
		// files dir (/custom_apps/<id>/), which never contains the routes.
		$requestPath = strtok((string)$this->request->getRequestUri(), '?');
		$scriptDir = is_string($requestPath) && $requestPath !== '' ? dirname($requestPath) : '/';
		$scriptDir = rtrim($scriptDir, '/');
		$scopeBase = ($scriptDir === '' || $scriptDir === '.') ? '/' : $scriptDir . '/';

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
