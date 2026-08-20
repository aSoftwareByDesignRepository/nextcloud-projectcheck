<?php

declare(strict_types=1);

/**
 * Integration-style contract: time-entries filter IDs stay wired to export + billing JS.
 *
 * Complements TimeEntriesFiltersLayoutContractTest by asserting cross-file invariants
 * that would break settlement filter-mode / CSV export if a control id drifted.
 *
 * @copyright Copyright (c) 2026, Software by Design
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;

class TimeEntriesFiltersWorkflowContractTest extends TestCase
{
	private static function read(string $relative): string
	{
		$path = dirname(__DIR__, 3) . '/' . $relative;
		self::assertFileExists($path);
		$content = file_get_contents($path);
		self::assertIsString($content);
		return $content;
	}

	public function testExportMenuFilterKeysMatchAlwaysVisibleControls(): void
	{
		$tpl = self::read('templates/time-entries.php');

		self::assertStringContainsString(
			"exportFilterKeys = 'search,project_id,user_id,project_type,date_from,date_to,billing_status'",
			$tpl
		);
		self::assertStringContainsString('id="billing-status-filter"', $tpl);
		self::assertStringContainsString('id="date-from-filter"', $tpl);
		self::assertStringContainsString('id="date-to-filter"', $tpl);
		self::assertStringContainsString('id="project-filter"', $tpl);
		self::assertStringContainsString('id="time-entry-project-type-filter"', $tpl);
		self::assertStringContainsString('id="time-entry-search"', $tpl);
	}

	public function testBillingFilterModeJsCollectsSameControlIds(): void
	{
		$js = self::read('js/time-entries.js');
		$tpl = self::read('templates/time-entries.php');

		self::assertMatchesRegularExpression('/function collectListFilters\s*\(/', $js);
		self::assertDoesNotMatchRegularExpression('/function collectListFiltersBroken\s*\(/', $js);
		self::assertStringContainsString("getElementById('date-from-filter')", $js);
		self::assertStringContainsString("getElementById('date-to-filter')", $js);
		self::assertStringContainsString('project-filter', $js);
		self::assertStringContainsString('billing-status-filter', $js);

		// Template must expose those IDs without hiding them behind details
		self::assertStringContainsString('pc-filters--all-visible', $tpl);
		self::assertStringNotContainsString('pc-filters__more', $tpl);
	}

	public function testInvalidDateRangeBlocksNavigationBeforeQueryMutation(): void
	{
		$js = self::read('js/time-entries.js');

		$guardPos = strpos($js, 'if (dateFrom && dateTo && dateFrom > dateTo)');
		$navPos = strpos($js, 'window.location.href = url.toString()');
		self::assertNotFalse($guardPos);
		self::assertNotFalse($navPos);
		self::assertLessThan($navPos, $guardPos, 'Date-range guard must run before navigation');
		self::assertStringContainsString("url.searchParams.set('page', '1')", $js);
	}
}
