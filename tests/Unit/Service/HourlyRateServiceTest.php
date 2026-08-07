<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Service;

use OCA\ProjectCheck\Db\Project;
use OCA\ProjectCheck\Db\ProjectMapper;
use OCA\ProjectCheck\Exception\RateResolutionException;
use OCA\ProjectCheck\Service\EmployeeHourlyRateService;
use OCA\ProjectCheck\Service\HourlyRateService;
use OCA\ProjectCheck\Service\ProjectMemberHourlyRateService;
use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Util\CostRateMode;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;

class HourlyRateServiceTest extends TestCase
{
	private function makeService(
		?Project $project,
		bool $onTeam = true,
		float $employeeRate = 85.0,
		float $memberRate = 0.0,
		bool $adminOverrideEligible = false,
	): HourlyRateService {
		$projectMapper = $this->createMock(ProjectMapper::class);
		$projectMapper->method('find')->willReturn($project);

		$projectService = $this->createMock(ProjectService::class);
		$projectService->method('isActiveTeamMember')->willReturn($onTeam);
		$projectService->method('isAdminTimeEntryOverrideEligible')->willReturn($adminOverrideEligible);

		$employeeRates = $this->createMock(EmployeeHourlyRateService::class);
		$employeeRates->method('resolveRateForDate')->willReturn($employeeRate);

		$memberRates = $this->createMock(ProjectMemberHourlyRateService::class);
		$memberRates->method('resolveRateForProjectMember')->willReturnCallback(
			static function () use ($memberRate): float {
				if ($memberRate <= 0) {
					throw new RateResolutionException('missing', 'member_rate_missing');
				}
				return $memberRate;
			}
		);

		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnArgument(0);

		return new HourlyRateService(
			$projectMapper,
			$projectService,
			$employeeRates,
			$memberRates,
			$l
		);
	}

	private function projectWithMode(string $mode, float $projectRate = 100.0): Project
	{
		$p = new Project();
		$p->setId(1);
		$p->setStatus('Active');
		$p->setCostRateMode($mode);
		$p->setHourlyRate($projectRate);
		return $p;
	}

	public function testResolveProjectMode(): void
	{
		$svc = $this->makeService($this->projectWithMode(CostRateMode::PROJECT, 120.0));
		$rate = $svc->resolveForTimeEntry(1, 'alice', new \DateTime('2026-01-15'));
		$this->assertEqualsWithDelta(120.0, $rate, HourlyRateService::CLIENT_TOLERANCE);
	}

	public function testResolveEmployeeMode(): void
	{
		$svc = $this->makeService($this->projectWithMode(CostRateMode::EMPLOYEE), true, 77.5);
		$rate = $svc->resolveForTimeEntry(1, 'alice', new \DateTime('2026-01-15'));
		$this->assertEqualsWithDelta(77.5, $rate, HourlyRateService::CLIENT_TOLERANCE);
	}

	public function testNotOnTeamRejected(): void
	{
		$svc = $this->makeService($this->projectWithMode(CostRateMode::PROJECT), false);
		$this->expectException(RateResolutionException::class);
		$svc->resolveForTimeEntry(1, 'alice', new \DateTime('2026-01-15'));
	}

	public function testAdminOverrideAllowsProjectModeWithoutTeamMembership(): void
	{
		$svc = $this->makeService(
			$this->projectWithMode(CostRateMode::PROJECT, 95.0),
			false,
			85.0,
			0.0,
			true,
		);
		$rate = $svc->resolveForTimeEntry(1, 'admin', new \DateTime('2026-05-01'));
		$this->assertEqualsWithDelta(95.0, $rate, HourlyRateService::CLIENT_TOLERANCE);
	}

	public function testAdminOverrideAllowsEmployeeModeWithoutTeamMembership(): void
	{
		$svc = $this->makeService(
			$this->projectWithMode(CostRateMode::EMPLOYEE),
			false,
			110.0,
			0.0,
			true,
		);
		$rate = $svc->resolveForTimeEntry(1, 'admin', new \DateTime('2026-05-01'));
		$this->assertEqualsWithDelta(110.0, $rate, HourlyRateService::CLIENT_TOLERANCE);
	}

	public function testAdminOverrideDoesNotApplyToProjectMemberMode(): void
	{
		// Even with admin override eligibility, PROJECT_MEMBER pricing requires
		// active team membership because per-member rates are not available
		// for non-members.
		$svc = $this->makeService(
			$this->projectWithMode(CostRateMode::PROJECT_MEMBER),
			false,
			85.0,
			62.5,
			true,
		);
		$this->expectException(RateResolutionException::class);
		$svc->resolveForTimeEntry(1, 'admin', new \DateTime('2026-05-01'));
	}

	public function testAssertClientRateTamper(): void
	{
		$svc = $this->makeService($this->projectWithMode(CostRateMode::PROJECT, 50.0));
		$this->expectException(RateResolutionException::class);
		$svc->assertClientRateMatchesResolved(99.0, 50.0);
	}

	public function testAssertClientRateWithinTolerance(): void
	{
		$svc = $this->makeService($this->projectWithMode(CostRateMode::PROJECT, 50.0));
		$svc->assertClientRateMatchesResolved(50.005, 50.0);
		$this->addToAssertionCount(1);
	}

	public function testProjectModeZeroRateFails(): void
	{
		$svc = $this->makeService($this->projectWithMode(CostRateMode::PROJECT, 0.0));
		$this->expectException(RateResolutionException::class);
		$svc->resolveForTimeEntry(1, 'alice', new \DateTime('2026-01-15'));
	}

	public function testResolveProjectMemberMode(): void
	{
		$svc = $this->makeService($this->projectWithMode(CostRateMode::PROJECT_MEMBER), true, 85.0, 62.5);
		$rate = $svc->resolveForTimeEntry(1, 'alice', new \DateTime('2026-04-01'));
		$this->assertEqualsWithDelta(62.5, $rate, HourlyRateService::CLIENT_TOLERANCE);
	}

	public function testResolveEmployeeModeMissingRatePropagates(): void
	{
		$projectMapper = $this->createMock(ProjectMapper::class);
		$projectMapper->method('find')->willReturn($this->projectWithMode(CostRateMode::EMPLOYEE));

		$projectService = $this->createMock(ProjectService::class);
		$projectService->method('isActiveTeamMember')->willReturn(true);

		$employeeRates = $this->createMock(EmployeeHourlyRateService::class);
		$employeeRates->method('resolveRateForDate')->willThrowException(
			new RateResolutionException('No employee hourly rate', 'employee_rate_missing')
		);

		$memberRates = $this->createMock(ProjectMemberHourlyRateService::class);
		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnArgument(0);

		$svc = new HourlyRateService(
			$projectMapper,
			$projectService,
			$employeeRates,
			$memberRates,
			$l
		);

		$this->expectException(RateResolutionException::class);
		$svc->resolveForTimeEntry(1, 'alice', new \DateTime('2026-01-15'));
	}
}
