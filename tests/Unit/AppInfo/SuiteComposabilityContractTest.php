<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\AppInfo;

use PHPUnit\Framework\TestCase;

/**
 * Composability doctrine (CHECK-SUITE D21): ProjectCheck is complete alone,
 * and exposes soft compose surfaces so InvoicingCheck / CustomerCheck add power.
 *
 * @see planning/check-productivity-suite/COMPOSABILITY.md
 */
final class SuiteComposabilityContractTest extends TestCase
{
	private string $root;

	protected function setUp(): void
	{
		parent::setUp();
		$this->root = dirname(__DIR__, 3);
	}

	public function testStandaloneCoreSurfacesExistWithoutSiblingApps(): void
	{
		foreach ([
			'/lib/Service/CustomerService.php',
			'/lib/Service/ProjectService.php',
			'/lib/Controller/TimeEntryController.php',
		] as $rel) {
			$this->assertFileExists($this->root . $rel, $rel . ' required for standalone PC');
		}
	}

	public function testComposeReadySettlementFacadesForInvoiceCheck(): void
	{
		$this->assertFileExists($this->root . '/lib/Public/SettlementReadFacade.php');
		$this->assertFileExists($this->root . '/lib/Public/SettlementWriteFacade.php');
		$write = (string)file_get_contents($this->root . '/lib/Public/SettlementWriteFacade.php');
		$this->assertStringContainsString('markEntriesInvoiced', $write);
		$this->assertStringContainsString('markEntriesPaid', $write);
		$this->assertStringContainsString('Server-side only', $write);
	}

	public function testComposeReadyCrmReadFacadeForCustomerCheck(): void
	{
		$path = $this->root . '/lib/Facade/CrmCustomerReadFacade.php';
		$this->assertFileExists($path);
		$src = (string)file_get_contents($path);
		$this->assertStringContainsString('function getCustomer', $src);
		$this->assertStringContainsString('function listProjects', $src);
		$this->assertStringContainsString('FACADE_VERSION = 2', $src);
		$this->assertStringContainsString('getStlOpenHours', $src);
		$this->assertStringNotContainsString("'openHours' => 0.0", $src);
		$this->assertStringNotContainsString('use OCA\\CustomerCheck\\', $src);
	}

	public function testInvoiceCheckDeepLinkIsCapabilityGatedNotHardWired(): void
	{
		$src = (string)file_get_contents($this->root . '/lib/Controller/ProjectController.php');
		$this->assertStringContainsString("isEnabledForUser('invoicecheck')", $src);
		$this->assertStringContainsString('catch (\\Throwable)', $src);
		$this->assertMatchesRegularExpression(
			'/\$invoicingCheckCreateUrl\s*=\s*null/',
			$src,
			'Deep link must default null when IC absent (standalone / degrade)'
		);
		$this->assertStringContainsString('invoicecheck.page.receivables', $src);
		$this->assertMatchesRegularExpression(
			'/\$invoicingCheckReceivablesUrl\s*=\s*null/',
			$src,
			'Receivables deep link must default null when IC absent'
		);
		$this->assertMatchesRegularExpression(
			'/\$invoicingCheckUnpaid\s*=\s*null/',
			$src,
			'Unpaid € strip must default null when IC absent'
		);
		$this->assertStringContainsString('summarizeUnpaidByPcProject', $src);
		$this->assertStringContainsString("array_key_exists('remainingCents'", $src);
		$tpl = (string)file_get_contents($this->root . '/templates/project-detail.php');
		$this->assertStringContainsString('invoicingCheckReceivablesUrl', $tpl);
		$this->assertStringContainsString('Open unpaid invoices (InvoicingCheck)', $tpl);
		$this->assertStringContainsString('invoicingCheckUnpaid', $tpl);
		$this->assertStringContainsString('pc-ic-unpaid-strip', $tpl);
		$this->assertStringContainsString('Unpaid invoices (InvoicingCheck):', $tpl);
	}

	public function testInfoXmlDoesNotHardRequireComposePartners(): void
	{
		$xml = (string)file_get_contents($this->root . '/appinfo/info.xml');
		$this->assertSame(1, preg_match('/<dependencies>(.*?)<\/dependencies>/is', $xml, $m));
		$hard = (string)($m[1] ?? '');
		foreach (['invoicecheck', 'customercheck', 'budgetcheck'] as $appId) {
			$this->assertDoesNotMatchRegularExpression(
				'/<app\b[^>]*>\s*' . preg_quote($appId, '/') . '\s*<\/app>/i',
				$hard,
				"Composability requires soft partners — {$appId} must not be a hard dependency"
			);
		}
	}
}
