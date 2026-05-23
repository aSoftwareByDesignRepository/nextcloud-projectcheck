<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Migration;

use OCA\ProjectCheck\Migration\LegacyTableRenamer;
use OCA\ProjectCheck\Migration\ProjectCheckSchemaEnsurer;
use PHPUnit\Framework\TestCase;

class PcCoreSchemaBootstrapTest extends TestCase
{
	public function testRequiredTablesCoverRenamedCore(): void
	{
		$renamed = array_values(LegacyTableRenamer::RENAMES);
		foreach (['pc_customers', 'pc_projects', 'pc_project_members', 'pc_time_entries'] as $required) {
			self::assertContains($required, $renamed);
			self::assertContains($required, ProjectCheckSchemaEnsurer::REQUIRED_TABLES);
		}
	}
}
