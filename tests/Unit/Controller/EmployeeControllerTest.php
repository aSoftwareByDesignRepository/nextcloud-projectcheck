<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Controller;

use OCA\ProjectCheck\Controller\EmployeeController;
use OCA\ProjectCheck\Db\Project;
use OCA\ProjectCheck\Db\UserAccountSnapshotMapper;
use OCA\ProjectCheck\Service\AccessControlService;
use OCA\ProjectCheck\Service\CSPService;
use OCA\ProjectCheck\Service\CustomerService;
use OCA\ProjectCheck\Service\IRequestTokenProvider;
use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Service\TimeEntryService;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

class EmployeeControllerTest extends TestCase
{
	/** @var IRequest|\PHPUnit\Framework\MockObject\MockObject */
	private $request;
	/** @var IUserSession|\PHPUnit\Framework\MockObject\MockObject */
	private $userSession;
	/** @var IUserManager|\PHPUnit\Framework\MockObject\MockObject */
	private $userManager;
	/** @var TimeEntryService|\PHPUnit\Framework\MockObject\MockObject */
	private $timeEntryService;
	/** @var ProjectService|\PHPUnit\Framework\MockObject\MockObject */
	private $projectService;
	/** @var CustomerService|\PHPUnit\Framework\MockObject\MockObject */
	private $customerService;
	/** @var EmployeeController */
	private $controller;
	/** @var IUser|\PHPUnit\Framework\MockObject\MockObject */
	private $user;
	/** @var AccessControlService|\PHPUnit\Framework\MockObject\MockObject */
	private $accessControl;

