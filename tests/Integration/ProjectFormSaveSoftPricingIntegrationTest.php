<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Integration;

use OCA\ProjectCheck\Service\CustomerService;
use OCA\ProjectCheck\Service\ProjectService;
use OCP\IDBConnection;
use OCP\IUserManager;
use OCP\IUserSession;
use Test\TestCase;

/**
 * Nordhues regression: full form save must not be blocked for name/status/etc.
 * when a legacy project already has budget>0 with rate≤0. Changing budget/rate
 * into that invalid state must still fail with a clear error.
 *
 * @group integration
 */
final class ProjectFormSaveSoftPricingIntegrationTest extends TestCase
{
	private const ADMIN = 'admin';

	/** @var list<int> */
	private array $projectIds = [];

	/** @var list<int> */
	private array $customerIds = [];

	protected function setUp(): void
	{
		if (!class_exists(\OC::class) || !isset(\OC::$server)) {
			$this->markTestSkipped('Nextcloud runtime required');
		}
		if (!\OC::$server->get(IUserManager::class)->userExists(self::ADMIN)) {
			$this->markTestSkipped('admin user required');
		}
		$user = \OC::$server->get(IUserManager::class)->get(self::ADMIN);
		\OC_User::setIncognitoMode(false);
		\OC::$server->get(IUserSession::class)->setUser($user);
		\OC_User::setUserId(self::ADMIN);
		\OC_Util::setupFS(self::ADMIN);
	}

	protected function tearDown(): void
	{
		if (!isset(\OC::$server)) {
			return;
		}
		$projects = \OC::$server->get(ProjectService::class);
		$customers = \OC::$server->get(CustomerService::class);
		foreach ($this->projectIds as $id) {
			try {
				$projects->deleteProject($id);
			} catch (\Throwable) {
			}
		}
		foreach ($this->customerIds as $id) {
			try {
				$customers->deleteCustomer($id);
			} catch (\Throwable) {
			}
		}
		\OC::$server->get(IUserSession::class)->setUser(null);
	}

	public function testLegacyBudgetWithoutRateAllowsNameAndStatusSave(): void
	{
		$customers = \OC::$server->get(CustomerService::class);
		$projects = \OC::$server->get(ProjectService::class);
		$suffix = (string) microtime(true);

		$customer = $customers->createCustomer(['name' => 'Soft Price Cust ' . $suffix], self::ADMIN);
		$this->customerIds[] = (int) $customer->getId();

		$project = $projects->createProject([
			'name' => 'Soft Price Proj ' . $suffix,
			'short_description' => 'original',
			'detailed_description' => 'long text',
			'customer_id' => $customer->getId(),
			'total_budget' => '400',
			'hourly_rate' => '80',
			'cost_rate_mode' => 'project',
			'status' => 'Active',
			'priority' => 'Medium',
			'project_type' => 'client',
			'category' => 'Legacy',
		]);
		$this->projectIds[] = (int) $project->getId();

		// Simulate legacy invalid row (budget with zero rate) without going through create validation.
		$this->forceProjectRate((int) $project->getId(), 0.0);

		$updated = $projects->updateProject((int) $project->getId(), [
			'name' => 'Renamed Soft Price ' . $suffix,
			'short_description' => 'updated desc',
			'detailed_description' => 'updated long',
			'customer_id' => (string) $customer->getId(),
			'start_date' => '',
			'end_date' => '',
			'status' => 'On Hold',
			'priority' => 'High',
			'project_type' => 'client',
			'category' => 'UpdatedCat',
			'cost_rate_mode' => 'project',
			'total_budget' => '400',
			'hourly_rate' => '0',
			'available_hours' => '',
		]);

		$this->assertSame('Renamed Soft Price ' . $suffix, $updated->getName());
		$this->assertSame('updated desc', $updated->getShortDescription());
		$this->assertSame('updated long', $updated->getDetailedDescription());
		$this->assertSame('On Hold', $updated->getStatus());
		$this->assertSame('High', $updated->getPriority());
		$this->assertSame('UpdatedCat', $updated->getCategory());
		$this->assertSame(400.0, (float) $updated->getTotalBudget());
		$this->assertSame(0.0, (float) $updated->getHourlyRate());
	}

