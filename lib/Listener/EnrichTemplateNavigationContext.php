<?php

declare(strict_types=1);

/**
 * Injects navigation context (org admin link) into every ProjectCheck TemplateResponse
 * so templates do not need OCP\Server or static service location.
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Listener;

use OCA\ProjectCheck\AppInfo\Application;
use OCA\ProjectCheck\Service\AccessControlService;
use OCA\ProjectCheck\Service\JsL10nCatalogBuilder;
use OCA\ProjectCheck\Service\LocaleFormatService;
use OCA\ProjectCheck\Service\SettingsSectionCatalog;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use OCP\Util;

/**
 * @template-implements IEventListener<BeforeTemplateRenderedEvent>
 */
class EnrichTemplateNavigationContext implements IEventListener
{
	public function __construct(
		private IUserSession $userSession,
		private AccessControlService $accessControl,
		private IURLGenerator $urlGenerator,
		private JsL10nCatalogBuilder $jsL10nCatalogBuilder,
		private LocaleFormatService $localeFormat,
		private IFactory $l10nFactory,
		private SettingsSectionCatalog $settingsSections,
	) {
	}

	public function handle(Event $event): void
	{
		if (!$event instanceof BeforeTemplateRenderedEvent) {
			return;
		}
		$response = $event->getResponse();
		if (!$response instanceof TemplateResponse) {
			return;
		}
		if ($response->getApp() !== Application::APP_ID) {
			return;
		}
		// WCAG / theming: semantic tokens (--pc-*, shell layout) must load on every view, every theme.
		Util::addStyle(Application::APP_ID, 'app', true);
		Util::addStyle(Application::APP_ID, 'common/shell', true);
		Util::addStyle(Application::APP_ID, 'common/app-layout', true);
		Util::addStyle(Application::APP_ID, 'common/mobile-nav', true);
		Util::addStyle(Application::APP_ID, 'common/accessibility', true);
		Util::addStyle(Application::APP_ID, 'common/stats-panel', true);
		// list-table.css is loaded from list page templates AFTER page CSS so its
		// overflow/reflow rules win over legacy .grid / cell width styles.
		if (!$event->isLoggedIn()) {
			return;
		}
		$user = $this->userSession->getUser();
		if ($user === null) {
			return;
		}
		$uid = $user->getUID();
		$params = $response->getParams();
		$canManageSettings = $this->accessControl->canManageSettings($uid);
		$canManageOrganization = $this->accessControl->canManageOrganization($uid);
		$params['canManageSettings'] = $canManageSettings;
		$params['canManageOrganization'] = $canManageOrganization;
		$params['canManageOrg'] = $canManageOrganization; // backward compatibility for existing templates
		$l = $this->l10nFactory->get(Application::APP_ID);
		$params['roleLabel'] = ($canManageSettings || $canManageOrganization)
			? $l->t('Administrator')
			: $l->t('Member');
		$params['orgAppSettingsUrl'] = $this->urlGenerator->linkToRoute('projectcheck.app_config.settingsIndex');
		if (!isset($params['dashboardUrl'])) {
			$params['dashboardUrl'] = $this->urlGenerator->linkToRoute('projectcheck.dashboard.index');
		}
		if (!isset($params['timeEntriesUrl'])) {
			$params['timeEntriesUrl'] = $this->urlGenerator->linkToRoute('projectcheck.time_entry.index');
		}
		if (!isset($params['projectsUrl'])) {
			$params['projectsUrl'] = $this->urlGenerator->linkToRoute('projectcheck.project.index');
		}
		if (!isset($params['customersUrl'])) {
			$params['customersUrl'] = $this->urlGenerator->linkToRoute('projectcheck.customer.index');
		}
		if (!isset($params['employeesUrl'])) {
			$params['employeesUrl'] = $this->urlGenerator->linkToRoute('projectcheck.employee.index');
		}
		if (!isset($params['settingsUrl'])) {
			$params['settingsUrl'] = $this->urlGenerator->linkToRoute('projectcheck.app_config.settingsIndex');
		}
		if (($canManageSettings || $canManageOrganization)
			&& (!isset($params['settingsSectionLabels']) || !is_array($params['settingsSectionLabels']) || $params['settingsSectionLabels'] === [])
		) {
			$labels = [];
			$sectionUrls = [];
			foreach (SettingsSectionCatalog::SECTIONS as $sectionId) {
				$labels[$sectionId] = $this->settingsSections->navLabel($l, $sectionId);
				$sectionUrls[$sectionId] = $this->urlGenerator->linkToRoute(
					'projectcheck.app_config.settingsSection',
					['section' => $sectionId],
				);
			}
			$params['settingsSectionLabels'] = $labels;
			$params['settingsSections'] = $sectionUrls;
			$urls = is_array($params['urls'] ?? null) ? $params['urls'] : [];
			if (!isset($urls['settingsSections']) || !is_array($urls['settingsSections'])) {
				$urls['settingsSections'] = $sectionUrls;
			}
			$params['urls'] = $urls;
		}
		$params['jsL10n'] = $this->jsL10nCatalogBuilder->buildForApp();
		// Locale-aware server-side formatting bridge (audit ref. AUDIT-FINDINGS B10/H28).
		$params['fmt'] = $this->localeFormat;
		$params['orgCurrency'] = $this->localeFormat->getCurrency();
		$params['htmlLang'] = str_replace('_', '-', $this->localeFormat->getLocale());
		if (!isset($params['urlGenerator'])) {
			$params['urlGenerator'] = $this->urlGenerator;
		}
		$response->setParams($params);
	}
}
