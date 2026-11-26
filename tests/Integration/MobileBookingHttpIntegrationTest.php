<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Integration;

use OCA\ProjectCheck\Controller\MobileController;
use OCA\ProjectCheck\Db\LicenseStateMapper;
use OCA\ProjectCheck\Db\MobileSeatMapper;
use OCA\ProjectCheck\Exception\MobileApiException;
use OCA\ProjectCheck\Exception\MobileGateException;
use OCA\ProjectCheck\Middleware\AppAccessMiddleware;
use OCA\ProjectCheck\Service\CustomerService;
use OCA\ProjectCheck\Service\LicenseService;
use OCA\ProjectCheck\Service\MobileBookingService;
use OCA\ProjectCheck\Service\MobileCatalogService;
use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Tests\Support\IntegrationTestUsers;
use OCA\ProjectCheck\Tests\Support\LicenseStateGuard;
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
		$this->wipeLicenseSetUp();
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
		\OC::$server->get(LicenseService::class)->remove(self::ADMIN);
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

	public function testCatalogCreateCustomerIdempotentReplay(): void
	{
		$this->loginAs(self::ADMIN);
		$catalog = \OC::$server->get(MobileCatalogService::class);
		$name = 'PC Cat Cust ' . bin2hex(random_bytes(3));
		$key = 'tc-' . bin2hex(random_bytes(6));
		$first = $catalog->createCustomer(self::ADMIN, ['name' => $name, 'clientRequestId' => $key]);
		self::assertArrayHasKey('id', $first['customer']);
		self::assertSame($name, $first['customer']['name']);

		$replay = $catalog->createCustomer(self::ADMIN, ['name' => $name, 'clientRequestId' => $key]);
		self::assertSame($first['customer']['id'], $replay['customer']['id']);

		try {
			$catalog->createCustomer(self::ADMIN, ['name' => 'PC Cat Other ' . bin2hex(random_bytes(3)), 'clientRequestId' => $key]);
			$this->fail('Expected idempotency_mismatch');
		} catch (MobileApiException $e) {
			self::assertSame('idempotency_mismatch', $e->getErrorCode());
			self::assertSame(409, $e->getHttpStatus());
		}
	}

	public function testCatalogCreateProjectReturnsRow(): void
	{
		$this->loginAs(self::ADMIN);
		$catalog = \OC::$server->get(MobileCatalogService::class);
		$customer = $catalog->createCustomer(self::ADMIN, ['name' => 'PC Cat Cust ' . bin2hex(random_bytes(3))]);
		$project = $catalog->createProject(self::ADMIN, [
			'name' => 'PC Cat Proj ' . bin2hex(random_bytes(3)),
			'customerId' => $customer['customer']['id'],
			'clientRequestId' => 'tp-' . bin2hex(random_bytes(6)),
		]);
		self::assertSame('active', $project['project']['status']);
		self::assertSame($customer['customer']['id'], $project['project']['customerId']);
		self::assertSame($customer['customer']['name'], $project['project']['customerName']);
	}

	public function testCatalogCreateProjectForbiddenForNonAdmin(): void
	{
		$this->loginAs(self::UID);
		$catalog = \OC::$server->get(MobileCatalogService::class);
		try {
			$catalog->createProject(self::UID, ['name' => 'PC Cat Proj ' . bin2hex(random_bytes(3)), 'customerId' => 1]);
			$this->fail('Expected forbidden');
		} catch (MobileApiException $e) {
			self::assertSame('forbidden', $e->getErrorCode());
			self::assertSame(403, $e->getHttpStatus());
		}
	}

	public function testCatalogCreateProjectIdempotentReplay(): void
	{
		$this->loginAs(self::ADMIN);
		$catalog = \OC::$server->get(MobileCatalogService::class);
		$customer = $catalog->createCustomer(self::ADMIN, ['name' => 'PC Cat Cust ' . bin2hex(random_bytes(3))]);
		$cid = $customer['customer']['id'];
		$key = 'tp-' . bin2hex(random_bytes(6));
		$body = ['name' => 'PC Cat Proj ' . bin2hex(random_bytes(3)), 'customerId' => $cid, 'clientRequestId' => $key];
		$first = $catalog->createProject(self::ADMIN, $body);
		$replay = $catalog->createProject(self::ADMIN, $body);
		self::assertSame($first['project']['id'], $replay['project']['id']);

		try {
			$catalog->createProject(self::ADMIN, ['name' => 'PC Cat Other ' . bin2hex(random_bytes(3)), 'customerId' => $cid, 'clientRequestId' => $key]);
			$this->fail('Expected idempotency_mismatch');
		} catch (MobileApiException $e) {
			self::assertSame('idempotency_mismatch', $e->getErrorCode());
			self::assertSame(409, $e->getHttpStatus());
		}
	}

	public function testCatalogReplayAfterEntityDeletedReturns409(): void
	{
		$this->loginAs(self::ADMIN);
		$catalog = \OC::$server->get(MobileCatalogService::class);
		$name = 'PC Cat Cust ' . bin2hex(random_bytes(3));
		$key = 'tc-' . bin2hex(random_bytes(6));
		$created = $catalog->createCustomer(self::ADMIN, ['name' => $name, 'clientRequestId' => $key]);

		$customers = \OC::$server->get(CustomerService::class);
		$entity = $customers->getCustomer((int)$created['customer']['id']);
		self::assertNotNull($entity);
		\OC::$server->get(\OCA\ProjectCheck\Db\CustomerMapper::class)->delete($entity);

		try {
			$catalog->createCustomer(self::ADMIN, ['name' => $name, 'clientRequestId' => $key]);
			$this->fail('Expected conflict for replay against a deleted entity');
		} catch (MobileApiException $e) {
			self::assertSame('conflict', $e->getErrorCode());
			self::assertSame(409, $e->getHttpStatus());
		}
	}

	public function testCatalogCreateCustomerForbiddenForNonAdmin(): void
	{
		$this->loginAs(self::UID);
		$catalog = \OC::$server->get(MobileCatalogService::class);
		try {
			$catalog->createCustomer(self::UID, ['name' => 'PC Cat Cust ' . bin2hex(random_bytes(3))]);
			$this->fail('Expected forbidden');
		} catch (MobileApiException $e) {
			self::assertSame('forbidden', $e->getErrorCode());
			self::assertSame(403, $e->getHttpStatus());
		}
	}

	public function testCatalogCreateCustomerInvalidEmailIs422(): void
	{
		$this->loginAs(self::ADMIN);
		$catalog = \OC::$server->get(MobileCatalogService::class);
		try {
			$catalog->createCustomer(self::ADMIN, ['name' => 'PC Cat Cust ' . bin2hex(random_bytes(3)), 'email' => 'not-an-email']);
			$this->fail('Expected validation');
		} catch (MobileApiException $e) {
			self::assertSame(422, $e->getHttpStatus());
		}
	}

	public function testCatalogRejectsMalformedClientRequestId(): void
	{
		$this->loginAs(self::ADMIN);
		$catalog = \OC::$server->get(MobileCatalogService::class);
		foreach (['bad id with spaces', str_repeat('x', 61), 'injection\n<script>'] as $bad) {
			try {
				$catalog->createCustomer(self::ADMIN, ['name' => 'PC Cat Cust ' . bin2hex(random_bytes(3)), 'clientRequestId' => $bad]);
				$this->fail('Expected validation for ' . var_export($bad, true));
			} catch (MobileApiException $e) {
				self::assertSame(422, $e->getHttpStatus());
				self::assertSame('invalid', $e->getDetails()['fields']['clientRequestId'] ?? null);
			}
		}
	}

	public function testCatalogCreateCustomerRejectsNonStringName(): void
	{
		$this->loginAs(self::ADMIN);
		$catalog = \OC::$server->get(MobileCatalogService::class);
		foreach ([['x'], 123, ['nested' => 'array'], true] as $badName) {
			try {
				$catalog->createCustomer(self::ADMIN, ['name' => $badName]);
				$this->fail('Expected validation for ' . json_encode($badName));
			} catch (MobileApiException $e) {
				self::assertSame(422, $e->getHttpStatus());
				self::assertSame('validation', $e->getErrorCode());
			}
		}
	}

	public function testCatalogCreateProjectRejectsNonStringName(): void
	{
		$this->loginAs(self::ADMIN);
		$catalog = \OC::$server->get(MobileCatalogService::class);
		$customer = $catalog->createCustomer(self::ADMIN, ['name' => 'PC Cat Cust ' . bin2hex(random_bytes(3))]);
		foreach ([['x'], 123] as $badName) {
			try {
				$catalog->createProject(self::ADMIN, ['name' => $badName, 'customerId' => $customer['customer']['id']]);
				$this->fail('Expected validation for ' . json_encode($badName));
			} catch (MobileApiException $e) {
				self::assertSame(422, $e->getHttpStatus());
			}
		}
		// name=123 WITH shortDescription bypassed the accidental short_description
		// fallback upstream and detonated strlen(int) → TypeError → 500. Must be 422.
		try {
			$catalog->createProject(self::ADMIN, [
				'name' => 123,
				'shortDescription' => 'x',
				'customerId' => $customer['customer']['id'],
			]);
			$this->fail('Expected validation for int name with shortDescription');
		} catch (MobileApiException $e) {
			self::assertSame(422, $e->getHttpStatus());
		}
		// tags as array previously flowed into a TEXT column insert → 500.
		try {
			$catalog->createProject(self::ADMIN, [
				'name' => 'PC Cat Proj ' . bin2hex(random_bytes(3)),
				'customerId' => $customer['customer']['id'],
				'tags' => ['a', 'b'],
			]);
			$this->fail('Expected validation for array tags');
		} catch (MobileApiException $e) {
			self::assertSame(422, $e->getHttpStatus());
		}
	}

	public function testCatalogCreateProjectRejectsGarbageDate(): void
	{
		$this->loginAs(self::ADMIN);
		$catalog = \OC::$server->get(MobileCatalogService::class);
		$customer = $catalog->createCustomer(self::ADMIN, ['name' => 'PC Cat Cust ' . bin2hex(random_bytes(3))]);
		try {
			$catalog->createProject(self::ADMIN, [
				'name' => 'PC Cat Proj ' . bin2hex(random_bytes(3)),
				'customerId' => $customer['customer']['id'],
				'startDate' => 'banana',
			]);
			$this->fail('Expected validation for unparseable startDate');
		} catch (MobileApiException $e) {
			self::assertSame(422, $e->getHttpStatus());
		}
	}

	public function testCatalogListCustomersReturnsRows(): void
	{
		$this->loginAs(self::ADMIN);
		$catalog = \OC::$server->get(MobileCatalogService::class);
		$name = 'PC Cat Cust ' . bin2hex(random_bytes(3));
		$catalog->createCustomer(self::ADMIN, ['name' => $name]);
		$list = $catalog->listCustomers(self::ADMIN);
		$names = array_column($list['customers'], 'name');
		self::assertContains($name, $names);
	}

	public function testCatalogCreateWithoutClientRequestIdIsNotIdempotent(): void
	{
		$this->loginAs(self::ADMIN);
		$catalog = \OC::$server->get(MobileCatalogService::class);
		// Same payload twice, no key: must create two entities (same rule as the
		// web form — idempotency is opt-in via clientRequestId).
		$a = $catalog->createCustomer(self::ADMIN, ['name' => 'PC Cat A ' . bin2hex(random_bytes(3))]);
		$b = $catalog->createCustomer(self::ADMIN, ['name' => 'PC Cat B ' . bin2hex(random_bytes(3))]);
		self::assertNotSame($a['customer']['id'], $b['customer']['id']);
	}

	public function testCatalogCustomersWithoutSeatReturns402(): void
	{
		$this->applyLicense(5);
		$this->loginAs(self::UID);
		$controller = \OC::$server->get(MobileController::class);
		try {
			$controller->customers();
			$this->fail('Expected MobileGateException seat_required');
		} catch (MobileGateException $e) {
			self::assertSame('seat_required', $e->getErrorCode());
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

	public function testUpstreamCreateProjectRejectsGarbageAndImpossibleDates(): void
	{
		$this->loginAs(self::ADMIN);
		$customers = \OC::$server->get(CustomerService::class);
		$projects = \OC::$server->get(ProjectService::class);
		$customer = $customers->createCustomer(['name' => 'PC Up Cust ' . bin2hex(random_bytes(3))], self::ADMIN);
		$base = [
			'name' => 'PC Up Proj ' . bin2hex(random_bytes(3)),
			'short_description' => 'x',
			'customer_id' => $customer->getId(),
		];
		// 'banana' previously → NULL start_date silently stored; '2026-02-30'
		// previously normalized to March 2nd; 'next Friday' passed the range
		// check via \DateTime then silently stored NULL.
		foreach (['banana', '2026-02-30', 'next Friday'] as $bad) {
			try {
				$projects->createProject($base + ['start_date' => $bad]);
				$this->fail('Expected rejection for ' . json_encode($bad));
			} catch (\Exception $e) {
				self::assertStringContainsString('Invalid start or end date', $e->getMessage());
			}
		}
		// Non-string dates hit the field-type guard → 'must be a string'.
		try {
			$projects->createProject($base + ['start_date' => ['x']]);
			$this->fail('Expected rejection for array start_date');
		} catch (\Exception $e) {
			self::assertStringContainsString('must be a string', $e->getMessage());
		}
		// European + ISO formats remain accepted.
		$p = $projects->createProject($base + ['start_date' => '15.03.2026', 'end_date' => '2026-04-01']);
		self::assertSame('2026-03-15', $p->getStartDate()->format('Y-m-d'));
		self::assertSame('2026-04-01', $p->getEndDate()->format('Y-m-d'));
	}

	public function testUpstreamUpdateProjectRejectsGarbageDateAndKeepsStored(): void
	{
		$this->loginAs(self::ADMIN);
		$customers = \OC::$server->get(CustomerService::class);
		$projects = \OC::$server->get(ProjectService::class);
		$customer = $customers->createCustomer(['name' => 'PC Up Cust ' . bin2hex(random_bytes(3))], self::ADMIN);
		$project = $projects->createProject([
			'name' => 'PC Up Proj ' . bin2hex(random_bytes(3)),
			'short_description' => 'x',
			'customer_id' => $customer->getId(),
			'start_date' => '2026-01-10',
		]);
		try {
			$projects->updateProject($project->getId(), ['start_date' => 'banana']);
			$this->fail('Expected rejection for garbage start_date on update');
		} catch (\Exception $e) {
			self::assertStringContainsString('Invalid start or end date', $e->getMessage());
		}
		// The stored date must be untouched — the update is atomic.
		self::assertSame('2026-01-10', $projects->getProject($project->getId())->getStartDate()->format('Y-m-d'));
	}

	public function testUpstreamCreateProjectRejectsNonStringFields(): void
	{
		$this->loginAs(self::ADMIN);
		$customers = \OC::$server->get(CustomerService::class);
		$projects = \OC::$server->get(ProjectService::class);
		$customer = $customers->createCustomer(['name' => 'PC Up Cust ' . bin2hex(random_bytes(3))], self::ADMIN);
		// name=123 with short_description previously reached strlen(int) →
		// TypeError 500 on the web store/apiStore paths (mobile masked it).
		foreach ([
			['name' => 123, 'short_description' => 'x', 'customer_id' => $customer->getId()],
			['name' => 'PC Up Proj ' . bin2hex(random_bytes(3)), 'short_description' => 'x',
				'customer_id' => $customer->getId(), 'cost_rate_mode' => 123],
		] as $payload) {
			try {
				$projects->createProject($payload);
				$this->fail('Expected rejection for ' . json_encode($payload));
			} catch (\Exception $e) {
				self::assertStringContainsString('must be a string', $e->getMessage());
			}
		}
	}

	public function testUpstreamCustomerWritePathsRejectNonStringFields(): void
	{
		$this->loginAs(self::ADMIN);
		$customers = \OC::$server->get(CustomerService::class);
		// createCustomer: name=123 previously detonated findByName(string).
		try {
			$customers->createCustomer(['name' => 123], self::ADMIN);
			$this->fail('Expected rejection for int name');
		} catch (\Exception $e) {
			self::assertStringContainsString('must be a string', $e->getMessage());
		}
		// validateCustomerData (web store/update pre-check) must return errors,
		// not TypeError, on array fields.
		$errors = $customers->validateCustomerData(['name' => 'x', 'phone' => ['a']]);
		self::assertArrayHasKey('phone', $errors);
		// updateCustomer: array phone previously detonated Customer::validate().
		$customer = $customers->createCustomer(['name' => 'PC Up Cust ' . bin2hex(random_bytes(3))], self::ADMIN);
		try {
			$customers->updateCustomer($customer->getId(), ['phone' => ['a']]);
			$this->fail('Expected rejection for array phone on update');
		} catch (\Exception $e) {
			self::assertStringContainsString('must be a string', $e->getMessage());
		}
	}

	public function testCreateEntryRejectsNonScalarFields(): void
	{
		$this->applyLicense(5);
		$this->assignSeat(self::UID);
		$projectId = $this->ensureBookableProject();
		$booking = \OC::$server->get(MobileBookingService::class);

		// projectId ["x"] → (int)1 under PHP array cast: silently books project 1
		// when bookable, or throws a bogus 403 that leaks whether project 1 is
		// bookable (existence oracle). Must be a 422 field error.
		foreach ([['x'], new \stdClass(), true] as $badProjectId) {
			try {
				$booking->createEntry(self::UID, [
					'projectId' => $badProjectId,
					'date' => '2026-07-20',
					'durationMinutes' => 60,
				]);
				$this->fail('non-scalar projectId accepted: ' . json_encode($badProjectId));
			} catch (MobileApiException $e) {
				self::assertSame(422, $e->getHttpStatus());
			}
		}

		// (int) truncation silently targets the WRONG project: "12abc"→12,
		// 12.9→12 — a crafted id books project 12 with no error. Rejected now.
		foreach (['12abc', 12.9] as $fuzzyProjectId) {
			try {
				$booking->createEntry(self::UID, [
					'projectId' => $fuzzyProjectId,
					'date' => '2026-07-20',
					'durationMinutes' => 60,
				]);
				$this->fail('truncatable projectId accepted: ' . json_encode($fuzzyProjectId));
			} catch (MobileApiException $e) {
				self::assertSame(422, $e->getHttpStatus());
			}
		}

		// description ["x"] → (string)"Array" stored verbatim in the entry.
		try {
			$booking->createEntry(self::UID, [
				'projectId' => $projectId,
				'date' => '2026-07-20',
				'durationMinutes' => 60,
				'description' => ['x'],
			]);
			$this->fail('array description accepted');
		} catch (MobileApiException $e) {
			self::assertSame(422, $e->getHttpStatus());
		}

		// clientRequestId ["x"] → "Array", which PASSES the charset regex — a
		// garbage idempotency key shared by every array-typed submission.
		try {
			$booking->createEntry(self::UID, [
				'projectId' => $projectId,
				'date' => '2026-07-20',
				'durationMinutes' => 60,
				'clientRequestId' => ['x'],
			]);
			$this->fail('array clientRequestId accepted');
		} catch (MobileApiException $e) {
			self::assertSame(422, $e->getHttpStatus());
		}
	}

	public function testUpdateEntryRejectsNonScalarFields(): void
	{
		$this->applyLicense(5);
		$this->assignSeat(self::UID);
		$projectId = $this->ensureBookableProject();
		$booking = \OC::$server->get(MobileBookingService::class);
		$entry = $booking->createEntry(self::UID, [
			'projectId' => $projectId,
			'date' => '2026-07-20',
			'durationMinutes' => 60,
			'description' => 'orig',
		]);
		$id = (int)$entry['id'];

		try {
			$booking->updateEntry(self::UID, $id, ['projectId' => ['x']]);
			$this->fail('array projectId accepted on update');
		} catch (MobileApiException $e) {
			self::assertSame(422, $e->getHttpStatus());
		}
		try {
			$booking->updateEntry(self::UID, $id, ['description' => ['x']]);
			$this->fail('array description accepted on update');
		} catch (MobileApiException $e) {
			self::assertSame(422, $e->getHttpStatus());
		}
		// Stored values untouched by the rejected writes.
		$stored = $booking->listMyEntries(self::UID, '2026-07-20', '2026-07-20', 'all')['entries'];
		$mine = array_values(array_filter($stored, static fn ($e) => (int)$e['id'] === $id));
		self::assertSame('orig', $mine[0]['description']);
		self::assertSame($projectId, (int)$mine[0]['projectId']);
	}

	public function testMobileGetEndpointsRejectArrayQueryParams(): void
	{
		$this->applyLicense(5);
		$this->assignSeat(self::UID);
		$this->loginAs(self::UID);
		$controller = \OC::$server->get(MobileController::class);
		// ?q[]=x / ?from[]=x reach ?string params as arrays → TypeError → 500.
		// A machine API must answer 422, not detonate.
		try {
			$controller->timeEntries(['x'], null, null);
			$this->fail('array from accepted');
		} catch (MobileApiException $e) {
			self::assertSame(422, $e->getHttpStatus());
		}
		try {
			$controller->projects(['x'], null);
			$this->fail('array q accepted');
		} catch (MobileApiException $e) {
			self::assertSame(422, $e->getHttpStatus());
		}
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
