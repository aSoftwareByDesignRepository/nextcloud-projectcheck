<?php
/** Synced from _shared/agent-auth — do not edit app-side. */

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Agent;

use OCA\ProjectCheck\Agent\AgentAuditMapper;
use OCA\ProjectCheck\Agent\AgentException;
use OCA\ProjectCheck\Agent\AgentManifest;
use OCA\ProjectCheck\Agent\AgentToken;
use OCA\ProjectCheck\Agent\AgentTokenMapper;
use OCA\ProjectCheck\Agent\AgentTokenService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Token lifecycle policy: wire format, hash-at-rest, uniform-401 resolve,
 * mint-first rotate, CAS mutation budget, serialization never leaking
 * secrets. The service takes $now explicitly — tests pin expiry math.
 */
class AgentTokenServiceTest extends TestCase
{
	private const NOW = 1_800_000_000;
	private const SCOPES = ['projectcheck:things:read', 'projectcheck:things:write'];

	private AgentTokenMapper&MockObject $tokens;
	private AgentAuditMapper&MockObject $audit;
	private AgentManifest $manifest;
	private AgentTokenService $service;
	private string $manifestFile;

	protected function setUp(): void
	{
		$this->tokens = $this->createMock(AgentTokenMapper::class);
		$this->audit = $this->createMock(AgentAuditMapper::class);
		$this->manifestFile = (string)tempnam(sys_get_temp_dir(), 'agent-ts-');
		file_put_contents($this->manifestFile, json_encode([
			'schemaVersion' => 1,
			'appId' => 'projectcheck',
			'agentExposure' => 'declared',
			'tools' => [
				['name' => 'a', 'kind' => 'read', 'riskClass' => 0, 'method' => 'GET',
				 'path' => '/x', 'scope' => 'projectcheck:things:read'],
				['name' => 'b', 'kind' => 'write', 'riskClass' => 1, 'method' => 'POST',
				 'path' => '/x', 'scope' => 'projectcheck:things:write'],
			],
		]));
		$this->manifest = new AgentManifest('projectcheck', $this->manifestFile);
		$this->service = new AgentTokenService($this->tokens, $this->audit, $this->manifest);
	}

	protected function tearDown(): void
	{
		@unlink($this->manifestFile);
	}

	public function testMintWireFormatAndHashOnlyStorage(): void
	{
		$this->tokens->method('findByUser')->willReturn([]);
		$this->tokens->expects($this->once())->method('insert')
			->with($this->callback(function (AgentToken $t): bool {
				$this->assertSame('alice', $t->getUserId());
				$this->assertSame('ci-bot', $t->getLabel());
				$this->assertSame(self::SCOPES, $t->scopeList());
				$this->assertSame(64, strlen($t->getTokenHash()));
				$this->assertSame(self::NOW + AgentTokenService::EXPIRY_SECONDS, $t->getExpiresAt());
				return true;
			}))
			->willReturnCallback(function (AgentToken $t) { $t->setId(1); return $t; });

		[$raw, $entity] = $this->service->mint('alice', 'ci-bot', self::SCOPES, self::NOW);
		$this->assertMatchesRegularExpression('/^ckst_projectcheck_[A-Za-z0-9_-]{32}$/', $raw);
		$this->assertSame(AgentTokenService::hashToken($raw), $entity->getTokenHash());
		$this->assertStringNotContainsString($raw, $entity->getTokenHash());
	}

	public function testMintRejectsUndeclaredScope(): void
	{
		$this->tokens->method('findByUser')->willReturn([]);
		$this->tokens->expects($this->never())->method('insert');
		try {
			$this->service->mint('alice', 'x', ['projectcheck:secret:read'], self::NOW);
			$this->fail('undeclared scope must throw');
		} catch (AgentException $e) {
			$this->assertSame('validation', $e->getErrorCode());
		}
	}

	public function testMintRejectsWildcardScope(): void
	{
		$this->tokens->method('findByUser')->willReturn([]);
		try {
			$this->service->mint('alice', 'x', ['projectcheck:*:read'], self::NOW);
			$this->fail('wildcard must throw');
		} catch (AgentException $e) {
			$this->assertSame('validation', $e->getErrorCode());
		}
	}

	public function testMintRejectsBlankAndOverlongLabel(): void
	{
		foreach (['', '   ', str_repeat('x', 191)] as $label) {
			try {
				$this->service->mint('alice', $label, self::SCOPES, self::NOW);
				$this->fail('label must be rejected: ' . var_export($label, true));
			} catch (AgentException $e) {
				$this->assertSame('validation', $e->getErrorCode());
			}
		}
	}

	public function testMintPerUserCap(): void
	{
		$this->tokens->method('findByUser')->willReturn(
			array_fill(0, AgentTokenService::MAX_TOKENS_PER_USER, new AgentToken())
		);
		try {
			$this->service->mint('alice', 'x', self::SCOPES, self::NOW);
			$this->fail('cap must throw');
		} catch (AgentException $e) {
			$this->assertSame('validation', $e->getErrorCode());
			$this->assertSame('limit_reached', $e->getDetails()['fields']['tokens']);
		}
	}

