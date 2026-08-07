<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Integration;

use OCA\ProjectCheck\Service\CustomerService;
use OCA\ProjectCheck\Service\ProjectService;
use OCP\IUserManager;
use OCP\IUserSession;
use Test\TestCase;

/**
 * Regression: changing a project's customer via the edit form must succeed when
 * available_hours is submitted as "" (zero-capacity projects). Previously MariaDB
 * rejected DECIMAL '' and the update failed after the inline customer was created.
 *
 * @group integration
 */
final class ProjectCustomerReassignIntegrationTest extends TestCase
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
				// best-effort cleanup
			}
		}
		foreach ($this->customerIds as $id) {
			try {
				$customers->deleteCustomer($id);
			} catch (\Throwable) {
				// best-effort cleanup
			}
		}
		\OC::$server->get(IUserSession::class)->setUser(null);
	}

	public function testUpdateProjectAcceptsEmptyAvailableHoursWhenReassigningCustomer(): void
	{
		$customers = \OC::$server->get(CustomerService::class);
		$projects = \OC::$server->get(ProjectService::class);
		$suffix = (string) microtime(true);

		$customerA = $customers->createCustomer(['name' => 'Reassign A ' . $suffix], self::ADMIN);
		$customerB = $customers->createCustomer(['name' => 'Reassign B ' . $suffix], self::ADMIN);
		$this->customerIds[] = (int) $customerA->getId();
		$this->customerIds[] = (int) $customerB->getId();

		$project = $projects->createProject([
			'name' => 'Reassign Proj ' . $suffix,
			'short_description' => 'Integration reassign',
			'customer_id' => $customerA->getId(),
			'total_budget' => '0',
			'hourly_rate' => '120',
			'cost_rate_mode' => 'project',
			'status' => 'Active',
			'priority' => 'Medium',
			'project_type' => 'client',
		]);
		$this->projectIds[] = (int) $project->getId();

		$this->assertSame((int) $customerA->getId(), (int) $project->getCustomerId());

		// Exact HTML form shape from project-form.php when capacity is zero.
		$updated = $projects->updateProject((int) $project->getId(), [
			'name' => $project->getName(),
			'short_description' => $project->getShortDescription(),
			'detailed_description' => '',
			'customer_id' => (string) $customerB->getId(),
			'start_date' => '',
			'end_date' => '',
			'status' => 'Active',
			'priority' => 'Medium',
			'project_type' => 'client',
			'category' => '',
			'cost_rate_mode' => 'project',
			'total_budget' => '0',
			'hourly_rate' => '120',
			'available_hours' => '',
		]);

		$this->assertSame((int) $customerB->getId(), (int) $updated->getCustomerId());
		$this->assertSame(0.0, (float) $updated->getAvailableHours());
	}

	public function testUpdateProjectCoercesEmptyTotalBudgetDecimal(): void
	{
		$customers = \OC::$server->get(CustomerService::class);
		$projects = \OC::$server->get(ProjectService::class);
		$suffix = (string) microtime(true);

		$customer = $customers->createCustomer(['name' => 'Empty Budget ' . $suffix], self::ADMIN);
		$this->customerIds[] = (int) $customer->getId();

		$project = $projects->createProject([
			'name' => 'Empty Budget Proj ' . $suffix,
			'short_description' => 'desc',
			'customer_id' => $customer->getId(),
			'total_budget' => '500',
			'hourly_rate' => '50',
			'cost_rate_mode' => 'project',
			'status' => 'Active',
			'priority' => 'Medium',
			'project_type' => 'client',
		]);
		$this->projectIds[] = (int) $project->getId();

		// Empty total_budget must not hit MariaDB as '' (FormDecimal coerce layer).
		$updated = $projects->updateProject((int) $project->getId(), [
			'name' => $project->getName(),
			'short_description' => $project->getShortDescription(),
			'customer_id' => (string) $customer->getId(),
			'total_budget' => '',
			'hourly_rate' => '50',
			'available_hours' => '10',
			'status' => 'Active',
			'priority' => 'Medium',
			'project_type' => 'client',
			'cost_rate_mode' => 'project',
		]);

		$this->assertSame(0.0, (float) $updated->getTotalBudget());
		$this->assertSame(0.0, (float) $updated->getAvailableHours());
	}

	public function testZeroBudgetForcesAvailableHoursToZeroEvenIfFormSendsStaleCapacity(): void
	{
		$customers = \OC::$server->get(CustomerService::class);
		$projects = \OC::$server->get(ProjectService::class);
		$suffix = (string) microtime(true);

		$customer = $customers->createCustomer(['name' => 'Stale Hours ' . $suffix], self::ADMIN);
		$this->customerIds[] = (int) $customer->getId();

		$project = $projects->createProject([
			'name' => 'Stale Hours Proj ' . $suffix,
			'short_description' => 'desc',
			'customer_id' => $customer->getId(),
			'total_budget' => '1000',
			'hourly_rate' => '50',
			'cost_rate_mode' => 'project',
			'status' => 'Active',
			'priority' => 'Medium',
			'project_type' => 'client',
		]);
		$this->projectIds[] = (int) $project->getId();
		$this->assertGreaterThan(0.0, (float) $project->getAvailableHours());

		$updated = $projects->updateProject((int) $project->getId(), [
			'name' => $project->getName(),
			'short_description' => $project->getShortDescription(),
			'customer_id' => (string) $customer->getId(),
			'total_budget' => '0',
			'hourly_rate' => '50',
			'available_hours' => '999',
			'status' => 'Active',
			'priority' => 'Medium',
			'project_type' => 'client',
			'cost_rate_mode' => 'project',
		]);

		$this->assertSame(0.0, (float) $updated->getAvailableHours());
	}

	public function testUpdateProjectRejectsGarbageDecimalWithoutWritingEmptyString(): void
	{
		$customers = \OC::$server->get(CustomerService::class);
		$projects = \OC::$server->get(ProjectService::class);
		$suffix = (string) microtime(true);

		$customer = $customers->createCustomer(['name' => 'Garbage Dec ' . $suffix], self::ADMIN);
		$this->customerIds[] = (int) $customer->getId();

		$project = $projects->createProject([
			'name' => 'Garbage Proj ' . $suffix,
			'short_description' => 'desc',
			'customer_id' => $customer->getId(),
			'total_budget' => '1000',
			'hourly_rate' => '50',
			'cost_rate_mode' => 'project',
			'status' => 'Active',
			'priority' => 'Medium',
			'project_type' => 'client',
		]);
		$this->projectIds[] = (int) $project->getId();

		$this->expectException(\Exception::class);
		$this->expectExceptionMessageMatches('/must be a non-negative number|must be a/i');
		$projects->updateProject((int) $project->getId(), [
			'name' => $project->getName(),
			'short_description' => $project->getShortDescription(),
			'customer_id' => (string) $customer->getId(),
			'total_budget' => '1000',
			'hourly_rate' => 'abc',
			'available_hours' => '10',
			'status' => 'Active',
			'priority' => 'Medium',
			'project_type' => 'client',
			'cost_rate_mode' => 'project',
		]);
	}
}
