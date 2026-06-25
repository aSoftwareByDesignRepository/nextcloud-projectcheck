<?php

declare(strict_types=1);

/**
 * Tables and app-data paths included in pre-update upgrade backups.
 *
 * SPDX-FileCopyrightText: 2026 Nextcloud DB-Standards (auto-generated)
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Regenerate via:
 *     php scripts/sync-upgrade-backup.php --app=projectcheck
 */
namespace OCA\ProjectCheck\Service;

final class UpgradeBackupCatalog
{
	public const APP_ID = 'projectcheck';

	public const FORMAT_VERSION = 1;

	public const APPDATA_ROOT = 'upgrade-backups';

	/** @var list<string> App-data folder names (under appdata_<instance>/projectcheck/) to include in snapshots. */
	public const APPDATA_FOLDERS = [
		'project_files',
	];

	public const CONFIG_MAX_SNAPSHOTS = 'upgrade_backup_max_snapshots';

	public const CONFIG_LAST_SNAPSHOT_ID = 'upgrade_backup_last_snapshot_id';

	public const DEFAULT_MAX_SNAPSHOTS = 5;

	public const MAX_SNAPSHOTS_LIMIT = 20;

	/** @var list<string> */
	public const BACKUP_TABLES = [
		'customers',
		'pc_customers',
		'pc_emp_rates',
		'pc_employee_hourly_rates',
		'pc_pm_rates',
		'pc_project_files',
		'pc_project_member_hourly_rates',
		'pc_project_members',
		'pc_projects',
		'pc_time_entries',
		'pc_user_account_snapshots',
		'project_files',
		'project_members',
		'projects',
		'time_entries',
	];

	/** @var list<string> */
	public const RESTORE_TABLE_ORDER = [
		'customers',
		'pc_customers',
		'projects',
		'pc_projects',
		'project_members',
		'pc_project_members',
		'pc_emp_rates',
		'pc_employee_hourly_rates',
		'pc_pm_rates',
		'pc_project_member_hourly_rates',
		'time_entries',
		'pc_time_entries',
		'project_files',
		'pc_project_files',
		'pc_user_account_snapshots',
	];

	public static function isBackupTable(string $table): bool
	{
		return in_array($table, self::BACKUP_TABLES, true);
	}

	public static function clampMaxSnapshots(int $requested): int
	{
		return max(1, min(self::MAX_SNAPSHOTS_LIMIT, $requested));
	}

	/**
	 * @return list<string>
	 */
	public static function existingBackupTables(callable $tableExists): array
	{
		$existing = [];
		foreach (self::BACKUP_TABLES as $table) {
			if ($tableExists($table)) {
				$existing[] = $table;
			}
		}

		return $existing;
	}

	/**
	 * @return list<string>
	 */
	public static function sortedRestoreTables(array $presentTables): array
	{
		$present = array_fill_keys($presentTables, true);
		$ordered = [];
		foreach (self::RESTORE_TABLE_ORDER as $table) {
			if (isset($present[$table])) {
				$ordered[] = $table;
			}
		}

		foreach ($presentTables as $table) {
			if (!in_array($table, $ordered, true)) {
				$ordered[] = $table;
			}
		}

		return $ordered;
	}
}
