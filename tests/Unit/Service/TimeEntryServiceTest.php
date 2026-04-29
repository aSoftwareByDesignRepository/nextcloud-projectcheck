<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Service;

use OCA\ProjectCheck\Db\Project;
use OCA\ProjectCheck\Db\ProjectMapper;
use OCA\ProjectCheck\Db\TimeEntry;
use OCA\ProjectCheck\Db\TimeEntryMapper;
use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Service\TimeEntryService;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;

class TimeEntryServiceTest extends TestCase {
	/** @var TimeEntryMapper|\PHPUnit\Framework\MockObject\MockObject */
	private $timeEntryMapper;
	/** @var ProjectMapper|\PHPUnit\Framework\MockObject\MockObject */
	private $projectMapper;
	/** @var ProjectService|\PHPUnit\Framework\MockObject\MockObject */
	private $projectService;
	private TimeEntryService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->timeEntryMapper = $this->createMock(TimeEntryMapper::class);
		$this->projectMapper = $this->createMock(ProjectMapper::class);
		$this->projectService = $this->createMock(ProjectService::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn(string $text): string => $text);

		$this->service = new TimeEntryService(
			$this->timeEntryMapper,
			$this->projectMapper,
			$this->projectService,
			$l10n
		);
	}

	public function testCreateTimeEntryDeniedWhenProjectAccessRevoked(): void {
		$project = new Project();
		$project->setId(11);
		$project->setStatus('Active');

		$this->projectMapper->method('find')->with(11)->willReturn($project);
		$this->projectService->method('canUserAccessProject')->with('member-user', 11)->willReturn(false);

		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Access denied');

		$this->service->createTimeEntry([
			'project_id' => 11,
			'date' => '2026-04-29',
			'hours' => 1.5,
			'hourly_rate' => 50,
			'description' => 'Blocked by revoked membership',
		], 'member-user');
	}

	public function testCreateTimeEntryRejectedForCompletedProject(): void {
		$project = new Project();
		$project->setId(12);
		$project->setStatus('Completed');

		$this->projectMapper->method('find')->with(12)->willReturn($project);

		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Time cannot be logged on this project.');

		$this->service->createTimeEntry([
			'project_id' => 12,
			'date' => '2026-04-29',
			'hours' => 1.0,
			'hourly_rate' => 45,
			'description' => 'Should fail for completed project',
		], 'member-user');
	}

	public function testUpdateTimeEntryDeniedWhenMovingToInaccessibleProject(): void {
		$existing = new TimeEntry();
		$existing->setId(99);
		$existing->setProjectId(3);
		$existing->setUserId('member-user');
		$existing->setDate(new \DateTime('2026-04-01'));
		$existing->setHours(2.0);
		$existing->setDescription('Existing');
		$existing->setHourlyRate(50.0);
		$existing->setCreatedAt(new \DateTime('2026-04-01 10:00:00'));
		$existing->setUpdatedAt(new \DateTime('2026-04-01 10:00:00'));

		$targetProject = new Project();
		$targetProject->setId(7);
		$targetProject->setStatus('Active');

		$this->timeEntryMapper->method('find')->with(99)->willReturn($existing);
		$this->projectMapper->method('find')->with(7)->willReturn($targetProject);
		$this->projectService->method('canUserAccessProject')->with('member-user', 7)->willReturn(false);
		$this->timeEntryMapper->expects($this->never())->method('update');

		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Access denied');

		$this->service->updateTimeEntry(99, ['project_id' => 7], 'member-user');
	}
}
