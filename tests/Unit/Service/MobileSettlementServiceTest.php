<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Service;

use OCA\ProjectCheck\Db\TimeEntry;
use OCA\ProjectCheck\Exception\InvalidBillingTransitionException;
use OCA\ProjectCheck\Exception\MobileApiException;
use OCA\ProjectCheck\Exception\PermissionDeniedException;
use OCA\ProjectCheck\Service\MobileSettlementService;
use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Service\ProjectSettlementService;
use OCA\ProjectCheck\Service\TimeEntryBillingService;
use OCA\ProjectCheck\Service\TimeEntryService;
use OCA\ProjectCheck\Util\BillingStatus;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;

final class MobileSettlementServiceTest extends TestCase
{
	private ProjectService $projects;
	private TimeEntryService $timeEntries;
	private TimeEntryBillingService $billing;
	private ProjectSettlementService $projectSettlement;
	private MobileSettlementService $svc;

	protected function setUp(): void
	{
		$this->projects = $this->createMock(ProjectService::class);
		$this->timeEntries = $this->createMock(TimeEntryService::class);
		$this->billing = $this->createMock(TimeEntryBillingService::class);
		$this->projectSettlement = $this->createMock(ProjectSettlementService::class);
		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnCallback(static function (string $s, array $a = []) {
			return $a === [] ? $s : vsprintf(str_replace('%s', '%s', $s), $a);
		});
		$this->svc = new MobileSettlementService(
			$this->projects,
			$this->timeEntries,
			$this->billing,
			$this->projectSettlement,
			$l,
		);
	}

	public function testActorCanSettleWhenGlobalSettler(): void
	{
		$this->projects->method('getSettleableProjectIdListForUser')->willReturn(null);
		self::assertTrue($this->svc->actorCanSettleAnything('admin'));
	}

	public function testActorCanSettleWhenScopedManager(): void
	{
		$this->projects->method('getSettleableProjectIdListForUser')->willReturn([7, 9]);
		self::assertTrue($this->svc->actorCanSettleAnything('mgr'));
	}

	public function testActorCannotSettleWithEmptyScope(): void
	{
		$this->projects->method('getSettleableProjectIdListForUser')->willReturn([]);
		self::assertFalse($this->svc->actorCanSettleAnything('member'));
	}

	public function testListRejectsNonSettler(): void
	{
		$this->projects->method('getSettleableProjectIdListForUser')->willReturn([]);
		$this->expectException(MobileApiException::class);
		try {
			$this->svc->listSettleableEntries('member', null, null, 'outstanding');
		} catch (MobileApiException $e) {
			self::assertSame('forbidden', $e->getErrorCode());
			self::assertSame(403, $e->getHttpStatus());
			throw $e;
		}
	}

	public function testListFiltersBySettleAclPerRow(): void
	{
		$this->projects->method('getSettleableProjectIdListForUser')->willReturn([1, 2]);
		$this->projects->method('canUserSettleProject')->willReturnCallback(
			static fn (string $uid, int $pid): bool => $pid === 1
		);
		$this->timeEntries->method('getTimeEntriesWithProjectInfo')->willReturn([
			[
				'id' => 10,
				'project_id' => 1,
				'project_name' => 'Keep',
				'customer_name' => 'Acme',
				'user_id' => 'bob',
				'date' => '2026-07-01',
				'hours' => 1.0,
				'description' => '',
				'hourly_rate' => 100.0,
				'billing_status' => BillingStatus::OPEN,
			],
			[
				'id' => 11,
				'project_id' => 2,
				'project_name' => 'Drop',
				'customer_name' => 'Acme',
				'user_id' => 'bob',
				'date' => '2026-07-02',
				'hours' => 2.0,
				'description' => '',
				'hourly_rate' => 100.0,
				'billing_status' => BillingStatus::OPEN,
			],
		]);

		$result = $this->svc->listSettleableEntries('mgr', '2026-07-01', '2026-07-31', 'outstanding');
		self::assertCount(1, $result['entries']);
		self::assertSame(10, $result['entries'][0]['id']);
		self::assertSame([BillingStatus::INVOICED, BillingStatus::EXCLUDED], $result['entries'][0]['allowedTargets']);
		self::assertFalse($result['hasMore']);
		self::assertSame(200, $result['limit']);
	}

	public function testChangeStatusMapsOpenToPaidAsValidation(): void
	{
		$entry = $this->createMock(TimeEntry::class);
		// changeStatus inside billing throws InvalidBillingTransitionException for open→paid
		$this->billing->method('changeStatus')->willThrowException(
			new InvalidBillingTransitionException(BillingStatus::OPEN, BillingStatus::PAID)
		);
		$this->expectException(MobileApiException::class);
		try {
			$this->svc->changeEntryStatus('mgr', 5, BillingStatus::PAID);
		} catch (MobileApiException $e) {
			self::assertSame('validation', $e->getErrorCode());
			self::assertSame(422, $e->getHttpStatus());
			throw $e;
		}
	}

