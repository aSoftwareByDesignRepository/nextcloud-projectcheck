<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud DB-Standards (auto-generated)
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Tables that must exist after projectcheck's current migrations ran —
 * every table ever created minus those intentionally dropped by later
 * migrations. Schema-repair (Ensure*Schema) and schema-readiness probes
 * use EXPECTED_TABLES; uninstall drop lists use UninstallDropTables::TABLES
 * (which deliberately keeps dropped legacy tables for complete cleanup).
 *
 * Regenerate via:
 *     php scripts/check-nextcloud-db-standards.php sync-uninstall --app=projectcheck
 */
namespace OCA\ProjectCheck\Repair;

final class SchemaTableCatalog
{
	/** @var list<string> */
	public const EXPECTED_TABLES = [
		'customers',
		'pc_customers',
		'pc_emp_rates',
		'pc_license_state',
		'pc_mob_idem',
		'pc_mobile_seats',
		'pc_pm_rates',
		'pc_project_files',
		'pc_project_members',
		'pc_projects',
		'pc_time_entries',
		'pc_user_account_snapshots',
		'project_files',
		'project_members',
		'projects',
		'time_entries',
	];

	/** @var list<string> tables dropped intentionally by later migrations */
	public const DROPPED_LEGACY_TABLES = [
		'pc_employee_hourly_rates',
		'pc_project_member_hourly_rates',
	];
}
