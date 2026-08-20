<?php

declare(strict_types=1);

/**
 * Contract: time-entries list shows every filter by default (no progressive disclosure).
 *
 * @copyright Copyright (c) 2026, Software by Design
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;

class TimeEntriesFiltersLayoutContractTest extends TestCase
{
	private static function read(string $relative): string
	{
		$path = dirname(__DIR__, 3) . '/' . $relative;
		self::assertFileExists($path);
		$content = file_get_contents($path);
		self::assertIsString($content);
		return $content;
	}

	public function testTimeEntriesTemplateExposesAllFiltersWithoutMoreDisclosure(): void
	{
		$tpl = self::read('templates/time-entries.php');

		self::assertStringContainsString('pc-filters--all-visible', $tpl);
		self::assertStringNotContainsString('pc-filters__more', $tpl);
		self::assertStringNotContainsString('pc-filters__more-summary', $tpl);
		self::assertStringNotContainsString('More filters', $tpl);
		self::assertStringNotContainsString('$teAdvancedOpen', $tpl);
		self::assertStringNotContainsString('pc-filters__grid--advanced', $tpl);

		foreach ([
			'id="time-entry-search"',
			'id="project-filter"',
			'id="time-entry-project-type-filter"',
			'id="billing-status-filter"',
			'id="date-from-filter"',
			'id="date-to-filter"',
			'id="apply-filters"',
			'id="clear-filters"',
		] as $needle) {
			self::assertStringContainsString($needle, $tpl);
		}

		// User filter remains capability-gated (canViewAllEntries)
		self::assertStringContainsString('id="user-filter"', $tpl);
		self::assertStringContainsString('canViewAllEntries', $tpl);

		// Single field grid — search and filters share one pc-filters__grid
		self::assertSame(1, preg_match_all('/class="pc-filters__grid"/', $tpl));
	}

	public function testFilterControlsKeepVisibleLabelsForWcag(): void
	{
		$tpl = self::read('templates/time-entries.php');

		self::assertMatchesRegularExpression(
			'/<label\s+for="time-entry-search"[^>]*>/s',
			$tpl
		);
		self::assertMatchesRegularExpression(
			'/<label\s+for="project-filter"[^>]*>/s',
			$tpl
		);
		self::assertMatchesRegularExpression(
			'/<label\s+for="billing-status-filter"[^>]*>/s',
			$tpl
		);
		self::assertMatchesRegularExpression(
			'/<label\s+for="date-from-filter"[^>]*>/s',
			$tpl
		);
		self::assertMatchesRegularExpression(
			'/<label\s+for="date-to-filter"[^>]*>/s',
			$tpl
		);
		self::assertStringContainsString('role="search"', $tpl);
		self::assertStringContainsString('aria-label', $tpl);
	}

	public function testSettlementFilterOptionsRemainComplete(): void
	{
		$tpl = self::read('templates/time-entries.php');

		foreach (['outstanding', 'open', 'invoiced', 'paid', 'excluded'] as $status) {
			self::assertMatchesRegularExpression(
				'/<option\s+value="' . preg_quote($status, '/') . '"/',
				$tpl
			);
		}
	}

	public function testCssGivesSearchProminenceWhenAllFiltersVisible(): void
	{
		$css = self::read('css/common/filters.css');

		self::assertStringContainsString('pc-filters--all-visible', $css);
		self::assertStringContainsString('.pc-filters--all-visible .pc-filters__field--search', $css);
		self::assertStringContainsString('grid-column: span 2', $css);
		self::assertStringContainsString('grid-column: 1 / -1', $css);
		self::assertStringContainsString('min-height: var(--pc-touch-min, 44px)', $css);
	}

	public function testFilterJsStillTargetsAlwaysVisibleControlIds(): void
	{
		$js = self::read('js/time-entries.js');

		foreach ([
			'time-entry-search',
			'project-filter',
			'time-entry-project-type-filter',
			'billing-status-filter',
			'date-from-filter',
			'date-to-filter',
			'apply-filters',
			'clear-filters',
		] as $id) {
			self::assertStringContainsString($id, $js);
		}

		// Date range edge case must still be enforced client-side before navigation
		self::assertStringContainsString(
			'if (dateFrom && dateTo && dateFrom > dateTo)',
			$js
		);
		self::assertStringNotContainsString(
			'if (false && dateFrom && dateTo && dateFrom > dateTo)',
			$js
		);
		self::assertStringContainsString('The start date must be on or before the end date.', $js);
	}
}
