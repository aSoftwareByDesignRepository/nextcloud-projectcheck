<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Util;

use OCA\ProjectCheck\Util\ProjectMemberRole;
use PHPUnit\Framework\TestCase;

class ProjectMemberRoleTest extends TestCase
{
	public function testNormalizeUnknownDemotesToMember(): void
	{
		$this->assertSame(ProjectMemberRole::MEMBER, ProjectMemberRole::normalize(null));
		$this->assertSame(ProjectMemberRole::MEMBER, ProjectMemberRole::normalize(''));
		$this->assertSame(ProjectMemberRole::MEMBER, ProjectMemberRole::normalize('Admin'));
		$this->assertSame(ProjectMemberRole::MEMBER, ProjectMemberRole::normalize('superuser'));
		$this->assertSame(ProjectMemberRole::MANAGER, ProjectMemberRole::normalize('manager'));
		$this->assertSame(ProjectMemberRole::MANAGER, ProjectMemberRole::normalize('Manager'));
		$this->assertSame(ProjectMemberRole::MEMBER, ProjectMemberRole::normalize('Member'));
	}

	public function testIsManager(): void
	{
		$this->assertTrue(ProjectMemberRole::isManager('Manager'));
		$this->assertFalse(ProjectMemberRole::isManager('Member'));
		$this->assertFalse(ProjectMemberRole::isManager('Owner'));
	}
}
