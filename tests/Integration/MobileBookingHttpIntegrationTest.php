<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Integration;

use OCA\ProjectCheck\Controller\MobileController;
use OCA\ProjectCheck\Db\LicenseStateMapper;
use OCA\ProjectCheck\Db\MobileSeatMapper;
use OCA\ProjectCheck\Exception\MobileGateException;
use OCA\ProjectCheck\Middleware\AppAccessMiddleware;
use OCA\ProjectCheck\Service\CustomerService;
use OCA\ProjectCheck\Service\LicenseService;
use OCA\ProjectCheck\Service\MobileBookingService;
use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Tests\Support\Pc2TestSigning;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;
use Test\TestCase;

/**
 * Seated user creates a time entry via mobile booking (happy path + seat gate + license remove).
 *
 * @group integration
 */
final class MobileBookingHttpIntegrationTest extends TestCase
{
	private const UID = 'pc_book_user';
	private const PASS = 'Pc-Book-Http-9xK!';
	private const ADMIN = 'admin';

	/** @var list<string> */
	private array $createdUsers = [];

	protected function setUp(): void
	{
		if (!class_exists(\OC::class) || !isset(\OC::$server)) {
			$this->markTestSkipped('Nextcloud runtime required');
		}
		putenv('PC_VENDOR_PUBLIC_KEY_B64=' . Pc2TestSigning::publicKeyB64());
		putenv('PC_ALLOW_VENDOR_KEY_OVERRIDE=1');
		\OC_User::setIncognitoMode(false);
		$this->wipeLicense();
		$this->ensureUser(self::UID);
		if (!\OC::$server->get(IUserManager::class)->userExists(self::ADMIN)) {
			$this->markTestSkipped('admin user required for project setup');
		}
	}

	protected function tearDown(): void
	{
		if (!isset(\OC::$server)) {
			return;
		}
		\OC::$server->get(IUserSession::class)->setUser(null);
		$this->wipeLicense();
		$um = \OC::$server->get(IUserManager::class);
		foreach ($this->createdUsers as $uid) {
			if ($um->userExists($uid)) {
				$um->get($uid)?->delete();
			}
		}
		$this->createdUsers = [];
		putenv('PC_VENDOR_PUBLIC_KEY_B64');
		putenv('PC_ALLOW_VENDOR_KEY_OVERRIDE');
	}

	public function testCreateTimeEntryAsSeatedUserReturnsEntry(): void
	{
		$this->applyLicense(5);
		$this->assignSeat(self::UID);
		$projectId = $this->ensureBookableProject();

		$booking = \OC::$server->get(MobileBookingService::class);
		$result = $booking->createEntry(self::UID, [
			'projectId' => $projectId,
			'date' => '2026-07-20',
			'durationMinutes' => 60,
			'description' => 'Integration booking',
		]);
		self::assertArrayHasKey('id', $result);
		self::assertSame(60, $result['durationMinutes']);
		self::assertSame('open', $result['billingStatus']);

		$list = $booking->listMyEntries(self::UID, '2026-07-20', '2026-07-20', 'open');
		self::assertNotEmpty($list['entries']);
	}

	public function testProjectsWithoutSeatReturns402ViaMiddleware(): void
	{
		$this->applyLicense(5);
		$this->loginAs(self::UID);
		$controller = \OC::$server->get(MobileController::class);
		$middleware = $this->middleware('/apps/projectcheck/mobile/v1/projects');
		try {
			$controller->projects(null, null);
			$this->fail('Expected MobileGateException seat_required');
		} catch (MobileGateException $e) {
			self::assertSame('seat_required', $e->getErrorCode());
			$response = $middleware->afterException($controller, 'projects', $e);
			self::assertInstanceOf(JSONResponse::class, $response);
			self::assertSame(402, $response->getStatus());
			self::assertSame('seat_required', $response->getData()['error']['code']);
		}
	}

	public function testRemoveLicenseClearsSeats(): void
	{
		$this->applyLicense(5);
		$this->assignSeat(self::UID);
		self::assertSame(1, \OC::$server->get(MobileSeatMapper::class)->countAll());
		\OC::$server->get(LicenseService::class)->remove();
		self::assertSame(0, \OC::$server->get(MobileSeatMapper::class)->countAll());
		self::assertNull(\OC::$server->get(LicenseStateMapper::class)->findSingleton());
	}

	public function testBootstrapIncludesMobileExpiresAt(): void
	{
		$this->applyLicense(5);
		$this->assignSeat(self::UID);
		$this->loginAs(self::UID);
		$data = \OC::$server->get(MobileController::class)->bootstrap()->getData();
		self::assertTrue($data['licensing']['mobile']['enabledForUser']);
		self::assertSame('2099-12-31', $data['licensing']['mobile']['expiresAt']);
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

	private function ensureBookableProject(): int
	{
		$this->loginAs(self::ADMIN);
		$customers = \OC::$server->get(CustomerService::class);
		$projects = \OC::$server->get(ProjectService::class);
		$suffix = bin2hex(random_bytes(3));
		$customer = $customers->createCustomer(['name' => 'PC Book Cust ' . $suffix], self::ADMIN);
		$customerId = (int)$customer->getId();
		$project = $projects->createProject([
			'name' => 'PC Book Proj ' . $suffix,
			'short_description' => 'Mobile booking integration project',
			'customer_id' => $customerId,
			'status' => 'Active',
			'cost_rate_mode' => 'project',
			'hourly_rate' => 85,
		]);
		$projectId = (int)$project->getId();
		$projects->addTeamMember($projectId, self::UID, ProjectService::DEFAULT_MEMBER_ROLE);
		return $projectId;
	}

	private function applyLicense(int $seats): void
	{
		$wire = Pc2TestSigning::signPayload([
			'v' => 2,
			'product' => 'projectcheck',
			'customerId' => 'pc-book-test',
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

	private function wipeLicense(): void
	{
		\OC::$server->get(MobileSeatMapper::class)->deleteAll();
		\OC::$server->get(LicenseStateMapper::class)->deleteAll();
	}

	private function ensureUser(string $uid): void
	{
		$um = \OC::$server->get(IUserManager::class);
		if (!$um->userExists($uid)) {
			$um->createUser($uid, self::PASS);
		}
		$this->createdUsers[] = $uid;
	}

	private function loginAs(string $uid): void
	{
		$user = \OC::$server->get(IUserManager::class)->get($uid);
		self::assertNotNull($user);
		\OC::$server->get(IUserSession::class)->setUser($user);
	}
}