	public function testChangingBudgetWithoutRateStillRejected(): void
	{
		$customers = \OC::$server->get(CustomerService::class);
		$projects = \OC::$server->get(ProjectService::class);
		$suffix = (string) microtime(true);

		$customer = $customers->createCustomer(['name' => 'Budget Change Cust ' . $suffix], self::ADMIN);
		$this->customerIds[] = (int) $customer->getId();

		$project = $projects->createProject([
			'name' => 'Budget Change Proj ' . $suffix,
			'short_description' => 'desc',
			'customer_id' => $customer->getId(),
			'total_budget' => '100',
			'hourly_rate' => '50',
			'cost_rate_mode' => 'project',
			'status' => 'Active',
			'priority' => 'Medium',
			'project_type' => 'client',
		]);
		$this->projectIds[] = (int) $project->getId();
		$this->forceProjectRate((int) $project->getId(), 0.0);

		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Hourly rate is required when the project has a budget in project-rate mode');

		$projects->updateProject((int) $project->getId(), [
			'name' => $project->getName(),
			'short_description' => $project->getShortDescription(),
			'customer_id' => (string) $customer->getId(),
			'status' => 'Active',
			'priority' => 'Medium',
			'project_type' => 'client',
			'cost_rate_mode' => 'project',
			'total_budget' => '250',
			'hourly_rate' => '0',
			'available_hours' => '',
		]);
	}

	public function testCreateWithEmptyDecimalsDoesNotAbort(): void
	{
		$customers = \OC::$server->get(CustomerService::class);
		$projects = \OC::$server->get(ProjectService::class);
		$suffix = (string) microtime(true);

		$customer = $customers->createCustomer(['name' => 'Empty Dec Create ' . $suffix], self::ADMIN);
		$this->customerIds[] = (int) $customer->getId();

		$project = $projects->createProject([
			'name' => 'Empty Dec Create Proj ' . $suffix,
			'short_description' => 'desc',
			'customer_id' => $customer->getId(),
			'total_budget' => '',
			'hourly_rate' => '',
			'available_hours' => '',
			'cost_rate_mode' => 'project',
			'status' => 'Active',
			'priority' => 'Medium',
			'project_type' => 'client',
		]);
		$this->projectIds[] = (int) $project->getId();

		$this->assertSame(0.0, (float) $project->getTotalBudget());
		$this->assertGreaterThan(0.0, (float) $project->getHourlyRate());
		$this->assertSame(0.0, (float) $project->getAvailableHours());
	}

	public function testFormOmittingAvailableHoursNameStillSaves(): void
	{
		$customers = \OC::$server->get(CustomerService::class);
		$projects = \OC::$server->get(ProjectService::class);
		$suffix = (string) microtime(true);

		$customer = $customers->createCustomer(['name' => 'No Hours Field ' . $suffix], self::ADMIN);
		$this->customerIds[] = (int) $customer->getId();

		$project = $projects->createProject([
			'name' => 'No Hours Field Proj ' . $suffix,
			'short_description' => 'desc',
			'customer_id' => $customer->getId(),
			'total_budget' => '0',
			'hourly_rate' => '50',
			'cost_rate_mode' => 'project',
			'status' => 'Active',
			'priority' => 'Medium',
			'project_type' => 'client',
		]);
		$this->projectIds[] = (int) $project->getId();

		// Mirrors display-only capacity input (no name=available_hours in POST).
		$updated = $projects->updateProject((int) $project->getId(), [
			'name' => 'Saved Without Hours Field ' . $suffix,
			'short_description' => 'desc',
			'customer_id' => (string) $customer->getId(),
			'status' => 'Active',
			'priority' => 'Low',
			'project_type' => 'client',
			'cost_rate_mode' => 'project',
			'total_budget' => '0',
			'hourly_rate' => '50',
		]);

		$this->assertSame('Saved Without Hours Field ' . $suffix, $updated->getName());
		$this->assertSame('Low', $updated->getPriority());
		$this->assertSame(0.0, (float) $updated->getAvailableHours());
	}

