<?php

declare(strict_types=1);

/**
 * Mutation gauntlet: time-entry metrics layout must stay Date|Hours|Rate|Total.
 *
 * Usage (Docker from nextcloud/):
 *   docker compose exec -u www-data nextcloud php /var/www/html/custom_apps/projectcheck/tests/Mutation/run-time-entry-form-metrics-mutations.php
 *
 * Host (from app root, when vendor/phpunit exists):
 *   php tests/Mutation/run-time-entry-form-metrics-mutations.php
 */

$appRoot = dirname(__DIR__, 2);
$phpunit = $appRoot . '/vendor/bin/phpunit';
if (!is_file($phpunit)) {
	$phpunit = 'phpunit';
}

/**
 * @return list<array{file:string,from:string,to:string,label:string}>
 */
function projectcheck_time_entry_metrics_mutations(string $appRoot): array
{
	return [
		[
			'file' => $appRoot . '/templates/time-entry-form.php',
			'from' => 'form-row--metrics',
			'to' => 'form-row--split-4',
			'label' => 'metrics_class_reverts_to_split_4',
		],
		[
			'file' => $appRoot . '/templates/time-entry-form.php',
			'from' => 'form-group--rate',
			'to' => 'form-group--pricing-collapsed',
			'label' => 'rate_group_marker_removed',
		],
		[
			'file' => $appRoot . '/templates/time-entry-form.php',
			'from' => 'id="hourly_rate" class="form-input" step="0.01" min="0.01" readonly',
			'to' => 'id="hourly_rate" class="form-input" step="0.01" min="0.01"',
			'label' => 'hourly_rate_readonly_stripped',
		],
		[
			'file' => $appRoot . '/css/time-entry-form.css',
			'from' => 'minmax(0, 1.2fr) minmax(0, 0.7fr) minmax(0, 1fr) minmax(0, 1fr)',
			'to' => 'repeat(4, minmax(0, 1fr))',
			'label' => 'four_track_grid_formula_removed',
		],
		[
			'file' => $appRoot . '/js/time-entry-form.js',
			'from' => 'resolve-hourly-rate',
			'to' => 'client-invent-hourly-rate',
			'label' => 'server_rate_endpoint_renamed',
		],
	];
}

function run_phpunit(string $phpunit, string $appRoot): int
{
	$cmd = escapeshellarg($phpunit)
		. ' -c ' . escapeshellarg($appRoot . '/phpunit.xml')
		. ' --filter TimeEntryFormMetricsLayoutContractTest';
	passthru($cmd, $code);
	return (int)$code;
}

$mutations = projectcheck_time_entry_metrics_mutations($appRoot);
$failed = 0;
$killed = 0;

echo "Baseline…\n";
if (run_phpunit($phpunit, $appRoot) !== 0) {
	fwrite(STDERR, "Baseline failed — aborting mutations.\n");
	exit(1);
}

foreach ($mutations as $m) {
	$original = (string)file_get_contents($m['file']);
	if (!str_contains($original, $m['from'])) {
		fwrite(STDERR, "SKIP (needle missing): {$m['label']}\n");
		$failed++;
		continue;
	}
	$mutated = str_replace($m['from'], $m['to'], $original, $count);
	if ($count < 1) {
		fwrite(STDERR, "SKIP (no replace): {$m['label']}\n");
		$failed++;
		continue;
	}
	file_put_contents($m['file'], $mutated);
	echo "Mutate: {$m['label']}…\n";
	$code = run_phpunit($phpunit, $appRoot);
	file_put_contents($m['file'], $original);
	if ($code === 0) {
		fwrite(STDERR, "SURVIVED: {$m['label']}\n");
		$failed++;
	} else {
		echo "Killed: {$m['label']}\n";
		$killed++;
	}
}

echo "Killed {$killed}/" . count($mutations) . "\n";
exit($failed > 0 ? 1 : 0);
