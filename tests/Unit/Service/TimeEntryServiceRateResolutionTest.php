<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Service;

use OCA\ProjectCheck\Db\Project;
use OCA\ProjectCheck\Db\ProjectMapper;
use OCA\ProjectCheck\Db\TimeEntry;
use OCA\ProjectCheck\Db\TimeEntryMapper;
use OCA\ProjectCheck\Exception\RateResolutionException;
use OCA\ProjectCheck\Service\HourlyRateService;
use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Service\TimeEntryService;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;

/**
 * Ensures time entries persist server-resolved rates (P0 A1, T2.08).
 */
class TimeEntryServiceRateResolutionTest extends TestCase
{
	public function testCreatePersistsResolvedRateWhenClientSendsMatchingValue(): void
	{
		$project = new Project();
		$project->setId(5);
		$project->setStatus('Active');

		$projectMapper = $this->createMock(ProjectMapper::class);
		$projectMapper->method('find')->with(5)->willReturn($project);

		$projectService = $this->createMock(ProjectService::class);
		$projectService->method('canUserAccessProject')->willReturn(true);

		$hourlyRateService = $this->createMock(HourlyRateService::class);
		$hourlyRateService->method('resolveForTimeEntry')->willReturn(62.5);
		$hourlyRateService->expects($this->once())
			->method('assertClientRateMatchesResolved')
			->with(62.5, 62.5);

		$captured = null;
		$timeEntryMapper = $this->createMock(TimeEntryMapper::class);
		$timeEntryMapper->method('insert')->willReturnCallback(static function (TimeEntry $entry) use (&$captured) {
			$captured = $entry;
			$entry->setId(1);
			return $entry;
		});

		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);

		$service = new TimeEntryService(
			$timeEntryMapper,
			$projectMapper,
			$projectService,
			$hourlyRateService,
			$l10n
		);

		$service->createTimeEntry([
			'project_id' => 5,
			'date' => '2026-03-10',
			'hours' => 2.0,
			'hourly_rate' => 62.5,
			'description' => 'Billable work',
		], 'alice');

		$this->assertNotNull($captured);
		$this->assertEqualsWithDelta(62.5, (float) $captured->getHourlyRate(), 0.001);
	}

	public function testCreateRejectsTamperedClientRate(): void
	{
		$project = new Project();
		$project->setId(5);
		$project->setStatus('Active');

		$projectMapper = $this->createMock(ProjectMapper::class);
		$projectMapper->method('find')->willReturn($project);

		$projectService = $this->createMock(ProjectService::class);
		$projectService->method('canUserAccessProject')->willReturn(true);

		$hourlyRateService = $this->createMock(HourlyRateService::class);
		$hourlyRateService->method('resolveForTimeEntry')->willReturn(50.0);
		$hourlyRateService->method('assertClientRateMatchesResolved')->willThrowException(
			new RateResolutionException('tamper', 'rate_mismatch')
		);

		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);

		$service = new TimeEntryService(
			$this->createMock(TimeEntryMapper::class),
			$projectMapper,
			$projectService,
			$hourlyRateService,
			$l10n
		);

		$this->expectException(\Exception::class);
		$service->createTimeEntry([
			'project_id' => 5,
			'date' => '2026-03-10',
			'hours' => 1.0,
			'hourly_rate' => 999.0,
			'description' => 'Tamper attempt',
		], 'alice');
	}

	/**
	 * E15: changing work date re-resolves hourly rate on update.
	 */
	public function testUpdateReResolvesRateWhenDateChanges(): void
	{
		$existing = new TimeEntry();
		$existing->setId(10);
		$existing->setProjectId(5);
		$existing->setUserId('alice');
		$existing->setDate(new \DateTime('2026-01-01'));
		$existing->setHours(2.0);
		$existing->setHourlyRate(40.0);
		$existing->setDescription('Work');
		$existing->setCreatedAt(new \DateTime('2026-01-01'));
		$existing->setUpdatedAt(new \DateTime('2026-01-01'));

		$project = new Project();
		$project->setId(5);
		$project->setStatus('Active');

		$projectMapper = $this->createMock(ProjectMapper::class);
		$projectMapper->method('find')->with(5)->willReturn($project);

		$projectService = $this->createMock(ProjectService::class);
		$projectService->method('canUserAccessProject')->willReturn(true);

		$hourlyRateService = $this->createMock(HourlyRateService::class);
		$hourlyRateService->expects($this->once())
			->method('resolveForTimeEntry')
			->with(5, 'alice', $this->callback(static function (\DateTimeInterface $d): bool {
				return $d->format('Y-m-d') === '2026-06-15';
			}))
			->willReturn(95.0);

		$timeEntryMapper = $this->createMock(TimeEntryMapper::class);
		$timeEntryMapper->method('find')->with(10)->willReturn($existing);
		$timeEntryMapper->method('update')->willReturnCallback(static function (TimeEntry $entry) {
			return $entry;
		});

		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);

		$service = new TimeEntryService(
			$timeEntryMapper,
			$projectMapper,
			$projectService,
			$hourlyRateService,
			$l10n
		);

		$updated = $service->updateTimeEntry(10, ['date' => '15.06.2026'], 'alice');

		$this->assertEqualsWithDelta(95.0, (float) $updated->getHourlyRate(), 0.001);
	}

	/**
	 * E16: changing project re-resolves hourly rate on update.
	 */
	public function testUpdateReResolvesRateWhenProjectChanges(): void
	{
		$existing = new TimeEntry();
		$existing->setId(11);
		$existing->setProjectId(5);
		$existing->setUserId('alice');
		$existing->setDate(new \DateTime('2026-03-01'));
		$existing->setHours(1.0);
		$existing->setHourlyRate(40.0);
		$existing->setDescription('Work');
		$existing->setCreatedAt(new \DateTime('2026-03-01'));
		$existing->setUpdatedAt(new \DateTime('2026-03-01'));

		$oldProject = new Project();
		$oldProject->setId(5);
		$oldProject->setStatus('Active');

		$newProject = new Project();
		$newProject->setId(9);
		$newProject->setStatus('Active');

		$projectMapper = $this->createMock(ProjectMapper::class);
		$projectMapper->method('find')->willReturnCallback(static function (int $id) use ($oldProject, $newProject) {
			return $id === 9 ? $newProject : $oldProject;
		});

		$projectService = $this->createMock(ProjectService::class);
		$projectService->method('canUserAccessProject')->willReturn(true);

		$hourlyRateService = $this->createMock(HourlyRateService::class);
		$hourlyRateService->expects($this->once())
			->method('resolveForTimeEntry')
			->with(9, 'alice', $this->isInstanceOf(\DateTimeInterface::class))
			->willReturn(110.0);

		$timeEntryMapper = $this->createMock(TimeEntryMapper::class);
		$timeEntryMapper->method('find')->with(11)->willReturn($existing);
		$timeEntryMapper->method('update')->willReturnCallback(static function (TimeEntry $entry) {
			return $entry;
		});

		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);

		$service = new TimeEntryService(
			$timeEntryMapper,
			$projectMapper,
			$projectService,
			$hourlyRateService,
			$l10n
		);

		$updated = $service->updateTimeEntry(11, ['project_id' => 9], 'alice');

		$this->assertSame(9, $updated->getProjectId());
		$this->assertEqualsWithDelta(110.0, (float) $updated->getHourlyRate(), 0.001);
	}
}
