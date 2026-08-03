<?php

declare(strict_types=1);

/**
 * Contract: project form exposes inline customer quick-add (no leave-page dead end).
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;

class ProjectFormQuickCustomerContractTest extends TestCase
{
	private static function read(string $relative): string
	{
		$path = dirname(__DIR__, 3) . '/' . $relative;
		self::assertFileExists($path);
		$content = file_get_contents($path);
		self::assertIsString($content);
		return $content;
	}

	public function testTemplateHasInlineQuickCustomerControls(): void
	{
		$tpl = self::read('templates/project-form.php');
		self::assertStringContainsString('pc-quick-customer', $tpl);
		self::assertStringContainsString('pc-quick-customer-name', $tpl);
		self::assertStringContainsString('pc-quick-customer-create', $tpl);
		self::assertStringContainsString('canCreateCustomer', $tpl);
		self::assertStringContainsString('customerStoreUrl', $tpl);
		self::assertStringContainsString('your project details stay filled in', $tpl);
	}

	public function testProjectFormJsPostsWithoutNavigation(): void
	{
		$js = self::read('js/project-form.js');
		self::assertStringContainsString('initializeQuickCustomer', $js);
		self::assertStringContainsString('pc-quick-customer-create', $js);
		self::assertStringContainsString('selectCustomerOption', $js);
		self::assertStringNotContainsString('window.location.href', $js);
		self::assertStringContainsString('Customer added and selected.', $js);
	}

	public function testCreateAndEditPassQuickCustomerParams(): void
	{
		$ctrl = self::read('lib/Controller/ProjectController.php');
		self::assertSame(2, substr_count($ctrl, "'canCreateCustomer'"));
		self::assertSame(2, substr_count($ctrl, "'customerStoreUrl'"));
		self::assertStringContainsString('canUserCreateCustomer', $ctrl);
	}
}
