<?php

declare(strict_types=1);

/**
 * Mutation gauntlet: projects/customers/time-entries list filters stay always visible.
 *
 * Usage (Docker from nextcloud/):
 *   docker compose exec -u www-data nextcloud php /var/www/html/custom_apps/projectcheck/tests/Mutation/run-list-filters-always-visible-mutations.php
 */

$appRoot = dirname(__DIR__, 2);
require_once __DIR__ . '/harness.php';

runMutations($appRoot, 'ListFiltersAlwaysVisibleContractTest', [
	[
		'name' => 'projects_reintroduce_more_filters',
		'file' => 'templates/projects.php',
		'search' => 'pc-filters--all-visible',
		'replace' => 'pc-filters__more',
	],
	[
		'name' => 'customers_reintroduce_more_filters',
		'file' => 'templates/customers.php',
		'search' => 'pc-filters--all-visible',
		'replace' => 'pc-filters__more',
	],
	[
		'name' => 'projects_hide_status_filter_id',
		'file' => 'templates/projects.php',
		'search' => 'id="status-filter"',
		'replace' => 'id="status-filter-hidden"',
	],
	[
		'name' => 'customers_hide_settlement_filter_id',
		'file' => 'templates/customers.php',
		'search' => 'id="settlement-filter"',
		'replace' => 'id="settlement-filter-hidden"',
	],
	[
		'name' => 'strip_shared_search_span',
		'file' => 'css/common/filters.css',
		'search' => 'grid-column: span 2',
		'replace' => 'grid-column: span 1',
	],
	[
		'name' => 'break_projects_export_keys',
		'file' => 'templates/projects.php',
		'search' => "exportFilterKeys = 'search,status,priority,project_type,customer_id,settlement'",
		'replace' => "exportFilterKeys = 'search,status'",
	],
	[
		'name' => 'break_customers_export_keys',
		'file' => 'templates/customers.php',
		'search' => "exportFilterKeys = 'search,settlement'",
		'replace' => "exportFilterKeys = 'search'",
	],
]);
