<?php

declare(strict_types=1);

/**
 * Mutation gauntlet: time-entries filters must stay always visible (no disclosure).
 *
 * Usage (Docker from nextcloud/):
 *   docker compose exec -u www-data nextcloud php /var/www/html/custom_apps/projectcheck/tests/Mutation/run-time-entries-filters-mutations.php
 *
 * Host (from app root, when vendor/phpunit exists):
 *   php tests/Mutation/run-time-entries-filters-mutations.php
 */

$appRoot = dirname(__DIR__, 2);
require_once __DIR__ . '/harness.php';

runMutations($appRoot, 'TimeEntriesFilters(Layout|Workflow)ContractTest', [
	[
		'name' => 'reintroduce_more_filters_details',
		'file' => 'templates/time-entries.php',
		'search' => 'pc-filters--all-visible',
		'replace' => 'pc-filters__more',
	],
	[
		'name' => 'remove_billing_status_filter_id',
		'file' => 'templates/time-entries.php',
		'search' => 'id="billing-status-filter"',
		'replace' => 'id="billing-status-hidden"',
	],
	[
		'name' => 'remove_date_from_filter_id',
		'file' => 'templates/time-entries.php',
		'search' => 'id="date-from-filter"',
		'replace' => 'id="date-from-hidden"',
	],
	[
		'name' => 'strip_search_span_prominence',
		'file' => 'css/common/filters.css',
		'search' => 'grid-column: span 2',
		'replace' => 'grid-column: span 1',
	],
	[
		'name' => 'disable_date_range_guard',
		'file' => 'js/time-entries.js',
		'search' => 'if (dateFrom && dateTo && dateFrom > dateTo)',
		'replace' => 'if (false && dateFrom && dateTo && dateFrom > dateTo)',
	],
	[
		'name' => 'drop_settlement_outstanding_option',
		'file' => 'templates/time-entries.php',
		'search' => 'value="outstanding"',
		'replace' => 'value="outstanding_removed"',
	],
	[
		'name' => 'break_export_filter_keys',
		'file' => 'templates/time-entries.php',
		'search' => "exportFilterKeys = 'search,project_id,user_id,project_type,date_from,date_to,billing_status'",
		'replace' => "exportFilterKeys = 'search,project_id'",
	],
	[
		'name' => 'rename_collect_list_filters',
		'file' => 'js/time-entries.js',
		'search' => 'function collectListFilters',
		'replace' => 'function collectListFiltersBroken',
	],
]);
