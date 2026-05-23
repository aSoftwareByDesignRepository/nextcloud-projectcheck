<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Service;

use OCA\ProjectCheck\Service\AccessControlService;
use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Util\CostRateMode;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

class ProjectServiceTest extends TestCase {
	private ProjectService $projectService;

	private function createDbWithExistingCustomer(): IDBConnection
	{
		$result = $this->createMock(\OCP\DB\IResult::class);
		$result->method('fetchOne')->willReturn(1);
		$expr = $this->createMock(\OCP\DB\QueryBuilder\IExpressionBuilder::class);
		$expr->method('eq')->willReturn('eq');
		$qb = $this->createMock(IQueryBuilder::class);
		$qb->method('select')->willReturnSelf();
		$qb->method('from')->willReturnSelf();
		$qb->method('where')->willReturnSelf();
		$qb->method('expr')->willReturn($expr);
		$qb->method('createNamedParameter')->willReturn('p');
		$qb->method('executeQuery')->willReturn($result);
		$db = $this->createMock(IDBConnection::class);
		$db->method('getQueryBuilder')->willReturn($qb);
		return $db;
	}

	protected function setUp(): void {
		parent::setUp();
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('test-user');
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);
		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('isAdmin')->with('test-user')->willReturn(true);
		$accessControl = $this->createMock(AccessControlService::class);
		$accessControl->method('canManageAppConfiguration')->with('test-user')->willReturn(true);
		$this->projectService = new ProjectService(
			$this->createDbWithExistingCustomer(),
			$userSession,
			$this->createMock(IUserManager::class),
			$this->createMock(IConfig::class),
			$groupManager,
			null,
			null,
			$accessControl
		);
	}

	public function testInvalidStatusTransitionIsRejected(): void {
		$this->assertFalse($this->projectService->isStatusTransitionAllowed('Completed', 'Active'));
		$this->assertFalse($this->projectService->isStatusTransitionAllowed('Cancelled', 'On Hold'));
	}

	public function testValidStatusTransitionsAreAllowed(): void {
		$this->assertTrue($this->projectService->isStatusTransitionAllowed('Active', 'On Hold'));
		$this->assertTrue($this->projectService->isStatusTransitionAllowed('On Hold', 'Archived'));
		$this->assertTrue($this->projectService->isStatusTransitionAllowed('Archived', 'Active'));
	}

	public function testGetAllowedStatusTargetsReflectsWorkflowMap(): void {
		$this->assertSame(['On Hold', 'Completed', 'Cancelled', 'Archived'], $this->projectService->getAllowedStatusTargets('Active'));
		$this->assertSame([], $this->projectService->getAllowedStatusTargets('Completed'));
	}

	public function testProjectHasLoggedTimeFalseWhenNoEntries(): void {
		$result = $this->createMock(\OCP\DB\IResult::class);
		$result->method('fetchOne')->willReturn('0');
		$qb = $this->createMock(IQueryBuilder::class);
		$qb->method('select')->willReturnSelf();
		$qb->method('from')->willReturnSelf();
		$qb->method('where')->willReturnSelf();
		$qb->method('createFunction')->willReturn('COUNT(*)');
		$qb->method('createNamedParameter')->willReturn('p');
		$qb->method('expr')->willReturn($this->createMock(\OCP\DB\QueryBuilder\IExpressionBuilder::class));
		$qb->method('executeQuery')->willReturn($result);
		$db = $this->createMock(IDBConnection::class);
		$db->method('getQueryBuilder')->willReturn($qb);
		$groupManager = $this->createMock(IGroupManager::class);
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('u');
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);
		$svc = new ProjectService($db, $userSession, $this->createMock(IUserManager::class), $this->createMock(IConfig::class), $groupManager);
		$this->assertFalse($svc->projectHasLoggedTime(42));
	}

	public function testProjectHasLoggedTimeTrueWhenEntriesExist(): void {
		$result = $this->createMock(\OCP\DB\IResult::class);
		$result->method('fetchOne')->willReturn('3');
		$qb = $this->createMock(IQueryBuilder::class);
		$qb->method('select')->willReturnSelf();
		$qb->method('from')->willReturnSelf();
		$qb->method('where')->willReturnSelf();
		$qb->method('createFunction')->willReturn('COUNT(*)');
		$qb->method('createNamedParameter')->willReturn('p');
		$qb->method('expr')->willReturn($this->createMock(\OCP\DB\QueryBuilder\IExpressionBuilder::class));
		$qb->method('executeQuery')->willReturn($result);
		$db = $this->createMock(IDBConnection::class);
		$db->method('getQueryBuilder')->willReturn($qb);
		$svc = new ProjectService($db, $this->createMock(IUserSession::class), $this->createMock(IUserManager::class), $this->createMock(IConfig::class), $this->createMock(IGroupManager::class));
		$this->assertTrue($svc->projectHasLoggedTime(42));
	}

	public function testCreateProjectRejectsMissingRequiredFieldsEarly(): void {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage("Field 'short_description' is required");
		$this->projectService->createProject([
			'name' => 'Test Project',
		]);
	}

	public function testCreateProjectRejectsInvalidNumericValuesEarly(): void {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Hourly rate must be a non-negative number');
		$this->projectService->createProject([
			'name' => 'Test Project',
			'short_description' => 'A test project',
			'customer_id' => 1,
			'hourly_rate' => -10.0,
			'total_budget' => 5000.0,
		]);
	}

	public function testCreateProjectRejectsInvalidDateRangeEarly(): void {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('End date must be after start date');
		$this->projectService->createProject([
			'name' => 'Test Project',
			'short_description' => 'A test project',
			'customer_id' => 1,
			'hourly_rate' => 50.0,
			'total_budget' => 5000.0,
			'start_date' => '2024-01-01',
			'end_date' => '2023-12-31',
		]);
	}

	/**
	 * E6: cannot switch pricing method after time entries exist.
	 */
	public function testUpdateProjectRejectsCostRateModeChangeWhenTimeLogged(): void {
		$projectRow = [
			'id' => 7,
			'name' => 'Locked Project',
			'short_description' => 'Short',
			'detailed_description' => '',
			'customer_id' => 1,
			'customer_name' => 'Acme',
			'hourly_rate' => 100.0,
			'total_budget' => 10000.0,
			'available_hours' => 100.0,
			'category' => '',
			'priority' => 'Medium',
			'status' => 'Active',
			'start_date' => null,
			'end_date' => null,
			'tags' => '',
			'project_type' => 'client',
			'cost_rate_mode' => CostRateMode::PROJECT,
			'created_by' => 'admin',
			'created_at' => '2026-01-01 00:00:00',
			'updated_at' => '2026-01-02 00:00:00',
		];

		$fetchResult = $this->createMock(\OCP\DB\IResult::class);
		$fetchResult->method('fetch')->willReturn($projectRow);
		$fetchResult->method('closeCursor');

		$countResult = $this->createMock(\OCP\DB\IResult::class);
		$countResult->method('fetchOne')->willReturn('2');

		$qbIndex = 0;
		$db = $this->createMock(IDBConnection::class);
		$db->method('getQueryBuilder')->willReturnCallback(function () use (&$qbIndex, $fetchResult, $countResult) {
			$qbIndex++;
			$qb = $this->createMock(IQueryBuilder::class);
			$qb->method('select')->willReturnSelf();
			$qb->method('from')->willReturnSelf();
			$qb->method('leftJoin')->willReturnSelf();
			$qb->method('where')->willReturnSelf();
			$qb->method('createNamedParameter')->willReturn('p');
			$qb->method('createFunction')->willReturn('COUNT(*)');
			$expr = $this->createMock(\OCP\DB\QueryBuilder\IExpressionBuilder::class);
			$expr->method('eq')->willReturn('eq');
			$qb->method('expr')->willReturn($expr);
			if ($qbIndex === 1) {
				$qb->method('executeQuery')->willReturn($fetchResult);
			} else {
				$qb->method('executeQuery')->willReturn($countResult);
			}
			return $qb;
		});

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('admin');
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);
		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('isAdmin')->willReturn(true);

		$svc = new ProjectService(
			$db,
			$userSession,
			$this->createMock(IUserManager::class),
			$this->createMock(IConfig::class),
			$groupManager,
			null,
			null,
			$this->createMock(AccessControlService::class)
		);

		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('The pricing method cannot be changed after time has been logged on this project.');
		$svc->updateProject(7, [
			'cost_rate_mode' => CostRateMode::EMPLOYEE,
		]);
	}
}
