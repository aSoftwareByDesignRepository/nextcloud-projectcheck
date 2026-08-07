<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Db;

use OCA\ProjectCheck\Db\ProjectQueryColumns;
use PHPUnit\Framework\TestCase;

class ProjectQueryColumnsTest extends TestCase
{
	public function testQualifiedIncludesAliasAndCoreColumns(): void
	{
		$cols = ProjectQueryColumns::qualified('p');
		self::assertContains('p.id', $cols);
		self::assertContains('p.project_type', $cols);
		self::assertContains('p.cost_rate_mode', $cols);
		self::assertNotContains('p.*', $cols);
	}
}
