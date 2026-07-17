<?php

declare(strict_types=1);

/**
 * One-time settlement data bootstrap after the schema gains the settlement
 * columns: recompute all project counters from entries and promote project
 * creators to the Manager role on their own membership rows.
 *
 * Guarded by an app-config flag so ordinary upgrades skip the (potentially
 * expensive) full recompute once it has completed. Operators can force a
 * fresh run with `occ projectcheck:settlement-recompute`.
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Migration;

use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;

final class SettlementBootstrap
{
	public const APP_ID = 'projectcheck';
	public const READY_FLAG = 'settlement_counters_ready';

	public function __construct(
		private IDBConnection $db,
		private IConfig $config,
	) {
	}

	public function isDone(): bool
	{
		return $this->config->getAppValue(self::APP_ID, self::READY_FLAG, '0') === '1';
	}

	/**
	 * Run recompute + role backfill unless already done. Idempotent.
	 */
	public function runOnce(IOutput $output): void
	{
		if ($this->isDone()) {
			return;
		}
		$this->run($output);
	}

	/**
	 * Always run recompute + role backfill and set the ready flag.
	 */
	public function run(IOutput $output): void
	{
		$recomputer = new SettlementRecomputer($this->db);
		$excluded = $recomputer->backfillOverheadExcluded();
		$projects = $recomputer->recomputeAll();
		$promoted = $recomputer->backfillCreatorManagerRoles();
		$this->config->setAppValue(self::APP_ID, self::READY_FLAG, '1');
		$output->info(sprintf(
			'ProjectCheck: settlement bootstrap — %d overhead entr(y/ies) marked excluded; counters recomputed for %d project(s); %d creator membership(s) promoted to Manager.',
			$excluded,
			$projects,
			$promoted
		));
	}
}
