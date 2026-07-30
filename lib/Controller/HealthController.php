<?php

declare(strict_types=1);

/**
 * Public health probe for load balancers and the official mobile login check.
 *
 * Intentionally omits app/Nextcloud version strings (fingerprinting). Advertises
 * that the mobile companion API prefix is present so clients can distinguish
 * “Nextcloud up, ProjectCheck missing/too old” from a live companion build.
 *
 * @copyright Copyright (c) 2026, Software by Design GbR
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Controller;

use OCA\ProjectCheck\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class HealthController extends Controller
{
	public function __construct(
		IRequest $request,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	public function check(): JSONResponse
	{
		return new JSONResponse([
			'status' => 'healthy',
			'app' => Application::APP_ID,
			// Stable capability flag — clients must not require a version field here.
			'mobileApi' => true,
		]);
	}
}
