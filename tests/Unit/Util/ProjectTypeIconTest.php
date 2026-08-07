<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Tests\Unit\Util;

use OCA\ProjectCheck\Util\ProjectTypeIcon;
use PHPUnit\Framework\TestCase;

class ProjectTypeIconTest extends TestCase
{
	public function testKnownTypesMapToLucide(): void
	{
		$this->assertSame('briefcase', ProjectTypeIcon::lucideName('client'));
		$this->assertSame('settings', ProjectTypeIcon::lucideName('admin'));
		$this->assertSame('clipboard-list', ProjectTypeIcon::lucideName('other'));
	}

	public function testUnknownFallsBackToOther(): void
	{
		$this->assertSame('clipboard-list', ProjectTypeIcon::lucideName('totally-unknown'));
		$this->assertSame('clipboard-list', ProjectTypeIcon::lucideName(null));
		$this->assertSame('clipboard-list', ProjectTypeIcon::lucideName(''));
	}

	public function testCaseInsensitive(): void
	{
		$this->assertSame('briefcase', ProjectTypeIcon::lucideName('CLIENT'));
		$this->assertSame('users', ProjectTypeIcon::lucideName(' Meeting '));
	}

	public function testNoEmojiInMap(): void
	{
		foreach (ProjectTypeIcon::knownTypes() as $type) {
			$name = ProjectTypeIcon::lucideName($type);
			$this->assertDoesNotMatchRegularExpression('/[\x{1F300}-\x{1FAFF}]/u', $name);
			$this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $name);
		}
	}
}
