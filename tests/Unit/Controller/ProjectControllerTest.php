<?php

declare(strict_types=1);

/**
 * Integration tests for ProjectController
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Tests\Unit\Controller;

use OCA\ProjectCheck\Controller\ProjectController;
use OCA\ProjectCheck\Service\HourlyRateService;
use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Service\CustomerService;
use OCA\ProjectCheck\Service\TimeEntryService;
use OCA\ProjectCheck\Service\BudgetService;
use OCA\ProjectCheck\Service\DeletionService;
use OCA\ProjectCheck\Service\ActivityService;
use OCA\ProjectCheck\Service\ProjectFileService;
use OCA\ProjectCheck\Service\CSPService;
use OCA\ProjectCheck\Service\IRequestTokenProvider;
use OCA\ProjectCheck\Service\ProjectMemberHourlyRateService;
use OCA\ProjectCheck\Service\ListExportService;
use OCA\ProjectCheck\Service\ProjectSettlementService;
use OCA\ProjectCheck\Db\Project;
use OCA\ProjectCheck\Db\ProjectMember;
use OCA\ProjectCheck\Exception\RateResolutionException;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\IURLGenerator;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IUser;
use PHPUnit\Framework\TestCase;

/**
 * Class ProjectControllerTest
 *
 * @package OCA\ProjectCheck\Tests\Unit\Controller
 */
class ProjectControllerTest extends TestCase {

	/** @var ProjectController */
	private $controller;

	/** @var ProjectService|\PHPUnit\Framework\MockObject\MockObject */
	private $projectService;

	/** @var HourlyRateService|\PHPUnit\Framework\MockObject\MockObject */
	private $hourlyRateService;

	/** @var IRequest|\PHPUnit\Framework\MockObject\MockObject */
	private $request;

	/** @var IUserSession|\PHPUnit\Framework\MockObject\MockObject */
	private $userSession;

	/** @var IUser|\PHPUnit\Framework\MockObject\MockObject */
	private $user;

	/** @var IL10N|\PHPUnit\Framework\MockObject\MockObject */
	private $l10n;

	/** @var \OCP\IUserManager|\PHPUnit\Framework\MockObject\MockObject */
	private $userManager;

	/** @var IConfig|\PHPUnit\Framework\MockObject\MockObject */
	private $config;

