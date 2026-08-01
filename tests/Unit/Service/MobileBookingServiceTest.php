<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Service;

use OCA\ProjectCheck\Db\Project;
use OCA\ProjectCheck\Db\TimeEntry;
use OCA\ProjectCheck\Exception\BillingLockedException;
use OCA\ProjectCheck\Exception\MobileApiException;
use OCA\ProjectCheck\Service\HourlyRateService;
use OCA\ProjectCheck\Service\MobileBookingService;
use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Service\TimeEntryService;
use OCA\ProjectCheck\Util\BillingStatus;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;

final class MobileBookingServiceTest extends TestCase
{
	private ProjectService $projects;
	private TimeEntryService $timeEntries;
	private HourlyRateService $rates;
	private MobileBookingService $svc;

	protected function setUp(): void
	{
		$this->projects = $this->createMock(ProjectService::class);
		$this->timeEntries = $this->createMock(TimeEntryService::class);
		$this->rates = $this->createMock(HourlyRateService::class);
		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnCallback(static fn (string $s, array $a = []) => vsprintf(str_replace('%s', '%s', $s), $a) ?: $s);
		$this->svc = new MobileBookingService($this->projects, $this->timeEntries, $this->rates, $l);
	}

	public function testCreateRejectsForeignProject(): void
	{
		$this->projects->method('canUserAddTimeEntryForProject')->willReturn(false);
		$this->expectException(MobileApiException::class);
		try {
			$this->svc->createEntry('alice', [
				'projectId' => 9,
				'date' => '2026-07-24',
				'durationMinutes' => 60,
			]);
		} catch (MobileApiException $e) {
			self::assertSame('forbidden', $e->getErrorCode());
			self::assertSame(403, $e->getHttpStatus());
			throw $e;
		}
	}

	public function testCreateConvertsMinutesAndIgnoresClientRate(): void
	{
		$this->projects->method('canUserAddTimeEntryForProject')->willReturn(true);
		$this->timeEntries->method('validateTimeEntryDataDetailed')->willReturnCallback(
			function (array $data): array {
				self::assertSame(12, (int)$data['project_id']);
				self::assertEqualsWithDelta(1.5, (float)$data['hours'], 0.0001);
				self::assertArrayNotHasKey('hourly_rate', $data);
				return ['errors' => [], 'errorCodes' => []];
			}
		);
		$entry = $this->makeEntry(5, 12, 'alice', 1.5, BillingStatus::OPEN);
		$this->timeEntries->expects(self::once())->method('createTimeEntry')->willReturn($entry);
		$this->projects->method('getProject')->willReturn($this->makeProject(12));

		$row = $this->svc->createEntry('alice', [
			'projectId' => 12,
			'date' => '2026-07-24',
			'durationMinutes' => 90,
			'hourlyRate' => 999.0,
			'description' => 'On-site',
		]);
		self::assertSame(5, $row['id']);
		self::assertSame(90, $row['durationMinutes']);
		self::assertTrue($row['editable']);
	}

	public function testCreateFromStartEndTimes(): void
	{
		$this->projects->method('canUserAddTimeEntryForProject')->willReturn(true);
		$this->timeEntries->method('validateTimeEntryDataDetailed')->willReturnCallback(
			function (array $data): array {
				self::assertEqualsWithDelta(2.0, (float)$data['hours'], 0.0001);
				return ['errors' => [], 'errorCodes' => []];
			}
		);
		$entry = $this->makeEntry(1, 3, 'alice', 2.0, BillingStatus::OPEN);
		$this->timeEntries->method('createTimeEntry')->willReturn($entry);
		$this->projects->method('getProject')->willReturn($this->makeProject(3));

		$row = $this->svc->createEntry('alice', [
			'projectId' => 3,
			'date' => '2026-07-24',
			'startTime' => '09:00',
			'endTime' => '11:00',
		]);
		self::assertSame(120, $row['durationMinutes']);
	}

	public function testRejectsNonIntegerDuration(): void
	{
		$this->projects->method('canUserAddTimeEntryForProject')->willReturn(true);
		$this->expectException(MobileApiException::class);
		try {
			$this->svc->createEntry('alice', [
				'projectId' => 1,
				'date' => '2026-07-24',
				'durationMinutes' => 90.5,
			]);
		} catch (MobileApiException $e) {
			self::assertSame('validation', $e->getErrorCode());
			throw $e;
		}
	}

