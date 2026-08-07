<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Tests\Unit\Service;

use OCA\ProjectCheck\Service\JsL10nCatalogBuilder;
use OCP\App\IAppManager;
use OCP\L10N\IFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class JsL10nCatalogBuilderTest extends TestCase
{
	public function testBuildForAppIncludesSprintfTemplatesWithoutThrowing(): void
	{
		$catalog = $this->buildCatalogForLanguage('de');
		$this->assertNotEmpty($catalog);
		$sampleKey = 'Project "%s" was created successfully!';
		$this->assertArrayHasKey($sampleKey, $catalog);
		$this->assertStringContainsString('%s', $catalog[$sampleKey], 'Client catalog must keep sprintf placeholders in the template');
	}

	/**
	 * @dataProvider localeJsonFileProvider
	 */
	public function testBuildForAppResolvesRegionalLocaleToJsonFile(string $nextcloudLang, string $expectedSubstring): void
	{
		$catalog = $this->buildCatalogForLanguage($nextcloudLang);
		$this->assertNotEmpty($catalog);
		$this->assertArrayHasKey('Access denied', $catalog);
		$this->assertStringContainsString(
			$expectedSubstring,
			$catalog['Access denied'],
			"Locale {$nextcloudLang} should load {$expectedSubstring} catalog",
		);
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function localeJsonFileProvider(): array
	{
		return [
			'de_DE' => ['de_DE', 'Zugriff'],
			'fr_FR' => ['fr_FR', 'refusé'],
			'es_ES' => ['es_ES', 'denegado'],
		];
	}

	/**
	 * @return array<string, string>
	 */
	private function buildCatalogForLanguage(string $lang): array
	{
		$appPath = dirname(__DIR__, 3);
		$this->assertFileExists($appPath . '/l10n/en.json');
		$this->assertFileExists($appPath . '/l10n/fr.json');
		$this->assertFileExists($appPath . '/l10n/es.json');

		/** @var IAppManager&MockObject $appManager */
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->with(JsL10nCatalogBuilder::APP_ID)->willReturn($appPath);

		/** @var IFactory&MockObject $factory */
		$factory = $this->createMock(IFactory::class);
		$factory->method('findLanguage')->with(JsL10nCatalogBuilder::APP_ID)->willReturn($lang);

		return (new JsL10nCatalogBuilder($factory, $appManager))->buildForApp();
	}
}
