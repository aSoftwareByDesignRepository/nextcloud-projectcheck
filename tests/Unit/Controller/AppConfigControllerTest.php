<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Controller;

use OCA\ProjectCheck\Controller\AppConfigController;
use OCA\ProjectCheck\Service\AccessControlService;
use OCA\ProjectCheck\Service\CSPService;
use OCA\ProjectCheck\Service\CustomerService;
use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Service\TimeEntryService;
use OCP\AppFramework\Http\JSONResponse;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Smoke tests for {@see AppConfigController}.
 *
 * Audit reference: AUDIT-FINDINGS G24/G25 - the org-policy controller exposes
 * search and policy-save endpoints that bypass the framework's admin gate.
 * These tests guarantee the explicit per-method authorisation checks remain
 * intact and that PII (user/group enumeration) cannot leak to unauthenticated
 * or unauthorised callers.
 */
class AppConfigControllerTest extends TestCase {
	/** @var IRequest|\PHPUnit\Framework\MockObject\MockObject */
	private $request;
	/** @var IUserSession|\PHPUnit\Framework\MockObject\MockObject */
	private $userSession;
	/** @var IUser|\PHPUnit\Framework\MockObject\MockObject */
	private $user;
	/** @var AccessControlService|\PHPUnit\Framework\MockObject\MockObject */
	private $accessControl;
	/** @var IConfig|\PHPUnit\Framework\MockObject\MockObject */
	private $config;
	/** @var IURLGenerator|\PHPUnit\Framework\MockObject\MockObject */
	private $urlGenerator;
	/** @var IFactory|\PHPUnit\Framework\MockObject\MockObject */
	private $l10nFactory;
	/** @var LoggerInterface|\PHPUnit\Framework\MockObject\MockObject */
	private $logger;
	/** @var IEventDispatcher|\PHPUnit\Framework\MockObject\MockObject */
	private $eventDispatcher;
	/** @var ProjectService|\PHPUnit\Framework\MockObject\MockObject */
	private $projectService;
	/** @var CustomerService|\PHPUnit\Framework\MockObject\MockObject */
	private $customerService;
	/** @var TimeEntryService|\PHPUnit\Framework\MockObject\MockObject */
	private $timeEntryService;
	/** @var IUserManager|\PHPUnit\Framework\MockObject\MockObject */
	private $userManager;
	/** @var IGroupManager|\PHPUnit\Framework\MockObject\MockObject */
	private $groupManager;
	/** @var CSPService|\PHPUnit\Framework\MockObject\MockObject */
	private $cspService;

