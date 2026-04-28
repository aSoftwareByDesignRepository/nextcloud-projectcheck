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
use OCP\AppFramework\Http\JSONResponse;
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
		$config = $this->createMock(IConfig::class);
		$csp = $this->createMock(CSPService::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn(string $t): string => $t);
		$snapshots = $this->createMock(UserAccountSnapshotMapper::class);
		$accessControl = $this->createMock(AccessControlService::class);
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
			$accessControl,
			$requestTokenProvider
		);

		$this->user = $this->createMock(IUser::class);
		$this->user->method('getUID')->willReturn('manager1');
		$this->userSession->method('getUser')->willReturn($this->user);
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
}

