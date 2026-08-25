<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2026 Alexander Mäule <info@software-by-design.de>
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Service;

use OCA\ProjectCheck\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\IURLGenerator;
use RuntimeException;

/**
 * Theme-safe ProjectCheck icon URLs for header, dashboard, and search surfaces.
 *
 * - Header / app menu: white {@see app.svg} — NC applies invert CSS vars.
 * - Dashboard surfaces: black/uncoloured {@see app-dashboard.svg} /
 *   {@see app-dark.svg} — per {@see \OCP\Dashboard\IIconWidget}.
 */
final class AppIconService
{
	private const SURFACE_CANDIDATES = ['app-dashboard.svg', 'app-dark.svg', 'app.svg'];

	public function __construct(
		private readonly IURLGenerator $urlGenerator,
		private readonly IAppManager $appManager,
	) {
	}

	public function headerIconPath(): string
	{
		return $this->withCacheBust(
			$this->urlGenerator->imagePath(Application::APP_ID, 'app.svg')
		);
	}

	public function surfaceIconPath(): string
	{
		foreach (self::SURFACE_CANDIDATES as $iconFile) {
			try {
				return $this->withCacheBust(
					$this->urlGenerator->imagePath(Application::APP_ID, $iconFile)
				);
			} catch (RuntimeException) {
				// Try next candidate.
			}
		}

		try {
			return $this->urlGenerator->imagePath('core', 'actions/projects.svg');
		} catch (RuntimeException) {
			return '';
		}
	}

	public function absoluteSurfaceIconUrl(): string
	{
		$path = $this->surfaceIconPath();
		if ($path === '') {
			return '';
		}
		return $this->urlGenerator->getAbsoluteURL($path);
	}

	private function withCacheBust(string $path): string
	{
		if ($path === '') {
			return '';
		}
		$version = $this->appManager->getAppVersion(Application::APP_ID);
		if ($version === '') {
			return $path;
		}
		$separator = str_contains($path, '?') ? '&' : '?';
		return $path . $separator . 'v=' . rawurlencode($version);
	}
}
