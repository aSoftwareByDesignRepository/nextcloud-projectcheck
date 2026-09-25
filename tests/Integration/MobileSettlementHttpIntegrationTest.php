<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Integration;

use OCA\ProjectCheck\Controller\MobileController;
use OCA\ProjectCheck\Db\LicenseStateMapper;
use OCA\ProjectCheck\Db\MobileSeatMapper;
use OCA\ProjectCheck\Service\CustomerService;
use OCA\ProjectCheck\Service\LicenseService;
use OCA\ProjectCheck\Service\MobileBookingService;
use OCA\ProjectCheck\Service\MobileSettlementService;
use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Tests\Support\IntegrationTestUsers;
use OCA\ProjectCheck\Tests\Support\LicenseStateGuard;
use OCA\ProjectCheck\Tests\Support\Pc2TestSigning;
use OCA\ProjectCheck\Util\BillingStatus;
use OCP\IUserManager;
use OCP\IUserSession;
use Test\TestCase;

/**
 * Mobile v1.1 settlement + idempotent create against a live Nextcloud DB.
 *
 * @group integration
 */
final class MobileSettlementHttpIntegrationTest extends TestCase
{
	private const MEMBER = 'pc_stl_member';
	private const PASS = 'Pc-Settle-Http-9xK!';
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
		$this->wipeLicenseSetUp();
		$this->ensureUser(self::MEMBER);
		if (!\OC::$server->get(IUserManager::class)->userExists(self::ADMIN)) {
			$this->markTestSkipped('admin user required for settlement setup');
		}
	}

	protected function tearDown(): void
	{
		if (!isset(\OC::$server)) {
			return;
		}
		try {
			\OCA\ProjectCheck\Tests\Support\IntegrationFixtureCleanup::purge(\OC::$server->get(\OCP\IDBConnection::class));
			\OC::$server->get(IUserSession::class)->setUser(null);
		} finally {
			// Shared-state restore is never skipped: a throw in fixture
			// cleanup must not strand the captured license/seat rows.
			try {
				$this->wipeLicenseTearDown();
			} finally {
				// Test-artifact cleanup is likewise never skipped — a
				// purge or license-restore throw must not leak the test
				// users or the vendor-key env override into the next test.
				$um = \OC::$server->get(IUserManager::class);
				foreach ($this->createdUsers as $uid) {
					if ($um->userExists($uid)) {
						$um->get($uid)?->delete();
					}
				}
				$this->createdUsers = [];
				putenv('PC_VENDOR_PUBLIC_KEY_B64');
				putenv('PC_ALLOW_VENDOR_KEY_OVERRIDE');
				parent::tearDown();
			}
		}
	}

	public function testSettlementListAndEntryBillingAsAdmin(): void
	{
		$this->applyLicense(5);
		$this->assignSeat(self::MEMBER);
		$projectId = $this->ensureBookableProject();

		$booking = \OC::$server->get(MobileBookingService::class);
		$created = $booking->createEntry(self::MEMBER, [
			'projectId' => $projectId,
			'date' => '2026-07-20',
			'durationMinutes' => 45,
			'description' => 'Settlement integration',
			'clientRequestId' => 'stl-create-' . bin2hex(random_bytes(4)),
		]);
		$entryId = (int)$created['id'];

		$settlement = \OC::$server->get(MobileSettlementService::class);
		$list = $settlement->listSettleableEntries(self::ADMIN, '2026-07-01', '2026-07-31', 'outstanding');
		self::assertArrayHasKey('hasMore', $list);
		self::assertArrayHasKey('limit', $list);
		$ids = array_map(static fn (array $row): int => (int)$row['id'], $list['entries']);
		self::assertContains($entryId, $ids);

		$changed = $settlement->changeEntryStatus(self::ADMIN, $entryId, BillingStatus::INVOICED);
		self::assertSame(BillingStatus::INVOICED, $changed['billingStatus']);

		// Controller path is seat-gated like all /mobile/v1 writes.
		$this->assignSeat(self::ADMIN);
		$this->loginAs(self::ADMIN);
		$controller = \OC::$server->get(MobileController::class);
		$payload = $controller->settlementEntries('2026-07-01', '2026-07-31', 'outstanding')->getData();
		self::assertArrayHasKey('entries', $payload);
		self::assertArrayHasKey('hasMore', $payload);
	}

	public function testIdempotentCreateReturnsSameEntry(): void
	{
		$this->applyLicense(5);
		$this->assignSeat(self::MEMBER);
		$projectId = $this->ensureBookableProject();
		$requestId = 'idem-' . bin2hex(random_bytes(8));

		$booking = \OC::$server->get(MobileBookingService::class);
		$payload = [
			'projectId' => $projectId,
			'date' => '2026-07-21',
			'durationMinutes' => 30,
			'description' => 'Idempotent create',
			'clientRequestId' => $requestId,
		];
		$first = $booking->createEntry(self::MEMBER, $payload);
		// A true offline retry resends the byte-identical payload.
		$second = $booking->createEntry(self::MEMBER, $payload);

		self::assertSame($first['id'], $second['id']);
		self::assertSame(30, $second['durationMinutes']);
	}

	public function testIdempotentCreateRejectsSameKeyDifferentPayload(): void
	{
		$this->applyLicense(5);
		$this->assignSeat(self::MEMBER);
		$projectId = $this->ensureBookableProject();
		$requestId = 'idem-' . bin2hex(random_bytes(8));

		$booking = \OC::$server->get(MobileBookingService::class);
		$booking->createEntry(self::MEMBER, [
			'projectId' => $projectId,
			'date' => '2026-07-21',
			'durationMinutes' => 30,
			'description' => 'Idempotent create',
			'clientRequestId' => $requestId,
		]);

		// Same clientRequestId + different payload must conflict (409), never
		// silently replay the original entry or create a second one.
		try {
			$booking->createEntry(self::MEMBER, [
				'projectId' => $projectId,
				'date' => '2026-07-21',
				'durationMinutes' => 30,
				'description' => 'Different data under the same key',
				'clientRequestId' => $requestId,
			]);
			self::fail('Expected idempotency_mismatch for same key with different payload');
		} catch (\OCA\ProjectCheck\Exception\MobileApiException $e) {
			self::assertSame('idempotency_mismatch', $e->getErrorCode());
			self::assertSame(409, $e->getHttpStatus());
		}
	}

	public function testMemberCannotSettle(): void
	{
		$this->applyLicense(5);
		$this->assignSeat(self::MEMBER);
		$settlement = \OC::$server->get(MobileSettlementService::class);
		$this->expectException(\OCA\ProjectCheck\Exception\MobileApiException::class);
		$settlement->listSettleableEntries(self::MEMBER, null, null, 'outstanding');
	}

	private function ensureBookableProject(): int
	{
		$this->loginAs(self::ADMIN);
		$customers = \OC::$server->get(CustomerService::class);
		$projects = \OC::$server->get(ProjectService::class);
		$suffix = bin2hex(random_bytes(3));
		$customer = $customers->createCustomer(['name' => 'PC Stl Cust ' . $suffix], self::ADMIN);
		$customerId = (int)$customer->getId();
		$project = $projects->createProject([
			'name' => 'PC Stl Proj ' . $suffix,
			'short_description' => 'Mobile settlement integration project',
			'customer_id' => $customerId,
			'status' => 'Active',
			'cost_rate_mode' => 'project',
			'hourly_rate' => 95,
		]);
		$projectId = (int)$project->getId();
		$projects->addTeamMember($projectId, self::MEMBER, ProjectService::DEFAULT_MEMBER_ROLE);
		return $projectId;
	}

	private function applyLicense(int $seats): void
	{
		$wire = Pc2TestSigning::signPayload([
			'v' => 2,
			'product' => 'projectcheck',
			'customerId' => 'pc-stl-test',
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
		$this->createdUsers[] = $uid;
	}

	private function loginAs(string $uid): void
	{
		$user = \OC::$server->get(IUserManager::class)->get($uid);
		self::assertNotNull($user);
		\OC::$server->get(IUserSession::class)->setUser($user);
	}
}
