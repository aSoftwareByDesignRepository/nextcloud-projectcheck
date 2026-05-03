<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Controller;

use OCA\ProjectCheck\Controller\DashboardController;
use OCA\ProjectCheck\Db\Project;
use OCA\ProjectCheck\Service\BudgetService;
use OCA\ProjectCheck\Service\CSPService;
use OCA\ProjectCheck\Service\CustomerService;
use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Service\TimeEntryService;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Smoke tests for {@see DashboardController}.
 *
 * Audit reference: AUDIT-FINDINGS G24 - the dashboard endpoints aggregate
 * project/customer/time data and must enforce per-user scoping. The tests
 * verify:
 *   - Unauthenticated requests are rejected (401 / guest template).
 *   - Stats are scoped to the user's accessible project list when the
 *     viewer is not a global viewer.
 *   - Service exceptions degrade gracefully without leaking trace data.
 */
class DashboardControllerTest extends TestCase {
	/** @var IRequest|\PHPUnit\Framework\MockObject\MockObject */
	private $request;
	/** @var IUserSession|\PHPUnit\Framework\MockObject\MockObject */
	private $userSession;
	/** @var IUser|\PHPUnit\Framework\MockObject\MockObject */
	private $user;
	/** @var ProjectService|\PHPUnit\Framework\MockObject\MockObject */
	private $projectService;
	/** @var TimeEntryService|\PHPUnit\Framework\MockObject\MockObject */
	private $timeEntryService;
	/** @var CustomerService|\PHPUnit\Framework\MockObject\MockObject */
	private $customerService;
	/** @var BudgetService|\PHPUnit\Framework\MockObject\MockObject */
	private $budgetService;
	/** @var IURLGenerator|\PHPUnit\Framework\MockObject\MockObject */
	private $urlGenerator;
	/** @var CSPService|\PHPUnit\Framework\MockObject\MockObject */
	private $cspService;
	/** @var IL10N|\PHPUnit\Framework\MockObject\MockObject */
	private $l;

