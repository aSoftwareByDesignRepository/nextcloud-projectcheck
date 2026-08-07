<?php

declare(strict_types=1);

/**
 * Contract: project lists use Lucide type chips — no emoji glyphs.
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;

class ProjectTypeChipThemeContractTest extends TestCase
{
	/** @return list<string> */
	private static function templates(): array
	{
		$root = dirname(__DIR__, 3) . '/templates';
		return [
			$root . '/projects.php',
			$root . '/dashboard.php',
			$root . '/customer-detail.php',
			$root . '/project-detail.php',
			$root . '/time-entries.php',
			$root . '/parts/project-type-chip.php',
		];
	}

	public function testNoEmojiProjectTypeMapsRemain(): void
	{
		foreach (self::templates() as $path) {
			self::assertFileExists($path);
			$content = (string) file_get_contents($path);
			self::assertStringNotContainsString("'client' => '👥'", $content, $path);
			self::assertStringNotContainsString('👥', $content, $path);
			self::assertStringNotContainsString('⚠️', $content, $path);
			self::assertStringNotContainsString('✅', $content, $path);
		}
	}

	public function testProjectsListUsesTypeChipPartial(): void
	{
		$projects = (string) file_get_contents(dirname(__DIR__, 3) . '/templates/projects.php');
		self::assertStringContainsString('parts/project-type-chip.php', $projects);
		self::assertStringContainsString('data-lucide="alert-triangle"', $projects);
	}

	public function testTypeChipPartialUsesLucideAndUtil(): void
	{
		$chip = (string) file_get_contents(dirname(__DIR__, 3) . '/templates/parts/project-type-chip.php');
		self::assertStringContainsString('ProjectTypeIcon', $chip);
		self::assertStringContainsString('data-lucide', $chip);
		self::assertStringContainsString('project-type-chip', $chip);
	}
}