	public function testResolveCollapsesAllFailuresToOne401(): void
	{
		foreach (['', 'Bearer abc', 'not_a_token', 'ckst_wrongapp_xyz', str_repeat('x', 500)] as $raw) {
			try {
				$this->service->resolve($raw, self::NOW);
				$this->fail('must throw for: ' . var_export($raw, true));
			} catch (AgentException $e) {
				$this->assertSame('agent_token_invalid', $e->getErrorCode());
				$this->assertSame(401, $e->getHttpStatus());
			}
		}

		// revoked → same 401 as unknown (no oracle); expiry must be valid
		// so the revoked check itself is the one under test.
		$revoked = new AgentToken();
		$revoked->setRevokedAt(self::NOW - 10);
		$revoked->setExpiresAt(self::NOW + 3600);
		$this->tokens->method('findByHash')->willReturnOnConsecutiveCalls($revoked, null);
		foreach ([0, 1] as $i) {
			try {
				$this->service->resolve('ckst_projectcheck_' . str_repeat('ab', 16), self::NOW);
				$this->fail();
			} catch (AgentException $e) {
				$this->assertSame('agent_token_invalid', $e->getErrorCode());
			}
		}
	}

	public function testResolveMissingTableFailsClosed401(): void
	{
		// oc_*_agent_tokens can be absent in a disabled-but-enabled app
		// state or mid-upgrade — the lookup must collapse to the same
		// undifferentiated 401, never a 500.
		$this->tokens->method('findByHash')
			->willThrowException(new \OCP\DB\Exception('table missing'));
		try {
			$this->service->resolve('ckst_projectcheck_' . str_repeat('ab', 16), self::NOW);
			$this->fail('missing table must not resolve');
		} catch (AgentException $e) {
			$this->assertSame('agent_token_invalid', $e->getErrorCode());
			$this->assertSame(401, $e->getHttpStatus());
		}
	}

	public function testIsWireFormatIsStrictPrefix(): void
	{
		$this->assertTrue(AgentTokenService::isWireFormat('ckst_anything'));
		$this->assertFalse(AgentTokenService::isWireFormat(''));
		$this->assertFalse(AgentTokenService::isWireFormat('Bearer ckst_x'));
		$this->assertFalse(AgentTokenService::isWireFormat('xckst_abc'));
	}

	public function testResolveExpiredIs401(): void
	{
		$expired = new AgentToken();
		$expired->setExpiresAt(self::NOW - 1);
		$this->tokens->method('findByHash')->willReturn($expired);
		try {
			$this->service->resolve('ckst_projectcheck_' . str_repeat('ab', 16), self::NOW);
			$this->fail('expired must be invalid');
		} catch (AgentException $e) {
			$this->assertSame(401, $e->getHttpStatus());
		}
	}

	public function testRotateMintsBeforeRevokingOld(): void
	{
		$old = new AgentToken();
		$old->setId(9);
		$old->setUserId('alice');
		$old->setLabel('deploy-bot');
		$old->setScopes(implode(' ', self::SCOPES));
		$this->tokens->method('findByUser')->willReturn([$old]);

		$order = [];
		$this->tokens->method('insert')->willReturnCallback(function (AgentToken $t) use (&$order) {
			$order[] = 'insert';
			$t->setId(10);
			return $t;
		});
		$this->tokens->expects($this->once())->method('revoke')->with(9, self::NOW)
			->willReturnCallback(function () use (&$order) { $order[] = 'revoke'; });

		[$raw, $new] = $this->service->rotate($old, self::NOW);
		$this->assertSame(['insert', 'revoke'], $order, 'new token must exist before old dies');
		$this->assertSame('deploy-bot', $new->getLabel());
		$this->assertSame(self::SCOPES, $new->scopeList());
		$this->assertStringStartsWith('ckst_projectcheck_', $raw);
	}

	public function testMutationBudgetWithinLimit(): void
	{
		$this->tokens->method('consumeMutationSlot')->with(5, self::NOW, 60)->willReturn(60);
		$token = new AgentToken();
		$token->setId(5);
		$token->setRateWindowStart(self::NOW - 10);
		$this->assertNull($this->service->consumeMutationBudget($token, self::NOW));
	}

	public function testMutationBudgetBreachReturnsRetryAfter(): void
	{
		$this->tokens->method('consumeMutationSlot')->willReturn(61);
		$token = new AgentToken();
		$token->setId(6);
		$token->setRateWindowStart(self::NOW - 30);
		$this->assertSame(30, $this->service->consumeMutationBudget($token, self::NOW));
	}

	public function testMutationBudgetStaleWindowGivesFullRetry(): void
	{
		// CAS already rolled the row into a fresh window — retry is the full 60s
		$this->tokens->method('consumeMutationSlot')->willReturn(61);
		$token = new AgentToken();
		$token->setId(7);
		$token->setRateWindowStart(self::NOW - 120);
		$this->assertSame(60, $this->service->consumeMutationBudget($token, self::NOW));
	}

	public function testSerializeNeverExposesSecrets(): void
	{
		$token = new AgentToken();
		$token->setId(1);
		$token->setUserId('alice');
		$token->setTokenHash(hash('sha256', 'secret'));
		$token->setScopes(implode(' ', self::SCOPES));
		$token->setCreatedAt(self::NOW);
		$token->setExpiresAt(self::NOW + 1000);
		$out = $this->service->serialize($token, self::NOW);
		$json = json_encode($out);
		$this->assertArrayNotHasKey('tokenHash', $out);
		$this->assertArrayNotHasKey('token_hash', $out);
		$this->assertArrayNotHasKey('secret', $out);
		$this->assertStringNotContainsString('tokenHash', $json);
		$this->assertStringNotContainsString('token_hash', $json);
		$this->assertStringNotContainsString('ckst_', $json);
		$this->assertSame('active', $out['state']);
	}

	public function testSerializeStates(): void
	{
		$revoked = new AgentToken();
		$revoked->setRevokedAt(1);
		$this->assertSame('revoked', $this->service->serialize($revoked, self::NOW)['state']);

		$expired = new AgentToken();
		$expired->setExpiresAt(self::NOW - 1);
		$this->assertSame('expired', $this->service->serialize($expired, self::NOW)['state']);
	}
}
