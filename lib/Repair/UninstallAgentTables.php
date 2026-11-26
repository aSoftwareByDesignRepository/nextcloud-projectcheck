<?php
/** Synced from _shared/agent-auth — do not edit app-side. */

declare(strict_types=1);

namespace OCA\ProjectCheck\Repair;

use OCA\ProjectCheck\Migration\AgentTablesSchema;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

/**
 * Drops the agent-auth tables on app removal (uninstall-clean per
 * nextcloud-db-standards). Runs as its own <uninstall> step alongside the
 * app's generated UninstallDropTables so the pack never edits that file.
 * DROP TABLE IF EXISTS keeps this safe on partial installs.
 */
final class UninstallAgentTables implements IRepairStep
{
	public function __construct(
		private readonly IDBConnection $db,
	) {
	}

	public function getName(): string
	{
		return 'Drop ProjectCheck agent token/audit tables';
	}

	public function run(IOutput $output): void
	{
		foreach (AgentTablesSchema::TABLES as $table) {
			$this->db->dropTable($table);
		}
	}
}