	public function testUpdateBlocksInvoiced(): void
	{
		$entry = $this->makeEntry(7, 1, 'alice', 1.0, BillingStatus::INVOICED);
		$this->timeEntries->method('getTimeEntry')->willReturn($entry);
		$this->expectException(MobileApiException::class);
		try {
			$this->svc->updateEntry('alice', 7, ['description' => 'x']);
		} catch (MobileApiException $e) {
			self::assertSame('entry_not_editable', $e->getErrorCode());
			self::assertSame(409, $e->getHttpStatus());
			throw $e;
		}
	}

	public function testUpdateBlocksExcluded(): void
	{
		$entry = $this->makeEntry(8, 1, 'alice', 1.0, BillingStatus::EXCLUDED);
		$this->timeEntries->method('getTimeEntry')->willReturn($entry);
		$this->timeEntries->expects(self::never())->method('updateTimeEntry');
		$this->expectException(MobileApiException::class);
		try {
			$this->svc->updateEntry('alice', 8, ['description' => 'should fail']);
		} catch (MobileApiException $e) {
			self::assertSame('entry_not_editable', $e->getErrorCode());
			self::assertSame(409, $e->getHttpStatus());
			self::assertSame(BillingStatus::EXCLUDED, $e->getDetails()['billingStatus'] ?? null);
			throw $e;
		}
	}

	public function testDeleteBlocksExcluded(): void
	{
		$entry = $this->makeEntry(9, 1, 'alice', 1.0, BillingStatus::EXCLUDED);
		$this->timeEntries->method('getTimeEntry')->willReturn($entry);
		$this->timeEntries->expects(self::never())->method('deleteTimeEntry');
		$this->expectException(MobileApiException::class);
		try {
			$this->svc->deleteEntry('alice', 9);
		} catch (MobileApiException $e) {
			self::assertSame('entry_not_editable', $e->getErrorCode());
			self::assertSame(409, $e->getHttpStatus());
			self::assertSame(BillingStatus::EXCLUDED, $e->getDetails()['billingStatus'] ?? null);
			throw $e;
		}
	}

	public function testDeleteBlocksPaid(): void
	{
		$entry = $this->makeEntry(10, 1, 'alice', 1.0, BillingStatus::PAID);
		$this->timeEntries->method('getTimeEntry')->willReturn($entry);
		$this->timeEntries->expects(self::never())->method('deleteTimeEntry');
		$this->expectException(MobileApiException::class);
		try {
			$this->svc->deleteEntry('alice', 10);
		} catch (MobileApiException $e) {
			self::assertSame('entry_not_editable', $e->getErrorCode());
			self::assertSame(409, $e->getHttpStatus());
			throw $e;
		}
	}

	public function testDeleteBlocksForeignOwner(): void
	{
		$entry = $this->makeEntry(7, 1, 'bob', 1.0, BillingStatus::OPEN);
		$this->timeEntries->method('getTimeEntry')->willReturn($entry);
		$this->expectException(MobileApiException::class);
		try {
			$this->svc->deleteEntry('alice', 7);
		} catch (MobileApiException $e) {
			self::assertSame('forbidden', $e->getErrorCode());
			throw $e;
		}
	}

	public function testDeletePropagatesBillingLockRace(): void
	{
		$entry = $this->makeEntry(7, 1, 'alice', 1.0, BillingStatus::OPEN);
		$this->timeEntries->method('getTimeEntry')->willReturn($entry);
		$this->timeEntries->method('deleteTimeEntry')->willThrowException(
			new BillingLockedException(BillingStatus::INVOICED, 'locked')
		);
		$this->expectException(MobileApiException::class);
		try {
			$this->svc->deleteEntry('alice', 7);
		} catch (MobileApiException $e) {
			self::assertSame('entry_not_editable', $e->getErrorCode());
			throw $e;
		}
	}

