<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Util;

use OCA\ProjectCheck\Db\Project;
use OCA\ProjectCheck\Util\CostRateMode;
use OCA\ProjectCheck\Util\ProjectCapacity;
use PHPUnit\Framework\TestCase;

class ProjectCapacityTest extends TestCase {
	public function testProjectModeRequiresRateForEstimate(): void {
		$project = new Project();
		$project->setCostRateMode(CostRateMode::PROJECT);
		$project->setTotalBudget(10000.0);
		$project->setHourlyRate(100.0);

		$capacity = ProjectCapacity::forProject($project, 25.0);

		$this->assertTrue($capacity['hours_estimated']);
		$this->assertSame(ProjectCapacity::BASIS_PROJECT_RATE, $capacity['basis']);
		$this->assertSame(100.0, $capacity['available_hours']);
		$this->assertSame(75.0, $capacity['remaining_hours']);
	}

	public function testEmployeeModeWithoutPlanningRateIsUnavailable(): void {
		$project = new Project();
		$project->setCostRateMode(CostRateMode::EMPLOYEE);
		$project->setTotalBudget(5000.0);
		$project->setHourlyRate(0.0);

		$capacity = ProjectCapacity::forProject($project, 10.0);

		$this->assertFalse($capacity['hours_estimated']);
		$this->assertSame(ProjectCapacity::BASIS_UNAVAILABLE, $capacity['basis']);
		$this->assertSame(0.0, $capacity['available_hours']);
	}

	public function testEmployeeModeUsesPlanningRateForEstimate(): void {
		$project = new Project();
		$project->setCostRateMode(CostRateMode::EMPLOYEE);
		$project->setTotalBudget(8000.0);
		$project->setHourlyRate(80.0);

		$capacity = ProjectCapacity::forProject($project, 0.0);

		$this->assertTrue($capacity['hours_estimated']);
		$this->assertSame(ProjectCapacity::BASIS_PLANNING_RATE, $capacity['basis']);
		$this->assertSame(100.0, $capacity['available_hours']);
	}

	public function testProgressFallsBackToBudgetConsumptionWhenHoursNotEstimated(): void {
		$budgetInfo = [
			'hours_estimated' => false,
			'available_hours' => 0.0,
			'consumption_percentage' => 42.5,
		];

		$this->assertSame(42.5, ProjectCapacity::progressPercent($budgetInfo, 120.0));
	}

	public function testProgressUsesHoursWhenEstimated(): void {
		$budgetInfo = [
			'hours_estimated' => true,
			'available_hours' => 200.0,
			'consumption_percentage' => 10.0,
		];

		$this->assertSame(50.0, ProjectCapacity::progressPercent($budgetInfo, 100.0));
	}

	public function testProjectModeWithoutRateIsUnavailable(): void {
		$project = new Project();
		$project->setCostRateMode(CostRateMode::PROJECT);
		$project->setTotalBudget(5000.0);
		$project->setHourlyRate(0.0);

		$capacity = ProjectCapacity::forProject($project, 0.0);

		$this->assertFalse($capacity['hours_estimated']);
		$this->assertSame(ProjectCapacity::BASIS_PROJECT_RATE, $capacity['basis']);
	}

	public function testStoredAvailableHoursZeroWhenRateMissing(): void {
		$this->assertSame(0.0, ProjectCapacity::storedAvailableHours(10000.0, 0.0, CostRateMode::EMPLOYEE));
	}
}