	protected function setUp(): void {
		parent::setUp();

		$this->projectService = $this->createMock(ProjectService::class);
		$this->request = $this->createMock(IRequest::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->user = $this->createMock(IUser::class);

		// IL10N mock: t() returns first argument
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')
			->willReturnCallback(static fn ($s, $p = []) => is_array($p) && !empty($p) ? vsprintf($s, $p) : $s);

		$this->hourlyRateService = $this->createMock(HourlyRateService::class);
		$customerService = $this->createMock(CustomerService::class);
		$timeEntryService = $this->createMock(TimeEntryService::class);
		$budgetService = $this->createMock(BudgetService::class);
		$deletionService = $this->createMock(DeletionService::class);
		$activityService = $this->createMock(ActivityService::class);
		$projectFileService = $this->createMock(ProjectFileService::class);
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$this->config = $this->createMock(IConfig::class);
		$config = $this->config;
		$cspService = $this->createMock(CSPService::class);
		$cspService->method('applyPolicyWithNonce')->willReturnArgument(0);
		$requestToken = $this->createMock(IRequestTokenProvider::class);
		$requestToken->method('getEncryptedRequestToken')->willReturn('mock-encrypted-csrf');
		$this->userManager = $this->createMock(\OCP\IUserManager::class);
		$userAccountSnapshot = $this->createMock(\OCA\ProjectCheck\Db\UserAccountSnapshotMapper::class);
		$projectMemberHourlyRateService = $this->createMock(\OCA\ProjectCheck\Service\ProjectMemberHourlyRateService::class);
		$listExportService = new ListExportService($this->config, 'projectcheck');

		$this->controller = new ProjectController(
			'projectcheck',
			$this->request,
			$this->projectService,
			$this->hourlyRateService,
			$customerService,
			$timeEntryService,
			$budgetService,
			$deletionService,
			$activityService,
			$projectFileService,
			$this->userSession,
			$urlGenerator,
			$config,
			$cspService,
			$requestToken,
			$this->l10n,
			$this->userManager,
			$userAccountSnapshot,
			$projectMemberHourlyRateService,
			$listExportService,
			$this->createMock(ProjectSettlementService::class)
		);
		$this->projectService->method('canUserCreateProject')->willReturn(true);
	}

	/**
	 * IRequest::getParam is invoked for status and optional reason; PHPUnit cannot match only "status".
	 *
	 * @return void
	 */
	private function stubRequestParamsForProjectStatusChange(string $status, string $reason = ''): void {
		$this->request->method('getParam')->willReturnCallback(
			static function (string $key, $default = null) use ($status, $reason) {
				return match ($key) {
					'status' => $status,
					'reason' => $reason,
					default => $default,
				};
			}
		);
	}

	/**
	 * Test complete project creation workflow
	 */
	public function testCompleteProjectCreationWorkflow(): void {
		$this->user->method('getUID')->willReturn('testuser');
		$this->userSession->method('getUser')->willReturn($this->user);

		$projectData = [
			'name' => 'Test Project',
			'short_description' => 'A test project',
			'customer_id' => 1,
			'hourly_rate' => 50.0,
			'total_budget' => 5000.0,
			'category' => 'Development',
			'priority' => 'Medium'
		];

		$this->request->method('getParams')->willReturn($projectData);
		$this->request->method('getHeader')->with('X-Requested-With')->willReturn('XMLHttpRequest');
		$this->request->method('getUploadedFile')->with('project_files')->willReturn(null);

		$project = new Project();
		$project->setId(1);
		$project->setName('Test Project');
		$project->setStatus('Active');
		$project->setStatus('Active');
		$project->setShortDescription('A test project');
		$project->setCustomerId(1);
		$project->setHourlyRate(50.0);
		$project->setTotalBudget(5000.0);
		$project->setAvailableHours(100.0);
		$project->setCategory('Development');
		$project->setPriority('Medium');
		$project->setStatus('Active');
		$project->setCreatedBy('testuser');

		$this->projectService->method('createProject')->willReturn($project);

		$response = $this->controller->store();

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals(200, $response->getStatus());

		$data = $response->getData();
		$this->assertTrue($data['success']);
		$this->assertEquals('Project created successfully', $data['message']);
		$this->assertIsScalar($data['project']);
		$this->assertEquals(1, $data['project']);
	}

	/**
	 * Test project editing and validation
	 */
	public function testProjectEditingAndValidation(): void {
		$this->user->method('getUID')->willReturn('testuser');
		$this->userSession->method('getUser')->willReturn($this->user);

		$existingProject = new Project();
		$existingProject->setId(1);
		$existingProject->setName('Original Project');
		$existingProject->setStatus('Active');
		$existingProject->setCreatedBy('testuser');

		$this->projectService->method('getProject')->with(1)->willReturn($existingProject);
		$this->projectService->method('canUserEditProject')->with('testuser', 1)->willReturn(true);

		$updateData = [
			'name' => 'Updated Project',
			'short_description' => 'Updated description',
			'total_budget' => 6000.0,
			'hourly_rate' => 60.0
		];

		$this->request->method('getParams')->willReturn($updateData);
		$this->request->method('getMethod')->willReturn('PUT');
		$this->request->method('getParam')->with('_method')->willReturn(null);
		$this->request->method('getHeader')->with('X-Requested-With')->willReturn('XMLHttpRequest');

		$updatedProject = clone $existingProject;
		$updatedProject->setName('Updated Project');
		$updatedProject->setShortDescription('Updated description');
		$updatedProject->setTotalBudget(6000.0);
		$updatedProject->setHourlyRate(60.0);
		$updatedProject->setAvailableHours(100.0);

		$this->projectService->method('updateProject')->willReturn($updatedProject);

		$response = $this->controller->update(1);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals(200, $response->getStatus());

		$data = $response->getData();
		$this->assertTrue($data['success']);
		$this->assertEquals('Project updated successfully', $data['message']);
	}

	/**
	 * Test team member management
	 */
	public function testTeamMemberManagement(): void {
		$this->user->method('getUID')->willReturn('testuser');
		$this->userSession->method('getUser')->willReturn($this->user);

		$project = new Project();
		$project->setId(1);
		$project->setName('Test Project');

		$this->projectService->method('getProject')->with(1)->willReturn($project);
		$this->projectService->method('canUserManageMembers')->with('testuser', 1)->willReturn(true);

		$this->request->method('getParam')
			->willReturnMap([
				['user_id', '', 'newuser'],
				['hourly_rate', null, 45.0]
			]);

		$member = new ProjectMember();
		$member->setId(1);
		$member->setProjectId(1);
		$member->setUserId('newuser');
		$member->setRole('Member');
		$member->setHourlyRate(45.0);

		$this->projectService->method('addTeamMember')->willReturn($member);

		$response = $this->controller->addTeamMember(1);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals(200, $response->getStatus());

		$data = $response->getData();
		$this->assertTrue($data['success']);
		$this->assertEquals('Team member added successfully', $data['message']);
		$this->assertInstanceOf(ProjectMember::class, $data['member']);
	}

	public function testAddTeamMemberRequiresRateInProjectMemberMode(): void {
		$this->user->method('getUID')->willReturn('testuser');
		$this->userSession->method('getUser')->willReturn($this->user);
		$project = new Project();
		$project->setId(1);
		$project->setStatus('Active');
		$project->setCostRateMode(\OCA\ProjectCheck\Util\CostRateMode::PROJECT_MEMBER);
		$this->projectService->method('getProject')->with(1)->willReturn($project);
		$this->projectService->method('canUserManageMembers')->with('testuser', 1)->willReturn(true);

		$this->request->method('getParam')
			->willReturnMap([
				['user_id', '', 'newuser'],
				['hourly_rate', null, null],
			]);

		$this->projectService->expects($this->never())->method('addTeamMember');

		$response = $this->controller->addTeamMember(1);
		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals(400, $response->getStatus());
		$data = $response->getData();
		$this->assertArrayHasKey('error', $data);
		$this->assertStringContainsString('hourly rate', strtolower((string)$data['error']));
	}

	public function testAddTeamMemberRejectsInvalidHourlyRate(): void {
		$this->user->method('getUID')->willReturn('testuser');
		$this->userSession->method('getUser')->willReturn($this->user);
		$project = new Project();
		$project->setId(1);
		$project->setStatus('Active');
		$this->projectService->method('getProject')->with(1)->willReturn($project);
		$this->projectService->method('canUserManageMembers')->with('testuser', 1)->willReturn(true);

		$this->request->method('getParam')
			->willReturnMap([
				['user_id', '', 'newuser'],
				['hourly_rate', null, 'abc'],
			]);

		$this->projectService->expects($this->never())->method('addTeamMember');

		$response = $this->controller->addTeamMember(1);
		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals(400, $response->getStatus());
		$data = $response->getData();
		$this->assertArrayHasKey('error', $data);
		$this->assertEquals('Hourly rate must be a non-negative number', $data['error']);
	}

	public function testAddTeamMemberRejectsEmptyUserId(): void {
		$this->user->method('getUID')->willReturn('testuser');
		$this->userSession->method('getUser')->willReturn($this->user);
		$project = new Project();
		$project->setId(1);
		$project->setStatus('Active');
		$this->projectService->method('getProject')->with(1)->willReturn($project);
		$this->projectService->method('canUserManageMembers')->with('testuser', 1)->willReturn(true);

		$this->request->method('getParam')
			->willReturnMap([
				['user_id', '', '   '],
				['hourly_rate', null, null],
			]);

		$this->projectService->expects($this->never())->method('addTeamMember');

		$response = $this->controller->addTeamMember(1);
		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals(400, $response->getStatus());
		$data = $response->getData();
		$this->assertArrayHasKey('error', $data);
		$this->assertEquals('Invalid parameters', $data['error']);
	}

	public function testAddTeamMemberRejectsNonEditableProjectState(): void {
		$this->user->method('getUID')->willReturn('testuser');
		$this->userSession->method('getUser')->willReturn($this->user);
		$project = new Project();
		$project->setId(1);
		$project->setStatus('Archived');
		$this->projectService->method('getProject')->with(1)->willReturn($project);
		$this->projectService->method('canUserManageMembers')->with('testuser', 1)->willReturn(true);
		$this->projectService->expects($this->never())->method('addTeamMember');

		$response = $this->controller->addTeamMember(1);
		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals(403, $response->getStatus());
		$data = $response->getData();
		$this->assertArrayHasKey('error', $data);
		$this->assertEquals('Cannot change the team for a completed, cancelled, or archived project', $data['error']);
	}

	public function testAddAllTeamMembersAddsOnlyEnabledNonMembers(): void {
		$this->user->method('getUID')->willReturn('testuser');
		$this->userSession->method('getUser')->willReturn($this->user);
		$project = new Project();
		$project->setId(1);
		$project->setStatus('Active');
		$this->projectService->method('getProject')->with(1)->willReturn($project);
		$this->projectService->method('canUserManageMembers')->with('testuser', 1)->willReturn(true);

		$existingMember = new ProjectMember();
		$existingMember->setUserId('existing');
		$this->projectService->method('getProjectTeamGrouped')->with(1)->willReturn(['active' => [$existingMember], 'former' => []]);

		$existing = $this->createMock(IUser::class);
		$existing->method('getUID')->willReturn('existing');
		$existing->method('isEnabled')->willReturn(true);
		$newEnabled = $this->createMock(IUser::class);
		$newEnabled->method('getUID')->willReturn('newenabled');
		$newEnabled->method('isEnabled')->willReturn(true);
		$newDisabled = $this->createMock(IUser::class);
		$newDisabled->method('getUID')->willReturn('newdisabled');
		$newDisabled->method('isEnabled')->willReturn(false);

		$this->userManager->expects($this->once())
			->method('search')
			->with('', 500, 0)
			->willReturn([$existing, $newEnabled, $newDisabled]);

		$this->projectService->expects($this->once())
			->method('addTeamMember')
			->with(1, 'newenabled', ProjectService::DEFAULT_MEMBER_ROLE, null);

		$response = $this->controller->addAllTeamMembers(1);
		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals(200, $response->getStatus());
		$data = $response->getData();
		$this->assertTrue($data['success']);
		$this->assertEquals(1, $data['added_count']);
	}

	public function testAddAllTeamMembersRejectsProjectMemberPricingMode(): void {
		$this->user->method('getUID')->willReturn('testuser');
		$this->userSession->method('getUser')->willReturn($this->user);
		$project = new Project();
		$project->setId(1);
		$project->setStatus('Active');
		$project->setCostRateMode(\OCA\ProjectCheck\Util\CostRateMode::PROJECT_MEMBER);
		$this->projectService->method('getProject')->with(1)->willReturn($project);
		$this->projectService->method('canUserManageMembers')->with('testuser', 1)->willReturn(true);
		$this->projectService->expects($this->never())->method('addTeamMember');
		$this->userManager->expects($this->never())->method('search');

		$response = $this->controller->addAllTeamMembers(1);

		$this->assertEquals(400, $response->getStatus());
		$data = $response->getData();
		$this->assertArrayHasKey('error', $data);
	}

	public function testAddAllTeamMembersRejectsNonEditableProjectState(): void {
		$this->user->method('getUID')->willReturn('testuser');
		$this->userSession->method('getUser')->willReturn($this->user);
		$project = new Project();
		$project->setId(1);
		$project->setStatus('Archived');
		$this->projectService->method('getProject')->with(1)->willReturn($project);
		$this->projectService->method('canUserManageMembers')->with('testuser', 1)->willReturn(true);
		$this->projectService->expects($this->never())->method('addTeamMember');
		$this->userManager->expects($this->never())->method('search');

		$response = $this->controller->addAllTeamMembers(1);
		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals(403, $response->getStatus());
		$data = $response->getData();
		$this->assertArrayHasKey('error', $data);
		$this->assertEquals('Cannot change the team for a completed, cancelled, or archived project', $data['error']);
	}

	public function testSearchAssignableUsersRejectsShortQueryWithoutDirectorySearch(): void {
		$this->user->method('getUID')->willReturn('testuser');
		$this->userSession->method('getUser')->willReturn($this->user);
		$project = new Project();
		$project->setId(1);
		$project->setStatus('Active');
		$this->projectService->method('getProject')->with(1)->willReturn($project);
		$this->projectService->method('canUserManageMembers')->with('testuser', 1)->willReturn(true);
		$this->request->method('getParam')->with('q', '')->willReturn('a');
		$this->userManager->expects($this->never())->method('search');
		$this->userManager->expects($this->never())->method('searchDisplayName');

		$response = $this->controller->searchAssignableUsers(1);

		$this->assertEquals(200, $response->getStatus());
		$data = $response->getData();
		$this->assertTrue($data['success']);
		$this->assertSame([], $data['items']);
	}

	public function testSearchAssignableUsersFiltersActiveMembersAndMergesMatches(): void {
		$this->user->method('getUID')->willReturn('testuser');
		$this->userSession->method('getUser')->willReturn($this->user);
		$project = new Project();
		$project->setId(1);
		$project->setStatus('Active');
		$this->projectService->method('getProject')->with(1)->willReturn($project);
		$this->projectService->method('canUserManageMembers')->with('testuser', 1)->willReturn(true);

		$existing = new ProjectMember();
		$existing->setUserId('existing');
		$this->projectService->method('getProjectTeamGrouped')->with(1)->willReturn(['active' => [$existing], 'former' => []]);
		$this->request->method('getParam')->with('q', '')->willReturn('al');

		$existingUser = $this->createMock(IUser::class);
		$existingUser->method('getUID')->willReturn('existing');
		$existingUser->method('getDisplayName')->willReturn('Existing Person');
		$existingUser->method('isEnabled')->willReturn(true);
		$alice = $this->createMock(IUser::class);
		$alice->method('getUID')->willReturn('alice');
		$alice->method('getDisplayName')->willReturn('Alice Example');
		$alice->method('isEnabled')->willReturn(true);
		$alex = $this->createMock(IUser::class);
		$alex->method('getUID')->willReturn('alex');
		$alex->method('getDisplayName')->willReturn('');
		$alex->method('isEnabled')->willReturn(true);
		$this->userManager->method('search')->with('al', 20, 0)->willReturn([$existingUser, $alice]);
		$this->userManager->method('searchDisplayName')->with('al', 20, 0)->willReturn([$alice, $alex]);

		$response = $this->controller->searchAssignableUsers(1);

		$this->assertEquals(200, $response->getStatus());
		$data = $response->getData();
		$this->assertTrue($data['success']);
		$this->assertCount(2, $data['items']);
		$this->assertSame('alice', $data['items'][0]['uid']);
		$this->assertSame('Alice Example (alice)', $data['items'][0]['label']);
		$this->assertSame('alex', $data['items'][1]['uid']);
	}

	public function testRemoveTeamMemberRejectsNonEditableProjectState(): void {
		$this->user->method('getUID')->willReturn('testuser');
		$this->userSession->method('getUser')->willReturn($this->user);
		$project = new Project();
		$project->setId(1);
		$project->setStatus('Completed');
		$this->projectService->method('getProject')->with(1)->willReturn($project);
		$this->projectService->method('canUserManageMembers')->with('testuser', 1)->willReturn(true);
		$this->projectService->expects($this->never())->method('removeTeamMember');

		$response = $this->controller->removeTeamMember(1, 'member1');
		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals(403, $response->getStatus());
		$data = $response->getData();
		$this->assertArrayHasKey('error', $data);
		$this->assertEquals('Cannot change the team for a completed, cancelled, or archived project', $data['error']);
	}

	/**
	 * Test search and filtering functionality
	 */
	public function testSearchAndFilteringFunctionality(): void {
		$this->user->method('getUID')->willReturn('testuser');
		$this->userSession->method('getUser')->willReturn($this->user);

		$searchParams = [
			'search' => 'test',
			'status' => 'Active',
			'category' => 'Development',
			'priority' => 'High',
			'limit' => 20,
			'offset' => 0
		];

		$this->request->method('getParam')->with('q', '')->willReturn('test');

		$projects = [
			new Project(),
			new Project()
		];

		$this->projectService->method('getUserScopedProjects')
			->with('testuser', ['search' => 'test'])
			->willReturn($projects);

		$response = $this->controller->search();

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals(200, $response->getStatus());

		$data = $response->getData();
		$this->assertTrue($data['success']);
		$this->assertIsArray($data['projects']);
		$this->assertCount(2, $data['projects']);
	}

	/**
	 * Test permission checks and access control
	 */
	public function testPermissionChecksAndAccessControl(): void {
		$this->user->method('getUID')->willReturn('testuser');
		$this->userSession->method('getUser')->willReturn($this->user);

		$project = new Project();
		$project->setId(1);
		$project->setName('Test Project');
		$project->setCreatedBy('otheruser');

		$this->projectService->method('getProject')->with(1)->willReturn($project);
		$this->projectService->method('canUserEditProject')->with('testuser', 1)->willReturn(true);
		$this->projectService->method('updateProject')
			->willThrowException(new \Exception('Access denied'));

		$this->request->method('getParams')->willReturn(['status' => 'Active']);
		$this->request->method('getMethod')->willReturn('PUT');
		$this->request->method('getParam')->with('_method')->willReturn(null);
		$this->request->method('getHeader')->with('X-Requested-With')->willReturn('XMLHttpRequest');

		$response = $this->controller->update(1);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals(400, $response->getStatus());

		$data = $response->getData();
		$this->assertArrayHasKey('error', $data);
		$this->assertEquals('Access denied', $data['error']);
	}

	/**
	 * Test API responses and error handling
	 */
	public function testApiResponsesAndErrorHandling(): void {
		$this->user->method('getUID')->willReturn('testuser');
		$this->userSession->method('getUser')->willReturn($this->user);

		$project = new Project();
		$project->setId(1);
		$project->setName('Test Project');

		$this->projectService->method('getProject')->with(1)->willReturn($project);
		$this->projectService->method('canUserAccessProject')->with('testuser', 1)->willReturn(true);

		$teamMembers = [
			new ProjectMember(),
			new ProjectMember()
		];

		$this->projectService->method('getProjectTeam')->with(1)->willReturn($teamMembers);

		$response = $this->controller->apiShow(1);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals(200, $response->getStatus());

		$data = $response->getData();
		$this->assertTrue($data['success']);
		$this->assertInstanceOf(Project::class, $data['project']);
		$this->assertIsArray($data['teamMembers']);
	}

	/**
	 * API: full project update still requires canUserEdit
	 */
	public function testApiUpdateFullProject(): void {
		$this->user->method('getUID')->willReturn('testuser');
		$this->userSession->method('getUser')->willReturn($this->user);

		$existing = new Project();
		$existing->setId(1);
		$existing->setName('N');
		$existing->setStatus('Active');

		$this->projectService->method('canUserAccessProject')->with('testuser', 1)->willReturn(true);
		$this->projectService->method('canUserEditProject')->with('testuser', 1)->willReturn(true);

		$payload = ['name' => 'Updated', 'requesttoken' => 'tok'];
		$this->request->method('getParams')->willReturn($payload);

		$updated = clone $existing;
		$updated->setName('Updated');
		$this->projectService->method('updateProject')->with(1, $this->equalTo(['name' => 'Updated']))->willReturn($updated);

		$response = $this->controller->apiUpdate(1);
		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals(200, $response->getStatus());
		$data = $response->getData();
		$this->assertTrue($data['success']);
	}

	/**
	 * API: status-only update works with canUserChange (e.g. reactivate Archived) when canUserEdit is false
	 */
	public function testApiUpdateStatusOnlyWithoutEditPermission(): void {
		$this->user->method('getUID')->willReturn('testuser');
		$this->userSession->method('getUser')->willReturn($this->user);

		$active = new Project();
		$active->setId(1);
		$active->setName('P');
		$active->setStatus('Active');

		$this->projectService->method('canUserAccessProject')->with('testuser', 1)->willReturn(true);
		$this->projectService->method('canUserEditProject')->with('testuser', 1)->willReturn(false);
		$this->projectService->method('canUserChangeProjectStatus')->with('testuser', 1)->willReturn(true);
		$this->request->method('getParams')->willReturn(['status' => 'Active', 'requesttoken' => 'tok']);

		$this->projectService->method('changeProjectStatus')->with(1, 'Active')->willReturn($active);
		$this->projectService->method('getProject')->with(1)->willReturn($active);

		$response = $this->controller->apiUpdate(1);
		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals(200, $response->getStatus());
		$data = $response->getData();
		$this->assertTrue($data['success']);
	}

	/**
	 * API: no access to project
	 */
	public function testApiUpdateAccessDeniedNoProjectAccess(): void {
		$this->user->method('getUID')->willReturn('testuser');
		$this->userSession->method('getUser')->willReturn($this->user);
		$this->projectService->method('canUserAccessProject')->with('testuser', 1)->willReturn(false);
		$this->request->method('getParams')->willReturn(['name' => 'X']);

		$response = $this->controller->apiUpdate(1);
		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals(403, $response->getStatus());
	}

	/**
	 * API: no payload after stripping internal keys
	 */
	public function testApiUpdateNoData(): void {
		$this->user->method('getUID')->willReturn('testuser');
		$this->userSession->method('getUser')->willReturn($this->user);
		$this->projectService->method('canUserAccessProject')->with('testuser', 1)->willReturn(true);
		$this->request->method('getParams')->willReturn(['requesttoken' => 'tok']);

		$response = $this->controller->apiUpdate(1);
		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals(400, $response->getStatus());
	}

	/**
	 * Test project status change functionality
	 */
	public function testProjectStatusChangeFunctionality(): void {
		$this->user->method('getUID')->willReturn('testuser');
		$this->userSession->method('getUser')->willReturn($this->user);

		$project = new Project();
		$project->setId(1);
		$project->setName('Test Project');
		$project->setStatus('Active');

		$this->projectService->method('getProject')->with(1)->willReturn($project);
		$this->projectService->method('canUserChangeProjectStatus')->with('testuser', 1)->willReturn(true);
		$this->stubRequestParamsForProjectStatusChange('On Hold', '');
		$this->request->method('getHeader')->with('X-Requested-With')->willReturn('XMLHttpRequest');

		$updatedProject = clone $project;
		$updatedProject->setStatus('On Hold');

		$this->projectService->method('changeProjectStatus')
			->with(1, 'On Hold')
			->willReturn($updatedProject);

		$response = $this->controller->changeStatus(1);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals(200, $response->getStatus());

		$data = $response->getData();
		$this->assertTrue($data['success']);
		$this->assertEquals('Project status updated successfully', $data['message']);
		// Controller returns only success/message; project object is not in response
	}

	public function testProjectStatusChangePostMatchesPutBehaviour(): void {
		$this->user->method('getUID')->willReturn('testuser');
		$this->userSession->method('getUser')->willReturn($this->user);

		$project = new Project();
		$project->setId(1);
		$project->setName('Test Project');
		$project->setStatus('Active');

		$this->projectService->method('getProject')->with(1)->willReturn($project);
		$this->projectService->method('canUserChangeProjectStatus')->with('testuser', 1)->willReturn(true);
		$this->stubRequestParamsForProjectStatusChange('Archived', '');
		$this->request->method('getHeader')->with('X-Requested-With')->willReturn('XMLHttpRequest');

		$updatedProject = clone $project;
		$updatedProject->setStatus('Archived');

		$this->projectService->method('changeProjectStatus')
			->with(1, 'Archived')
			->willReturn($updatedProject);

		$response = $this->controller->changeStatusPost(1);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals(200, $response->getStatus());
		$data = $response->getData();
		$this->assertTrue($data['success']);
	}

	public function testProjectStatusChangeMissingStatusReturnsErrorPayload(): void {
		$this->user->method('getUID')->willReturn('testuser');
		$this->userSession->method('getUser')->willReturn($this->user);
		$this->projectService->method('canUserChangeProjectStatus')->with('testuser', 1)->willReturn(true);
		$this->request->method('getParam')->with('status')->willReturn('');
		$this->request->method('getHeader')->with('X-Requested-With')->willReturn('XMLHttpRequest');

		$this->projectService->expects($this->never())->method('changeProjectStatus');

		$response = $this->controller->changeStatusPost(1);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals(400, $response->getStatus());
		$data = $response->getData();
		$this->assertFalse($data['success']);
		$this->assertEquals('Please select a status.', $data['error']);
	}

	public function testProjectStatusChangeInvalidTransitionMessage(): void {
		$this->user->method('getUID')->willReturn('testuser');
		$this->userSession->method('getUser')->willReturn($this->user);

		$project = new Project();
		$project->setId(1);
		$project->setName('Test Project');
		$project->setStatus('Completed');

		$this->projectService->method('canUserChangeProjectStatus')->with('testuser', 1)->willReturn(true);
		$this->stubRequestParamsForProjectStatusChange('Active', '');
		$this->request->method('getHeader')->with('X-Requested-With')->willReturn('XMLHttpRequest');

		$this->projectService->method('getProject')->with(1)->willReturn($project);

		$this->projectService->method('changeProjectStatus')
			->willThrowException(new \Exception("Invalid status transition from 'Completed' to 'Active'"));

		$response = $this->controller->changeStatus(1);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals(400, $response->getStatus());
		$data = $response->getData();
		$this->assertFalse($data['success']);
		$this->assertEquals('This status change is not allowed for the current project state.', $data['error']);
	}

	/**
	 * Test project deletion functionality
	 */
	public function testProjectDeletionFunctionality(): void {
		$this->user->method('getUID')->willReturn('testuser');
		$this->userSession->method('getUser')->willReturn($this->user);

		$project = new Project();
		$project->setId(1);
		$project->setName('Test Project');
		$project->setStatus('Active');

		$this->projectService->method('getProject')->with(1)->willReturn($project);
		$this->projectService->method('canUserDeleteProject')->with('testuser', 1)->willReturn(true);

		$this->request->method('getMethod')->willReturn('DELETE');
		$this->request->method('getParam')->with('_method')->willReturn(null);
		$this->request->method('getHeader')->with('X-Requested-With')->willReturn('XMLHttpRequest');

		$this->projectService->method('deleteProject')->with(1)->willReturn(true);

		$response = $this->controller->delete(1);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals(200, $response->getStatus());

		$data = $response->getData();
		$this->assertTrue($data['success']);
		$this->assertEquals('Project deleted successfully', $data['message']);
	}

	/**
	 * Deletion modal uses POST /projects/{id}/delete (reliable CSRF).
	 */
	public function testProjectDeletionPost(): void {
		$this->user->method('getUID')->willReturn('testuser');
		$this->userSession->method('getUser')->willReturn($this->user);

		$project = new Project();
		$project->setId(1);
		$project->setName('Test Project');
		$project->setStatus('Active');

		$this->projectService->method('getProject')->with(1)->willReturn($project);
		$this->projectService->method('canUserDeleteProject')->with('testuser', 1)->willReturn(true);
		$this->request->method('getHeader')->with('X-Requested-With')->willReturn('XMLHttpRequest');
		$this->projectService->method('deleteProject')->with(1)->willReturn(true);

		$response = $this->controller->deletePost(1);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals(200, $response->getStatus());
		$data = $response->getData();
		$this->assertTrue($data['success']);
	}

	/**
	 * Test error handling for invalid data
	 */
	public function testErrorHandlingForInvalidData(): void {
		$this->user->method('getUID')->willReturn('testuser');
		$this->userSession->method('getUser')->willReturn($this->user);

		$invalidData = [
			'name' => '',
			'short_description' => 'A test project',
			'customer_id' => 1,
			'hourly_rate' => -10.0,
			'total_budget' => 5000.0
		];

		$this->request->method('getParams')->willReturn($invalidData);
		$this->request->method('getHeader')->with('X-Requested-With')->willReturn('XMLHttpRequest');

		$this->projectService->method('createProject')
			->willThrowException(new \Exception('Project name is required'));

		$response = $this->controller->store();

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals(400, $response->getStatus());

		$data = $response->getData();
		$this->assertArrayHasKey('error', $data);
		$this->assertEquals('Project name is required', $data['error']);
	}

	/**
	 * Test unauthenticated access
	 */
	public function testUnauthenticatedAccess(): void {
		$this->userSession->method('getUser')->willReturn(null);

		$response = $this->controller->index();

		$this->assertInstanceOf(TemplateResponse::class, $response);
		$this->assertEquals('error', $response->getTemplateName());

		$params = $response->getParams();
		$this->assertArrayHasKey('message', $params);
		$this->assertArrayHasKey('homeUrl', $params);
		$this->assertStringContainsString('User not authenticated', (string) $params['message']);
	}

	/**
	 * Resolve API: self-only user_id (A6 / T2.19).
	 */
	public function testResolveHourlyRateRejectsQueryingAnotherUser(): void {
		$this->user->method('getUID')->willReturn('alice');
		$this->userSession->method('getUser')->willReturn($this->user);
		$this->projectService->method('canUserAccessProject')->with('alice', 9)->willReturn(true);
		$this->request->method('getParam')->willReturnCallback(static function (string $key, $default = null) {
			return match ($key) {
				'date' => '2026-04-01',
				'user_id' => 'bob',
				default => $default,
			};
		});
		$this->hourlyRateService->expects($this->never())->method('resolvePreview');

		$response = $this->controller->resolveHourlyRate(9);

		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertEquals(403, $response->getStatus());
	}

	public function testResolveHourlyRateSuccessForSessionUser(): void {
		$this->user->method('getUID')->willReturn('alice');
		$this->userSession->method('getUser')->willReturn($this->user);
		$this->projectService->method('canUserAccessProject')->with('alice', 9)->willReturn(true);
		$this->request->method('getParam')->willReturnCallback(static function (string $key, $default = null) {
			return match ($key) {
				'date' => '01.04.2026',
				'user_id' => '',
				default => $default,
			};
		});
		$this->hourlyRateService->method('resolvePreview')->willReturn([
			'hourly_rate' => 88.0,
			'cost_rate_mode' => 'project',
			'source' => 'project',
		]);

		$response = $this->controller->resolveHourlyRate(9);

		$this->assertEquals(200, $response->getStatus());
		$data = $response->getData();
		$this->assertTrue($data['success']);
		$this->assertEqualsWithDelta(88.0, (float) $data['data']['hourly_rate'], 0.001);
	}

	public function testResolveHourlyRateDeniedWithoutProjectAccess(): void {
		$this->user->method('getUID')->willReturn('alice');
		$this->userSession->method('getUser')->willReturn($this->user);
		$this->projectService->method('canUserAccessProject')->with('alice', 9)->willReturn(false);
		$this->request->method('getParam')->willReturn('2026-04-01');
		$this->hourlyRateService->expects($this->never())->method('resolvePreview');

		$response = $this->controller->resolveHourlyRate(9);

		$this->assertEquals(403, $response->getStatus());
	}

	/**
	 * Rate-resolution API must not echo raw exception text (audit / info leak).
	 */
	public function testResolveHourlyRateDoesNotExposeInternalExceptionText(): void {
		$leaked = 'INTERNAL_SQL_or_stack_trace_xyz_9f3a';
		$this->user->method('getUID')->willReturn('alice');
		$this->userSession->method('getUser')->willReturn($this->user);
		$this->projectService->method('canUserAccessProject')->with('alice', 9)->willReturn(true);
		$this->request->method('getParam')->willReturnCallback(static function (string $key, $default = null) {
			return match ($key) {
				'date' => '2026-04-01',
				'user_id' => '',
				default => $default,
			};
		});
		$this->hourlyRateService->method('resolvePreview')->willThrowException(
			new RateResolutionException($leaked, 'employee_rate_missing')
		);

		$response = $this->controller->resolveHourlyRate(9);

		$this->assertEquals(400, $response->getStatus());
		$data = $response->getData();
		$this->assertFalse($data['success']);
		$this->assertSame('employee_rate_missing', $data['code']);
		$this->assertStringNotContainsString($leaked, (string) ($data['error'] ?? ''));
		$this->assertStringContainsString('employee hourly rate', (string) $data['error']);
	}

	public function testCheckBudgetImpactDoesNotExposeInternalExceptionText(): void {
		$leaked = 'SECRET_RATE_DEBUG_b7c2';
		$project = $this->createMock(Project::class);
		$this->user->method('getUID')->willReturn('alice');
		$this->userSession->method('getUser')->willReturn($this->user);
		$this->projectService->method('getProject')->with(9)->willReturn($project);
		$this->projectService->method('canUserAddTimeEntryForProject')->with('alice', 9)->willReturn(true);
		$this->request->method('getParam')->willReturnCallback(static function (string $key, $default = null) {
			return match ($key) {
				'project_id' => '9',
				'additional_hours' => '2',
				'entry_date' => '2026-04-01',
				default => $default,
			};
		});
		$this->hourlyRateService->method('resolveForTimeEntry')->willThrowException(
			new RateResolutionException($leaked, 'rate_tamper')
		);

		$response = $this->controller->checkBudgetImpact();

		$this->assertEquals(400, $response->getStatus());
		$data = $response->getData();
		$this->assertFalse($data['success']);
		$this->assertSame('rate_tamper', $data['code']);
		$this->assertStringNotContainsString($leaked, (string) ($data['error'] ?? ''));
		$this->assertStringContainsString('hourly rate', (string) $data['error']);
	}

	/**
	 * Build an enriched list-view item as returned by ProjectService::getProjectsForListView().
	 *
	 * @param array<string, mixed> $budgetInfo
	 * @return array{project: Project, budgetInfo: array, canEdit: bool}
	 */
	private function enrichedProjectFixture(Project $project, array $budgetInfo = []): array {
		return [
			'project' => $project,
			'budgetInfo' => array_merge([
				'total_budget' => 1000.0,
				'used_budget' => 250.0,
				'remaining_budget' => 750.0,
				'consumption_percentage' => 25.0,
				'warning_level' => 'none',
				'used_hours' => 5.0,
				'remaining_hours' => 15.0,
				'available_hours' => 20.0,
				'hours_estimated' => false,
			], $budgetInfo),
			'canEdit' => true,
		];
	}

	public function testExportRequiresAuthentication(): void {
		// No user stubbed on the session: getUser() returns null.
		$response = $this->controller->export();

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertSame(401, $response->getStatus());
	}

	public function testExportGeneratesCsvWithConfiguredCurrencyAndRowData(): void {
		$this->user->method('getUID')->willReturn('alice');
		$this->userSession->method('getUser')->willReturn($this->user);
		$this->request->method('getParam')->willReturnCallback(
			static fn (string $key, $default = null) => $default
		);
		$this->config->method('getAppValue')->willReturnCallback(
			static fn (string $app, string $key, string $default = ''): string =>
				($app === 'projectcheck' && $key === 'currency') ? 'usd' : $default
		);

		$project = new Project();
		$project->setId(7);
		$project->setName('Website Relaunch');
		$project->setCustomerName('ACME GmbH');
		$project->setStatus('Active');
		$project->setPriority('High');
		$project->setProjectType('client');
		$project->setHourlyRate(120.0);
		$project->setTotalBudget(1000.0);
		$project->setStartDate(new \DateTime('2026-01-15'));
		$project->setEndDate(new \DateTime('2026-06-30'));
		$project->setTags('web, relaunch');
		$project->setCreatedBy('alice');
		$project->setCreatedAt(new \DateTime('2026-01-10 09:30:00'));

		$this->projectService->expects($this->once())
			->method('getProjectsForListView')
			->with(
				$this->callback(static function (array $filters): bool {
					// Missing status parameter must default to Active — the same
					// view the list page shows — and pagination must be absent so
					// the export covers all pages.
					return ($filters['status'] ?? null) === 'Active'
						&& !array_key_exists('limit', $filters)
						&& !array_key_exists('offset', $filters);
				}),
				'remaining_budget',
				'asc',
				'alice'
			)
			->willReturn([$this->enrichedProjectFixture($project)]);

		$response = $this->controller->export();

		$this->assertInstanceOf(DataDownloadResponse::class, $response);
		$this->assertSame(200, $response->getStatus());
		$csv = (string)$response->render();
		$this->assertStringStartsWith("\xEF\xBB\xBF", $csv, 'CSV DataDownloadResponse must include a single UTF-8 BOM');
		$this->assertStringContainsString('"Total Budget (USD)";"Used Budget (USD)"', $csv);
		$this->assertStringContainsString('"Website Relaunch";"ACME GmbH";"Client Project";"Active";"High"', $csv);
		$this->assertStringContainsString('"2026-01-15";"2026-06-30"', $csv);
		$this->assertStringContainsString('"1000,00";"250,00";"750,00";"25,0";"5,00";"15,00";"120,00"', $csv);
		$this->assertStringContainsString('"One rate for the whole project"', $csv);
		$headers = $response->getHeaders();
		$this->assertSame('1', $headers['X-ProjectCheck-Export-Row-Count'] ?? null);
		$this->assertSame('csv', $headers['X-ProjectCheck-Export-Format'] ?? null);
		$this->assertMatchesRegularExpression(
			'/attachment;\s*filename="?projects_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.csv"?/i',
			(string)($headers['Content-Disposition'] ?? '')
		);
	}

	public function testExportJsonFormatReturnsMachineReadableRecords(): void {
		$this->user->method('getUID')->willReturn('alice');
		$this->userSession->method('getUser')->willReturn($this->user);
		$this->request->method('getParam')->willReturnCallback(
			static function (string $key, $default = null) {
				return $key === 'format' ? 'json' : $default;
			}
		);
		$this->config->method('getAppValue')->willReturnCallback(
			static fn (string $app, string $key, string $default = ''): string =>
				($app === 'projectcheck' && $key === 'currency') ? 'EUR' : $default
		);

		$project = new Project();
		$project->setId(7);
		$project->setName('Website Relaunch');
		$project->setCustomerName('ACME GmbH');
		$project->setStatus('Active');
		$project->setPriority('High');
		$project->setProjectType('client');
		$project->setHourlyRate(120.0);
		$project->setTotalBudget(1000.0);
		$project->setCreatedBy('alice');

		$this->projectService->method('getProjectsForListView')
			->willReturn([$this->enrichedProjectFixture($project)]);

		$response = $this->controller->export();
		$this->assertInstanceOf(DataDownloadResponse::class, $response);
		$this->assertSame(200, $response->getStatus());
		$headers = $response->getHeaders();
		$this->assertSame('json', $headers['X-ProjectCheck-Export-Format'] ?? null);
		$this->assertSame('1', $headers['X-ProjectCheck-Export-Row-Count'] ?? null);
		$this->assertMatchesRegularExpression(
			'/attachment;\s*filename="?projects_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.json"?/i',
			(string)($headers['Content-Disposition'] ?? '')
		);
		$decoded = json_decode((string)$response->render(), true);
		$this->assertIsArray($decoded);
		$this->assertSame(1, $decoded['record_count']);
		$this->assertSame('EUR', $decoded['currency']);
		$this->assertSame('Website Relaunch', $decoded['records'][0]['name']);
		$this->assertEqualsWithDelta(1000.0, (float)$decoded['records'][0]['total_budget'], 0.0001);
	}

	public function testExportNeutralizesCsvFormulaInjectionInTextFields(): void {
		$this->user->method('getUID')->willReturn('alice');
		$this->userSession->method('getUser')->willReturn($this->user);
		$this->request->method('getParam')->willReturnCallback(
			static fn (string $key, $default = null) => $default
		);

		$project = new Project();
		$project->setId(8);
		$project->setName('=cmd|/c calc');
		$project->setCustomerName('+SUM(A1:A2)');
		$project->setStatus('Active');
		$project->setPriority('Low');
		$project->setTags('@import');
		$project->setShortDescription('-2+3');
		$project->setCreatedBy('alice');

		$this->projectService->method('getProjectsForListView')
			->willReturn([$this->enrichedProjectFixture($project)]);

		$response = $this->controller->export();

		$this->assertInstanceOf(DataDownloadResponse::class, $response);
		$this->assertSame(200, $response->getStatus());
		$csv = (string)$response->render();
		$this->assertStringContainsString("\"'=cmd|/c calc\"", $csv);
		$this->assertStringContainsString("\"'+SUM(A1:A2)\"", $csv);
		$this->assertStringContainsString("\"'@import\"", $csv);
		$this->assertStringContainsString("\"'-2+3\"", $csv);
	}

	public function testExportHonoursStatusAllAndRejectsUnknownSortColumns(): void {
		$this->user->method('getUID')->willReturn('alice');
		$this->userSession->method('getUser')->willReturn($this->user);
		$this->request->method('getParam')->willReturnCallback(
			static function (string $key, $default = null) {
				return match ($key) {
					'status' => 'all',
					'sort' => 'evil;drop table',
					'direction' => 'DESC',
					'search' => 'relaunch',
					'customer_id' => '3',
					default => $default,
				};
			}
		);

		$this->projectService->expects($this->once())
			->method('getProjectsForListView')
			->with(
				$this->callback(static function (array $filters): bool {
					return ($filters['status'] ?? null) === 'all'
						&& ($filters['search'] ?? null) === 'relaunch'
						&& ($filters['customer_id'] ?? null) === '3';
				}),
				'remaining_budget',
				'desc',
				'alice'
			)
			->willReturn([]);

		$response = $this->controller->export();

		$this->assertInstanceOf(DataDownloadResponse::class, $response);
		$this->assertSame(200, $response->getStatus());
		$this->assertSame('0', $response->getHeaders()['X-ProjectCheck-Export-Row-Count'] ?? null);
		// Header-only CSV after BOM: exactly one data line terminator from the header row.
		$csv = (string)$response->render();
		$this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
		$this->assertSame(1, substr_count(substr($csv, 3), "\n"));
	}

	public function testExportRejectsWhenOverRowCeiling(): void {
		$this->user->method('getUID')->willReturn('alice');
		$this->userSession->method('getUser')->willReturn($this->user);
		$this->request->method('getParam')->willReturnCallback(
			static fn (string $key, $default = null) => $default
		);

		// Build a sparse list larger than the ceiling without materialising real projects.
		$overLimit = array_fill(0, ListExportService::MAX_EXPORT_ROWS + 1, ['skip' => true]);
		$this->projectService->method('getProjectsForListView')->willReturn($overLimit);

		$response = $this->controller->export();

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertSame(422, $response->getStatus());
		$this->assertArrayHasKey('error', $response->getData());
		$this->assertStringContainsString('100,000', (string)$response->getData()['error']);
		$this->assertStringContainsString('Too many rows', (string)$response->getData()['error']);
	}

	public function testExportFailureDoesNotLeakInternals(): void {
		$leaked = 'SQLSTATE_secret_detail_42';
		$this->user->method('getUID')->willReturn('alice');
		$this->userSession->method('getUser')->willReturn($this->user);
		$this->request->method('getParam')->willReturnCallback(
			static fn (string $key, $default = null) => $default
		);
		$this->projectService->method('getProjectsForListView')
			->willThrowException(new \RuntimeException($leaked));

		$response = $this->controller->export();

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertSame(500, $response->getStatus());
		$this->assertStringNotContainsString($leaked, (string) ($response->getData()['error'] ?? ''));
	}
}
