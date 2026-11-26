<?php
/** Synced from _shared/agent-auth — do not edit app-side. */

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Agent;

use OCA\ProjectCheck\Agent\AgentAudit;
use PHPUnit\Framework\TestCase;

/**
 * QBMapper entities only persist fields marked updated — and the generic
 * setter skips marking when the new value equals the property default.
 * A property default equal to a real audit value (e.g. 'ok') would make
 * INSERT silently omit the NOT NULL column and 500 the audit write.
 */
final class AgentAuditEntityTest extends TestCase
{
	public function testSetResultMarksFieldUpdatedEvenForOk(): void
	{
		$audit = new AgentAudit();
		$audit->setResult(AgentAudit::RESULT_OK);
		$this->assertArrayHasKey('result', $audit->getUpdatedFields(),
			"'ok' must be marked updated — else the INSERT omits the NOT NULL column");
	}

	public function testSetActorTypeMarksFieldUpdatedForAgent(): void
	{
		$audit = new AgentAudit();
		$audit->setActorType('agent');
		$this->assertArrayHasKey('actorType', $audit->getUpdatedFields(),
			"'agent' must be marked updated — the column has a DB default, but relying on it hides drift");
	}

	public function testResultConstantsDistinct(): void
	{
		$this->assertNotSame(AgentAudit::RESULT_OK, AgentAudit::RESULT_DENIED);
		$this->assertNotSame(AgentAudit::RESULT_OK, AgentAudit::RESULT_ERROR);
		$this->assertNotSame(AgentAudit::RESULT_OK, AgentAudit::RESULT_DRYRUN);
	}
}
