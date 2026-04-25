<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Tests\Unit\Service;

use OCA\ProjectCheck\Service\AccessControlService;
use OCP\IConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AccessControlServiceTest extends TestCase
{
	/**
	 * @return array{0: IConfig&MockObject, 1: IGroupManager&MockObject, 2: IUserManager&MockObject, 3: LoggerInterface&MockObject}
	 */
	private function newMocks(): array
	{
		$config = $this->createMock(IConfig::class);
		$groupManager = $this->createMock(IGroupManager::class);
		$userManager = $this->createMock(IUserManager::class);
		$logger = $this->createMock(LoggerInterface::class);
		return [ $config, $groupManager, $userManager, $logger ];
	}

	private function service(IConfig $c, IGroupManager $g, IUserManager $u, LoggerInterface $l): AccessControlService
	{
		return new AccessControlService($c, $g, $u, $l);
	}

	public function testRestrictionOffAllowsRegularUser(): void
	{
		[ $config, $groupManager, $userManager, $logger ] = $this->newMocks();
		$config->method('getAppValue')->willReturnMap([
			[AccessControlService::APP_ID, AccessControlService::KEY_ACCESS_RESTRICTION, '0', '0'],
			[AccessControlService::APP_ID, AccessControlService::KEY_ACCESS_ALLOWED_USER_IDS, '[]', '[]'],
			[AccessControlService::APP_ID, AccessControlService::KEY_ACCESS_ALLOWED_GROUP_IDS, '[]', '[]'],
			[AccessControlService::APP_ID, AccessControlService::KEY_APP_ADMIN_USER_IDS, '[]', '[]'],
		]);
		$groupManager->method('isAdmin')->with('user1')->willReturn(false);
		$this->assertTrue(
			$this->service($config, $groupManager, $userManager, $logger)->canUseApp('user1')
		);
	}

	public function testSystemAdminAlwaysAllowedWithRestrictionOn(): void
	{
		[ $config, $groupManager, $userManager, $logger ] = $this->newMocks();
		$config->method('getAppValue')->willReturnMap([
			[AccessControlService::APP_ID, AccessControlService::KEY_ACCESS_RESTRICTION, '0', '1'],
			[AccessControlService::APP_ID, AccessControlService::KEY_ACCESS_ALLOWED_USER_IDS, '[]', '[]'],
			[AccessControlService::APP_ID, AccessControlService::KEY_ACCESS_ALLOWED_GROUP_IDS, '[]', '[]'],
			[AccessControlService::APP_ID, AccessControlService::KEY_APP_ADMIN_USER_IDS, '[]', '[]'],
		]);
		$groupManager->method('isAdmin')->willReturnMap([ [ 'admin1', true ] ]);
		$s = $this->service($config, $groupManager, $userManager, $logger);
		$this->assertTrue($s->canUseApp('admin1'));
		$this->assertTrue($s->isSystemAdministrator('admin1'));
	}

	public function testAllowlistUser(): void
	{
		[ $config, $groupManager, $userManager, $logger ] = $this->newMocks();
		$config->method('getAppValue')->willReturnMap([
			[AccessControlService::APP_ID, AccessControlService::KEY_ACCESS_RESTRICTION, '0', '1'],
			[AccessControlService::APP_ID, AccessControlService::KEY_ACCESS_ALLOWED_USER_IDS, '[]', '["u1","u2"]'],
			[AccessControlService::APP_ID, AccessControlService::KEY_ACCESS_ALLOWED_GROUP_IDS, '[]', '[]'],
			[AccessControlService::APP_ID, AccessControlService::KEY_APP_ADMIN_USER_IDS, '[]', '[]'],
		]);
		$groupManager->method('isAdmin')->willReturn(false);
		$s = $this->service($config, $groupManager, $userManager, $logger);
		$this->assertTrue($s->canUseApp('u1'));
		$this->assertFalse($s->canUseApp('unknown'));
	}

	public function testApplyFullPolicyRejectsEmptyWhenRestrictOn(): void
	{
		[ $config, $groupManager, $userManager, $logger ] = $this->newMocks();
		$config->method('getAppValue')->willReturn('0');
		$s = $this->service($config, $groupManager, $userManager, $logger);
		$this->expectException(\InvalidArgumentException::class);
		$s->applyFullAccessPolicy(true, [], [], []);
	}

	public function testRemoveUserIdFromAllLists(): void
	{
		[ $config, $groupManager, $userManager, $logger ] = $this->newMocks();
		$sets = [];
		$config
			->method('getAppValue')
			->willReturnCallback(function ($app, $key, $default) {
				if ($key === AccessControlService::KEY_ACCESS_ALLOWED_USER_IDS) {
					return '["a","b"]';
				}
				if ($key === AccessControlService::KEY_APP_ADMIN_USER_IDS) {
					return '["a","c"]';
				}
				return $default;
			});
		$config->method('setAppValue')->willReturnCallback(function ($app, $key, $value) use (&$sets) {
			$sets[$key] = $value;
		});
		$this->service($config, $groupManager, $userManager, $logger)->removeUserIdFromAllLists('a');
		$this->assertStringContainsString('"b"', (string)($sets[AccessControlService::KEY_ACCESS_ALLOWED_USER_IDS] ?? ''));
		$this->assertStringContainsString('"c"', (string)($sets[AccessControlService::KEY_APP_ADMIN_USER_IDS] ?? ''));
		$this->assertStringNotContainsString('a', (string)($sets[AccessControlService::KEY_ACCESS_ALLOWED_USER_IDS] ?? ''));
	}

	/** Duplicate IDs in the request are normalized to unique */
	public function testApplyFullDeduplicatesUserAndGroupAndAdmin(): void
	{
		[ $config, $groupManager, $userManager, $logger ] = $this->newMocks();
		$u1 = $this->createUserMock('alice');
		$u2 = $this->createUserMock('bob');
		$userManager->method('get')->willReturnMap([
			[ 'alice', $u1 ],
			[ 'bob', $u2 ],
		]);
		$g = $this->createMock(IGroup::class);
		$groupManager->method('get')->with('teamA')->willReturn($g);
		$captured = [];
		$config->method('getAppValue')->willReturn('0');
		$config->method('setAppValue')->willReturnCallback(
			static function (string $app, string $key, string $value) use (&$captured) {
				$captured[$key] = $value;
			}
		);
		$s = $this->service($config, $groupManager, $userManager, $logger);
		$s->applyFullAccessPolicy(true, [ 'alice', 'alice', 'bob' ], [ 'teamA', 'teamA' ], [ 'bob', 'bob' ]);
		$uJson = (string)($captured[AccessControlService::KEY_ACCESS_ALLOWED_USER_IDS] ?? '[]');
		$gJson = (string)($captured[AccessControlService::KEY_ACCESS_ALLOWED_GROUP_IDS] ?? '[]');
		$aJson = (string)($captured[AccessControlService::KEY_APP_ADMIN_USER_IDS] ?? '[]');
		$this->assertStringContainsString('alice', $uJson, $uJson);
		$this->assertStringContainsString('bob', $aJson, $aJson);
		$this->assertStringContainsString('teamA', $gJson, $gJson);
	}

	/** @return IUser&MockObject */
	private function createUserMock(string $id): IUser
	{
		$u = $this->createMock(IUser::class);
		$u->method('getUID')->willReturn($id);
		return $u;
	}
}
