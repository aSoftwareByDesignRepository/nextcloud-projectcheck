<?php
/** Synced from _shared/agent-auth — do not edit app-side. */

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Repair;

use OCA\ProjectCheck\Migration\AgentTablesSchema;
use OCA\ProjectCheck\Repair\UninstallAgentTables;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;

/**
 * Nextcloud runs <uninstall> repair steps on EVERY app:disable — including
 * auto-disable during a server upgrade — not only on real app removal.
 * Dropping unconditionally would silently destroy every minted agent
 * token and the forensic audit trail on a routine disable. This test
 * runs in a non-removal call context, so UninstallRepairFlow::isRemovalContext()
 * is false and the step MUST preserve the tables.
 */
final class UninstallAgentTablesTest extends TestCase
{
	public function testDisableContextNeverDropsAgentTables(): void
	{
		$db = $this->createMock(IDBConnection::class);
		$db->expects($this->never())->method('dropTable');
		$output = $this->createMock(IOutput::class);
		$step = new UninstallAgentTables($db);
		$step->run($output);
		$this->assertNotEmpty(AgentTablesSchema::TABLES, 'catalog must not be empty');
	}
}
