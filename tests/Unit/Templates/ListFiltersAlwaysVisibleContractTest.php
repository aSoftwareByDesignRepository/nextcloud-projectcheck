<?php

declare(strict_types=1);

/**
 * Contract: projects + customers list filters are always visible (no More filters).
 *
 * @copyright Copyright (c) 2026, Software by Design
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;

class ListFiltersAlwaysVisibleContractTest extends TestCase
{
	private static function read(string $relative): string
	{
		$path = dirname(__DIR__, 3) . '/' . $relative;
		self::assertFileExists($path);
		$content = file_get_contents($path);
		self::assertIsString($content);
		return $content;
	}

	/**
	 * @return list<array{0:string,1:list<string>}>
	 */
	public function listTemplateProvider(): array
	{
		return [
			'projects' => ['templates/projects.php', [
				'id="project-search"',
				'id="status-filter"',
				'id="priority-filter"',
				'id="project-type-filter"',
				'id="customer-filter"',
				'id="settlement-filter"',
				'id="apply-filters"',
				'id="clear-filters"',
			]],
			'customers' => ['templates/customers.php', [
				'id="customer-search"',
				'id="settlement-filter"',
				'id="apply-filters"',
				'id="clear-filters"',
			]],
			'time-entries' => ['templates/time-entries.php', [
				'id="time-entry-search"',
				'id="project-filter"',
				'id="billing-status-filter"',
				'id="date-from-filter"',
				'id="date-to-filter"',
			]],
		];
	}

	/**
	 * @dataProvider listTemplateProvider
	 * @param list<string> $requiredIds
	 */
	public function testListTemplateShowsAllFiltersWithoutDisclosure(string $relative, array $requiredIds): void
	{
		$tpl = self::read($relative);

		self::assertStringContainsString('pc-filters--all-visible', $tpl);
		self::assertStringNotContainsString('pc-filters__more', $tpl);
		self::assertStringNotContainsString('pc-filters__more-summary', $tpl);
		self::assertStringNotContainsString('More filters', $tpl);
		self::assertStringNotContainsString('pc-filters__grid--advanced', $tpl);
		self::assertSame(1, preg_match_all('/class="pc-filters__grid"/', $tpl));
		self::assertStringContainsString('role="search"', $tpl);

		foreach ($requiredIds as $needle) {
			self::assertStringContainsString($needle, $tpl);
		}
	}

	public function testSharedFiltersCssPromotesSearchForAllVisibleMode(): void
	{
		$css = self::read('css/common/filters.css');

		self::assertStringContainsString('.pc-filters--all-visible', $css);
		self::assertStringContainsString('.pc-filters--all-visible .pc-filters__field--search', $css);
		self::assertStringContainsString('grid-column: span 2', $css);
		self::assertStringContainsString('grid-column: 1 / -1', $css);
		self::assertStringContainsString('min-height: var(--pc-touch-min, 44px)', $css);
		self::assertStringContainsString(':focus-visible', $css);
	}

	public function testProjectsExportKeysStayAlignedWithVisibleControls(): void
	{
		$tpl = self::read('templates/projects.php');
		self::assertStringContainsString(
			"exportFilterKeys = 'search,status,priority,project_type,customer_id,settlement'",
			$tpl
		);
	}

	public function testCustomersExportKeysStayAlignedWithVisibleControls(): void
	{
		$tpl = self::read('templates/customers.php');
		self::assertStringContainsString(
			"exportFilterKeys = 'search,settlement'",
			$tpl
		);
	}
}
