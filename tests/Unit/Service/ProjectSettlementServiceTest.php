<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Service;

use OCA\ProjectCheck\Db\Project;
use OCA\ProjectCheck\Service\ProjectSettlementCounterService;
use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Service\ProjectSettlementService;
use OCA\ProjectCheck\Service\TimeEntryBillingService;
use OCA\ProjectCheck\Util\SettlementPosture;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;

/**
 * Read-model posture + outstanding math from project counters (no DB).
 */
class ProjectSettlementServiceTest extends TestCase
{
	private function makeService(): ProjectSettlementService
	{
		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnCallback(static fn (string $s): string => $s);

		return new ProjectSettlementService(
			$this->createMock(ProjectService::class),
			$this->createMock(TimeEntryBillingService::class),
			$this->createMock(ProjectSettlementCounterService::class),
			$l,
		);
	}

	private function projectWithCounters(array $counters): Project
	{
		$project = new Project();
		$project->setId(42);
		$project->setStlOpenHours((float) ($counters['open_hours'] ?? 0));
		$project->setStlInvoicedHours((float) ($counters['invoiced_hours'] ?? 0));
		$project->setStlPaidHours((float) ($counters['paid_hours'] ?? 0));
		$project->setStlExcludedHours((float) ($counters['excluded_hours'] ?? 0));
		$project->setStlOpenAmount((float) ($counters['open_amount'] ?? 0));
		$project->setStlInvoicedAmount((float) ($counters['invoiced_amount'] ?? 0));
		$project->setStlPaidAmount((float) ($counters['paid_amount'] ?? 0));
		$project->setStlExcludedAmount((float) ($counters['excluded_amount'] ?? 0));
		return $project;
	}

	public function testSettlementInfoDerivesPartialPostureAndOutstanding(): void
	{
		$projectService = $this->createMock(ProjectService::class);
		$projectService->method('canUserSettleProject')->willReturn(true);

		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnCallback(static fn (string $s): string => $s);

		$service = new ProjectSettlementService(
			$projectService,
			$this->createMock(TimeEntryBillingService::class),
			$this->createMock(ProjectSettlementCounterService::class),
			$l,
		);

		$project = $this->projectWithCounters([
			'open_hours' => 10.0,
			'invoiced_hours' => 5.0,
			'paid_hours' => 2.0,
			'open_amount' => 1000.0,
			'invoiced_amount' => 500.0,
			'paid_amount' => 200.0,
		]);

		$info = $service->getSettlementInfo($project, 'alice');

		$this->assertSame(SettlementPosture::PARTIAL, $info['posture']);
		$this->assertSame(15.0, $info['outstanding_hours']);
		$this->assertSame(1500.0, $info['outstanding_amount']);
		$this->assertSame(17.0, $info['chargeable_hours']);
		$this->assertTrue($info['can_settle']);
		$this->assertTrue($info['progress']['has_chargeable']);
		$this->assertSame(100, array_sum($info['progress']['bar']));
		$this->assertSame(
			$info['progress']['paid_percent'] + $info['progress']['invoiced_percent'],
			$info['progress']['billed_percent']
		);
	}

	public function testSettlementInfoPaidWhenNoOutstanding(): void
	{
		$service = $this->makeService();
		$project = $this->projectWithCounters([
			'paid_hours' => 8.0,
			'paid_amount' => 800.0,
		]);

		// Bypass ACL via enrich path with settleable nowhere → can_settle false
		$info = $service->enrichProjectsWithSettlementInfo([$project], 'bob');
		$this->assertSame(SettlementPosture::PAID, $info[42]['posture']);
		$this->assertSame(0.0, $info[42]['outstanding_hours']);
		$this->assertFalse($info[42]['can_settle']);
		$this->assertSame(100, $info[42]['progress']['paid_percent']);
		$this->assertSame(100, $info[42]['progress']['billed_percent']);
	}

	public function testResolveActionRejectsUnknown(): void
	{
		$service = $this->makeService();
		$ref = new \ReflectionClass($service);
		$method = $ref->getMethod('resolveAction');
		$method->setAccessible(true);

		$this->expectException(\OCA\ProjectCheck\Exception\ValidationException::class);
		$method->invoke($service, 'skip_to_paid');
	}
}
