<?php
/** Synced from _shared/agent-auth — do not edit app-side. */

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Agent;

use OCA\ProjectCheck\Agent\AgentManifest;
use PHPUnit\Framework\TestCase;

class AgentManifestTest extends TestCase
{
	private string $tmpFile;
	private AgentManifest $manifest;

	protected function setUp(): void
	{
		$this->tmpFile = (string)tempnam(sys_get_temp_dir(), 'agent-manifest-');
		file_put_contents($this->tmpFile, json_encode([
			'schemaVersion' => 1,
			'appId' => 'projectcheck',
			'agentExposure' => 'declared',
			'tools' => [
				[
					'name' => 'pc_list_things',
					'kind' => 'read',
					'riskClass' => 0,
					'route' => 'mobile#things',
					'method' => 'GET',
					'path' => '/mobile/v1/things',
					'scope' => 'projectcheck:things:read',
					'description' => 'List things. More detail here.',
				],
				[
					'name' => 'pc_make_thing',
					'kind' => 'write',
					'riskClass' => 1,
					'confirm' => 'required',
					'route' => 'mobile#createThing',
					'method' => 'POST',
					'path' => '/mobile/v1/things',
					'idempotency' => 'clientRequestId',
					'scope' => 'projectcheck:things:write',
					'label' => 'Create a thing',
					'description' => 'Create one thing.',
				],
				[
					'name' => 'pc_send_invoice',
					'kind' => 'write',
					'riskClass' => 3,
					'confirm' => 'required',
					'batch' => 'forbidden',
					'route' => 'mobile#sendInvoice',
					'method' => 'POST',
					'path' => '/mobile/v1/invoices/{id}/send',
					'idempotency' => 'clientRequestId',
					'scope' => 'projectcheck:invoices:write',
					'description' => 'Send an invoice.',
				],
			],
		]));
		$this->manifest = new AgentManifest('projectcheck', $this->tmpFile);
	}

	protected function tearDown(): void
	{
		@unlink($this->tmpFile);
	}

	public function testToolForMatchesExactAndTemplated(): void
	{
		$tool = $this->manifest->toolFor('GET', '/mobile/v1/things');
		$this->assertSame('pc_list_things', $tool['name']);

		$send = $this->manifest->toolFor('POST', '/mobile/v1/invoices/17/send');
		$this->assertSame('pc_send_invoice', $send['name']);
	}

	public function testToolForDoesNotMatchOtherMethodOrPath(): void
	{
		$this->assertNull($this->manifest->toolFor('DELETE', '/mobile/v1/things'));
		$this->assertNull($this->manifest->toolFor('GET', '/mobile/v1/other'));
		$this->assertNull($this->manifest->toolFor('GET', '/mobile/v1/things/extra/path'));
		$this->assertNull($this->manifest->toolFor('POST', '/mobile/v1/invoices/17/send/again'));
	}

	public function testScopeSetIsTheGrantCeiling(): void
	{
		$this->assertSame(
			['projectcheck:things:read', 'projectcheck:things:write', 'projectcheck:invoices:write'],
			$this->manifest->scopeSet(),
		);
	}

	public function testPresetScopesExcludeMoneyLegal(): void
	{
		$this->assertSame(['projectcheck:things:read'], $this->manifest->scopesForPreset('read'));
		$act = $this->manifest->scopesForPreset('act');
		$this->assertContains('projectcheck:things:read', $act);
		$this->assertContains('projectcheck:things:write', $act);
		$this->assertNotContains('projectcheck:invoices:write', $act, 'R3 money/legal must never be in the lazy preset');
	}

	public function testRequiresConfirmFromRiskClassOrFlag(): void
	{
		$send = $this->manifest->toolFor('POST', '/mobile/v1/invoices/9/send');
		$this->assertTrue($this->manifest->requiresConfirm($send));

		$make = $this->manifest->toolFor('POST', '/mobile/v1/things');
		$this->assertTrue($this->manifest->requiresConfirm($make));

		$list = $this->manifest->toolFor('GET', '/mobile/v1/things');
		$this->assertFalse($this->manifest->requiresConfirm($list));
	}

	public function testIsMutatingHonoursVerbs(): void
	{
		$this->assertTrue($this->manifest->isMutating($this->manifest->toolFor('POST', '/mobile/v1/things')));
		$this->assertFalse($this->manifest->isMutating($this->manifest->toolFor('GET', '/mobile/v1/things')));
	}

	public function testServedEnvelopeCarriesManifestVerbatim(): void
	{
		$env = $this->manifest->servedEnvelope('2.1.0');
		$this->assertSame(1, $env['schemaVersion']);
		$this->assertSame('projectcheck', $env['appId']);
		$this->assertSame('2.1.0', $env['appVersion']);
		$this->assertSame('X-ProjectCheck-Agent', $env['agentHeader']);
		$this->assertSame('declared', $env['manifest']['agentExposure']);
		$this->assertCount(3, $env['manifest']['tools']);
	}

	public function testMissingFileMeansUnavailableNotCrash(): void
	{
		$missing = new AgentManifest('projectcheck', '/nonexistent/agent-manifest.json');
		$this->assertFalse($missing->isAvailable());
		$this->assertFalse($missing->isDeclared());
		$this->assertSame([], $missing->scopeSet());
		$this->assertNull($missing->toolFor('GET', '/mobile/v1/things'));
	}

	public function testScopeLabelsPreferShortLabel(): void
	{
		$labels = $this->manifest->scopeLabels();
		$this->assertSame('Create a thing', $labels['projectcheck:things:write']);
		$this->assertSame('List things', $labels['projectcheck:things:read']);
	}
}
