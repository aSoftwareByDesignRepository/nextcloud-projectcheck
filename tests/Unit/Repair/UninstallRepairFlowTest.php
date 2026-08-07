<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Repair;

use OCA\ProjectCheck\Repair\UninstallRepairFlow;
use PHPUnit\Framework\TestCase;

final class UninstallRepairFlowTest extends TestCase
{
	public function testDetectsInstallerRemoveApp(): void
	{
		self::assertTrue(UninstallRepairFlow::isRemovalContext([
			['class' => 'OC\\Installer', 'function' => 'removeApp'],
		]));
	}

	public function testDetectsSettingsUninstallApp(): void
	{
		self::assertTrue(UninstallRepairFlow::isRemovalContext([
			['class' => 'OCA\\Settings\\Controller\\AppSettingsController', 'function' => 'uninstallApp'],
		]));
	}

	public function testIgnoresDisableApp(): void
	{
		self::assertFalse(UninstallRepairFlow::isRemovalContext([
			['class' => 'OC\\App\\AppManager', 'function' => 'disableApp'],
		]));
	}

	public function testIgnoresUpdaterAutoDisable(): void
	{
		self::assertFalse(UninstallRepairFlow::isRemovalContext([
			['class' => 'OC\\Updater', 'function' => 'checkAppsRequirements'],
			['class' => 'OC\\App\\AppManager', 'function' => 'disableApp'],
		]));
	}
}
