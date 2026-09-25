<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Integration;

use OCA\ProjectCheck\Controller\MobileController;
use OCA\ProjectCheck\Db\LicenseStateMapper;
use OCA\ProjectCheck\Db\MobileSeatMapper;
use OCA\ProjectCheck\Exception\MobileGateException;
use OCA\ProjectCheck\Middleware\AppAccessMiddleware;
use OCA\ProjectCheck\Service\LicenseService;
use OCA\ProjectCheck\Service\MobileGateService;
use OCA\ProjectCheck\Tests\Support\IntegrationTestUsers;
use OCA\ProjectCheck\Tests\Support\LicenseStateGuard;
use OCA\ProjectCheck\Tests\Support\Pc2TestSigning;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;
use Test\TestCase;

/**
 * Mobile gate ladder via controller + middleware envelope.
 *
 * @group integration
 */
final class MobileGateHttpIntegrationTest extends TestCase
{
	private const ADMIN = 'pc_mob_admin';
	private const UID = 'pc_mob_user';
	private const PASS = 'Pc-Mob-Http-9xK!';

	/** @var list<string> */
	private array $testUsers = [];

	protected function setUp(): void
	{
		if (!class_exists(\OC::class) || !isset(\OC::$server)) {
			$this->markTestSkipped('Nextcloud runtime required');
		}
		putenv('PC_VENDOR_PUBLIC_KEY_B64=' . Pc2TestSigning::publicKeyB64());
		putenv('PC_ALLOW_VENDOR_KEY_OVERRIDE=1');
		\OC_User::setIncognitoMode(false);
		$this->wipeLicenseSetUp();
		$this->ensureUser(self::UID);
		$this->ensureUser(self::ADMIN);
	}

	protected function tearDown(): void
	{
		if (!isset(\OC::$server)) {
			return;
		}
		try {
			\OC::$server->get(IUserSession::class)->setUser(null);
		} finally {
			// Shared-state restore is never skipped: a throw in fixture
			// cleanup must not strand the captured license/seat rows.
			try {
				$this->wipeLicenseTearDown();
			} finally {
				// Test-artifact cleanup is likewise never skipped — a
				// license-restore throw must not leak the test users or
				// the vendor-key env override into the next test.
				$um = \OC::$server->get(IUserManager::class);
				foreach ($this->testUsers as $uid) {
					if ($um->userExists($uid)) {
						$um->get($uid)?->delete();
					}
				}
				$this->testUsers = [];
				putenv('PC_VENDOR_PUBLIC_KEY_B64');
				putenv('PC_ALLOW_VENDOR_KEY_OVERRIDE');
				parent::tearDown();
			}
		}
	}

	public function testBootstrapWithoutLicenseReportsNullEnvelope(): void
	{
		$this->loginAs(self::UID);
		$controller = \OC::$server->get(MobileController::class);
		$response = $controller->bootstrap();
		self::assertInstanceOf(JSONResponse::class, $response);
		$data = $response->getData();
		self::assertNull($data['licensing']);
		self::assertFalse($data['seatAssigned']);
		self::assertSame('available', $data['mobileAppStatus']);
	}

	public function testBootstrapWithLicenseIncludesVendorPublicKey(): void
	{
		$this->applyLicense(5);
		$this->assignSeat(self::UID);
		$this->loginAs(self::UID);
		$data = \OC::$server->get(MobileController::class)->bootstrap()->getData();
		self::assertIsArray($data['licensing']);
		self::assertArrayHasKey('vendorPublicKeyB64', $data['licensing']);
		self::assertNotSame('', $data['licensing']['vendorPublicKeyB64']);
		self::assertArrayHasKey('envelope', $data['licensing']);
		self::assertTrue($data['seatAssigned']);
	}

	public function testProjectsWithoutLicenseReturns402LicenseMissing(): void
	{
		$this->loginAs(self::UID);
		$this->assertGateCode('license_missing', function () {
			return \OC::$server->get(MobileController::class)->projects(null, null);
		});
	}

	public function testProjectsWithoutSeatReturns402SeatRequired(): void
	{
		$this->applyLicense(5);
		$this->loginAs(self::UID);
		$this->assertGateCode('seat_required', function () {
			return \OC::$server->get(MobileController::class)->projects(null, null);
		});
	}

