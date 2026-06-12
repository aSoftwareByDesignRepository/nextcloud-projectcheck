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

use OCA\ProjectCheck\Migration\LegacyTableRenamer;
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

		$ensurer = new ProjectCheckSchemaEnsurer($this->db, $this->config);
		if ($ensurer->isReady()) {
			// Fast path: still run renamer (no-op) only when legacy tables linger.
			if ($this->hasLegacyTables()) {
				$ensurer->ensure($output);
			}
			return;
		}

		$ensurer->ensure($output);
	}

	private function hasLegacyTables(): bool
	{
		foreach (LegacyTableRenamer::RENAMES as $legacy => $_new) {
			if ($this->db->tableExists($legacy)) {
				return true;
			}
		}
		return false;
	}
}
