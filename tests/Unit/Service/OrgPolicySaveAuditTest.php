<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Tests\Unit\Service;

use OCA\ProjectCheck\Service\OrgPolicySaveAudit;
use PHPUnit\Framework\TestCase;

class OrgPolicySaveAuditTest extends TestCase
{
	public function testBuildDetectsListChangesWithoutLoggingContents(): void
	{
		$before = [
			'restrictionEnabled' => false,
			'allowedUserIds' => [ 'a' ],
			'allowedGroupIds' => [ 'g1' ],
			'appAdminUserIds' => [ 'adm' ],
		];
		$after = [
			'restrictionEnabled' => true,
			'allowedUserIds' => [ 'a', 'b' ],
			'allowedGroupIds' => [ 'g1' ],
			'appAdminUserIds' => [ 'adm' ],
		];
		$db = [ 'k' => '1' ];
		$out = OrgPolicySaveAudit::build($before, $after, $db, $db, 'actor1');
		$this->assertSame('org_policy_changed', $out['event']);
		$this->assertSame('actor1', $out['actor']);
		$flags = $out['flags'];
		$this->assertTrue($flags['restriction_toggled']);
		$this->assertTrue($flags['allowed_users_set_changed']);
		$this->assertFalse($flags['allowed_groups_set_changed']);
		$this->assertFalse($flags['app_admins_set_changed']);
		$this->assertFalse($flags['app_defaults_changed']);
		$this->assertSame(1, $flags['count_allowed_users_before']);
		$this->assertSame(2, $flags['count_allowed_users_after']);
	}

	public function testDuplicateIdsFingerprintMatches(): void
	{
		$a = OrgPolicySaveAudit::fingerprintList([ 'x', 'y' ]);
		$b = OrgPolicySaveAudit::fingerprintList([ 'y', 'x', 'x' ]);
		$this->assertSame($a, $b);
	}
}
