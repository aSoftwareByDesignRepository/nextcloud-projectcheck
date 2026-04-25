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
use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Service\CustomerService;
use OCA\ProjectCheck\Service\TimeEntryService;
use OCA\ProjectCheck\Service\BudgetService;
use OCA\ProjectCheck\Service\DeletionService;
use OCA\ProjectCheck\Service\ActivityService;
use OCA\ProjectCheck\Service\ProjectFileService;
use OCA\ProjectCheck\Service\CSPService;
use OCA\ProjectCheck\Service\IRequestTokenProvider;
use OCA\ProjectCheck\Db\Project;
use OCA\ProjectCheck\Db\ProjectMember;
use OCP\AppFramework\Http\DataResponse;
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

	/** @var IRequest|\PHPUnit\Framework\MockObject\MockObject */
	private $request;

	/** @var IUserSession|\PHPUnit\Framework\MockObject\MockObject */
	private $userSession;

	/** @var IUser|\PHPUnit\Framework\MockObject\MockObject */
	private $user;

	/** @var IL10N|\PHPUnit\Framework\MockObject\MockObject */
	private $l10n;

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

		$customerService = $this->createMock(CustomerService::class);
		$timeEntryService = $this->createMock(TimeEntryService::class);
		$budgetService = $this->createMock(BudgetService::class);
		$deletionService = $this->createMock(DeletionService::class);
		$activityService = $this->createMock(ActivityService::class);
		$projectFileService = $this->createMock(ProjectFileService::class);
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$config = $this->createMock(IConfig::class);
		$cspService = $this->createMock(CSPService::class);
		$cspService->method('applyPolicyWithNonce')->willReturnArgument(0);
		$requestToken = $this->createMock(IRequestTokenProvider::class);
		$requestToken->method('getEncryptedRequestToken')->willReturn('mock-encrypted-csrf');
		$userManager = $this->createMock(\OCP\IUserManager::class);
		$userAccountSnapshot = $this->createMock(\OCA\ProjectCheck\Db\UserAccountSnapshotMapper::class);

		$this->controller = new ProjectController(
			'projectcheck',
			$this->request,
			$this->projectService,
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
			$userManager,
			$userAccountSnapshot
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
				['user_id', null, 'newuser'],
				['role', null, 'Developer'],
				['hourly_rate', null, 45.0]
			]);

		$member = new ProjectMember();
		$member->setId(1);
		$member->setProjectId(1);
		$member->setUserId('newuser');
		$member->setRole('Developer');
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

		$this->projectService->method('getProjects')->willReturn($projects);

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
		$this->request->method('getParam')->with('status')->willReturn('On Hold');
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
		$this->assertArrayHasKey('error', $params);
		$this->assertEquals('User not authenticated', $params['error']);
	}
}