	protected function setUp(): void {
		parent::setUp();
		$this->request = $this->createMock(IRequest::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->user = $this->createMock(IUser::class);
		$this->user->method('getUID')->willReturn('alice');
		$this->accessControl = $this->createMock(AccessControlService::class);
		$this->config = $this->createMock(IConfig::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->urlGenerator->method('linkToRoute')->willReturn('/x');
		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnCallback(static fn ($s, $p = []) => (string)$s);
		$this->l10nFactory = $this->createMock(IFactory::class);
		$this->l10nFactory->method('get')->willReturn($l);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->projectService = $this->createMock(ProjectService::class);
		$this->customerService = $this->createMock(CustomerService::class);
		$this->timeEntryService = $this->createMock(TimeEntryService::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->cspService = $this->createMock(CSPService::class);
	}

	private function makeController(): AppConfigController {
		return new AppConfigController(
			'projectcheck',
			$this->request,
			$this->userSession,
			$this->accessControl,
			$this->config,
			$this->urlGenerator,
			$this->l10nFactory,
			$this->logger,
			$this->eventDispatcher,
			$this->projectService,
			$this->customerService,
			$this->timeEntryService,
			$this->userManager,
			$this->groupManager,
			$this->cspService
		);
	}

	public function testSearchUsersRequiresAuthentication(): void {
		$this->userSession->method('getUser')->willReturn(null);
		// Must NOT call the user manager at all when unauthenticated.
		$this->userManager->expects($this->never())->method('search');
		$this->userManager->expects($this->never())->method('searchDisplayName');

		$response = $this->makeController()->searchUsers();
		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertSame(401, $response->getStatus());
		$body = $response->getData();
		$this->assertSame(false, $body['ok']);
	}

	public function testSearchUsersRequiresOrgManagementPermission(): void {
		$this->userSession->method('getUser')->willReturn($this->user);
		$this->accessControl->method('canManageOrganization')->with('alice')->willReturn(false);
		$this->userManager->expects($this->never())->method('search');

		$response = $this->makeController()->searchUsers();
		$this->assertSame(403, $response->getStatus());
	}

	public function testSearchUsersIgnoresShortQueriesWithEmptyResult(): void {
		$this->userSession->method('getUser')->willReturn($this->user);
		$this->accessControl->method('canManageOrganization')->willReturn(true);
		$this->request->method('getParam')->with('q', '')->willReturn('a');
		$this->userManager->expects($this->never())->method('search');

		$response = $this->makeController()->searchUsers();
		$this->assertSame(200, $response->getStatus());
		$body = $response->getData();
		$this->assertTrue($body['ok']);
		$this->assertSame([], $body['items']);
	}

	public function testSearchUsersDeduplicatesAndCapsResults(): void {
		$this->userSession->method('getUser')->willReturn($this->user);
		$this->accessControl->method('canManageOrganization')->willReturn(true);
		$this->request->method('getParam')->with('q', '')->willReturn('bob');

		$bob = $this->createMock(IUser::class);
		$bob->method('getUID')->willReturn('bob');
		$bob->method('getDisplayName')->willReturn('Bob B');
		$bobby = $this->createMock(IUser::class);
		$bobby->method('getUID')->willReturn('bobby');
		$bobby->method('getDisplayName')->willReturn('Bobby B');

		// Same user appears in both maps but must be returned once.
		$this->userManager->method('search')->willReturn([$bob]);
		$this->userManager->method('searchDisplayName')->willReturn([$bob, $bobby]);

		$response = $this->makeController()->searchUsers();
		$this->assertSame(200, $response->getStatus());
		$body = $response->getData();
		$ids = array_map(static fn ($u) => $u['id'], $body['items']);
		$this->assertSame(['bob', 'bobby'], $ids);
	}

	public function testSavePolicyRequiresAuthentication(): void {
		$this->userSession->method('getUser')->willReturn(null);
		$this->accessControl->expects($this->never())->method('applyFullAccessPolicy');

		$response = $this->makeController()->savePolicy();
		$this->assertSame(401, $response->getStatus());
	}

	public function testSavePolicyRequiresOrgManagementPermission(): void {
		$this->userSession->method('getUser')->willReturn($this->user);
		$this->accessControl->method('canManageOrganization')->willReturn(false);
		$this->accessControl->expects($this->never())->method('applyFullAccessPolicy');
		$this->logger->expects($this->atLeastOnce())->method('warning');

		$response = $this->makeController()->savePolicy();
		$this->assertSame(403, $response->getStatus());
	}

	public function testSavePersonalPreferencesValidatesThresholdRange(): void {
		$this->userSession->method('getUser')->willReturn($this->user);
		$this->accessControl->method('canUseApp')->willReturn(true);
		$this->request->method('getHeader')->willReturn('');
		$this->request->method('getParams')->willReturn([
			'budget_warning_threshold' => '120',
			'budget_critical_threshold' => '200',
		]);
		// No actual write must happen on validation failure.
		$this->config->expects($this->never())->method('setUserValue');

		$response = $this->makeController()->savePersonalPreferences();
		$this->assertSame(400, $response->getStatus());
		$body = $response->getData();
		$this->assertFalse($body['success']);
		$this->assertArrayHasKey('fieldErrors', $body);
	}

	public function testSavePersonalPreferencesEnforcesWarningLessThanCritical(): void {
		$this->userSession->method('getUser')->willReturn($this->user);
		$this->accessControl->method('canUseApp')->willReturn(true);
		$this->request->method('getHeader')->willReturn('');
		$this->request->method('getParams')->willReturn([
			'budget_warning_threshold' => '90',
			'budget_critical_threshold' => '80',
		]);
		$this->config->expects($this->never())->method('setUserValue');

		$response = $this->makeController()->savePersonalPreferences();
		$this->assertSame(400, $response->getStatus());
		$body = $response->getData();
		$this->assertArrayHasKey('budget_warning_threshold', $body['fieldErrors']);
		$this->assertArrayHasKey('budget_critical_threshold', $body['fieldErrors']);
	}

	public function testSavePersonalPreferencesPersistsValidValues(): void {
		$this->userSession->method('getUser')->willReturn($this->user);
		$this->accessControl->method('canUseApp')->willReturn(true);
		$this->request->method('getHeader')->willReturn('');
		$this->request->method('getParams')->willReturn([
			'budget_warning_threshold' => '60',
			'budget_critical_threshold' => '85',
		]);
		$this->config->expects($this->exactly(2))->method('setUserValue')
			->withConsecutive(
				['alice', 'projectcheck', 'budget_warning_threshold', '60'],
				['alice', 'projectcheck', 'budget_critical_threshold', '85']
			);
		$this->config->method('getUserValue')->willReturnOnConsecutiveCalls('60', '85');

		$response = $this->makeController()->savePersonalPreferences();
		$this->assertSame(200, $response->getStatus());
		$body = $response->getData();
		$this->assertTrue($body['success']);
	}

	public function testSavePersonalPreferencesRequiresAuthentication(): void {
		$this->userSession->method('getUser')->willReturn(null);
		$this->config->expects($this->never())->method('setUserValue');

		$response = $this->makeController()->savePersonalPreferences();
		$this->assertSame(401, $response->getStatus());
	}

	public function testSavePersonalPreferencesRequiresAppAccess(): void {
		$this->userSession->method('getUser')->willReturn($this->user);
		$this->accessControl->method('canUseApp')->willReturn(false);
		$this->config->expects($this->never())->method('setUserValue');

		$response = $this->makeController()->savePersonalPreferences();
		$this->assertSame(403, $response->getStatus());
	}

	public function testSavePolicyPersistsValidCurrency(): void {
		$this->userSession->method('getUser')->willReturn($this->user);
		$this->accessControl->method('canManageOrganization')->willReturn(true);
		$this->accessControl->method('applyFullAccessPolicy')->with(false, [], [], []);
		$this->accessControl->method('getPolicyState')->willReturn([
			'restrictionEnabled' => false,
			'allowedUserIds' => [],
			'allowedGroupIds' => [],
			'appAdminUserIds' => [],
		]);
		$this->request->method('getHeader')->willReturn('');
		$this->request->method('getParams')->willReturn([
			'currency' => 'usd',
		]);
		$this->config->expects($this->once())
			->method('setAppValue')
			->with('projectcheck', 'currency', 'USD');
		$this->eventDispatcher->method('dispatchTyped');

		$response = $this->makeController()->savePolicy();
		$this->assertSame(200, $response->getStatus());
		$body = $response->getData();
		$this->assertTrue($body['success']);
	}

	public function testSavePolicyIgnoresInvalidCurrency(): void {
		$this->userSession->method('getUser')->willReturn($this->user);
		$this->accessControl->method('canManageOrganization')->willReturn(true);
		$this->accessControl->method('applyFullAccessPolicy')->with(false, [], [], []);
		$this->accessControl->method('getPolicyState')->willReturn([
			'restrictionEnabled' => false,
			'allowedUserIds' => [],
			'allowedGroupIds' => [],
			'appAdminUserIds' => [],
		]);
		$this->request->method('getHeader')->willReturn('');
		$this->request->method('getParams')->willReturn([
			'currency' => 'usd<script>',
		]);
		$this->config->expects($this->never())
			->method('setAppValue')
			->with('projectcheck', 'currency', $this->anything());
		$this->eventDispatcher->method('dispatchTyped');

		$response = $this->makeController()->savePolicy();
		$this->assertSame(200, $response->getStatus());
		$body = $response->getData();
		$this->assertTrue($body['success']);
	}
}
