<?php

declare(strict_types=1);

/**
 * Settings section descriptor for the ProjectCheck app.
 *
 * Registering a section gives both the Admin and Personal settings panels a
 * dedicated, labelled, icon-decorated entry in the Nextcloud settings
 * sidebar instead of being grouped under "Additional settings". The same
 * class is reused for both admin and personal sections in info.xml because
 * the visual identity is identical and Nextcloud routes by section id.
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Settings;

use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class Section implements IIconSection
{
	public function __construct(
		private IL10N $l,
		private IURLGenerator $urlGenerator,
	) {
	}

	public function getID(): string
	{
		return 'projectcheck';
	}

	public function getName(): string
	{
		return $this->l->t('ProjectCheck');
	}

	public function getPriority(): int
	{
		// Same neighbourhood as other productivity apps (e.g. Tasks, Calendar);
		// keeps the section under the "Office & Productivity" group.
		return 75;
	}

	public function getIcon(): string
	{
		// Absolute URL: avoids broken icons when the settings UI is served from a context
		// where relative /custom_apps/... paths resolve incorrectly.
		// Try app.svg then app-dark.svg; fall back to core projects icon if the app bundle
		// is missing img/ (mis-packaged installs).
		foreach (['app.svg', 'app-dark.svg'] as $file) {
			try {
				$relative = $this->urlGenerator->imagePath('projectcheck', $file);
				return $this->urlGenerator->getAbsoluteURL($relative);
			} catch (\Throwable) {
			}
		}
		try {
			return $this->urlGenerator->getAbsoluteURL(
				$this->urlGenerator->imagePath('core', 'actions/projects.svg')
			);
		} catch (\Throwable) {
			return $this->urlGenerator->getAbsoluteURL(
				$this->urlGenerator->imagePath('core', 'places/default-app-icon.svg')
			);
		}
	}
}
