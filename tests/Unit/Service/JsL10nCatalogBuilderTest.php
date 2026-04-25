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
		// From tests/Unit/Service this is the projectcheck app root
		$appPath = dirname(__DIR__, 3);
		$this->assertFileExists($appPath . '/l10n/en.json');

		/** @var IAppManager&MockObject $appManager */
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->with(JsL10nCatalogBuilder::APP_ID)->willReturn($appPath);

		/** @var IFactory&MockObject $factory */
		$factory = $this->createMock(IFactory::class);
		$factory->method('findLanguage')->with(JsL10nCatalogBuilder::APP_ID)->willReturn('de');

		$builder = new JsL10nCatalogBuilder($factory, $appManager);
		$catalog = $builder->buildForApp();

		$this->assertNotEmpty($catalog);
		$sampleKey = 'Project "%s" was created successfully!';
		$this->assertArrayHasKey($sampleKey, $catalog);
		$this->assertStringContainsString('%s', $catalog[$sampleKey], 'Client catalog must keep sprintf placeholders in the template');
	}
}
