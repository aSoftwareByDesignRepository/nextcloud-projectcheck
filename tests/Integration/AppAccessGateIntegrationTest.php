<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Integration;

use OCA\ProjectCheck\AppInfo\Application;
use OCA\ProjectCheck\Controller\DashboardController;
use OCA\ProjectCheck\Exception\AppAccessDeniedException;
use OCA\ProjectCheck\Middleware\AppAccessMiddleware;
use OCA\ProjectCheck\Service\AccessControlService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;
use Test\TestCase;

/** App-entry middleware against live restriction policy. */
final class AppAccessGateIntegrationTest extends TestCase
{
	private const ALLOWED = 'pc_gate_allowed';
	private const DENIED = 'pc_gate_denied';
	private const PASSWORD = 'pc-test-pass-9xK!';

	private ?string $prevRestriction = null;
	private ?string $prevAllowedUsers = null;
	private ?string $prevAllowedGroups = null;

	protected function setUp(): void
	{
		if (!class_exists(\OC::class) || !isset(\OC::$server)) {
			$this->markTestSkipped('Nextcloud is not bootstrapped (run inside Docker with NEXTCLOUD_ROOT).');
		}
		/** @var IConfig $config */
		$config = \OC::$server->get(IConfig::class);
		$this->prevRestriction = $config->getAppValue(Application::APP_ID, AccessControlService::KEY_ACCESS_RESTRICTION, '0');
		$this->prevAllowedUsers = $config->getAppValue(Application::APP_ID, AccessControlService::KEY_ACCESS_ALLOWED_USER_IDS, '[]');
		$this->prevAllowedGroups = $config->getAppValue(Application::APP_ID, AccessControlService::KEY_ACCESS_ALLOWED_GROUP_IDS, '[]');

		/** @var IUserManager $userManager */
		$userManager = \OC::$server->get(IUserManager::class);
		foreach ([self::ALLOWED, self::DENIED] as $uid) {
			if ($userManager->userExists($uid)) {
				$userManager->get($uid)?->delete();
			}
		}
	}

	protected function tearDown(): void
	{
		if (!isset(\OC::$server)) {
			return;
		}
		/** @var IConfig $config */
		$config = \OC::$server->get(IConfig::class);
		if ($this->prevRestriction !== null) {
			$config->setAppValue(Application::APP_ID, AccessControlService::KEY_ACCESS_RESTRICTION, $this->prevRestriction);
		}
		if ($this->prevAllowedUsers !== null) {
			$config->setAppValue(Application::APP_ID, AccessControlService::KEY_ACCESS_ALLOWED_USER_IDS, $this->prevAllowedUsers);
		}
		if ($this->prevAllowedGroups !== null) {
			$config->setAppValue(Application::APP_ID, AccessControlService::KEY_ACCESS_ALLOWED_GROUP_IDS, $this->prevAllowedGroups);
		}
		/** @var IUserManager $userManager */
		$userManager = \OC::$server->get(IUserManager::class);
		foreach ([self::ALLOWED, self::DENIED] as $uid) {
			if ($userManager->userExists($uid)) {
				$userManager->get($uid)?->delete();
			}
		}
		/** @var IUserSession $session */
		$session = \OC::$server->get(IUserSession::class);
		$session->setUser(null);
	}

	public function testDeniedUserBlockedByMiddleware(): void
	{
		/** @var IUserManager $userManager */
		$userManager = \OC::$server->get(IUserManager::class);
		$userManager->createUser(self::ALLOWED, self::PASSWORD);
		$userManager->createUser(self::DENIED, self::PASSWORD);

		/** @var IConfig $config */
		$config = \OC::$server->get(IConfig::class);
		$config->setAppValue(Application::APP_ID, AccessControlService::KEY_ACCESS_RESTRICTION, '1');
		$config->setAppValue(
			Application::APP_ID,
			AccessControlService::KEY_ACCESS_ALLOWED_USER_IDS,
			json_encode([self::ALLOWED], JSON_THROW_ON_ERROR),
		);
		$config->setAppValue(Application::APP_ID, AccessControlService::KEY_ACCESS_ALLOWED_GROUP_IDS, '[]');

		/** @var IUserSession $session */
		$session = \OC::$server->get(IUserSession::class);
		$session->setUser($userManager->get(self::DENIED));

		/** @var DashboardController $controller */
		$controller = \OC::$server->get(DashboardController::class);
		$middleware = $this->middlewareWithMockRequest();

		try {
			$middleware->beforeController($controller, 'index');
			$this->fail('Expected AppAccessDeniedException for gated user');
		} catch (AppAccessDeniedException) {
			$this->addToAssertionCount(1);
		}

		$response = $middleware->afterException($controller, 'index', new AppAccessDeniedException());
		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
	}

	public function testAllowedUserPassesGate(): void
	{
		/** @var IUserManager $userManager */
		$userManager = \OC::$server->get(IUserManager::class);
		$userManager->createUser(self::ALLOWED, self::PASSWORD);

		/** @var IConfig $config */
		$config = \OC::$server->get(IConfig::class);
		$config->setAppValue(Application::APP_ID, AccessControlService::KEY_ACCESS_RESTRICTION, '1');
		$config->setAppValue(
			Application::APP_ID,
			AccessControlService::KEY_ACCESS_ALLOWED_USER_IDS,
			json_encode([self::ALLOWED], JSON_THROW_ON_ERROR),
		);

		/** @var IUserSession $session */
		$session = \OC::$server->get(IUserSession::class);
		$session->setUser($userManager->get(self::ALLOWED));

		/** @var DashboardController $controller */
		$controller = \OC::$server->get(DashboardController::class);
		$this->middlewareWithMockRequest()->beforeController($controller, 'index');
		$this->addToAssertionCount(1);
	}

	private function middlewareWithMockRequest(): AppAccessMiddleware
	{
		$request = $this->createMock(IRequest::class);
		$request->method('getPathInfo')->willReturn('/apps/projectcheck/');
		$request->method('getMethod')->willReturn('GET');
		$request->method('getHeader')->willReturnCallback(
			static fn (string $name): string => match (strtolower($name)) {
				'accept' => 'application/json',
				default => '',
			},
		);

		return new AppAccessMiddleware(
			\OC::$server->get(IUserSession::class),
			\OC::$server->get(AccessControlService::class),
			$request,
			\OC::$server->get(\OCP\IURLGenerator::class),
			\OC::$server->get(\OCP\L10N\IFactory::class),
			\OC::$server->get(\Psr\Log\LoggerInterface::class),
		);
	}
}