	protected function setUp(): void
	{
		parent::setUp();
		$this->request = $this->createMock(IRequest::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->timeEntryService = $this->createMock(TimeEntryService::class);
		$this->projectService = $this->createMock(ProjectService::class);
		$this->customerService = $this->createMock(CustomerService::class);
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('linkToRoute')->willReturnCallback(
			static function (string $route, array $params = []): string {
				if ($route === 'projectcheck.employee.show' && isset($params['userId'])) {
					return '/index.php/apps/projectcheck/employees/' . $params['userId'];
				}
				return '/index.php/' . $route;
			}
		);
		$config = $this->createMock(IConfig::class);
		$csp = $this->createMock(CSPService::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn(string $t): string => $t);
		$snapshots = $this->createMock(UserAccountSnapshotMapper::class);
		$this->accessControl = $this->createMock(AccessControlService::class);
		$requestTokenProvider = $this->createMock(IRequestTokenProvider::class);

		$this->controller = new EmployeeController(
			'projectcheck',
			$this->request,
			$this->userSession,
			$this->userManager,
			$this->timeEntryService,
			$this->projectService,
			$this->customerService,
			$urlGenerator,
			$config,
			$csp,
			$l10n,
			$snapshots,
			$this->accessControl,
			$requestTokenProvider
		);

		$this->user = $this->createMock(IUser::class);
		$this->user->method('getUID')->willReturn('manager1');
		$this->userSession->method('getUser')->willReturn($this->user);
		$this->accessControl->method('isSystemAdministrator')->willReturn(false);
		$this->accessControl->method('canManageAppConfiguration')->willReturn(false);
	}

	public function testAssignProjectDeniedWhenNoPermission(): void
	{
		$target = $this->createMock(IUser::class);
		$this->userManager->method('get')->with('employee1')->willReturn($target);
		$this->request->method('getParam')->willReturnMap([
			['project_id', 0, 7],
			['hourly_rate', null, null],
		]);
		$project = new Project();
		$project->setId(7);
		$project->setStatus('Active');
		$this->projectService->method('getProject')->with(7)->willReturn($project);
		$this->projectService->method('canUserManageMembers')->with('manager1', 7)->willReturn(false);
		$this->projectService->expects($this->never())->method('addTeamMember');

		$response = $this->controller->assignProject('employee1');
		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertEquals(403, $response->getStatus());
	}

	public function testAssignProjectDeniedForArchivedProject(): void
	{
		$target = $this->createMock(IUser::class);
		$this->userManager->method('get')->with('employee1')->willReturn($target);
		$this->request->method('getParam')->willReturnMap([
			['project_id', 0, 11],
			['hourly_rate', null, null],
		]);
		$project = new Project();
		$project->setId(11);
		$project->setStatus('Archived');
		$this->projectService->method('getProject')->with(11)->willReturn($project);
		$this->projectService->expects($this->never())->method('addTeamMember');

		$response = $this->controller->assignProject('employee1');
		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertEquals(403, $response->getStatus());
	}

	public function testUnassignProjectDeniedWhenNoPermission(): void
	{
		$project = new Project();
		$project->setId(8);
		$project->setStatus('Active');
		$this->projectService->method('getProject')->with(8)->willReturn($project);
		$this->projectService->method('canUserManageMembers')->with('manager1', 8)->willReturn(false);
		$this->projectService->expects($this->never())->method('removeTeamMember');

		$response = $this->controller->unassignProject('employee1', 8);
		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertEquals(403, $response->getStatus());
	}

	public function testAssignProjectSuccess(): void
	{
		$target = $this->createMock(IUser::class);
		$this->userManager->method('get')->with('employee1')->willReturn($target);
		$this->request->method('getParam')->willReturnMap([
			['project_id', 0, 9],
			['hourly_rate', null, '60'],
		]);
		$project = new Project();
		$project->setId(9);
		$project->setStatus('Active');
		$this->projectService->method('getProject')->with(9)->willReturn($project);
		$this->projectService->method('canUserManageMembers')->with('manager1', 9)->willReturn(true);
		$this->projectService->expects($this->once())->method('addTeamMember');

		$response = $this->controller->assignProject('employee1');
		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertEquals(200, $response->getStatus());
	}

	public function testShowRedirectsNonAdminToOwnProfile(): void
	{
		$response = $this->controller->show('employee1');

		$this->assertInstanceOf(RedirectResponse::class, $response);
		$this->assertSame('/index.php/apps/projectcheck/employees/manager1', $response->getRedirectURL());
	}

	public function testGetStatsWithoutUserIdReturnsOwnStatsForNonAdmin(): void
	{
		$this->request->method('getParam')->with('user_id', null)->willReturn(null);
		$this->timeEntryService->expects($this->once())
			->method('getYearlyStatsForEmployee')
			->with('manager1')
			->willReturn([['year' => 2026, 'total_hours' => 3.5]]);
		$this->timeEntryService->expects($this->never())->method('getEmployeeYearlyStats');

		$response = $this->controller->getStats();

		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertSame(200, $response->getStatus());
	}

	public function testIndexScopesNonAdminEmployeeOverviewToOwnData(): void
	{
		$this->request->method('getParam')->willReturnMap([
			['search', '', ''],
			['page', 1, 1],
		]);
		$project = new Project();
		$project->setId(5);
		$project->setStatus('Active');
		$this->projectService->method('getAccessibleProjectIdListForUser')->with('manager1')->willReturn([5]);
		$this->projectService->method('getProjectsByIdList')->with([5])->willReturn([$project]);
		$this->timeEntryService->expects($this->once())
			->method('getEmployeeComparisonStats')
			->with([5], ['manager1'])
			->willReturn([
				[
					'user_id' => 'manager1',
					'user_display_name' => 'Manager One',
					'total_hours' => 4.0,
					'total_cost' => 100.0,
					'entry_count' => 2,
					'avg_hourly_rate' => 25.0,
					'first_entry' => '2026-01-01',
					'last_entry' => '2026-01-02',
				],
			]);
		$this->timeEntryService->expects($this->once())
			->method('getEmployeeYearlyStats')
			->with([5], ['manager1'])
			->willReturn([]);
		$this->timeEntryService->expects($this->once())
			->method('getDetailedYearlyStatsByProjectTypeForEmployees')
			->with([5], ['manager1'])
			->willReturn([]);
		$this->timeEntryService->expects($this->once())
			->method('getUsersWithTimeEntries')
			->with([5])
			->willReturn([['user_id' => 'manager1', 'displayname' => 'Manager One']]);
		$this->timeEntryService->method('getYearlyStatsByProjectTypeForEmployee')->willReturn([]);

		$response = $this->controller->index();

		$this->assertInstanceOf(TemplateResponse::class, $response);
	}
}

