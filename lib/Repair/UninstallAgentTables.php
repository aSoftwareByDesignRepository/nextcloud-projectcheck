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
 *
 * Nextcloud runs <uninstall> repair steps on EVERY app:disable — including
 * auto-disable during a server upgrade — not just on real removal. This
 * step must therefore honour the same UninstallRepairFlow guard as
 * UninstallDropTables: disable preserves data, only an explicit removal
 * (Installer::removeApp / AppSettingsController::uninstallApp) drops.
 * Without the guard, a routine disable would silently destroy every
 * minted agent token AND the forensic audit trail while re-enable
 * recreates empty tables — data loss that looks like "tokens vanished".
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
		if (!UninstallRepairFlow::isRemovalContext()) {
			$output->info('ProjectCheck: preserving agent tables on disable.');
			return;
		}
		foreach (AgentTablesSchema::TABLES as $table) {
			$this->db->dropTable($table);
		}
	}
}
