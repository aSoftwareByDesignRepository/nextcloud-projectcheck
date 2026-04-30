<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Service;

use OCA\ProjectCheck\Db\Project;
use OCA\ProjectCheck\Db\TimeEntryMapper;
use OCA\ProjectCheck\Service\BudgetService;
use OCP\IConfig;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class BudgetServiceTest extends TestCase {
	/** @var TimeEntryMapper|\PHPUnit\Framework\MockObject\MockObject */
	private $timeEntryMapper;
	private BudgetService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->timeEntryMapper = $this->createMock(TimeEntryMapper::class);
		$config = $this->createMock(IConfig::class);
		$logger = $this->createMock(LoggerInterface::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn(string $text): string => $text);

		$this->service = new BudgetService(
			$this->timeEntryMapper,
			$config,
			$logger,
			$l10n,
			'projectcheck'
		);
	}

	public function testCheckTimeEntryBudgetImpactSkipsProjectsWithoutBudget(): void {
		$project = new Project();
		$project->setId(42);
		$project->setTotalBudget(0.0);
		$project->setHourlyRate(50.0);

		$this->timeEntryMapper->method('getTotalCostForProject')->with(42)->willReturn(120.0);
		$this->timeEntryMapper->method('getTotalHoursForProject')->with(42)->willReturn(2.0);

		$impact = $this->service->checkTimeEntryBudgetImpact($project, 1.5, 80.0);

		$this->assertFalse($impact['has_budget']);
		$this->assertSame(120.0, $impact['additional_cost']);
		$this->assertFalse($impact['would_exceed_budget']);
		$this->assertSame('none', $impact['warning_level_after']);
	}
}
