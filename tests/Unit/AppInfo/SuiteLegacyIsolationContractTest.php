<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\AppInfo;

use PHPUnit\Framework\TestCase;

/**
 * Suite legacy isolation (CHECK-SUITE L1): ProjectCheck must remain usable
 * without CustomerCheck / InvoiceCheck / InventoryCheck / MaintenanceCheck.
 * Soft deep-links and facades for siblings are allowed; hard deps are not.
 *
 * @see planning/check-productivity-suite/LEGACY-SAFETY.md
 */
final class SuiteLegacyIsolationContractTest extends TestCase
{
	private const FORBIDDEN_HARD_DEPS = [
		'customercheck',
		'invoicecheck',
		'inventorycheck',
		'maintenancecheck',
	];

	private string $infoXml;
	private string $root;

	protected function setUp(): void
	{
		parent::setUp();
		$this->root = dirname(__DIR__, 3);
		$path = $this->root . '/appinfo/info.xml';
		$this->assertFileExists($path);
		$this->infoXml = (string)file_get_contents($path);
		$this->assertNotSame('', trim($this->infoXml));
	}

	public function testInfoXmlDeclaresProjectcheckId(): void
	{
		$this->assertMatchesRegularExpression('/<id>\s*projectcheck\s*<\/id>/', $this->infoXml);
	}

	public function testHardDependenciesDoNotRequireSuiteSpineApps(): void
	{
		$hardBlock = $this->dependenciesInnerXml('dependencies');
		foreach (self::FORBIDDEN_HARD_DEPS as $appId) {
			$this->assertDoesNotMatchRegularExpression(
				'/<app\b[^>]*>\s*' . preg_quote($appId, '/') . '\s*<\/app>/i',
				$hardBlock,
				"Hard <dependencies> must not require {$appId} (suite L1)"
			);
		}
	}

	public function testSettlementFacadesExistWithoutRequiringInvoiceCheckInstall(): void
	{
		$this->assertFileExists($this->root . '/lib/Public/SettlementReadFacade.php');
		$this->assertFileExists($this->root . '/lib/Public/SettlementWriteFacade.php');
	}

	public function testCrmCustomerReadFacadeExistsForOptionalCustomerCheck(): void
	{
		$this->assertFileExists($this->root . '/lib/Facade/CrmCustomerReadFacade.php');
		$src = (string)file_get_contents($this->root . '/lib/Facade/CrmCustomerReadFacade.php');
		$this->assertStringContainsString('FACADE_VERSION', $src);
		$this->assertStringNotContainsString('use OCA\\CustomerCheck\\', $src);
	}

	public function testProjectDetailInvoiceDeepLinkIsSoftGuarded(): void
	{
		$src = (string)file_get_contents($this->root . '/lib/Controller/ProjectController.php');
		$this->assertStringContainsString("isEnabledForUser('invoicecheck')", $src);
		$this->assertStringContainsString('catch (\\Throwable)', $src);
		$this->assertStringContainsString('$invoicingCheckCreateUrl = null', $src);
	}

	public function testPhpSourcesDoNotStaticallyUseForbiddenSuiteNamespaces(): void
	{
		$hits = $this->scanPhpForForbiddenUse($this->root . '/lib', [
			'OCA\\CustomerCheck\\',
			'OCA\\InvoiceCheck\\',
			'OCA\\InventoryCheck\\',
			'OCA\\MaintenanceCheck\\',
		]);
		$this->assertSame([], $hits, implode("\n", $hits));
	}

	/**
	 * @param list<string> $forbidden
	 * @return list<string>
	 */
	private function scanPhpForForbiddenUse(string $root, array $forbidden): array
	{
		$hits = [];
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
		);
		/** @var \SplFileInfo $file */
		foreach ($iterator as $file) {
			if (!$file->isFile() || $file->getExtension() !== 'php') {
				continue;
			}
			$contents = (string)file_get_contents($file->getPathname());
			foreach ($forbidden as $ns) {
				if (str_contains($contents, 'use ' . $ns) || str_contains($contents, 'new ' . $ns)) {
					$hits[] = $file->getPathname() . ' → ' . $ns;
				}
			}
		}
		return $hits;
	}

	private function dependenciesInnerXml(string $tag): string
	{
		if (!preg_match(
			'/' . preg_quote('<' . $tag . '>', '/') . '(.*?)' . preg_quote('</' . $tag . '>', '/') . '/is',
			$this->infoXml,
			$m
		)) {
			return '';
		}
		return (string)$m[1];
	}
}
