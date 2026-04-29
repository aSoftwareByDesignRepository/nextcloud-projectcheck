<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Service;

use OCA\ProjectCheck\Service\ProjectService;
use OCP\IDBConnection;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

class ProjectServiceTest extends TestCase {
	private ProjectService $projectService;

	protected function setUp(): void {
		parent::setUp();
		$this->projectService = new ProjectService(
			$this->createMock(IDBConnection::class),
			$this->createMock(IUserSession::class),
			$this->createMock(IUserManager::class),
			$this->createMock(IConfig::class),
			$this->createMock(IGroupManager::class),
			null
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
}
