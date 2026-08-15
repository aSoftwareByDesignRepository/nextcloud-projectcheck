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
			'pt_BR' => ['pt_BR', 'negado'],
			'pt-BR' => ['pt-BR', 'negado'],
		];
	}

	public function testBuildForAppPtBrKeepsSprintfAndProductNames(): void
	{
		$catalog = $this->buildCatalogForLanguage('pt_BR');
		$this->assertNotEmpty($catalog);

		$created = 'Project "%s" was created successfully!';
		$this->assertArrayHasKey($created, $catalog);
		$this->assertStringContainsString('%s', $catalog[$created]);
		$this->assertDoesNotMatchRegularExpression('/\{|\}/', $catalog[$created]);

		$seats = '%1$s of %2$s seats used';
		$this->assertArrayHasKey($seats, $catalog);
		$this->assertSame(
			['%1$s', '%2$s'],
			$this->printfPlaceholders($catalog[$seats]),
			'Ordered printf placeholders must survive pt_BR translation',
		);

		$this->assertArrayHasKey('ProjectCheck', $catalog);
		$this->assertSame('ProjectCheck', $catalog['ProjectCheck'], 'Product name must not be machine-translated');
	}

	/**
	 * @return list<string>
	 */
	private function printfPlaceholders(string $s): array
	{
		preg_match_all('/%%|%(?:\d+\$)?[sd]/', $s, $m);

		return $m[0];
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
		$this->assertFileExists($appPath . '/l10n/pt_BR.json');

		/** @var IAppManager&MockObject $appManager */
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->with(JsL10nCatalogBuilder::APP_ID)->willReturn($appPath);

		/** @var IFactory&MockObject $factory */
		$factory = $this->createMock(IFactory::class);
		$factory->method('findLanguage')->with(JsL10nCatalogBuilder::APP_ID)->willReturn($lang);

		return (new JsL10nCatalogBuilder($factory, $appManager))->buildForApp();
	}
}