	public function testCreateWithClientRequestIdReturnsPriorEntry(): void
	{
		$idem = $this->createMock(\OCA\ProjectCheck\Db\MobileIdempotencyMapper::class);
		$time = $this->createMock(\OCP\AppFramework\Utility\ITimeFactory::class);
		$time->method('getTime')->willReturn(1_700_000_000);
		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnCallback(static fn (string $s, array $a = []) => $a === [] ? $s : vsprintf($s, $a));
		$svc = new MobileBookingService($this->projects, $this->timeEntries, $this->rates, $l, $idem, $time);

		$map = new \OCA\ProjectCheck\Db\MobileIdempotency();
		$map->setTimeEntryId(42);
		$idem->expects(self::once())->method('findByUserAndRequestId')
			->with('alice', 'req-abc-1')
			->willReturn($map);
		$prior = $this->makeEntry(42, 12, 'alice', 1.0, BillingStatus::OPEN);
		$this->timeEntries->expects(self::once())->method('getTimeEntry')->with(42)->willReturn($prior);
		$this->timeEntries->expects(self::never())->method('createTimeEntry');
		$this->projects->method('getProject')->willReturn($this->makeProject(12));

		$row = $svc->createEntry('alice', [
			'projectId' => 12,
			'date' => '2026-07-24',
			'durationMinutes' => 60,
			'clientRequestId' => 'req-abc-1',
		]);
		self::assertSame(42, $row['id']);
	}

	public function testCreateIdempotencyRaceDeletesOrphanAndReturnsWinner(): void
	{
		$idem = $this->createMock(\OCA\ProjectCheck\Db\MobileIdempotencyMapper::class);
		$time = $this->createMock(\OCP\AppFramework\Utility\ITimeFactory::class);
		$time->method('getTime')->willReturn(1_700_000_000);
		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnCallback(static fn (string $s, array $a = []) => $a === [] ? $s : vsprintf($s, $a));
		$svc = new MobileBookingService($this->projects, $this->timeEntries, $this->rates, $l, $idem, $time);

		$this->projects->method('canUserAddTimeEntryForProject')->willReturn(true);
		$this->timeEntries->method('validateTimeEntryDataDetailed')->willReturn(['errors' => [], 'errorCodes' => []]);
		$orphan = $this->makeEntry(99, 12, 'alice', 1.0, BillingStatus::OPEN);
		$winner = $this->makeEntry(42, 12, 'alice', 1.0, BillingStatus::OPEN);
		$this->timeEntries->expects(self::once())->method('createTimeEntry')->willReturn($orphan);
		$this->timeEntries->expects(self::once())->method('deleteTimeEntryForMaintenance')->with(99);
		$idem->method('findByUserAndRequestId')->willReturnOnConsecutiveCalls(
			null,
			(static function () {
				$m = new \OCA\ProjectCheck\Db\MobileIdempotency();
				$m->setTimeEntryId(42);
				return $m;
			})()
		);
		$idem->method('tryInsert')->willReturn(false);
		$this->timeEntries->method('getTimeEntry')->with(42)->willReturn($winner);
		$this->projects->method('getProject')->willReturn($this->makeProject(12));

		$row = $svc->createEntry('alice', [
			'projectId' => 12,
			'date' => '2026-07-24',
			'durationMinutes' => 60,
			'clientRequestId' => 'race-1',
		]);
		self::assertSame(42, $row['id']);
	}

	public function testCreateClearsStaleIdempotencyMap(): void
	{
		$idem = $this->createMock(\OCA\ProjectCheck\Db\MobileIdempotencyMapper::class);
		$time = $this->createMock(\OCP\AppFramework\Utility\ITimeFactory::class);
		$time->method('getTime')->willReturn(1_700_000_000);
		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnCallback(static fn (string $s, array $a = []) => $a === [] ? $s : vsprintf($s, $a));
		$svc = new MobileBookingService($this->projects, $this->timeEntries, $this->rates, $l, $idem, $time);

		$stale = new \OCA\ProjectCheck\Db\MobileIdempotency();
		$stale->setTimeEntryId(77);
		$idem->method('findByUserAndRequestId')->willReturn($stale);
		$this->timeEntries->method('getTimeEntry')->with(77)->willReturn(null);
		$idem->expects(self::once())->method('deleteByUserAndRequestId')->with('alice', 'stale-1');
		$this->projects->method('canUserAddTimeEntryForProject')->willReturn(true);
		$this->timeEntries->method('validateTimeEntryDataDetailed')->willReturn(['errors' => [], 'errorCodes' => []]);
		$fresh = $this->makeEntry(88, 12, 'alice', 1.0, BillingStatus::OPEN);
		$this->timeEntries->method('createTimeEntry')->willReturn($fresh);
		$idem->method('tryInsert')->willReturn(true);
		$this->projects->method('getProject')->willReturn($this->makeProject(12));

		$row = $svc->createEntry('alice', [
			'projectId' => 12,
			'date' => '2026-07-24',
			'durationMinutes' => 60,
			'clientRequestId' => 'stale-1',
		]);
		self::assertSame(88, $row['id']);
	}

