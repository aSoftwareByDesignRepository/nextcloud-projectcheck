<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Service;

use OCA\ProjectCheck\Service\SettingsSectionCatalog;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit tests for the settings sub-page catalog — the single source of
 * truth for routes, controller validation, template dispatch, and the legacy
 * anchor forwarding.
 */
final class SettingsSectionCatalogTest extends TestCase
{
	private SettingsSectionCatalog $catalog;

	protected function setUp(): void
	{
		parent::setUp();
		$this->catalog = new SettingsSectionCatalog();
	}

	private function l10n(): IL10N
	{
		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnCallback(
			static fn (string $text, $parameters = []): string => 'T:' . $text,
		);
		return $l;
	}

	public function testDefaultSectionIsAccessAndListed(): void
	{
		self::assertSame('access', SettingsSectionCatalog::DEFAULT_SECTION);
		self::assertContains(SettingsSectionCatalog::DEFAULT_SECTION, SettingsSectionCatalog::SECTIONS);
	}

	public function testSectionsAreUniqueLowercaseSlugs(): void
	{
		$sections = SettingsSectionCatalog::SECTIONS;
		self::assertSame($sections, array_values(array_unique($sections)), 'Section slugs must be unique');
		self::assertCount(5, $sections);
		foreach ($sections as $section) {
			self::assertMatchesRegularExpression(
				'/^[a-z]+(-[a-z]+)*$/',
				$section,
				"Slug '{$section}' must be lowercase kebab-case (URL- and regex-safe)",
			);
		}
	}

	public function testIsSectionAcceptsEveryCatalogSlug(): void
	{
		foreach (SettingsSectionCatalog::SECTIONS as $section) {
			self::assertTrue($this->catalog->isSection($section), "isSection('{$section}') must be true");
		}
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function rejectedSectionProvider(): array
	{
		return [
			'empty string' => [''],
			'unknown slug' => ['nonsense'],
			'case variant' => ['Access'],
			'trailing whitespace' => ['access '],
			'leading whitespace' => [' access'],
			'path traversal' => ['../access'],
			'alternation injection' => ['access|admins'],
			'null byte' => ["access\0"],
			'legacy anchor id' => ['pc-access-heading'],
		];
	}

	/**
	 * @dataProvider rejectedSectionProvider
	 */
	public function testIsSectionRejectsInvalidInput(string $candidate): void
	{
		self::assertFalse($this->catalog->isSection($candidate));
	}

	public function testRouteRequirementIsPipeJoinedAllowlist(): void
	{
		$requirement = SettingsSectionCatalog::routeRequirement();
		self::assertSame(implode('|', SettingsSectionCatalog::SECTIONS), $requirement);
		self::assertMatchesRegularExpression('/^[a-z-]+(\|[a-z-]+)*$/', $requirement);
		foreach (SettingsSectionCatalog::SECTIONS as $section) {
			self::assertSame(
				1,
				preg_match('/^(?:' . $requirement . ')$/', $section),
				"Requirement regex must accept '{$section}'",
			);
		}
		self::assertSame(0, preg_match('/^(?:' . $requirement . ')$/', 'not-a-section'));
	}

	public function testEveryLegacyAnchorMapsToAKnownSection(): void
	{
		self::assertNotSame([], SettingsSectionCatalog::LEGACY_ANCHORS);
		foreach (SettingsSectionCatalog::LEGACY_ANCHORS as $anchor => $section) {
			self::assertTrue(
				$this->catalog->isSection($section),
				"Legacy anchor '{$anchor}' targets unknown section '{$section}'",
			);
		}
	}

	public function testEverySectionIsReachableFromALegacyAnchor(): void
	{
		$targets = array_values(array_unique(array_values(SettingsSectionCatalog::LEGACY_ANCHORS)));
		sort($targets);
		$sections = SettingsSectionCatalog::SECTIONS;
		sort($sections);
		self::assertSame($sections, $targets, 'Every section owns at least one legacy anchor');
	}

	public function testLabelsArePinnedAndTranslated(): void
	{
		$l = $this->l10n();
		$expected = [
			'access' => 'T:Access and visibility',
			'admins' => 'T:App administrators',
			'defaults' => 'T:App defaults',
			'license' => 'T:Mobile license',
			'support' => 'T:Support & us',
		];
		self::assertSame(array_keys($expected), SettingsSectionCatalog::SECTIONS, 'Label pinning must cover the catalog in order');
		foreach ($expected as $section => $label) {
			self::assertSame($label, $this->catalog->label($l, $section));
		}
	}

	public function testNavLabelsAreShortPinnedAndTranslated(): void
	{
		$l = $this->l10n();
		$expected = [
			'access' => 'T:Access',
			'admins' => 'T:App admins',
			'defaults' => 'T:Defaults',
			'license' => 'T:License',
			'support' => 'T:Support us',
		];
		self::assertSame(array_keys($expected), SettingsSectionCatalog::SECTIONS, 'Nav-label pinning must cover the catalog in order');
		foreach ($expected as $section => $label) {
			$nav = $this->catalog->navLabel($l, $section);
			self::assertSame($label, $nav);
			$page = $this->catalog->label($l, $section);
			self::assertLessThanOrEqual(
				strlen($page),
				strlen($nav),
				"navLabel('{$section}') must not be longer than label()",
			);
		}
		self::assertSame('T:Settings', $this->catalog->navLabel($l, 'nonsense'));
	}

	public function testLabelFallsBackToSettingsForUnknownSection(): void
	{
		self::assertSame('T:Settings', $this->catalog->label($this->l10n(), 'nonsense'));
		self::assertSame('T:Settings', $this->catalog->label($this->l10n(), ''));
	}

	public function testHelpTextsAreDistinctTranslatedAndSectionSpecific(): void
	{
		$l = $this->l10n();
		$fingerprints = [
			'access' => 'Decide who may open ProjectCheck',
			'admins' => 'open ProjectCheck settings',
			'defaults' => 'Default values for projects and budgets',
		];
		$seen = [];
		foreach ($fingerprints as $section => $fingerprint) {
			$help = $this->catalog->help($l, $section);
			self::assertStringStartsWith('T:', $help, "help('{$section}') must be translated");
			self::assertStringContainsString($fingerprint, $help, "help('{$section}') lost its section-specific copy");
			self::assertNotContains($help, $seen, "help('{$section}') duplicates another section's copy");
			$seen[] = $help;
		}
	}

	public function testHelpIsEmptyForSelfDescribingPanelsAndUnknown(): void
	{
		$l = $this->l10n();
		self::assertSame('', $this->catalog->help($l, 'license'), 'License panel ships its own intro');
		self::assertSame('', $this->catalog->help($l, 'support'), 'Support panel ships its own intro');
		self::assertSame('', $this->catalog->help($l, 'nonsense'));
	}
}
