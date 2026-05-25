<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Service;

use OCA\ProjectCheck\Db\Project;
use OCA\ProjectCheck\Db\TimeEntryMapper;
use OCA\ProjectCheck\Service\BudgetService;
use OCA\ProjectCheck\Service\LocaleFormatService;
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
		// Return the configured default so warning thresholds (80) and critical
		// thresholds (90) match production defaults instead of degenerating to
		// 0 (which would make every consumption value land in `critical`).
		$config->method('getAppValue')->willReturnCallback(static function (string $app, string $key, string $default = '') {
			return $default;
		});
		$config->method('getUserValue')->willReturnCallback(static function (string $user, string $app, string $key, $default = '') {
			return $default;
		});
		$logger = $this->createMock(LoggerInterface::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static function (string $text, array $args = []): string {
			if ($args === []) {
				return $text;
			}
			return vsprintf($text, $args);
		});

		// Audit ref. AUDIT-FINDINGS B10/H28: budget alert messages route monetary
		// and percentage values through LocaleFormatService. The unit test stubs
		// it with deterministic outputs so message assertions are stable.
		$localeFormat = $this->createMock(LocaleFormatService::class);
		$localeFormat->method('currency')->willReturnCallback(static function ($value): string {
			return '€' . number_format((float)$value, 2, '.', ',');
		});
		$localeFormat->method('percent')->willReturnCallback(static function ($value, int $maxDecimals = 1): string {
			return number_format((float)$value, $maxDecimals, '.', '') . ' %';
		});
		$localeFormat->method('number')->willReturnCallback(static function ($value, int $minDecimals = 0, int $maxDecimals = 2): string {
			return number_format((float)$value, $maxDecimals, '.', ',');
		});
		$localeFormat->method('hours')->willReturnCallback(static function ($value): string {
			return number_format((float)$value, 2, '.', ',') . ' h';
		});
		$localeFormat->method('getCurrency')->willReturn('EUR');
		$localeFormat->method('getLocale')->willReturn('en');

		$this->service = new BudgetService(
			$this->timeEntryMapper,
			$config,
			$logger,
			$l10n,
			$localeFormat,
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

	/**
	 * Audit ref. A5: precision regression for fixed-point money math.
	 *
	 * 0.1 + 0.2 famously becomes 0.30000000000000004 in IEEE-754, so a
	 * naive `additional_cost = hours * rate` would surface values like
	 * 392.5499999... cents in JSON responses. The Money helper must
	 * eliminate that drift across the budget impact endpoint.
	 */
	public function testCheckTimeEntryBudgetImpactProducesFixedPointAdditionalCost(): void {
		$project = new Project();
		$project->setId(7);
		$project->setTotalBudget(1000.00);
		$project->setHourlyRate(0.0); // hourly rate not used here

		// 7.5 * 52.34 == 392.55 exactly (no IEEE drift permitted)
		$this->timeEntryMapper->method('getTotalCostForProject')->willReturn(0.0);
		$this->timeEntryMapper->method('getTotalHoursForProject')->willReturn(0.0);

		$impact = $this->service->checkTimeEntryBudgetImpact($project, 7.5, 52.34);
		$this->assertSame(392.55, $impact['additional_cost']);
		$this->assertSame(607.45, $impact['remaining_budget_after']);
		// Percentages are rounded half-away-from-zero at 2 decimals, so
		// 39.255% becomes 39.26% (not the IEEE-754 drift value 39.25%).
		$this->assertSame(39.26, $impact['new_consumption']);
	}

	public function testCheckTimeEntryBudgetImpactDoesNotInflateNear100(): void {
		$project = new Project();
		$project->setId(7);
		$project->setTotalBudget(1000.00);
		$project->setHourlyRate(100.0);

		// Already used 999.99; adding 0 should not round up to 100%.
		$this->timeEntryMapper->method('getTotalCostForProject')->willReturn(999.99);
		$this->timeEntryMapper->method('getTotalHoursForProject')->willReturn(9.9999);

		$impact = $this->service->checkTimeEntryBudgetImpact($project, 0.0, 100.0);
		$this->assertFalse($impact['would_exceed_budget']);
		// percentageBounded() must clamp 99.999% to 99.99% so the UI never
		// lies about budget exhaustion at the 100% boundary.
		$this->assertSame(99.99, $impact['new_consumption']);
	}

	/**
	 * Audit ref. AUDIT-FINDINGS B10/H28: alert messages must use neutral
	 * `%s` placeholders that the {@see LocaleFormatService} fills with
	 * locale-aware currency / percent strings — never a hard-coded `€`.
	 */
	public function testGenerateBudgetAlertExceededUsesLocaleFormatService(): void {
		$project = new Project();
		$project->setId(11);
		$project->setName('Foo');
		$project->setTotalBudget(1000.00);
		$project->setHourlyRate(50.0);

		// 7520 spent vs 1000 budget => 752% / over by 6520
		$this->timeEntryMapper->method('getTotalCostForProject')->willReturn(7520.00);
		$this->timeEntryMapper->method('getTotalHoursForProject')->willReturn(150.4);

		$info = $this->service->getProjectBudgetInfo($project);
		$this->assertNotEmpty($info['alerts']);
		$alert = $info['alerts'][0];
		$this->assertSame('budget_exceeded', $alert['type']);
		$this->assertStringContainsString('Foo', $alert['message']);
		$this->assertStringContainsString('€6,520.00', $alert['message']);
		$this->assertStringContainsString('652.0 %', $alert['message']);
		$this->assertStringNotContainsString('%.2f', $alert['message']);
		$this->assertStringNotContainsString('%.1f', $alert['message']);
	}

	public function testGenerateBudgetAlertWarningUsesLocalePercent(): void {
		$project = new Project();
		$project->setId(12);
		$project->setName('Bar');
		$project->setTotalBudget(1000.00);
		$project->setHourlyRate(50.0);

		// 850 spent vs 1000 budget => 85% (warning, not critical with default 80/90)
		$this->timeEntryMapper->method('getTotalCostForProject')->willReturn(850.00);
		$this->timeEntryMapper->method('getTotalHoursForProject')->willReturn(17.0);

		$info = $this->service->getProjectBudgetInfo($project);
		$this->assertNotEmpty($info['alerts']);
		$alert = $info['alerts'][0];
		$this->assertSame('budget_warning', $alert['type']);
		$this->assertStringContainsString('Bar', $alert['message']);
		$this->assertStringContainsString('85.0 %', $alert['message']);
		$this->assertStringNotContainsString('%.1f', $alert['message']);
	}

	public function testGetProjectBudgetInfoMarksCapacityUnavailableWithoutPlanningRate(): void {
		$project = new Project();
		$project->setId(20);
		$project->setCostRateMode(\OCA\ProjectCheck\Util\CostRateMode::PROJECT_MEMBER);
		$project->setTotalBudget(5000.0);
		$project->setHourlyRate(0.0);

		$this->timeEntryMapper->method('getTotalCostForProject')->willReturn(1000.0);
		$this->timeEntryMapper->method('getTotalHoursForProject')->willReturn(20.0);

		$info = $this->service->getProjectBudgetInfo($project);

		$this->assertFalse($info['hours_estimated']);
		$this->assertSame('unavailable', $info['capacity_basis']);
		$this->assertSame(20.0, $info['used_hours']);
		$this->assertSame(4000.0, $info['remaining_budget']);
	}

	public function testGetProjectBudgetInfoExposesOverBudgetAmount(): void {
		$project = new Project();
		$project->setId(13);
		$project->setName('Over');
		$project->setTotalBudget(1000.00);
		$project->setHourlyRate(50.0);

		$this->timeEntryMapper->method('getTotalCostForProject')->willReturn(1250.00);
		$this->timeEntryMapper->method('getTotalHoursForProject')->willReturn(25.0);

		$info = $this->service->getProjectBudgetInfo($project);

		$this->assertTrue($info['is_over_budget']);
		$this->assertSame(250.0, $info['over_budget_amount']);
		$this->assertSame(0.0, $info['remaining_budget']);
	}

	public function testCheckTimeEntryBudgetImpactReportsOverBudgetAfter(): void {
		$project = new Project();
		$project->setId(14);
		$project->setTotalBudget(1000.00);
		$project->setHourlyRate(100.0);

		$this->timeEntryMapper->method('getTotalCostForProject')->willReturn(900.00);
		$this->timeEntryMapper->method('getTotalHoursForProject')->willReturn(9.0);

		// +200 cost => 1100 used => 100 over
		$impact = $this->service->checkTimeEntryBudgetImpact($project, 2.0, 100.0);
		$this->assertTrue($impact['would_exceed_budget']);
		$this->assertSame(100.0, $impact['over_budget_after']);
		$this->assertSame(-100.0, $impact['remaining_budget_after']);
	}

	public function testNormalizedThresholdsWhenWarningExceedsCritical(): void {
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(static function (string $app, string $key, string $default = '') {
			if ($key === 'budget_warning_threshold') {
				return '95';
			}
			if ($key === 'budget_critical_threshold') {
				return '90';
			}
			return $default;
		});
		$config->method('getUserValue')->willReturnCallback(static function (string $user, string $app, string $key, $default = '') {
			return $default;
		});

		$logger = $this->createMock(LoggerInterface::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$localeFormat = $this->createMock(LocaleFormatService::class);
		$localeFormat->method('currency')->willReturn('€0.00');
		$localeFormat->method('percent')->willReturn('0 %');

		$service = new BudgetService(
			$this->timeEntryMapper,
			$config,
			$logger,
			$l10n,
			$localeFormat,
			'projectcheck'
		);

		$project = new Project();
		$project->setId(15);
		$project->setTotalBudget(1000.00);
		$project->setHourlyRate(50.0);

		// 89.5% consumed — above normalized warning (89), below critical (90)
		$this->timeEntryMapper->method('getTotalCostForProject')->willReturn(895.00);
		$this->timeEntryMapper->method('getTotalHoursForProject')->willReturn(17.9);

		$info = $service->getProjectBudgetInfo($project);
		$this->assertSame('warning', $info['warning_level']);
		$this->assertSame(89.0, $info['warning_threshold']);
		$this->assertSame(90.0, $info['critical_threshold']);
	}

	public function testCheckTimeEntryBudgetImpactUsesUserThresholds(): void {
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(static function (string $app, string $key, string $default = '') {
			return $default;
		});
		$config->method('getUserValue')->willReturnCallback(static function (string $user, string $app, string $key, $default = '') {
			if ($user === 'alice' && $key === 'budget_warning_threshold') {
				return '70';
			}
			if ($user === 'alice' && $key === 'budget_critical_threshold') {
				return '85';
			}
			return $default;
		});

		$logger = $this->createMock(LoggerInterface::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$localeFormat = $this->createMock(LocaleFormatService::class);
		$localeFormat->method('currency')->willReturn('€0.00');
		$localeFormat->method('percent')->willReturn('0 %');
		$localeFormat->method('number')->willReturn('0');
		$localeFormat->method('hours')->willReturn('0 h');
		$localeFormat->method('getCurrency')->willReturn('EUR');
		$localeFormat->method('getLocale')->willReturn('en');

		$service = new BudgetService(
			$this->timeEntryMapper,
			$config,
			$logger,
			$l10n,
			$localeFormat,
			'projectcheck'
		);

		$project = new Project();
		$project->setId(99);
		$project->setTotalBudget(1000.00);
		$project->setHourlyRate(100.0);

		$this->timeEntryMapper->method('getTotalCostForProject')->willReturn(840.00);
		$this->timeEntryMapper->method('getTotalHoursForProject')->willReturn(8.4);

		// 840 + 10 = 850 => 85% — critical for alice (85%), warning at defaults (80/90)
		$impact = $service->checkTimeEntryBudgetImpact($project, 0.1, 100.0, 'alice');
		$this->assertSame('critical', $impact['warning_level_after']);

		$impactDefault = $this->service->checkTimeEntryBudgetImpact($project, 0.1, 100.0);
		$this->assertSame('warning', $impactDefault['warning_level_after']);
	}
}
