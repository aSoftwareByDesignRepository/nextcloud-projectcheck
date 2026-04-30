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
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IURLGenerator;
use OCP\IUserSession;
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
		// WCAG / theming: semantic tokens (--pc-*, *-light tints) must load on every view, every theme.
		Util::addStyle(Application::APP_ID, 'common/colors', true);
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
		$params['orgAppSettingsUrl'] = $this->urlGenerator->linkToRoute('projectcheck.app_config.settingsIndex');
		$params['jsL10n'] = $this->jsL10nCatalogBuilder->buildForApp();
		$response->setParams($params);
	}
}
