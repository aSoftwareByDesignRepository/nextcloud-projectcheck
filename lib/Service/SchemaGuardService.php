<?php

declare(strict_types=1);

/**
 * Repairs incomplete ProjectCheck schema on demand (HTTP, widgets, jobs).
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Service;

use OCA\ProjectCheck\Exception\SchemaRepairFailedException;
use OCA\ProjectCheck\Migration\SilentOutput;
use OCA\ProjectCheck\Migration\LegacyTableRenamer;
use OCA\ProjectCheck\Migration\ProjectCheckSchemaEnsurer;
use OCA\ProjectCheck\Migration\ProjectCheckTableCatalog;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;
use Psr\Log\LoggerInterface;
use RuntimeException;

class SchemaGuardService
{
	private const LOCK_KEY = 'projectcheck-schema-repair';

	/** @var bool|null null = not checked yet; true = ready; false = repair failed */
	private static ?bool $requestState = null;

	public function __construct(
		private IDBConnection $db,
		private IConfig $config,
		private LoggerInterface $logger,
		private ILockingProvider $locking,
	) {
	}

	/**
	 * Idempotent per request: renames legacy tables and creates any missing pc_* tables.
	 *
	 * @throws SchemaRepairFailedException
	 */
	public function ensureReady(): void
	{
		if (self::$requestState === true) {
			return;
		}
		if (self::$requestState === false) {
			throw new SchemaRepairFailedException(
				'ProjectCheck database schema is incomplete and automatic repair already failed in this request.'
			);
		}

		$ensurer = new ProjectCheckSchemaEnsurer($this->db, $this->config);
		$bootstrap = new \OCA\ProjectCheck\Migration\SettlementBootstrap($this->db, $this->config);
		// Tables alone are not enough: the settlement counter recompute flag
		// must be set, or list filters / posture chips silently lie with
		// DEFAULT 0 counters (spec §10.6). ensure() clears the flag whenever
		// it adds settlement columns, so a set flag implies columns exist.
		if ($ensurer->isReady() && $bootstrap->isDone()) {
			self::$requestState = true;
			return;
		}

		$missing = $this->missingRequiredTables();
		$legacy = $this->presentLegacyTables();
		$this->logger->warning('ProjectCheck: incomplete schema detected, running runtime repair', [
			'app' => ProjectCheckTableCatalog::APP_ID,
			'missing' => $missing,
			'legacyTables' => $legacy,
			'settlementReady' => $bootstrap->isDone(),
		]);

		try {
			$this->runRepairExclusive($ensurer);
		} catch (RuntimeException $e) {
			self::$requestState = false;
			$this->logger->error('ProjectCheck: runtime schema repair failed', [
				'app' => ProjectCheckTableCatalog::APP_ID,
				'exception' => $e,
			]);
			throw new SchemaRepairFailedException(
				'ProjectCheck could not repair the database schema automatically. '
				. 'Ask an administrator to run `occ upgrade` or `occ app:update projectcheck`.',
				0,
				$e
			);
		}

		if (!$ensurer->isReady() || !$bootstrap->isDone()) {
			self::$requestState = false;
			throw new SchemaRepairFailedException(
				'ProjectCheck schema is still incomplete after runtime repair.'
			);
		}

		$this->logger->info('ProjectCheck: runtime schema repair completed', [
			'app' => ProjectCheckTableCatalog::APP_ID,
		]);
		self::$requestState = true;
	}

	/**
	 * @return list<string>
	 */
	private function missingRequiredTables(): array
	{
		$missing = [];
		foreach (ProjectCheckTableCatalog::REQUIRED_TABLES as $table) {
			if (!$this->db->tableExists($table)) {
				$missing[] = $table;
			}
		}

		return $missing;
	}

	/**
	 * @return list<string>
	 */
	private function presentLegacyTables(): array
	{
		$present = [];
		foreach (array_keys(LegacyTableRenamer::RENAMES) as $legacy) {
			if ($this->db->tableExists($legacy)) {
				$present[] = $legacy;
			}
		}

		return $present;
	}

	private function runRepairExclusive(ProjectCheckSchemaEnsurer $ensurer): void
	{
		$lockAcquired = false;
		try {
			$this->locking->acquireLock(
				self::LOCK_KEY,
				ILockingProvider::LOCK_EXCLUSIVE,
				'ProjectCheck schema repair'
			);
			$lockAcquired = true;
		} catch (LockedException $e) {
			$this->waitForConcurrentRepair($ensurer);
			return;
		}

		try {
			// Always run ensure() under the lock when we reach here — tables may
			// exist while settlement columns / counter bootstrap are still missing.
			if ($ensurer->isReady()) {
				$bootstrap = new \OCA\ProjectCheck\Migration\SettlementBootstrap($this->db, $this->config);
				if ($bootstrap->isDone()) {
					return;
				}
			}
			$ensurer->ensure(new SilentOutput());
		} finally {
			if ($lockAcquired) {
				$this->locking->releaseLock(self::LOCK_KEY, ILockingProvider::LOCK_EXCLUSIVE);
			}
		}
	}

	private function waitForConcurrentRepair(ProjectCheckSchemaEnsurer $ensurer): void
	{
		$bootstrap = new \OCA\ProjectCheck\Migration\SettlementBootstrap($this->db, $this->config);
		for ($attempt = 0; $attempt < 30; $attempt++) {
			usleep(200_000);
			if ($ensurer->isReady() && $bootstrap->isDone()) {
				return;
			}
		}

		throw new RuntimeException('ProjectCheck schema repair is in progress on another request.');
	}
}
