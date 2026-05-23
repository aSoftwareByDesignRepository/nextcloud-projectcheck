<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Service;

use OCA\ProjectCheck\Db\Project;
use OCA\ProjectCheck\Service\BudgetAlertService;
use OCA\ProjectCheck\Service\BudgetService;
use OCA\ProjectCheck\Service\ProjectService;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Notification\IManager as NotificationManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class BudgetAlertServiceTest extends TestCase {
	public function testCheckProjectBudgetDelegatesToBudgetService(): void {
		$project = new Project();
		$project->setId(5);
		$project->setName('Alpha');
		$project->setTotalBudget(1000.0);

		$budgetService = $this->createMock(BudgetService::class);
		$budgetService->expects($this->once())
			->method('getProjectBudgetInfo')
			->with($project, 'alice')
			->willReturn([
				'total_budget' => 1000.0,
				'used_budget' => 850.0,
				'remaining_budget' => 150.0,
				'consumption_percentage' => 85.0,
				'warning_level' => 'warning',
				'alerts' => [
					[
						'type' => 'budget_warning',
						'project_id' => 5,
						'project_name' => 'Alpha',
						'used_budget' => 850.0,
						'total_budget' => 1000.0,
						'consumption_percentage' => 85.0,
						'remaining_budget' => 150.0,
						'message' => 'Budget warning for Alpha',
					],
				],
			]);

		$service = new BudgetAlertService(
			$this->createMock(IConfig::class),
			$this->createMock(IUserSession::class),
			$this->createMock(NotificationManager::class),
			$this->createMock(IUserManager::class),
			$this->createMock(LoggerInterface::class),
			$this->createMock(ProjectService::class),
			$budgetService,
			$this->createMock(IL10N::class),
		);

		$alerts = $service->checkProjectBudget($project, 'alice');

		$this->assertCount(1, $alerts);
		$this->assertSame('warning', $alerts[0]['type']);
		$this->assertSame(5, $alerts[0]['project_id']);
		$this->assertSame(850.0, $alerts[0]['spent_amount']);
		$this->assertSame(1000.0, $alerts[0]['budget']);
		$this->assertSame(85.0, $alerts[0]['percentage_used']);
	}

	public function testCheckProjectBudgetReturnsEmptyWhenNoBudget(): void {
		$project = new Project();
		$project->setId(6);
		$project->setTotalBudget(0.0);

		$budgetService = $this->createMock(BudgetService::class);
		$budgetService->method('getProjectBudgetInfo')->willReturn([
			'total_budget' => 0.0,
			'used_budget' => 0.0,
			'alerts' => [],
		]);

		$service = new BudgetAlertService(
			$this->createMock(IConfig::class),
			$this->createMock(IUserSession::class),
			$this->createMock(NotificationManager::class),
			$this->createMock(IUserManager::class),
			$this->createMock(LoggerInterface::class),
			$this->createMock(ProjectService::class),
			$budgetService,
			$this->createMock(IL10N::class),
		);

		$this->assertSame([], $service->checkProjectBudget($project, 'bob'));
	}
}
