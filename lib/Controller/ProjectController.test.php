<?php
/**
 * Integration tests for ProjectController
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Tests\Controller;

use OCA\ProjectCheck\Controller\ProjectController;
use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Db\Project;
use OCA\ProjectCheck\Db\ProjectMember;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\ILogger;
use OCP\IUser;
use PHPUnit\Framework\TestCase;

/**
 * Class ProjectControllerTest
 *
 * @package OCA\ProjectControl\Tests\Controller
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

	/** @var ILogger|\PHPUnit\Framework\MockObject\MockObject */
	private $logger;

	/** @var IUser|\PHPUnit\Framework\MockObject\MockObject */
	private $user;

	protected function setUp(): void {
		parent::setUp();

		$this->projectService = $this->createMock(ProjectService::class);
		$this->request = $this->createMock(IRequest::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->logger = $this->createMock(ILogger::class);
		$this->user = $this->createMock(IUser::class);

		$this->controller = new ProjectController(
			'projectcheck',
			$this->request,
			$this->projectService,
			$this->userSession,
			$this->logger
		);
	}

	/**
	 * Test complete project creation workflow
	 */
	public function testCompleteProjectCreationWorkflow(): void {
		// Mock user authentication
		$this->user->method('getUID')->willReturn('testuser');
		$this->userSession->method('getUser')->willReturn($this->user);

		// Mock request parameters
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

		// Mock project creation
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

		// Test project creation
		$response = $this->controller->store();

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals(200, $response->getStatus());
		
		$data = $response->getData();
		$this->assertTrue($data['success']);
		$this->assertEquals('Project created successfully', $data['message']);
		$this->assertInstanceOf(Project::class, $data['project']);
	}

	/**
	 * Test project editing and validation
	 */
	public function testProjectEditingAndValidation(): void {
		// Mock user authentication
		$this->user->method('getUID')->willReturn('testuser');
		$this->userSession->method('getUser')->willReturn($this->user);

		// Mock existing project
		$existingProject = new Project();
		$existingProject->setId(1);
		$existingProject->setName('Original Project');
		$existingProject->setStatus('Active');
		$existingProject->setCreatedBy('testuser');

		$this->projectService->method('getProject')->with(1)->willReturn($existingProject);

		// Mock update data
		$updateData = [
			'name' => 'Updated Project',
			'short_description' => 'Updated description',
			'total_budget' => 6000.0,
			'hourly_rate' => 60.0
		];

		$this->request->method('getParams')->willReturn($updateData);

		// Mock updated project
		$updatedProject = clone $existingProject;
		$updatedProject->setName('Updated Project');
		$updatedProject->setShortDescription('Updated description');
		$updatedProject->setTotalBudget(6000.0);
		$updatedProject->setHourlyRate(60.0);
		$updatedProject->setAvailableHours(100.0);

		$this->projectService->method('updateProject')->willReturn($updatedProject);

		// Test project update
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
		// Mock user authentication
		$this->user->method('getUID')->willReturn('testuser');
		$this->userSession->method('getUser')->willReturn($this->user);

		// Mock project
		$project = new Project();
		$project->setId(1);
		$project->setName('Test Project');

		$this->projectService->method('getProject')->with(1)->willReturn($project);

		// Mock team member data
		$memberData = [
			'user_id' => 'newuser',
			'role' => 'Developer',
			'hourly_rate' => 45.0
		];

		$this->request->method('getParam')
			->willReturnMap([
				['user_id', 'newuser'],
				['role', 'Developer'],
				['hourly_rate', 45.0]
			]);

		// Mock team member
		$member = new ProjectMember();
		$member->setId(1);
		$member->setProjectId(1);
		$member->setUserId('newuser');
		$member->setRole('Developer');
		$member->setHourlyRate(45.0);

		$this->projectService->method('addTeamMember')->willReturn($member);

		// Test adding team member
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
		// Mock user authentication
		$this->user->method('getUID')->willReturn('testuser');
		$this->userSession->method('getUser')->willReturn($this->user);

		// Mock search parameters
		$searchParams = [
			'search' => 'test',
			'status' => 'Active',
			'category' => 'Development',
			'priority' => 'High',
			'limit' => 20,
			'offset' => 0
		];

		$this->request->method('getParams')->willReturn($searchParams);

		// Mock search results
		$projects = [
			new Project(),
			new Project()
		];

		$this->projectService->method('getProjects')->willReturn($projects);

		// Test search functionality
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
		// Mock user authentication
		$this->user->method('getUID')->willReturn('testuser');
		$this->userSession->method('getUser')->willReturn($this->user);

		// Mock project with different creator
		$project = new Project();
		$project->setId(1);
		$project->setName('Test Project');
		$project->setCreatedBy('otheruser');

		$this->projectService->method('getProject')->with(1)->willReturn($project);

		// Mock permission checks
		$this->projectService->method('canUserAccessProject')
			->with('testuser', 1)
			->willReturn(false);

		$this->projectService->method('canUserEditProject')
			->with('testuser', 1)
			->willReturn(false);

		// Test access control - should return error for unauthorized access
		$this->projectService->method('updateProject')
			->willThrowException(new \Exception('Access denied'));

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
		// Mock user authentication
		$this->user->method('getUID')->willReturn('testuser');
		$this->userSession->method('getUser')->willReturn($this->user);

		// Mock project
		$project = new Project();
		$project->setId(1);
		$project->setName('Test Project');

		$this->projectService->method('getProject')->with(1)->willReturn($project);

		// Mock team members
		$teamMembers = [
			new ProjectMember(),
			new ProjectMember()
		];

		$this->projectService->method('getProjectTeam')->with(1)->willReturn($teamMembers);

		// Test API show endpoint
		$response = $this->controller->apiShow(1);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals(200, $response->getStatus());
		
		$data = $response->getData();
		$this->assertTrue($data['success']);
		$this->assertInstanceOf(Project::class, $data['project']);
		$this->assertIsArray($data['teamMembers']);
	}

	/**
	 * Test project status change functionality
	 */
	public function testProjectStatusChangeFunctionality(): void {
		// Mock user authentication
		$this->user->method('getUID')->willReturn('testuser');
		$this->userSession->method('getUser')->willReturn($this->user);

		// Mock project
		$project = new Project();
		$project->setId(1);
		$project->setName('Test Project');
		$project->setStatus('Active');

		$this->projectService->method('getProject')->with(1)->willReturn($project);

		// Mock status change
		$this->request->method('getParam')->with('status')->willReturn('On Hold');

		$updatedProject = clone $project;
		$updatedProject->setStatus('On Hold');

		$this->projectService->method('updateProject')
			->with(1, ['status' => 'On Hold'])
			->willReturn($updatedProject);

		// Test status change
		$response = $this->controller->changeStatus(1);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals(200, $response->getStatus());
		
		$data = $response->getData();
		$this->assertTrue($data['success']);
		$this->assertEquals('Project status updated successfully', $data['message']);
		$this->assertEquals('On Hold', $data['project']->getStatus());
	}

	/**
	 * Test project deletion functionality
	 */
	public function testProjectDeletionFunctionality(): void {
		// Mock user authentication
		$this->user->method('getUID')->willReturn('testuser');
		$this->userSession->method('getUser')->willReturn($this->user);

		// Mock project
		$project = new Project();
		$project->setId(1);
		$project->setName('Test Project');
		$project->setStatus('Active');

		$this->projectService->method('getProject')->with(1)->willReturn($project);

		// Mock empty team members (allows deletion)
		$this->projectService->method('getProjectTeam')->with(1)->willReturn([]);

		// Mock successful deletion
		$this->projectService->method('deleteProject')->with(1)->willReturn(true);

		// Test project deletion
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
		// Mock user authentication
		$this->user->method('getUID')->willReturn('testuser');
		$this->userSession->method('getUser')->willReturn($this->user);

		// Mock invalid project data
		$invalidData = [
			'name' => '', // Empty name should cause validation error
			'short_description' => 'A test project',
			'customer_id' => 1,
			'hourly_rate' => -10.0, // Invalid negative rate
			'total_budget' => 5000.0
		];

		$this->request->method('getParams')->willReturn($invalidData);

		// Mock validation error
		$this->projectService->method('createProject')
			->willThrowException(new \Exception('Project name is required'));

		// Test error handling
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
		// Mock no user authentication
		$this->userSession->method('getUser')->willReturn(null);

		// Test unauthenticated access
		$response = $this->controller->index();

		$this->assertInstanceOf(TemplateResponse::class, $response);
		$this->assertEquals('error', $response->getTemplateName());
		
		$data = $response->getData();
		$this->assertEquals('User not authenticated', $data['error']);
	}
}