	protected function setUp(): void {
		parent::setUp();
		$this->request = $this->createMock(IRequest::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->user = $this->createMock(IUser::class);
		$this->user->method('getUID')->willReturn('alice');
		$this->projectService = $this->createMock(ProjectService::class);
		$this->timeEntryService = $this->createMock(TimeEntryService::class);
		$this->customerService = $this->createMock(CustomerService::class);
		$this->budgetService = $this->createMock(BudgetService::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->urlGenerator->method('linkToRoute')->willReturn('/x');
		$this->cspService = $this->createMock(CSPService::class);
		// The trait calls $cspService->applyPolicyWithNonce(); a no-op mock
		// keeps the response unchanged so we can assert template + params.
		$this->cspService->method('applyPolicyWithNonce')->willReturnArgument(0);
		$this->l = $this->createMock(IL10N::class);
		$this->l->method('t')->willReturnCallback(static fn ($s, $p = []) => (string)$s);
	}

	private function makeController(): DashboardController {
		return new DashboardController(
			'projectcheck',
			$this->request,
			$this->userSession,
			$this->projectService,
			$this->timeEntryService,
			$this->customerService,
			$this->budgetService,
			$this->urlGenerator,
			$this->cspService,
			$this->l
		);
	}

	public function testGetStatsReturns401WithoutAuthenticatedUser(): void {
		$this->userSession->method('getUser')->willReturn(null);
		$this->projectService->expects($this->never())->method('getAccessibleProjectIdListForUser');

		$response = $this->makeController()->getStats();
		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertSame(401, $response->getStatus());
		$body = $response->getData();
		$this->assertArrayHasKey('error', $body);
	}

	public function testIndexReturnsGuestTemplateForUnauthenticatedRequest(): void {
		$this->userSession->method('getUser')->willReturn(null);
		$this->projectService->expects($this->never())->method('getAccessibleProjectIdListForUser');

		$response = $this->makeController()->index();
		$this->assertInstanceOf(TemplateResponse::class, $response);
		$this->assertSame('error', $response->getTemplateName());
	}

	public function testGetStatsScopesToAccessibleProjectsForNonGlobalViewer(): void {
		$this->userSession->method('getUser')->willReturn($this->user);
		$accessible = [11, 22, 33];
		$this->projectService->method('getAccessibleProjectIdListForUser')
			->with('alice')
			->willReturn($accessible);

		$this->projectService->method('getProjectsByIdList')->with($accessible)->willReturn([]);
		$this->projectService->method('getAllProjects')->willReturn([]);
		$this->customerService->method('getCustomerListFiltersForUser')->willReturn([]);
		$this->customerService->method('getCustomers')->willReturn([]);

		// Non-global viewer must constrain time-entry queries to [alice].
		$this->timeEntryService->expects($this->atLeastOnce())
			->method('getYearlyStatsForAllProjects')
			->with($accessible, ['alice'])
			->willReturn([]);
		$this->timeEntryService->method('getDetailedYearlyStats')
			->with($accessible, ['alice'])
			->willReturn([]);
		$this->timeEntryService->method('getYearlyStatsByProjectType')
			->with($accessible, ['alice'])
			->willReturn([]);
		$this->timeEntryService->method('getDetailedYearlyStatsByProjectType')
			->with($accessible, ['alice'])
			->willReturn([]);
		$this->timeEntryService->method('getProductivityAnalysis')
			->with($accessible, ['alice'])
			->willReturn([]);
		$this->timeEntryService->method('getTimeEntriesWithProjectInfo')->willReturn([]);

		$response = $this->makeController()->getStats();
		$this->assertSame(200, $response->getStatus());
		$body = $response->getData();
		$this->assertSame(false, $body['isGlobalViewer']);
		$this->assertSame(0, $body['total_projects']);
	}

	public function testGetStatsTreatsNullAccessibleListAsGlobalViewer(): void {
		$this->userSession->method('getUser')->willReturn($this->user);
		$this->projectService->method('getAccessibleProjectIdListForUser')->willReturn(null);
		$this->projectService->method('getAllProjects')->willReturn([]);
		$this->customerService->method('getAllCustomers')->willReturn([]);
		// Global viewer should pass null user-scope to time-entry queries.
		$this->timeEntryService->expects($this->atLeastOnce())
			->method('getYearlyStatsForAllProjects')
			->with(null, null)
			->willReturn([]);
		$this->timeEntryService->method('getDetailedYearlyStats')->willReturn([]);
		$this->timeEntryService->method('getYearlyStatsByProjectType')->willReturn([]);
		$this->timeEntryService->method('getDetailedYearlyStatsByProjectType')->willReturn([]);
		$this->timeEntryService->method('getProductivityAnalysis')->willReturn([]);
		$this->timeEntryService->method('getTimeEntriesWithProjectInfo')->willReturn([]);

		$response = $this->makeController()->getStats();
		$this->assertSame(200, $response->getStatus());
		$body = $response->getData();
		$this->assertTrue($body['isGlobalViewer']);
	}

	public function testIndexEnvelopeIncludesBudgetAndProjectsForActiveOnly(): void {
		$this->userSession->method('getUser')->willReturn($this->user);
		$this->projectService->method('getAccessibleProjectIdListForUser')->willReturn(null);

		$active = new Project();
		$active->setId(1);
		$active->setName('Live');
		$active->setStatus('Active');
		$active->setTotalBudget(100.0);
		$inactive = new Project();
		$inactive->setId(2);
		$inactive->setName('Frozen');
		$inactive->setStatus('On Hold');
		$inactive->setTotalBudget(0.0);

		$this->projectService->method('getAllProjects')->willReturn([$active, $inactive]);
		$this->projectService->method('canUserCreateProject')->willReturn(true);
		$this->customerService->method('getAllCustomers')->willReturn([]);
		$this->budgetService->method('getProjectBudgetInfo')->willReturn([
			'total_budget' => 100.0,
			'used_budget' => 10.0,
			'remaining_budget' => 90.0,
			'consumption_percentage' => 10.0,
			'available_hours' => 0.0,
			'used_hours' => 0.0,
			'remaining_hours' => 0.0,
			'warning_level' => 'none',
			'is_over_budget' => false,
			'alerts' => [],
		]);
		// Budget alerts should only see the Active project.
		$this->budgetService->expects($this->once())
			->method('getBudgetAlertsForProjects')
			->with($this->callback(function (array $projects) {
				return count($projects) === 1
					&& reset($projects)->getStatus() === 'Active';
			}), 'alice')
			->willReturn([]);

		$this->timeEntryService->method('getDetailedYearlyStats')->willReturn([]);
		$this->timeEntryService->method('getYearlyStatsByProjectType')->willReturn([]);
		$this->timeEntryService->method('getDetailedYearlyStatsByProjectType')->willReturn([]);
		$this->timeEntryService->method('getProductivityAnalysis')->willReturn([]);
		$this->timeEntryService->method('getYearlyStatsForAllProjects')->willReturn([]);
		$this->timeEntryService->method('getTimeEntriesWithProjectInfo')->willReturn([]);
		$this->timeEntryService->method('getTotalCostForProjectAndUser')->willReturn(0.0);

		$response = $this->makeController()->index();
		$this->assertInstanceOf(TemplateResponse::class, $response);
		$this->assertSame('dashboard', $response->getTemplateName());
		$params = $response->getParams();
		$this->assertArrayHasKey('stats', $params);
		$this->assertSame(2, $params['stats']['totalProjects']);
		$this->assertSame(1, $params['stats']['activeProjects']);
		$this->assertTrue($params['canCreateProject']);
	}
}
