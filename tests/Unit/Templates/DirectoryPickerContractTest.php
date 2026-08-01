<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;

/**
 * Portfolio rule: org settings never expose Manual entry for raw UIDs/GIDs.
 * Directory pickers only; when search URLs are missing, fail closed (read-only chips).
 */
final class DirectoryPickerContractTest extends TestCase
{
	private string $form;

	protected function setUp(): void
	{
		parent::setUp();
		$root = dirname(__DIR__, 3) . '/templates/parts';
		$this->form = (string) file_get_contents($root . '/org-settings-form.php')
			. (string) file_get_contents($root . '/settings/_access-fields.php')
			. (string) file_get_contents($root . '/settings/_admins-fields.php');
	}

	public function testManualEntryIsRemovedEntirely(): void
	{
		$this->assertStringNotContainsString('Manual entry:', $this->form);
		$this->assertStringNotContainsString('projectcheck-entity-picker__manual', $this->form);
		$this->assertStringNotContainsString('one user ID per line', $this->form);
		$this->assertStringNotContainsString('one group ID per line', $this->form);
		$this->assertStringNotContainsString('Type one account login', $this->form);
	}

	public function testUnavailableSearchShowsFailClosedMessage(): void
	{
		$this->assertStringContainsString('Directory search is unavailable', $this->form);
		$this->assertStringContainsString('Never type a raw user id', $this->form);
		$this->assertStringContainsString('Never type a raw group id', $this->form);
	}

	public function testValuesStayInHiddenTextareas(): void
	{
		$this->assertStringContainsString('name="access_allowed_user_ids"', $this->form);
		$this->assertStringContainsString('name="access_allowed_group_ids"', $this->form);
		$this->assertStringContainsString('name="app_admin_user_ids"', $this->form);
		$this->assertGreaterThanOrEqual(3, substr_count($this->form, 'visually-hidden'));
		$this->assertStringContainsString('aria-hidden="true"', $this->form);
	}

	public function testEntityPickersExposeComboboxWhenSearchAvailable(): void
	{
		foreach (['pc_allowed_users_q', 'pc_allowed_groups_q', 'pc_app_admins_q'] as $id) {
			$this->assertStringContainsString('id="' . $id . '"', $this->form);
		}
		$this->assertStringContainsString('role="combobox"', $this->form);
		$this->assertStringContainsString('aria-autocomplete="list"', $this->form);
	}

	public function testSavePolicyUiStringsBagForbidsManualEntryCopy(): void
	{
		$src = (string) file_get_contents(dirname(__DIR__, 3) . '/lib/Service/SavePolicyUiStrings.php');
		$this->assertStringNotContainsString('use manual entry below', $src);
		$this->assertStringNotContainsString('Manual entry: one user ID per line', $src);
		$this->assertStringNotContainsString('manualUserIds', $src);
		$this->assertStringContainsString('never type a raw user or group id', $src);
	}
}
