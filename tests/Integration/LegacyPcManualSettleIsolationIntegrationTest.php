<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Integration;

use OCA\ProjectCheck\Service\CustomerService;
use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Service\TimeEntryBillingService;
use OCA\ProjectCheck\Service\TimeEntryService;
use OCA\ProjectCheck\Util\BillingStatus;
use OCP\App\IAppManager;
use OCP\IUserManager;
use OCP\IUserSession;
use Test\TestCase;

/**
 * AC-L2 / LEGACY-PC — time entry + manual settle with IC/CUC disabled.
 *
 * @group integration
 */
final class LegacyPcManualSettleIsolationIntegrationTest extends TestCase
{
	private const ACTOR = 'admin';

	/** @var array<string, bool> */
	private array $wasEnabled = [];

	protected function setUp(): void
	{
		if (!class_exists(\OC::class) || !isset(\OC::$server)) {
			$this->markTestSkipped('Nextcloud runtime required');
		}
		if (!\OC::$server->get(IUserManager::class)->userExists(self::ACTOR)) {
			$this->markTestSkipped('admin user required');
		}
	}

	protected function tearDown(): void
	{
		if (!isset(\OC::$server)) {
			return;
		}
		\OC::$server->get(IUserSession::class)->setUser(null);
		$apps = \OC::$server->get(IAppManager::class);
		foreach ($this->wasEnabled as $appId => $enabled) {
			try {
				if ($enabled) {
					$apps->enableApp($appId);
				}
			} catch (\Throwable) {
			}
		}
		$this->wasEnabled = [];
	}

	public function testCreateTimeEntryAndManualSettleWithSuiteHubsDisabled(): void
	{
		$apps = \OC::$server->get(IAppManager::class);
		foreach (['invoicecheck', 'customercheck'] as $appId) {
			$this->wasEnabled[$appId] = $apps->isEnabledForUser($appId);
			if ($this->wasEnabled[$appId]) {
				$apps->disableApp($appId);
			}
		}
		$this->assertFalse($apps->isEnabledForUser('invoicecheck'));
		$this->assertFalse($apps->isEnabledForUser('customercheck'));

		$user = \OC::$server->get(IUserManager::class)->get(self::ACTOR);
		$this->assertNotNull($user);
		\OC::$server->get(IUserSession::class)->setUser($user);

		$suffix = bin2hex(random_bytes(3));
		$customer = \OC::$server->get(CustomerService::class)->createCustomer([
			'name' => 'Legacy PC Cust ' . $suffix,
		], self::ACTOR);
		$project = \OC::$server->get(ProjectService::class)->createProject([
			'name' => 'Legacy PC Proj ' . $suffix,
			'short_description' => 'AC-L2 isolation settle',
			'customer_id' => (int)$customer->getId(),
			'status' => 'Active',
			'cost_rate_mode' => 'project',
			'hourly_rate' => 90,
		]);

		$entry = \OC::$server->get(TimeEntryService::class)->createTimeEntry([
			'project_id' => (int)$project->getId(),
			'date' => '2026-07-20',
			'hours' => 1.5,
			'description' => 'Legacy settle hours',
		], self::ACTOR);

		$this->assertSame(BillingStatus::OPEN, $entry->getBillingStatus());

		$billing = \OC::$server->get(TimeEntryBillingService::class);
		$invoiced = $billing->changeStatus((int)$entry->getId(), BillingStatus::INVOICED, self::ACTOR);
		$this->assertSame(BillingStatus::INVOICED, $invoiced->getBillingStatus());
		$paid = $billing->changeStatus((int)$entry->getId(), BillingStatus::PAID, self::ACTOR);
		$this->assertSame(BillingStatus::PAID, $paid->getBillingStatus());
	}
}
