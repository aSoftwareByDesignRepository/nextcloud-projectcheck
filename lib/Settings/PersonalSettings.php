<?php

declare(strict_types=1);

/**
 * Personal settings panel for the ProjectCheck app.
 *
 * The form is rendered inside Nextcloud's Personal Settings UI; the
 * accompanying template MUST therefore not duplicate any of Nextcloud's
 * layout chrome (no #app-navigation, no #app-content, no app-level styles
 * that affect the surrounding page).
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use OCP\Settings\ISettings;
use OCA\ProjectCheck\Service\AccessControlService;

class PersonalSettings implements ISettings
{
	public function __construct(
		private IConfig $config,
		private IUserSession $userSession,
		private IURLGenerator $urlGenerator,
		private IFactory $l10nFactory,
		private AccessControlService $accessControl,
	) {
	}

	public function getForm(): TemplateResponse
	{
		$l = $this->l10nFactory->get('projectcheck');
		$user = $this->userSession->getUser();

		// When the user has no access to ProjectCheck the panel must still
		// render something coherent (Nextcloud has already rendered the
		// section header at this point) but never expose configuration.
		if ($user === null || !$this->accessControl->canUseApp($user->getUID())) {
			return new TemplateResponse('projectcheck', 'personal-settings', [
				'l' => $l,
				'hasAccess' => false,
				'budget_warning_threshold' => '',
				'budget_critical_threshold' => '',
				'appBudgetWarningDefault' => $this->config->getAppValue('projectcheck', 'budget_warning_threshold', '80'),
				'appBudgetCriticalDefault' => $this->config->getAppValue('projectcheck', 'budget_critical_threshold', '90'),
				'saveUrl' => '',
			]);
		}

		$userId = $user->getUID();

		// Personal values fall back to the org-wide defaults when the user
		// has not set their own override.
		$appWarning = $this->config->getAppValue('projectcheck', 'budget_warning_threshold', '80');
		$appCritical = $this->config->getAppValue('projectcheck', 'budget_critical_threshold', '90');
		$warning = $this->config->getUserValue($userId, 'projectcheck', 'budget_warning_threshold', $appWarning);
		$critical = $this->config->getUserValue($userId, 'projectcheck', 'budget_critical_threshold', $appCritical);

		return new TemplateResponse('projectcheck', 'personal-settings', [
			'l' => $l,
			'hasAccess' => true,
			'budget_warning_threshold' => (string) $warning,
			'budget_critical_threshold' => (string) $critical,
			'appBudgetWarningDefault' => (string) $appWarning,
			'appBudgetCriticalDefault' => (string) $appCritical,
			'saveUrl' => $this->urlGenerator->linkToRoute('projectcheck.app_config.savePersonalPreferences'),
		]);
	}

	public function getSection(): ?string
	{
		$user = $this->userSession->getUser();
		// Hide the panel entirely from users who cannot use the app.
		if ($user === null || !$this->accessControl->canUseApp($user->getUID())) {
			return null;
		}
		return 'projectcheck';
	}

	public function getPriority(): int
	{
		return 50;
	}
}
