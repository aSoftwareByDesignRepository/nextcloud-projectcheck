<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;

/**
 * Portfolio §2.1 — dedicated App Admin OR-semantics.
 */
final class DedicatedAppAdminContractTest extends TestCase
{
	public function testIsAppAdminIsSystemAdminOrListedUid(): void
	{
		$src = (string) file_get_contents(dirname(__DIR__, 3) . '/lib/Service/AccessControlService.php');
		$this->assertStringContainsString('function isAppAdmin(string $userId): bool', $src);
		$this->assertStringContainsString('function canManageAppConfiguration(string $userId): bool', $src);
		$start = strpos($src, 'public function canManageAppConfiguration(string $userId): bool');
		$this->assertNotFalse($start);
		$end = strpos($src, 'public function canManageAppConfigurationByUser', $start);
		$this->assertNotFalse($end);
		$body = substr($src, $start, $end - $start);
		$this->assertStringContainsString('isSystemAdministrator($userId)', $body);
		$this->assertStringContainsString('getAppAdminUserIds()', $body);
		$this->assertStringNotContainsString('&&', $body);
		$aliasStart = strpos($src, 'public function isAppAdmin(string $userId): bool');
		$this->assertNotFalse($aliasStart);
		$aliasEnd = strpos($src, 'public function canManageAppConfigurationByUser', $aliasStart);
		$this->assertNotFalse($aliasEnd);
		$aliasBody = substr($src, $aliasStart, $aliasEnd - $aliasStart);
		$this->assertStringContainsString('return $this->canManageAppConfiguration($userId);', $aliasBody);
	}

	public function testOrgSettingsHideManualEntryWhenSearchAvailable(): void
	{
		$access = (string) file_get_contents(dirname(__DIR__, 3) . '/templates/parts/settings/_access-fields.php');
		$admins = (string) file_get_contents(dirname(__DIR__, 3) . '/templates/parts/settings/_admins-fields.php');
		$form = $access . $admins;
		$this->assertStringContainsString('Never type a raw user id', $form);
		$this->assertStringNotContainsString('Manual entry:', $form);
		$this->assertStringContainsString('Directory search is unavailable', $form);
	}
}
