<?php

declare(strict_types=1);

/**
 * Logical tables ProjectCheck needs at runtime (excludes legacy names).
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Migration;

final class ProjectCheckTableCatalog
{
	public const APP_ID = 'projectcheck';

	/**
	 * Every prefixed table the app queries in normal operation.
	 *
	 * @var list<string>
	 */
	public const REQUIRED_TABLES = [
		'pc_customers',
		'pc_projects',
		'pc_project_members',
		'pc_time_entries',
		'pc_project_files',
		RateHistoryTables::EMPLOYEE,
		RateHistoryTables::PROJECT_MEMBER,
		'pc_user_account_snapshots',
	];
}
