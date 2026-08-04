<?php

declare(strict_types=1);

/**
 * Mutation gauntlet for route → controller naming (GitHub #8).
 *
 * Usage (from app root, host with Docker):
 *   php tests/Mutation/run-route-controller-naming-mutations.php
 *
 * @copyright Copyright (c) 2026, Software by Design GbR
 * @license AGPL-3.0-or-later
 */

$appRoot = dirname(__DIR__, 2);
require_once $appRoot . '/tests/Mutation/harness.php';

$phpFilter = 'RoutesControllerNamingContractTest|MobileCompanionRoutesContractTest';

/**
 * Prefer Docker PHPUnit (Nextcloud gauntlet); fall back to local vendor binary.
 */
function run_route_naming_tests(string $appRoot, string $filter): int
{
	$nextcloudRoot = dirname($appRoot, 2);
	if (!is_file('/.dockerenv') && is_file($nextcloudRoot . '/docker-compose.yml')) {
		passthru(
			'cd ' . escapeshellarg($nextcloudRoot)
			. ' && docker compose exec -u www-data -w /var/www/html/custom_apps/projectcheck nextcloud'
			. ' php -d opcache.enable_cli=0 -d opcache.enable=0 vendor/bin/phpunit -c phpunit.xml --filter '
			. escapeshellarg($filter),
			$code,
		);
		return (int) $code;
	}
	$phpunit = $appRoot . '/vendor/bin/phpunit';
	if (!is_file($phpunit)) {
		$phpunit = 'phpunit';
	}
	passthru(
		'php -d opcache.enable_cli=0 -d opcache.enable=0 '
		. escapeshellarg($phpunit)
		. ' -c ' . escapeshellarg($appRoot . '/phpunit.xml')
		. ' --filter ' . escapeshellarg($filter),
		$code,
	);
	return (int) $code;
}

$mutations = [
	[
		'name' => 'project_file_to_compact_projectfile',
		'file' => 'appinfo/routes.php',
		'search' => "'name' => 'project_file#",
		'replace' => "'name' => 'projectfile#",
	],
	[
		'name' => 'time_entry_to_compact_timeentry',
		'file' => 'appinfo/routes.php',
		'search' => "'name' => 'time_entry#",
		'replace' => "'name' => 'timeentry#",
	],
	[
		'name' => 'project_member_to_compact_projectmember',
		'file' => 'appinfo/routes.php',
		'search' => "'name' => 'project_member#",
		'replace' => "'name' => 'projectmember#",
	],
	[
		'name' => 'app_config_to_compact_appconfig',
		'file' => 'appinfo/routes.php',
		'search' => "'name' => 'app_config#",
		'replace' => "'name' => 'appconfig#",
	],
	[
		'name' => 'service_worker_to_compact_serviceworker',
		'file' => 'appinfo/routes.php',
		'search' => "'name' => 'service_worker#",
		'replace' => "'name' => 'serviceworker#",
	],
	[
		'name' => 'mobile_bootstrap_stolen_by_time_entry',
		'file' => 'appinfo/routes.php',
		'search' => "['name' => 'mobile#bootstrap', 'url' => '/mobile/v1/bootstrap', 'verb' => 'GET'],",
		'replace' => "['name' => 'time_entry#index', 'url' => '/mobile/v1/bootstrap', 'verb' => 'GET'],",
	],
	[
		'name' => 'mobile_time_entries_path_drift',
		'file' => 'appinfo/routes.php',
		'search' => "['name' => 'mobile#timeEntries', 'url' => '/mobile/v1/time-entries', 'verb' => 'GET'],",
		'replace' => "['name' => 'mobile#timeEntries', 'url' => '/mobile/v2/time-entries', 'verb' => 'GET'],",
	],
	[
		'name' => 'mobile_create_remapped_to_web_store',
		'file' => 'appinfo/routes.php',
		'search' => "['name' => 'mobile#createTimeEntry', 'url' => '/mobile/v1/time-entries', 'verb' => 'POST'],",
		'replace' => "['name' => 'time_entry#store', 'url' => '/mobile/v1/time-entries', 'verb' => 'POST'],",
	],
	[
		'name' => 'health_probe_path_removed',
		'file' => 'appinfo/routes.php',
		'search' => "['name' => 'health#check', 'url' => '/health', 'verb' => 'GET'],",
		'replace' => "['name' => 'health#check', 'url' => '/healthz', 'verb' => 'GET'],",
	],
];

// Custom runner: harness.php execs local phpunit; we need Docker. Inline kill loop.
$backupDir = $appRoot . '/tests/Mutation/.originals';
if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
	fwrite(STDERR, "Cannot create mutation backup dir\n");
	exit(2);
}

/** @var array<string, string> $originals */
$originals = [];
foreach ($mutations as $mutant) {
	$path = $appRoot . '/' . $mutant['file'];
	if (!isset($originals[$path])) {
		$content = file_get_contents($path);
		if ($content === false) {
			fwrite(STDERR, "Cannot read {$path}\n");
			exit(2);
		}
		$originals[$path] = $content;
		$backupName = str_replace(['/', '\\'], '__', $mutant['file']) . '.bak';
		file_put_contents($backupDir . '/' . $backupName, $content);
	}
}

$restore = static function () use ($originals): void {
	foreach ($originals as $path => $content) {
		file_put_contents($path, $content);
	}
};
register_shutdown_function($restore);

echo "Baseline (must pass)…\n";
if (run_route_naming_tests($appRoot, $phpFilter) !== 0) {
	fwrite(STDERR, "Baseline RoutesControllerNamingContractTest failed — aborting mutations\n");
	$restore();
	exit(1);
}

$killed = 0;
$survived = [];
foreach ($mutations as $mutant) {
	$path = $appRoot . '/' . $mutant['file'];
	$original = $originals[$path];
	$mutated = str_replace($mutant['search'], $mutant['replace'], $original);
	if ($mutated === $original) {
		fwrite(STDERR, "MISS (search string not found): {$mutant['name']}\n");
		$survived[] = $mutant['name'] . ' (miss)';
		continue;
	}
	file_put_contents($path, $mutated);
	$code = run_route_naming_tests($appRoot, $phpFilter);
	file_put_contents($path, $original);
	if ($code === 0) {
		$survived[] = $mutant['name'];
		fwrite(STDERR, "SURVIVED: {$mutant['name']}\n");
	} else {
		$killed++;
		echo "Killed: {$mutant['name']}\n";
	}
}

$restore();
echo "Killed {$killed} / " . count($mutations) . "\n";
if ($survived !== []) {
	fwrite(STDERR, "Survivors: " . implode(', ', $survived) . "\n");
	exit(1);
}
exit(0);