	public function testCreateRejectsInvalidClientRequestId(): void
	{
		$this->expectException(MobileApiException::class);
		try {
			$this->svc->createEntry('alice', [
				'projectId' => 12,
				'date' => '2026-07-24',
				'durationMinutes' => 60,
				'clientRequestId' => 'bad id with spaces!',
			]);
		} catch (MobileApiException $e) {
			self::assertSame('validation', $e->getErrorCode());
			throw $e;
		}
	}

	public function testListProjectsFiltersBookableOnly(): void
	{
		$a = $this->makeProject(1, 'A');
		$b = $this->makeProject(2, 'B');
		$this->projects->method('getProjectsForUserTimeEntry')->willReturn([$a, $b]);
		$this->projects->method('canUserAddTimeEntryForProject')->willReturnCallback(
			static fn (string $uid, int $id): bool => $id === 1
		);
		$result = $this->svc->listProjectsForBooking('alice', null, 50);
		self::assertCount(1, $result['projects']);
		self::assertSame(1, $result['projects'][0]['id']);
		self::assertSame('A', $result['projects'][0]['name']);
	}

	public function testResolveHourlyRateAllowsSelfPreview(): void
	{
		$this->projects->method('canUserAddTimeEntryForProject')->willReturn(true);
		$this->rates->expects(self::once())
			->method('resolvePreview')
			->with(9, 'alice', self::callback(static fn (\DateTimeInterface $d): bool => $d->format('Y-m-d') === '2026-07-24'))
			->willReturn([
				'hourly_rate' => 85.5,
				'cost_rate_mode' => 'project',
				'source' => 'project',
			]);

		$row = $this->svc->resolveHourlyRate('alice', 9, '2026-07-24', null);
		self::assertSame(9, $row['projectId']);
		self::assertSame('alice', $row['employeeUserId']);
		self::assertSame(85.5, $row['hourlyRate']);
	}

	public function testResolveHourlyRateRejectsOtherEmployeeEvenWithProjectAccess(): void
	{
		$this->projects->method('canUserAddTimeEntryForProject')->willReturn(true);
		$this->projects->method('canUserAccessProject')->willReturn(true);
		$this->rates->expects(self::never())->method('resolvePreview');
		$this->expectException(MobileApiException::class);
		try {
			$this->svc->resolveHourlyRate('alice', 9, '2026-07-24', 'bob');
		} catch (MobileApiException $e) {
			self::assertSame('forbidden', $e->getErrorCode());
			self::assertSame(403, $e->getHttpStatus());
			throw $e;
		}
	}

	private function makeEntry(int $id, int $projectId, string $uid, float $hours, string $status): TimeEntry
	{
		$e = new TimeEntry();
		$e->setId($id);
		$e->setProjectId($projectId);
		$e->setUserId($uid);
		$e->setDate(new \DateTime('2026-07-24'));
		$e->setHours($hours);
		$e->setDescription('desc');
		$e->setHourlyRate(100.0);
		$e->setCreatedAt(new \DateTime('2026-07-24 10:00:00'));
		$e->setUpdatedAt(new \DateTime('2026-07-24 10:00:00'));
		$e->setBillingStatus($status);
		return $e;
	}

	private function makeProject(int $id, string $name = 'Proj'): Project
	{
		$p = new Project();
		$p->setId($id);
		$p->setName($name);
		$p->setCustomerName('Acme');
		$p->setStatus('Active');
		return $p;
	}
}
