<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Controller;

use PHPUnit\Framework\TestCase;

/**
 * Family rule: org/team assignment surfaces never offer deactivated
 * Nextcloud accounts. The member search filters them; the bulk-add
 * enumeration skips them.
 */
final class DisabledAccountGuardContractTest extends TestCase
{
	private function src(string $rel): string
	{
		return (string)file_get_contents(dirname(__DIR__, 3) . '/' . $rel);
	}

	public function testOrgUserSearchSkipsDeactivatedAccounts(): void
	{
		$src = $this->src('lib/Controller/AppConfigController.php');
		$this->assertMatchesRegularExpression(
			'/array_merge\(\$byId, \$byName\)[\s\S]{0,1200}if \(!\$user->isEnabled\(\)\)/',
			$src,
			'AppConfigController::searchUsers must skip deactivated accounts'
		);
	}

	public function testBulkMemberAddSkipsDeactivatedAccounts(): void
	{
		$src = $this->src('lib/Controller/ProjectController.php');
		$this->assertMatchesRegularExpression(
			"/if \(method_exists\(\\\$candidate, 'isEnabled'\) && !\\\$candidate->isEnabled\(\)\)/",
			$src,
			'ProjectController bulk-add must skip deactivated accounts'
		);
	}
}
