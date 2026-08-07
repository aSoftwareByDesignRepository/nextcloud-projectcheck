<?php

declare(strict_types=1);

/**
 * Runs on every `occ upgrade` (post-migration repair) to keep ProjectCheck schema ready.
 *
 * Migrations run only when new versions ship; this step is the safety net when a
 * migration was marked complete without effect (e.g. Version2006 no-op) or when
 * an operator restores an older database snapshot.
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Repair;

use OCA\ProjectCheck\Migration\ProjectCheckSchemaEnsurer;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class EnsureProjectCheckSchema implements IRepairStep
{
	public function __construct(
		private IDBConnection $db,
		private IConfig $config,
	) {
	}

	public function getName(): string
	{
		return 'Ensure ProjectCheck pc_* database tables and legacy renames';
	}

	public function run(IOutput $output): void
	{
		// Re-enable after auto-disable during a server upgrade must not inherit a pending uninstall pass.
		$this->config->deleteAppValue(UninstallDropTables::APP_ID, UninstallDropTables::REPAIR_PASS_KEY);

		// Always run ensure(): it is idempotent for tables/columns/indexes and
		// the settlement bootstrap is flag-guarded (cheap no-op when ready).
		// Skipping when "tables exist" previously left settlement columns /
		// counter recompute / new indexes unapplied on restored snapshots.
		$ensurer = new ProjectCheckSchemaEnsurer($this->db, $this->config);
		$ensurer->ensure($output);
	}
}
