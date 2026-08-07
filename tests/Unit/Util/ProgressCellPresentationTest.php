<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Tests\Unit\Util;

use OCA\ProjectCheck\Util\ProgressCellPresentation;
use PHPUnit\Framework\TestCase;

class ProgressCellPresentationTest extends TestCase
{
	/** @return list<array{0:mixed,1:mixed,2:bool}> */
	public function showBarProvider(): array
	{
		return [
			[0, 0, false],
			[0.0, 5.0, false],
			['', null, false],
			[null, 0, false],
			[-1, 10, false],
			['nan', 1, false],
			[0.01, 0, true],
			[12.5, 0, true],
			[100, 40, true],
			['25', '3', true],
		];
	}

	/**
	 * @dataProvider showBarProvider
	 * @param mixed $pct
	 * @param mixed $hours
	 */
	public function testShouldShowProgressBar($pct, $hours, bool $expected): void
	{
		self::assertSame($expected, ProgressCellPresentation::shouldShowProgressBar($pct, $hours));
	}

	public function testBarWidthClampsAndZeros(): void
	{
		self::assertSame(0.0, ProgressCellPresentation::barWidthPercent(0));
		self::assertSame(0.0, ProgressCellPresentation::barWidthPercent(-5));
		self::assertSame(0.0, ProgressCellPresentation::barWidthPercent(null));
		self::assertSame(50.0, ProgressCellPresentation::barWidthPercent(50));
		self::assertSame(100.0, ProgressCellPresentation::barWidthPercent(140));
	}

	public function testNormalizedHours(): void
	{
		self::assertSame(0.0, ProgressCellPresentation::normalizedHours(null));
		self::assertSame(0.0, ProgressCellPresentation::normalizedHours(-2));
		self::assertSame(3.5, ProgressCellPresentation::normalizedHours(3.5));
	}
}
