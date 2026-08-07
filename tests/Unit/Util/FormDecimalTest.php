<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Tests\Unit\Util;

use OCA\ProjectCheck\Util\FormDecimal;
use PHPUnit\Framework\TestCase;

class FormDecimalTest extends TestCase
{
	public function testEmptyStringBecomesZero(): void
	{
		$this->assertSame(0.0, FormDecimal::coerce(''));
		$this->assertSame(0.0, FormDecimal::coerce('   '));
		$this->assertSame(0.0, FormDecimal::coerce(null));
	}

	public function testNumericStringsAndNumbers(): void
	{
		$this->assertSame(0.0, FormDecimal::coerce('0'));
		$this->assertSame(120.5, FormDecimal::coerce('120.5'));
		$this->assertSame(10.0, FormDecimal::coerce(10));
		$this->assertSame(3.25, FormDecimal::coerce(3.25));
	}

	public function testRejectsNonNumeric(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		FormDecimal::coerce('not-a-number');
	}
}
