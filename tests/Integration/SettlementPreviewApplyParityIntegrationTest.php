<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Integration;

use OCA\ProjectCheck\Exception\SettlementConflictException;
use OCA\ProjectCheck\Service\CustomerService;
use OCA\ProjectCheck\Service\LicenseService;
use OCA\ProjectCheck\Service\MobileBookingService;
use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Service\TimeEntryBillingService;
use OCA\ProjectCheck\Tests\Support\IntegrationTestUsers;
use OCA\ProjectCheck\Tests\Support\LicenseStateGuard;
use OCA\ProjectCheck\Tests\Support\Pc2TestSigning;
use OCA\ProjectCheck\Util\BillingStatus;
use OCP\IDBConnection;
use OCP\IUserManager;
use OCP\IUserSession;
use Test\TestCase;

/**
 * Flow-parity proof for the settlement preview → apply pair (web and mobile
 * both funnel into {@see TimeEntryBillingService::previewByFilters} /
 * {@see TimeEntryBillingService::applyByFilters}):
 *
 *  - happy path: apply must persist exactly what preview counted;
 *  - drift: rows added between preview and apply must abort the whole
 *    operation (stale_preview), never apply a different set than previewed;
 *  - replay: a consumed token must not apply the same batch twice.
 *
 * @group integration
 */
final class SettlementPreviewApplyParityIntegrationTest extends TestCase
{
	private const MEMBER = 'pc_par_member';
	private const PASS = 'Pc-Parity-Http-9xK!';
	private const ADMIN = 'admin';

	/** @var list<string> */
	private array $createdUsers = [];

	private LicenseStateGuard $licenseGuard;

	protected function setUp(): void
	{
		if (!class_exists(\OC::class) || !isset(\OC::$server)) {
			$this->markTestSkipped('Nextcloud runtime required');
		}
		putenv('PC_VENDOR_PUBLIC_KEY_B64=' . Pc2TestSigning::publicKeyB64());
		putenv('PC_ALLOW_VENDOR_KEY_OVERRIDE=1');
		\OC_User::setIncognitoMode(false);
		$this->licenseGuard = new LicenseStateGuard();
		$this->licenseGuard->setUp();
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
			\OCA\ProjectCheck\Tests\Support\IntegrationFixtureCleanup::purge(\OC::$server->get(IDBConnection::class));
			\OC::$server->get(IUserSession::class)->setUser(null);
		} finally {
			try {
				$this->licenseGuard->tearDown();
			} finally {
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

	public function testApplyPersistsExactlyWhatPreviewCounted(): void
	{
		$this->applyLicense(5);
		$this->assignSeat(self::MEMBER);
		$projectId = $this->ensureBookableProject();
		$this->createEntry($projectId, '2026-08-03', 60);
		$this->createEntry($projectId, '2026-08-04', 30);

		$service = \OC::$server->get(TimeEntryBillingService::class);
		$filters = [
			'billing_status' => BillingStatus::OPEN,
			'project_id' => $projectId,
			'date_from' => '2026-08-01',
			'date_to' => '2026-08-31',
		];

		$preview = $service->previewByFilters($filters, BillingStatus::INVOICED, self::ADMIN);
		self::assertSame(2, $preview['count']);
		self::assertNotNull($preview['token']);
		self::assertFalse($preview['capExceeded']);

		$result = $service->applyByFilters($filters, BillingStatus::INVOICED, self::ADMIN, $preview['token']);
		self::assertSame($preview['count'], $result['applied']);
		self::assertSame([], $result['failed']);

		// Post-state: nothing left in the source bucket for these filters.
		$after = $service->previewByFilters($filters, BillingStatus::INVOICED, self::ADMIN);
		self::assertSame(0, $after['count']);
	}

	public function testApplyAbortsWhenRowsChangedAfterPreview(): void
	{
		$this->applyLicense(5);
		$this->assignSeat(self::MEMBER);
		$projectId = $this->ensureBookableProject();
		$this->createEntry($projectId, '2026-08-05', 60);

		$service = \OC::$server->get(TimeEntryBillingService::class);
		$filters = [
			'billing_status' => BillingStatus::OPEN,
			'project_id' => $projectId,
			'date_from' => '2026-08-01',
			'date_to' => '2026-08-31',
		];

		$preview = $service->previewByFilters($filters, BillingStatus::INVOICED, self::ADMIN);
		self::assertSame(1, $preview['count']);
		self::assertNotNull($preview['token']);

		// Drift: a second open entry lands between preview and confirm.
		$this->createEntry($projectId, '2026-08-06', 45);

		try {
			$service->applyByFilters($filters, BillingStatus::INVOICED, self::ADMIN, $preview['token']);
			self::fail('Expected SettlementConflictException for drifted candidate set');
		} catch (SettlementConflictException $e) {
			self::assertSame(SettlementConflictException::CODE_STALE_PREVIEW, $e->getConflictCode());
		}

		// Fail-closed means fail-closed: both entries must still be open.
		$after = $service->previewByFilters($filters, BillingStatus::INVOICED, self::ADMIN);
		self::assertSame(2, $after['count']);
	}

	public function testConsumedTokenCannotReplayTheBatch(): void
	{
		$this->applyLicense(5);
		$this->assignSeat(self::MEMBER);
		$projectId = $this->ensureBookableProject();
		$this->createEntry($projectId, '2026-08-10', 60);

		$service = \OC::$server->get(TimeEntryBillingService::class);
		$filters = [
			'billing_status' => BillingStatus::OPEN,
			'project_id' => $projectId,
			'date_from' => '2026-08-01',
			'date_to' => '2026-08-31',
		];

		$preview = $service->previewByFilters($filters, BillingStatus::INVOICED, self::ADMIN);
		$result = $service->applyByFilters($filters, BillingStatus::INVOICED, self::ADMIN, $preview['token']);
		self::assertSame(1, $result['applied']);

		// Double-submit protection: replaying the consumed token must throw,
		// never silently re-apply or report success.
		$this->expectException(SettlementConflictException::class);
		$service->applyByFilters($filters, BillingStatus::INVOICED, self::ADMIN, $preview['token']);
	}

	private function createEntry(int $projectId, string $date, int $minutes): int
	{
		$created = \OC::$server->get(MobileBookingService::class)->createEntry(self::MEMBER, [
			'projectId' => $projectId,
			'date' => $date,
			'durationMinutes' => $minutes,
			'description' => 'Parity integration',
			'clientRequestId' => 'par-' . bin2hex(random_bytes(8)),
		]);
		return (int) $created['id'];
	}

	private function ensureBookableProject(): int
	{
		$this->loginAs(self::ADMIN);
		$customers = \OC::$server->get(CustomerService::class);
		$projects = \OC::$server->get(ProjectService::class);
		$suffix = bin2hex(random_bytes(3));
		$customer = $customers->createCustomer(['name' => 'PC Par Cust ' . $suffix], self::ADMIN);
		$project = $projects->createProject([
			'name' => 'PC Par Proj ' . $suffix,
			'short_description' => 'Settlement parity integration project',
			'customer_id' => (int) $customer->getId(),
			'status' => 'Active',
			'cost_rate_mode' => 'project',
			'hourly_rate' => 95,
		]);
		$projectId = (int) $project->getId();
		$projects->addTeamMember($projectId, self::MEMBER, ProjectService::DEFAULT_MEMBER_ROLE);
		return $projectId;
	}

	private function applyLicense(int $seats): void
	{
		$wire = Pc2TestSigning::signPayload([
			'v' => 2,
			'product' => 'projectcheck',
			'customerId' => 'pc-par-test',
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