	public function testProjectsWithSeatReturns200(): void
	{
		$this->applyLicense(5);
		$this->assignSeat(self::UID);
		$this->loginAs(self::UID);
		$response = \OC::$server->get(MobileController::class)->projects(null, '10');
		self::assertSame(200, $response->getStatus());
		$data = $response->getData();
		self::assertArrayHasKey('projects', $data);
		self::assertIsArray($data['projects']);
	}

	public function testBootstrapWithLicenseExposesEnvelope(): void
	{
		$this->applyLicense(5);
		$this->assignSeat(self::UID);
		$this->loginAs(self::UID);
		$data = \OC::$server->get(MobileController::class)->bootstrap()->getData();
		self::assertNotNull($data['licensing']);
		self::assertSame('PC2', $data['licensing']['format']);
		self::assertTrue($data['seatAssigned']);
		self::assertNotSame('', $data['licensing']['payloadB64']);
		self::assertNotSame('', $data['licensing']['signatureB64']);
	}

	/**
	 * @param callable(): JSONResponse $call
	 */
	private function assertGateCode(string $code, callable $call): void
	{
		$controller = \OC::$server->get(MobileController::class);
		$middleware = $this->middleware('/apps/projectcheck/mobile/v1/projects');
		try {
			$call();
			$this->fail('Expected MobileGateException ' . $code);
		} catch (MobileGateException $e) {
			self::assertSame($code, $e->getErrorCode());
			$response = $middleware->afterException($controller, 'projects', $e);
			self::assertInstanceOf(JSONResponse::class, $response);
			self::assertSame(402, $response->getStatus());
			$body = $response->getData();
			self::assertSame($code, $body['error']['code']);
			self::assertNotSame('', $body['error']['message']);
		}
	}

	private function middleware(string $path): AppAccessMiddleware
	{
		$request = $this->createMock(IRequest::class);
		$request->method('getPathInfo')->willReturn($path);
		$request->method('getMethod')->willReturn('GET');
		$request->method('getHeader')->willReturn('');
		return new AppAccessMiddleware(
			\OC::$server->get(IUserSession::class),
			\OC::$server->get(\OCA\ProjectCheck\Service\AccessControlService::class),
			$request,
			\OC::$server->get(\OCP\IURLGenerator::class),
			\OC::$server->get(\OCP\L10N\IFactory::class),
			\OC::$server->get(\Psr\Log\LoggerInterface::class),
		);
	}

	private function applyLicense(int $seats): void
	{
		$wire = Pc2TestSigning::signPayload([
			'v' => 2,
			'product' => 'projectcheck',
			'customerId' => 'pc-mob-test',
			'issuedAt' => '2026-01-01',
			'validUntil' => '2099-12-31',
			'mobileSeats' => $seats,
		]);
		\OC::$server->get(LicenseService::class)->apply(self::ADMIN, $wire);
	}

	private function assignSeat(string $uid): void
	{
		\OC::$server->get(LicenseService::class)->assignSeat(self::ADMIN, $uid);
	}

	private LicenseStateGuard $licenseGuard;

	/**
	 * Capture-and-restore, not unscoped deleteAll: a suite run on a
	 * licensed instance must not destroy the installed license or real
	 * seat assignments. setUp captures + clears; tearDown clears the
	 * test-created state and re-inserts the captured rows.
	 */
	private function wipeLicenseSetUp(): void
	{
		$this->licenseGuard = new LicenseStateGuard();
		$this->licenseGuard->setUp();
	}

	private function wipeLicenseTearDown(): void
	{
		$this->licenseGuard->tearDown();
	}

	private function ensureUser(string $uid): void
	{
		$um = \OC::$server->get(IUserManager::class);
		IntegrationTestUsers::ensure($um, $uid, self::PASS);
		$this->testUsers[] = $uid;
	}

	private function loginAs(string $uid): void
	{
		$user = \OC::$server->get(IUserManager::class)->get($uid);
		self::assertNotNull($user);
		\OC::$server->get(IUserSession::class)->setUser($user);
	}
}
