<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Db;

use OCA\ProjectCheck\Db\Project;
use OCA\ProjectCheck\Db\ProjectMapper;
use OCA\ProjectCheck\Util\CostRateMode;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for {@see ProjectMapper::mapRowToEntity()}.
 *
 * Historical bug: the override silently dropped {@code cost_rate_mode} and
 * {@code project_type} when hydrating a Project from a row, so every load
 * through {@see ProjectMapper::find()} returned NULL for both fields. That
 * collapsed all pricing modes to the PROJECT default at rate-resolution time
 * — a security-relevant correctness issue.
 *
 * @group projectcheck
 */
class ProjectMapperHydrationTest extends TestCase
{
	private function row(array $overrides = []): array
	{
		return array_merge([
			'id' => 42,
			'name' => 'Sample',
			'short_description' => 'short',
			'detailed_description' => 'detailed',
			'customer_id' => 7,
			'hourly_rate' => '100.00',
			'total_budget' => '0.00',
			'available_hours' => '0.00',
			'category' => 'misc',
			'priority' => 'low',
			'status' => 'Active',
			'start_date' => null,
			'end_date' => null,
			'tags' => '',
			'project_type' => 'client',
			'cost_rate_mode' => CostRateMode::EMPLOYEE,
			'created_by' => 'admin',
			'created_at' => '2026-01-01 00:00:00',
			'updated_at' => '2026-01-01 00:00:00',
		], $overrides);
	}

	private function mapper(): ProjectMapper
	{
		// QBMapper only needs the DB for query building; mapRowToEntity does not touch it.
		return new ProjectMapper($this->createMock(IDBConnection::class));
	}

	private function invokeMapRowToEntity(ProjectMapper $mapper, array $row): Project
	{
		$ref = new \ReflectionClass($mapper);
		$method = $ref->getMethod('mapRowToEntity');
		$method->setAccessible(true);
		return $method->invoke($mapper, $row);
	}

	public function testHydratesCostRateModeFromRow(): void
	{
		foreach ([CostRateMode::PROJECT, CostRateMode::EMPLOYEE, CostRateMode::PROJECT_MEMBER] as $mode) {
			$project = $this->invokeMapRowToEntity($this->mapper(), $this->row(['cost_rate_mode' => $mode]));
			self::assertSame($mode, $project->getCostRateMode(), "expected mode {$mode} to round-trip");
		}
	}

	public function testHydratesProjectTypeFromRow(): void
	{
		$project = $this->invokeMapRowToEntity($this->mapper(), $this->row(['project_type' => 'internal']));
		self::assertSame('internal', $project->getProjectType());
	}

	public function testNullCostRateModeFallsBackToDefault(): void
	{
		$project = $this->invokeMapRowToEntity($this->mapper(), $this->row(['cost_rate_mode' => null]));
		self::assertSame(CostRateMode::DEFAULT, $project->getCostRateMode());
	}

	public function testNullProjectTypeFallsBackToClient(): void
	{
		$project = $this->invokeMapRowToEntity($this->mapper(), $this->row(['project_type' => null]));
		self::assertSame('client', $project->getProjectType());
	}

	public function testInvalidCostRateModeIsNormalised(): void
	{
		$project = $this->invokeMapRowToEntity($this->mapper(), $this->row(['cost_rate_mode' => 'totally-not-real']));
		self::assertSame(CostRateMode::DEFAULT, $project->getCostRateMode());
	}
}
