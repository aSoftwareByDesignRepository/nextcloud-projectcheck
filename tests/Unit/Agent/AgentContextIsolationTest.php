<?php
/** Synced from _shared/agent-auth — do not edit app-side. */

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Agent;

use OCA\ProjectCheck\Agent\AgentContext;
use PHPUnit\Framework\TestCase;

/**
 * The single most dangerous wiring mistake in this pack: a context that
 * leaks across requests = a cross-user auth bug. These tests pin the
 * contract that a fresh context per request sees nothing of the last one,
 * and that arming is total (no partial state).
 */
class AgentContextIsolationTest extends TestCase
{
	public function testFreshContextIsNotArmed(): void
	{
		$ctx = new AgentContext();
		$this->assertFalse($ctx->isArmed());
		$this->assertNull($ctx->uid());
		$this->assertNull($ctx->tokenId());
		$this->assertSame([], $ctx->scopes());
	}

	public function testSequentialContextsDoNotLeak(): void
	{
		// Simulates two sequential PHP-FPM requests: each request resolves a
		// fresh AgentContext from the container.
		$first = new AgentContext();
		$first->arm('alice', 7, ['projectcheck:x:read']);
		$first->setTool('pc_tool_one');

		$second = new AgentContext();
		$this->assertFalse($second->isArmed(), 'second request context must start unarmed');
		$this->assertNull($second->uid());
		$this->assertNull($second->tokenId());
		$this->assertSame([], $second->scopes());
		$this->assertNull($second->tool());
	}

	public function testArmSetsCompletePrincipal(): void
	{
		$ctx = new AgentContext();
		$ctx->arm('bob', 42, ['projectcheck:a:read', 'projectcheck:b:write']);
		$this->assertTrue($ctx->isArmed());
		$this->assertSame('bob', $ctx->uid());
		$this->assertSame(42, $ctx->tokenId());
		$this->assertTrue($ctx->hasScope('projectcheck:b:write'));
		$this->assertFalse($ctx->hasScope('projectcheck:b:read'));
	}

	public function testSessionCallerSeesUnarmedContext(): void
	{
		// A session-authed request (no agent header) leaves the context
		// unarmed — requireUid() must fall through to the session user.
		$ctx = new AgentContext();
		$this->assertFalse($ctx->isArmed());
	}
}