	public function testChangeStatusHappyPath(): void
	{
		$entry = new TimeEntry();
		$entry->setId(5);
		$entry->setProjectId(1);
		$entry->setUserId('bob');
		$entry->setDate(new \DateTime('2026-07-01'));
		$entry->setHours(1.0);
		$entry->setHourlyRate(100.0);
		$entry->setBillingStatus(BillingStatus::INVOICED);
		$entry->setCreatedAt(new \DateTime('2026-07-01'));
		$entry->setUpdatedAt(new \DateTime('2026-07-01'));
		$this->billing->expects(self::once())->method('changeStatus')
			->with(5, BillingStatus::INVOICED, 'mgr')
			->willReturn($entry);

		$row = $this->svc->changeEntryStatus('mgr', 5, BillingStatus::INVOICED);
		self::assertSame(5, $row['id']);
		self::assertSame(BillingStatus::INVOICED, $row['billingStatus']);
		self::assertSame([BillingStatus::PAID, BillingStatus::OPEN], $row['allowedTargets']);
	}

	public function testApplyRequiresToken(): void
	{
		$this->projects->method('canUserSettleProject')->willReturn(true);
		$this->expectException(MobileApiException::class);
		try {
			$this->svc->applyProjectSettle('mgr', 3, ['action' => 'invoice_open']);
		} catch (MobileApiException $e) {
			self::assertSame('validation', $e->getErrorCode());
			self::assertSame('required', $e->getDetails()['fields']['token'] ?? null);
			throw $e;
		}
	}

	public function testPreviewNormalizesMobileCamelCase(): void
	{
		$this->projects->method('canUserSettleProject')->willReturn(true);
		$this->projectSettlement->expects(self::once())->method('previewProjectSettle')
			->with(9, 'invoice_open', ['date_from' => '2026-07-01', 'date_to' => '2026-07-31'], 'mgr')
			->willReturn([
				'count' => 2,
				'hours' => 3.0,
				'amount' => 300.0,
				'token' => 'tok',
				'cap' => 500,
				'capExceeded' => false,
				'target' => 'invoiced',
			]);

		$row = $this->svc->previewProjectSettle('mgr', 9, [
			'action' => 'invoice_open',
			'date_from' => '2026-07-01',
			'date_to' => '2026-07-31',
		]);
		self::assertSame(2, $row['entryCount']);
		self::assertSame(3.0, $row['totalHours']);
		self::assertSame(300.0, $row['totalAmount']);
		self::assertSame('tok', $row['token']);
		self::assertSame(2, $row['count']);
	}

	public function testApplyNormalizesAppliedCount(): void
	{
		$this->projects->method('canUserSettleProject')->willReturn(true);
		$this->projectSettlement->expects(self::once())->method('applyProjectSettle')
			->willReturn(['applied' => 5, 'failed' => []]);

		$row = $this->svc->applyProjectSettle('mgr', 9, [
			'action' => 'mark_paid',
			'token' => 'tok',
		]);
		self::assertSame(5, $row['appliedCount']);
		self::assertSame(5, $row['applied']);
		self::assertArrayNotHasKey('totalHours', $row);
		self::assertArrayNotHasKey('totalAmount', $row);
	}

	public function testListSetsHasMoreWhenOverCap(): void
	{
		$this->projects->method('getSettleableProjectIdListForUser')->willReturn(null);
		$this->projects->method('canUserSettleProject')->willReturn(true);

		$rows = [];
		for ($i = 1; $i <= 201; $i++) {
			$rows[] = [
				'id' => $i,
				'project_id' => 1,
				'project_name' => 'P',
				'customer_name' => 'C',
				'user_id' => 'bob',
				'date' => '2026-07-01',
				'hours' => 1.0,
				'description' => '',
				'hourly_rate' => 100.0,
				'billing_status' => BillingStatus::OPEN,
			];
		}
		$this->timeEntries->method('getTimeEntriesWithProjectInfo')->willReturn($rows);

		$result = $this->svc->listSettleableEntries('admin', null, null, 'outstanding');
		self::assertCount(200, $result['entries']);
		self::assertTrue($result['hasMore']);
		self::assertSame(200, $result['limit']);
	}

	public function testApplyForbiddenWithoutAcl(): void
	{
		$this->projects->method('canUserSettleProject')->willReturn(false);
		$this->expectException(MobileApiException::class);
		try {
			$this->svc->applyProjectSettle('member', 3, ['action' => 'invoice_open', 'token' => 'abc']);
		} catch (MobileApiException $e) {
			self::assertSame('forbidden', $e->getErrorCode());
			throw $e;
		}
	}
}
