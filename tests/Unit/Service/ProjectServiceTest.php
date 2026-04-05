<?php

declare(strict_types=1);

/**
 * Unit tests for ProjectService
 *
 * Not added to the default `unit` testsuite in `phpunit.xml` until QueryBuilder / IResult mocks match the current API.
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Tests\Unit\Service;

use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Db\Project;
use OCA\ProjectCheck\Db\ProjectMember;
use OCP\IDBConnection;
use OCP\IUserSession;
use OCP\IUserManager;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\DB\QueryBuilder\IQueryBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Class ProjectServiceTest
 *
 * @package OCA\ProjectCheck\Tests\Unit\Service
 */
class ProjectServiceTest extends TestCase {

	/** @var ProjectService */
	private $projectService;

	/** @var IDBConnection|\PHPUnit\Framework\MockObject\MockObject */
	private $db;

	/** @var IUserSession|\PHPUnit\Framework\MockObject\MockObject */
	private $userSession;

	/** @var IConfig|\PHPUnit\Framework\MockObject\MockObject */
	private $config;

	/** @var IGroupManager|\PHPUnit\Framework\MockObject\MockObject */
	private $groupManager;

	/** @var IUserManager|\PHPUnit\Framework\MockObject\MockObject */
	private $userManager;

	/** @var IUser|\PHPUnit\Framework\MockObject\MockObject */
	private $user;