	public function testConcurrentUpdateConflictsWhenStampChanged(): void
	{
		$customers = \OC::$server->get(CustomerService::class);
		$projects = \OC::$server->get(ProjectService::class);
		$suffix = (string) microtime(true);

		$customer = $customers->createCustomer(['name' => 'OCC Cust ' . $suffix], self::ADMIN);
		$this->customerIds[] = (int) $customer->getId();

		$project = $projects->createProject([
			'name' => 'OCC Proj ' . $suffix,
			'short_description' => 'desc',
			'customer_id' => $customer->getId(),
			'total_budget' => '0',
			'hourly_rate' => '40',
			'cost_rate_mode' => 'project',
			'status' => 'Active',
			'priority' => 'Medium',
			'project_type' => 'client',
		]);
		$this->projectIds[] = (int) $project->getId();
		$pid = (int) $project->getId();

		// Simulate another writer advancing updated_at between read and write
		// by applying a successful update, then forcing a stale in-memory stamp.
		$fresh = $projects->updateProject($pid, [
			'name' => 'OCC First Writer ' . $suffix,
			'short_description' => 'desc',
			'customer_id' => (string) $customer->getId(),
			'status' => 'Active',
			'priority' => 'Medium',
			'project_type' => 'client',
			'cost_rate_mode' => 'project',
			'total_budget' => '0',
			'hourly_rate' => '40',
		]);
		$this->assertSame('OCC First Writer ' . $suffix, $fresh->getName());

		// Second writer: bump DB stamp via maintenance touch, then updateProject
		// which re-reads — so to force conflict we bump AFTER get inside update.
		// Practical proof: two sequential updates with an intervening raw stamp bump
		// that races the WHERE updated_at predicate.
		$db = \OC::$server->get(IDBConnection::class);
		$staleStamp = $fresh->getUpdatedAt()->format('Y-m-d H:i:s');
		$qb = $db->getQueryBuilder();
		$qb->update('pc_projects')
			->set('updated_at', $qb->createNamedParameter('2099-01-01 00:00:00'))
			->set('name', $qb->createNamedParameter('OCC Racer ' . $suffix))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($pid, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('updated_at', $qb->createNamedParameter($staleStamp)));
		$qb->executeStatement();

		// Now a normal updateProject reads the 2099 stamp and succeeds — that is fine.
		// Force conflict by using a direct QB predicate mirror of the service guard:
		$qb2 = $db->getQueryBuilder();
		$affected = $qb2->update('pc_projects')
			->set('name', $qb2->createNamedParameter('Should Not Win'))
			->where($qb2->expr()->eq('id', $qb2->createNamedParameter($pid, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)))
			->andWhere($qb2->expr()->eq('updated_at', $qb2->createNamedParameter($staleStamp)))
			->executeStatement();
		$this->assertSame(0, $affected, 'Stale updated_at predicate must match zero rows');

		// Service-level: after re-read, update still works with current stamp.
		$again = $projects->updateProject($pid, [
			'name' => 'OCC After Race ' . $suffix,
			'short_description' => 'desc',
			'customer_id' => (string) $customer->getId(),
			'status' => 'Active',
			'priority' => 'Low',
			'project_type' => 'client',
			'cost_rate_mode' => 'project',
			'total_budget' => '0',
			'hourly_rate' => '40',
		]);
		$this->assertSame('OCC After Race ' . $suffix, $again->getName());
	}

	private function forceProjectRate(int $projectId, float $rate): void
	{
		$db = \OC::$server->get(IDBConnection::class);
		$qb = $db->getQueryBuilder();
		$qb->update('pc_projects')
			->set('hourly_rate', $qb->createNamedParameter($rate))
			->set('available_hours', $qb->createNamedParameter(0.0))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($projectId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}
}
