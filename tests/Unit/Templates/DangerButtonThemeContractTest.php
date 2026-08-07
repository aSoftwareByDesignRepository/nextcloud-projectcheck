<?php

declare(strict_types=1);

/**
 * Contract: solid danger CTAs must not paint on-fill ink on pale --color-error.
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;

class DangerButtonThemeContractTest extends TestCase
{
	public function testTokensDefineElementErrorDangerFill(): void
	{
		$tokens = (string) file_get_contents(dirname(__DIR__, 3) . '/css/common/tokens.css');
		self::assertMatchesRegularExpression(
			'/--pc-danger-fill:\s*var\(\s*--color-element-error/s',
			$tokens
		);
		self::assertStringContainsString('--pc-danger-on-fill', $tokens);
		self::assertStringContainsString('body[data-theme-dark]', $tokens);
	}

	public function testListDangerActionsDoNotUsePaleColorErrorFill(): void
	{
		$list = (string) file_get_contents(dirname(__DIR__, 3) . '/css/common/list-table.css');
		self::assertStringContainsString('--pc-danger-fill', $list);
		self::assertStringContainsString('--pc-tint-danger', $list);
		self::assertStringContainsString('--pc-danger-ink', $list);
		// Solid rest-state fill with pale --color-error is forbidden for danger icon buttons.
		self::assertDoesNotMatchRegularExpression(
			'/\.action-item--danger\s*\{[^}]*background:\s*var\(--color-error\)/s',
			$list
		);
	}

	public function testBtnDangerUsesDangerFillToken(): void
	{
		$components = (string) file_get_contents(dirname(__DIR__, 3) . '/css/common/components.css');
		self::assertMatchesRegularExpression(
			'/\.btn--danger\s*\{[^}]*background-color:\s*var\(--pc-danger-fill/s',
			$components
		);
		self::assertDoesNotMatchRegularExpression(
			'/\.btn--danger\s*\{[^}]*background-color:\s*var\(--color-error\)\s*;/s',
			$components
		);
	}

	public function testProjectsListUsesProgressPartialWithoutZeroTrackFallback(): void
	{
		$projects = (string) file_get_contents(dirname(__DIR__, 3) . '/templates/projects.php');
		self::assertStringContainsString('parts/project-progress-cell.php', $projects);
		self::assertStringNotContainsString('style="width: 0%"', $projects);

		$partial = (string) file_get_contents(dirname(__DIR__, 3) . '/templates/parts/project-progress-cell.php');
		self::assertStringContainsString('ProgressCellPresentation', $partial);
		self::assertStringContainsString('shouldShowProgressBar', $partial);
		self::assertStringContainsString('No hours logged yet', $partial);
	}
}
