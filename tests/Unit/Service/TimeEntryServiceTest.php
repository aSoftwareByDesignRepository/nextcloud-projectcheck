<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Service;

use OCA\ProjectCheck\Db\Project;
use OCA\ProjectCheck\Db\ProjectMapper;
use OCA\ProjectCheck\Db\TimeEntry;
use OCA\ProjectCheck\Db\TimeEntryMapper;
use OCA\ProjectCheck\Exception\PermissionDeniedException;
use OCA\ProjectCheck\Exception\RateResolutionException;
use OCA\ProjectCheck\Exception\TimeEntryNotFoundException;
use OCA\ProjectCheck\Service\HourlyRateService;
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
	/** @var HourlyRateService|\PHPUnit\Framework\MockObject\MockObject */
	private $hourlyRateService;
	private TimeEntryService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->timeEntryMapper = $this->createMock(TimeEntryMapper::class);
		$this->projectMapper = $this->createMock(ProjectMapper::class);
		$this->projectService = $this->createMock(ProjectService::class);
		$this->hourlyRateService = $this->createMock(HourlyRateService::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn(string $text): string => $text);

		$this->service = new TimeEntryService(
			$this->timeEntryMapper,
			$this->projectMapper,
			$this->projectService,
			$this->hourlyRateService,
			$l10n
		);
	}

	private function makeOwnedEntry(int $id, int $projectId, string $ownerId, string $date = '2026-04-01', float $rate = 50.0): TimeEntry {
		$entry = new TimeEntry();
		$entry->setId($id);
		$entry->setProjectId($projectId);
		$entry->setUserId($ownerId);
		$entry->setDate(new \DateTime($date));
		$entry->setHours(2.0);
		$entry->setDescription('Existing');
		$entry->setHourlyRate($rate);
		$entry->setCreatedAt(new \DateTime($date . ' 10:00:00'));
		$entry->setUpdatedAt(new \DateTime($date . ' 10:00:00'));
		return $entry;
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

	/**
	 * Regression: owners must be able to fix hours/description of their own entry
	 * even after the project was Completed/Archived (the status gate only applies
	 * to *moving* an entry).
	 */
	public function testUpdateOwnEntryOnCompletedProjectKeepsStoredRateAndSucceeds(): void {
		$existing = $this->makeOwnedEntry(99, 3, 'member-user');

		$completedProject = new Project();
		$completedProject->setId(3);
		$completedProject->setStatus('Completed');

		$this->timeEntryMapper->method('find')->with(99)->willReturn($existing);
		$this->projectMapper->method('find')->with(3)->willReturn($completedProject);
		// No rate-relevant change → the frozen stored rate must not be re-resolved.
		$this->hourlyRateService->expects($this->never())->method('resolveForTimeEntry');
		$this->timeEntryMapper->expects($this->once())
			->method('update')
			->willReturnArgument(0);

		$updated = $this->service->updateTimeEntry(99, [
			'project_id' => 3,
			'date' => '2026-04-01',
			'hours' => 3.25,
			'description' => 'Corrected hours',
		], 'member-user');

		$this->assertSame(3.25, $updated->getHours());
		$this->assertSame('Corrected hours', $updated->getDescription());
		$this->assertSame(50.0, (float) $updated->getHourlyRate());
	}

	public function testUpdateReResolvesRateWhenWorkDateChanges(): void {
		$existing = $this->makeOwnedEntry(99, 3, 'member-user');

		$activeProject = new Project();
		$activeProject->setId(3);
		$activeProject->setStatus('Active');

		$this->timeEntryMapper->method('find')->with(99)->willReturn($existing);
		$this->projectMapper->method('find')->with(3)->willReturn($activeProject);
		$this->hourlyRateService->expects($this->once())
			->method('resolveForTimeEntry')
			->with(3, 'member-user', $this->isInstanceOf(\DateTimeInterface::class))
			->willReturn(75.0);
		$this->timeEntryMapper->method('update')->willReturnArgument(0);

		$updated = $this->service->updateTimeEntry(99, [
			'project_id' => 3,
			'date' => '2026-04-02',
			'hours' => 2.0,
		], 'member-user');

		$this->assertSame(75.0, (float) $updated->getHourlyRate());
	}

	public function testUpdateRejectsTamperedClientRateWhenNothingRateRelevantChanged(): void {
		$existing = $this->makeOwnedEntry(99, 3, 'member-user');

		$activeProject = new Project();
		$activeProject->setId(3);
		$activeProject->setStatus('Active');

		$this->timeEntryMapper->method('find')->with(99)->willReturn($existing);
		$this->projectMapper->method('find')->with(3)->willReturn($activeProject);
		$this->hourlyRateService->method('assertClientRateMatchesResolved')
			->willThrowException(new RateResolutionException('The hourly rate does not match the server. Refresh the page and try again.', 'rate_tamper'));
		$this->timeEntryMapper->expects($this->never())->method('update');

		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('The hourly rate does not match the server.');

		$this->service->updateTimeEntry(99, [
			'project_id' => 3,
			'date' => '2026-04-01',
			'hours' => 2.0,
			'hourly_rate' => 999.99,
		], 'member-user');
	}

	public function testUpdateDeniedForNonOwner(): void {
		$existing = $this->makeOwnedEntry(99, 3, 'owner-user');
		$this->timeEntryMapper->method('find')->with(99)->willReturn($existing);
		$this->timeEntryMapper->expects($this->never())->method('update');

		$this->expectException(PermissionDeniedException::class);

		$this->service->updateTimeEntry(99, ['hours' => 1.0], 'other-user');
	}

	public function testUpdateThrowsTypedNotFound(): void {
		$this->timeEntryMapper->method('find')
			->willThrowException(new \OCP\AppFramework\Db\DoesNotExistException('nope'));

		$this->expectException(TimeEntryNotFoundException::class);

		$this->service->updateTimeEntry(404, ['hours' => 1.0], 'member-user');
	}

	public function testDeleteOwnEntrySucceedsRegardlessOfProjectState(): void {
		$existing = $this->makeOwnedEntry(55, 12, 'member-user');
		$this->timeEntryMapper->method('find')->with(55)->willReturn($existing);
		$this->timeEntryMapper->expects($this->once())->method('delete')->with($existing);

		$this->service->deleteTimeEntry(55, 'member-user');
	}

	public function testDeleteDeniedForNonOwner(): void {
		$existing = $this->makeOwnedEntry(55, 12, 'owner-user');
		$this->timeEntryMapper->method('find')->with(55)->willReturn($existing);
		$this->timeEntryMapper->expects($this->never())->method('delete');

		$this->expectException(PermissionDeniedException::class);

		$this->service->deleteTimeEntry(55, 'other-user');
	}

	public function testGetTimeEntryAcceptsNumericStringIdSafely(): void {
		$existing = $this->makeOwnedEntry(7, 1, 'member-user');
		$this->timeEntryMapper->method('find')->with(7)->willReturn($existing);

		$this->assertSame($existing, $this->service->getTimeEntry('7'));
		$this->assertNull($this->service->getTimeEntry('not-a-number'));
	}

	public function testSumTimeEntriesHoursStripsPaginationAndDelegatesToMapper(): void {
		$filters = [
			'project_id' => 3,
			'limit' => 20,
			'offset' => 40,
		];

		$this->timeEntryMapper->expects($this->once())
			->method('sumHours')
			->with($this->callback(static function (array $passed): bool {
				return ($passed['project_id'] ?? null) === 3
					&& !array_key_exists('limit', $passed)
					&& !array_key_exists('offset', $passed);
			}))
			->willReturn(12.5);

		$this->assertSame(12.5, $this->service->sumTimeEntriesHours($filters));
	}
}
