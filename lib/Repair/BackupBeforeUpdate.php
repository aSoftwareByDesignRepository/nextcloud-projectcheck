<?php

declare(strict_types=1);

/**
 * Pre-migration repair step: snapshot ProjectCheck data before schema migrations run.
 *
 * Registered under {@see info.xml} `<repair-steps><pre-migration>` so every
 * `occ app:update projectcheck` and app reinstall over an existing version creates
 * a recoverable backup before migrations mutate the database.
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Repair;

use OCA\ProjectCheck\Exception\UpgradeBackupException;
use OCA\ProjectCheck\Service\UpgradeBackupService;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

final class BackupBeforeUpdate implements IRepairStep
{
	public function __construct(
		private readonly UpgradeBackupService $backupService,
	) {
	}

	public function getName(): string
	{
		return 'Back up ProjectCheck data before update migrations';
	}

	public function run(IOutput $output): void
	{
		if (!$this->backupService->hasDataToBackup()) {
			$output->info('ProjectCheck: no existing tables to back up (fresh install); skipping pre-update snapshot.');
			return;
		}

		try {
			$result = $this->backupService->createSnapshot('pre-migration');
		} catch (UpgradeBackupException $e) {
			$output->warning('ProjectCheck: pre-update backup failed: ' . $e->getMessage());
			throw $e;
		}

		$tableCount = count($result['manifest']['tables'] ?? []);
		$output->info(sprintf(
			'ProjectCheck: pre-update backup created (%s, %d table(s)). '
			. 'Restore with `occ projectcheck:upgrade-backup restore --latest --force` if needed.',
			$result['id'],
			$tableCount,
		));
	}
}
