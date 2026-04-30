<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Tests\Unit\Listener;

use OCA\ProjectCheck\AppInfo\Application;
use OCA\ProjectCheck\Listener\EnrichTemplateNavigationContext;
use OCA\ProjectCheck\Service\AccessControlService;
use OCA\ProjectCheck\Service\JsL10nCatalogBuilder;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class EnrichTemplateNavigationContextTest extends TestCase
{
	public function testEnrichesParamsWhenProjectCheckTemplateAndLoggedIn(): void
	{
		/** @var IUserSession&MockObject $userSession */
		$userSession = $this->createMock(IUserSession::class);
		/** @var AccessControlService&MockObject $accessControl */
		$accessControl = $this->createMock(AccessControlService::class);
		/** @var IURLGenerator&MockObject $urlGenerator */
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$jsL10nCatalog = $this->createMock(JsL10nCatalogBuilder::class);
		$jsL10nCatalog->method('buildForApp')->willReturn(['X' => 'Y']);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('u1');

		$response = new TemplateResponse(Application::APP_ID, 'main', ['foo' => 1]);
		$event = new BeforeTemplateRenderedEvent(true, $response);

		$userSession->method('getUser')->willReturn($user);
		$accessControl->method('canManageSettings')
			->with('u1')
			->willReturn(true);
		$accessControl->method('canManageOrganization')
			->with('u1')
			->willReturn(true);
		$urlGenerator->method('linkToRoute')
			->with('projectcheck.app_config.settingsIndex')
			->willReturn('/index.php/apps/projectcheck/organization');

		$listener = new EnrichTemplateNavigationContext($userSession, $accessControl, $urlGenerator, $jsL10nCatalog);
		$listener->handle($event);

		$params = $response->getParams();
		$this->assertSame(1, $params['foo'] ?? null);
		$this->assertSame(['X' => 'Y'], $params['jsL10n'] ?? null);
		$this->assertTrue($params['canManageSettings'] ?? null);
		$this->assertTrue($params['canManageOrganization'] ?? null);
		$this->assertTrue($params['canManageOrg'] ?? null);
		$this->assertSame(
			'/index.php/apps/projectcheck/organization',
			$params['orgAppSettingsUrl'] ?? null
		);
	}

	public function testDoesNotRunWhenNotLoggedIn(): void
	{
		/** @var IUserSession&MockObject $userSession */
		$userSession = $this->createMock(IUserSession::class);
		$accessControl = $this->createMock(AccessControlService::class);
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$jsL10nCatalog = $this->createMock(JsL10nCatalogBuilder::class);
		$jsL10nCatalog->expects($this->never())->method('buildForApp');

		$response = new TemplateResponse(Application::APP_ID, 'main', ['canManageOrg' => 'old']);
		$event = new BeforeTemplateRenderedEvent(false, $response);

		$userSession->expects($this->never())->method('getUser');
		$accessControl->expects($this->never())->method('canManageSettings');
		$accessControl->expects($this->never())->method('canManageOrganization');
		$urlGenerator->expects($this->never())->method('linkToRoute');

		$listener = new EnrichTemplateNavigationContext($userSession, $accessControl, $urlGenerator, $jsL10nCatalog);
		$listener->handle($event);
		$this->assertSame('old', $response->getParams()['canManageOrg']);
	}

	public function testSkipsWhenWrongApp(): void
	{
		/** @var IUserSession&MockObject $userSession */
		$userSession = $this->createMock(IUserSession::class);
		$accessControl = $this->createMock(AccessControlService::class);
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$jsL10nCatalog = $this->createMock(JsL10nCatalogBuilder::class);
		$jsL10nCatalog->expects($this->never())->method('buildForApp');

		$response = new TemplateResponse('files', 'list', []);
		$event = new BeforeTemplateRenderedEvent(true, $response);

		$userSession->expects($this->never())->method('getUser');

		$listener = new EnrichTemplateNavigationContext($userSession, $accessControl, $urlGenerator, $jsL10nCatalog);
		$listener->handle($event);
	}

	public function testSkipsWhenUserSessionNull(): void
	{
		/** @var IUserSession&MockObject $userSession */
		$userSession = $this->createMock(IUserSession::class);
		$accessControl = $this->createMock(AccessControlService::class);
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$jsL10nCatalog = $this->createMock(JsL10nCatalogBuilder::class);
		$jsL10nCatalog->expects($this->never())->method('buildForApp');

		$response = new TemplateResponse(Application::APP_ID, 'main', []);
		$event = new BeforeTemplateRenderedEvent(true, $response);
		$userSession->method('getUser')->willReturn(null);
		$accessControl->expects($this->never())->method('canManageSettings');
		$accessControl->expects($this->never())->method('canManageOrganization');

		$listener = new EnrichTemplateNavigationContext($userSession, $accessControl, $urlGenerator, $jsL10nCatalog);
		$listener->handle($event);
		$this->assertSame([], $response->getParams());
	}
}
