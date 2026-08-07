<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * SHARED-IDENTITY AC-C-15 / NN-10 — reverse CRM discoverability; no PC→CUC write-back.
 */
final class SharedIdentityReverseLinkContractTest extends TestCase
{
	public function testCustomerDetailTemplateHasCrmLinkSurface(): void
	{
		$html = (string)file_get_contents(dirname(__DIR__, 2) . '/templates/customer-detail.php');
		$this->assertStringContainsString('id="pc-crm-link"', $html);
		$this->assertStringContainsString('pc-crm-link-title', $html);
		$this->assertStringContainsString('Open CRM company', $html);
		$this->assertStringContainsString('min-height:44px', $html);
		$this->assertStringContainsString("\$crmState === 'linked'", $html);
		$this->assertStringContainsString('sibling_unavailable', $html);
	}

	public function testCustomerControllerBuildsCrmLinkPayload(): void
	{
		$src = (string)file_get_contents(dirname(__DIR__, 2) . '/lib/Controller/CustomerController.php');
		$this->assertStringContainsString('CrmCompanyLinkReadFacade', $src);
		$this->assertStringContainsString("'crmLink'", $src);
		$this->assertStringContainsString("'state' => 'linked'", $src);
		$this->assertStringContainsString('sibling_unavailable', $src);
	}

	public function testCustomerServiceDoesNotWriteBackToCustomerCheck(): void
	{
		$src = (string)file_get_contents(dirname(__DIR__, 2) . '/lib/Service/CustomerService.php');
		$this->assertStringNotContainsString('CustomerCheck', $src);
		$this->assertStringNotContainsString('CrmCompany', $src);
		$this->assertStringNotContainsString('OCA\\CustomerCheck', $src);
		$this->assertStringNotContainsString('updateDisplayName', $src);
	}
}
