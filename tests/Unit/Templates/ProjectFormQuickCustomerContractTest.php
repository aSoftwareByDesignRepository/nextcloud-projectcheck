<?php

declare(strict_types=1);

/**
 * Contract: project form has exactly one primary Save; quick-add stays secondary.
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
		self::assertStringContainsString('pc-quick-customer-next', $tpl);
		self::assertStringContainsString('pc-quick-customer-goto-save', $tpl);
		self::assertStringContainsString('pc-project-save', $tpl);
		self::assertStringContainsString('canCreateCustomer', $tpl);
		self::assertStringContainsString('customerStoreUrl', $tpl);
		self::assertStringContainsString('Not in the list?', $tpl);
		self::assertStringContainsString('Add to list', $tpl);
		// Quick-add must NOT steal primary styling from Save
		self::assertDoesNotMatchRegularExpression(
			'/id="pc-quick-customer-create"[^>]*\bprimary\b|class="[^"]*primary[^"]*"[^>]*id="pc-quick-customer-create"/',
			$tpl
		);
		// Exactly one primary submit on the form
		self::assertSame(1, preg_match_all('/type="submit"/', $tpl));
		self::assertStringContainsString('id="pc-project-save"', $tpl);
		self::assertStringContainsString('button primary pc-form-actions__save', $tpl);
		self::assertStringNotContainsString('pc-quick-customer-save', $tpl);
		self::assertStringNotContainsString('button primary pc-form-callout__cta', $tpl);
		self::assertStringContainsString('One button saves everything on this page.', $tpl);
	}

	public function testProjectFormJsPostsWithoutNavigation(): void
	{
		$js = self::read('js/project-form.js');
		self::assertStringContainsString('initializeQuickCustomer', $js);
		self::assertStringContainsString('pc-quick-customer-create', $js);
		self::assertStringContainsString('selectCustomerOption', $js);
		self::assertStringContainsString('showSaveNextStep', $js);
		self::assertStringContainsString('focusPrimarySave', $js);
		self::assertStringContainsString('pc-quick-customer-goto-save', $js);
		self::assertStringContainsString('creating', $js);
		self::assertStringContainsString('submitting', $js);
		self::assertStringNotContainsString('window.location.href', $js);
		self::assertStringContainsString('Save the project to finish.', $js);
		self::assertStringContainsString('normalizeCapacityFieldsForSubmit', $js);
		self::assertStringNotContainsString('pc-quick-customer-save', $js);
	}

	public function testCreateAndEditPassQuickCustomerParams(): void
	{
		$ctrl = self::read('lib/Controller/ProjectController.php');
		self::assertSame(2, substr_count($ctrl, "'canCreateCustomer'"));
		self::assertSame(2, substr_count($ctrl, "'customerStoreUrl'"));
		self::assertStringContainsString('canUserCreateCustomer', $ctrl);
	}

	public function testSinglePrimarySaveHierarchy(): void
	{
		$tpl = self::read('templates/project-form.php');
		$primaryButtons = preg_match_all('/class="[^"]*\bprimary\b[^"]*"/', $tpl);
		self::assertSame(1, $primaryButtons, 'Only the footer Save may use primary styling');
		self::assertStringContainsString('pc-form-actions--sticky', $tpl);
		self::assertStringContainsString('pc-form-tip', $tpl);
	}

	public function testCreateFormIncludesIdempotencyNonceField(): void
	{
		$tpl = self::read('templates/project-form.php');
		self::assertStringContainsString('name="pc_form_nonce"', $tpl);
		self::assertStringContainsString('createIdempotencyNonce', $tpl);
		$ctrl = self::read('lib/Controller/ProjectController.php');
		self::assertStringContainsString("'createIdempotencyNonce'", $ctrl);
		self::assertStringContainsString('mintNonce()', $ctrl);
		self::assertStringContainsString('rememberCreate(', $ctrl);
	}

	public function testCreateFormCollapsesAdvancedSectionsByDefault(): void
	{
		$tpl = self::read('templates/project-form.php');
		self::assertStringContainsString('id="pc-advanced-classification"', $tpl);
		self::assertStringContainsString('id="pc-advanced-pricing"', $tpl);
		self::assertStringContainsString('id="pc-advanced-budget"', $tpl);
		self::assertStringContainsString('id="pc-advanced-schedule"', $tpl);
		self::assertStringContainsString('pc-advanced-details', $tpl);
		self::assertStringContainsString('More about this project', $tpl);
		self::assertStringContainsString('Leave blank to use the project name.', $tpl);
		// short_description must not be HTML-required (auto-filled from name)
		self::assertDoesNotMatchRegularExpression(
			'/<textarea[^>]*id="short_description"[^>]*\brequired\b/',
			$tpl
		);
		self::assertStringContainsString('fillShortDescriptionFromName', self::read('js/project-form.js'));
		self::assertStringContainsString('pc-form-team-link', $tpl);
		self::assertStringNotContainsString('pc-form-callout__title', $tpl);
	}
}