	protected function setUp(): void {
		parent::setUp();

		$this->db = $this->createMock(IDBConnection::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->config = $this->createMock(IConfig::class);
		$this->config->method('getUserValue')->willReturnArgument(3);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->groupManager->method('isAdmin')->willReturn(false);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->user = $this->createMock(IUser::class);

		$this->projectService = new ProjectService(
			$this->db,
			$this->userSession,
			$this->userManager,
			$this->config,
			$this->groupManager,
			null
		);
	}

	/**
	 * Test project creation with valid data
	 */
	public function testCreateProjectWithValidData(): void {
		$data = [
			'name' => 'Test Project',
			'short_description' => 'A test project',
			'customer_id' => 1,
			'hourly_rate' => 50.0,
			'total_budget' => 5000.0,
			'category' => 'Development',
			'priority' => 'Medium'
		];

		$this->user->method('getUID')->willReturn('testuser');
		$this->userSession->method('getUser')->willReturn($this->user);

		$qb = $this->createMock(IQueryBuilder::class);
		$qb->method('insert')->willReturnSelf();
		$qb->method('values')->willReturnSelf();
		$qb->method('createNamedParameter')->willReturn(':param');
		$qb->method('executeQuery')->willReturnSelf();
		$qb->method('executeStatement')->willReturn(1);
		$this->db->method('getQueryBuilder')->willReturn($qb);
		$this->db->method('lastInsertId')->willReturn(1);

		$project = $this->projectService->createProject($data);

		$this->assertInstanceOf(Project::class, $project);
		$this->assertEquals('Test Project', $project->getName());
		$this->assertEquals('testuser', $project->getCreatedBy());
	}

	/**
	 * Test project creation with missing required fields
	 */
	public function testCreateProjectWithMissingRequiredFields(): void {
		$data = [
			'name' => 'Test Project',
			// Missing required fields
		];

		$this->expectException(\Exception::class);
		$this->expectExceptionMessage("Field 'short_description' is required");

		$this->projectService->createProject($data);
	}

	/**
	 * Test project creation with invalid field lengths
	 */
	public function testCreateProjectWithInvalidFieldLengths(): void {
		$data = [
			'name' => str_repeat('a', 101), // Too long
			'short_description' => 'A test project',
			'customer_id' => 1,
			'hourly_rate' => 50.0,
			'total_budget' => 5000.0
		];

		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Project name must be 100 characters or less');

		$this->projectService->createProject($data);
	}

	/**
	 * Test project creation with invalid numeric values
	 */
	public function testCreateProjectWithInvalidNumericValues(): void {
		$data = [
			'name' => 'Test Project',
			'short_description' => 'A test project',
			'customer_id' => 1,
			'hourly_rate' => -10.0, // Invalid
			'total_budget' => 5000.0
		];

		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Hourly rate must be greater than zero');

		$this->projectService->createProject($data);
	}

	/**
	 * Test project creation with invalid date range
	 */
	public function testCreateProjectWithInvalidDateRange(): void {
		$data = [
			'name' => 'Test Project',
			'short_description' => 'A test project',
			'customer_id' => 1,
			'hourly_rate' => 50.0,
			'total_budget' => 5000.0,
			'start_date' => '2024-01-01',
			'end_date' => '2023-12-31' // Before start date
		];

		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('End date must be after start date');

		$this->projectService->createProject($data);
	}

	/**
	 * Test adding team member
	 */
	public function testAddTeamMember(): void {
		$projectId = 1;
		$userId = 'testuser';
		$role = 'Developer';
		$hourlyRate = 45.0;

		// Mock project exists
		$project = new Project();
		$project->setId($projectId);
		$project->setName('Test Project');

		$qb = $this->createMock(IQueryBuilder::class);
		$qb->method('select')->willReturnSelf();
		$qb->method('from')->willReturnSelf();
		$qb->method('where')->willReturnSelf();
		$qb->method('andWhere')->willReturnSelf();
		$qb->method('createNamedParameter')->willReturn(':param');
		$qb->method('executeQuery')->willReturnSelf();
		$qb->method('executeStatement')->willReturn(1);
		$qb->method('fetch')->willReturn(false); // No existing member
		$qb->method('closeCursor')->willReturnSelf();
		$qb->method('insert')->willReturnSelf();
		$qb->method('values')->willReturnSelf();
		$this->db->method('getQueryBuilder')->willReturn($qb);
		$this->db->method('lastInsertId')->willReturn(1);

		// Mock user exists
		$user = $this->createMock(IUser::class);
		$this->userManager->method('get')->with($userId)->willReturn($user);

		// Mock current user
		$this->user->method('getUID')->willReturn('admin');
		$this->userSession->method('getUser')->willReturn($this->user);

		$member = $this->projectService->addTeamMember($projectId, $userId, $role, $hourlyRate);

		$this->assertInstanceOf(ProjectMember::class, $member);
		$this->assertEquals($projectId, $member->getProjectId());
		$this->assertEquals($userId, $member->getUserId());
		$this->assertEquals($role, $member->getRole());
		$this->assertEquals($hourlyRate, $member->getHourlyRate());
	}

	/**
	 * Test adding team member with invalid role
	 */
	public function testAddTeamMemberWithInvalidRole(): void {
		$projectId = 1;
		$userId = 'testuser';
		$role = 'InvalidRole';

		// Mock project exists
		$qb = $this->createMock(IQueryBuilder::class);
		$qb->method('select')->willReturnSelf();
		$qb->method('from')->willReturnSelf();
		$qb->method('where')->willReturnSelf();
		$qb->method('andWhere')->willReturnSelf();
		$qb->method('createNamedParameter')->willReturn(':param');
		$qb->method('executeQuery')->willReturnSelf();
		$qb->method('executeStatement')->willReturn(1);
		$qb->method('fetch')->willReturn(false);
		$qb->method('closeCursor')->willReturnSelf();
		$this->db->method('getQueryBuilder')->willReturn($qb);

		// Mock user exists
		$user = $this->createMock(IUser::class);
		$this->userManager->method('get')->with($userId)->willReturn($user);

		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Invalid role value');

		$this->projectService->addTeamMember($projectId, $userId, $role);
	}

	/**
	 * Test adding team member with non-existent user
	 */
	public function testAddTeamMemberWithNonExistentUser(): void {
		$projectId = 1;
		$userId = 'nonexistentuser';
		$role = 'Developer';

		// Mock project exists
		$qb = $this->createMock(IQueryBuilder::class);
		$qb->method('select')->willReturnSelf();
		$qb->method('from')->willReturnSelf();
		$qb->method('where')->willReturnSelf();
		$qb->method('andWhere')->willReturnSelf();
		$qb->method('createNamedParameter')->willReturn(':param');
		$qb->method('executeQuery')->willReturnSelf();
		$qb->method('executeStatement')->willReturn(1);
		$qb->method('fetch')->willReturn(false);
		$qb->method('closeCursor')->willReturnSelf();
		$this->db->method('getQueryBuilder')->willReturn($qb);

		// Mock user doesn't exist
		$this->userManager->method('get')->with($userId)->willReturn(null);

		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('User not found');

		$this->projectService->addTeamMember($projectId, $userId, $role);
	}

	/**
	 * Test changing project status
	 */
	public function testChangeProjectStatus(): void {
		$projectId = 1;
		$newStatus = 'On Hold';

		// Mock project exists
		$project = new Project();
		$project->setId($projectId);
		$project->setStatus('Active');
		$project->setName('Test Project');

		$qb = $this->createMock(IQueryBuilder::class);
		$qb->method('select')->willReturnSelf();
		$qb->method('from')->willReturnSelf();
		$qb->method('where')->willReturnSelf();
		$qb->method('createNamedParameter')->willReturn(':param');
		$qb->method('executeQuery')->willReturnSelf();
		$qb->method('executeStatement')->willReturn(1);
		$qb->method('fetch')->willReturn([
			'id' => $projectId,
			'name' => 'Test Project',
			'status' => 'Active',
			'short_description' => 'Test',
			'detailed_description' => '',
			'customer_id' => 1,
			'hourly_rate' => 50.0,
			'total_budget' => 5000.0,
			'available_hours' => 100.0,
			'category' => '',
			'priority' => 'Medium',
			'start_date' => null,
			'end_date' => null,
			'tags' => '',
			'created_by' => 'testuser',
			'created_at' => '2024-01-01 00:00:00',
			'updated_at' => '2024-01-01 00:00:00'
		]);
		$qb->method('closeCursor')->willReturnSelf();
		$qb->method('update')->willReturnSelf();
		$qb->method('set')->willReturnSelf();
		$this->db->method('getQueryBuilder')->willReturn($qb);

		// Mock current user
		$this->user->method('getUID')->willReturn('testuser');
		$this->userSession->method('getUser')->willReturn($this->user);

		$updatedProject = $this->projectService->changeProjectStatus($projectId, $newStatus);

		$this->assertInstanceOf(Project::class, $updatedProject);
		$this->assertEquals($newStatus, $updatedProject->getStatus());
	}

	/**
	 * Test invalid status transition
	 */
	public function testInvalidStatusTransition(): void {
		$projectId = 1;
		$newStatus = 'Active';

		// Mock project exists with Completed status
		$qb = $this->createMock(IQueryBuilder::class);
		$qb->method('select')->willReturnSelf();
		$qb->method('from')->willReturnSelf();
		$qb->method('where')->willReturnSelf();
		$qb->method('createNamedParameter')->willReturn(':param');
		$qb->method('executeQuery')->willReturnSelf();
		$qb->method('executeStatement')->willReturn(1);
		$qb->method('fetch')->willReturn([
			'id' => $projectId,
			'name' => 'Test Project',
			'status' => 'Completed',
			'short_description' => 'Test',
			'detailed_description' => '',
			'customer_id' => 1,
			'hourly_rate' => 50.0,
			'total_budget' => 5000.0,
			'available_hours' => 100.0,
			'category' => '',
			'priority' => 'Medium',
			'start_date' => null,
			'end_date' => null,
			'tags' => '',
			'created_by' => 'testuser',
			'created_at' => '2024-01-01 00:00:00',
			'updated_at' => '2024-01-01 00:00:00'
		]);
		$qb->method('closeCursor')->willReturnSelf();
		$this->db->method('getQueryBuilder')->willReturn($qb);

		$this->expectException(\Exception::class);
		$this->expectExceptionMessage("Invalid status transition from 'Completed' to 'Active'");

		$this->projectService->changeProjectStatus($projectId, $newStatus);
	}

	/**
	 * Test permission checks
	 */
	public function testPermissionChecks(): void {
		$projectId = 1;
		$userId = 'testuser';

		// Mock project exists
		$qb = $this->createMock(IQueryBuilder::class);
		$qb->method('select')->willReturnSelf();
		$qb->method('from')->willReturnSelf();
		$qb->method('where')->willReturnSelf();
		$qb->method('andWhere')->willReturnSelf();
		$qb->method('createNamedParameter')->willReturn(':param');
		$qb->method('executeQuery')->willReturnSelf();
		$qb->method('executeStatement')->willReturn(1);
		$qb->method('fetch')->willReturn([
			'id' => $projectId,
			'name' => 'Test Project',
			'status' => 'Active',
			'short_description' => 'Test',
			'detailed_description' => '',
			'customer_id' => 1,
			'hourly_rate' => 50.0,
			'total_budget' => 5000.0,
			'available_hours' => 100.0,
			'category' => '',
			'priority' => 'Medium',
			'start_date' => null,
			'end_date' => null,
			'tags' => '',
			'created_by' => 'testuser',
			'created_at' => '2024-01-01 00:00:00',
			'updated_at' => '2024-01-01 00:00:00'
		]);
		$qb->method('closeCursor')->willReturnSelf();
		$this->db->method('getQueryBuilder')->willReturn($qb);

		// Mock user exists
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$this->userManager->method('get')->with($userId)->willReturn($user);

		// Test access permission (project creator can access)
		$this->assertTrue($this->projectService->canUserAccessProject($userId, $projectId));

		// Test edit permission (project creator can edit)
		$this->assertTrue($this->projectService->canUserEditProject($userId, $projectId));

		// Test delete permission (project creator can delete)
		$this->assertTrue($this->projectService->canUserDeleteProject($userId, $projectId));
	}

	/**
	 * Test search projects
	 */
	public function testSearchProjects(): void {
		$query = 'test';

		$qb = $this->createMock(IQueryBuilder::class);
		$qb->method('select')->willReturnSelf();
		$qb->method('from')->willReturnSelf();
		$qb->method('where')->willReturnSelf();
		$qb->method('andWhere')->willReturnSelf();
		$qb->method('orX')->willReturnSelf();
		$qb->method('like')->willReturnSelf();
		$qb->method('createNamedParameter')->willReturn(':param');
		$qb->method('executeQuery')->willReturnSelf();
		$qb->method('executeStatement')->willReturn(1);
		$qb->method('fetch')->willReturn(false);
		$qb->method('closeCursor')->willReturnSelf();
		$this->db->method('getQueryBuilder')->willReturn($qb);

		$projects = $this->projectService->searchProjects($query);

		$this->assertIsArray($projects);
	}

	/**
	 * Test filter projects
	 */
	public function testFilterProjects(): void {
		$filters = [
			'status' => 'Active',
			'category' => 'Development',
			'priority' => 'High'
		];

		$qb = $this->createMock(IQueryBuilder::class);
		$qb->method('select')->willReturnSelf();
		$qb->method('from')->willReturnSelf();
		$qb->method('where')->willReturnSelf();
		$qb->method('andWhere')->willReturnSelf();
		$qb->method('createNamedParameter')->willReturn(':param');
		$qb->method('executeQuery')->willReturnSelf();
		$qb->method('executeStatement')->willReturn(1);
		$qb->method('fetch')->willReturn(false);
		$qb->method('closeCursor')->willReturnSelf();
		$this->db->method('getQueryBuilder')->willReturn($qb);

		$projects = $this->projectService->filterProjects($filters);

		$this->assertIsArray($projects);
	}
}
