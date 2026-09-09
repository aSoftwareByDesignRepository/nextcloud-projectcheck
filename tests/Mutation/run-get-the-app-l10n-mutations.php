<?php

declare(strict_types=1);

/**
 * Mutation gauntlet: Get the App footer-locale copy must not silently fall back to English.
 *
 * @copyright Copyright (c) 2026, Software by Design GbR
 * @license AGPL-3.0-or-later
 */

$appRoot = dirname(__DIR__, 2);
$appId = basename($appRoot);
$workspaceRoot = dirname($appRoot, 2);
$phpunit = is_file($appRoot . '/vendor/bin/phpunit') ? $appRoot . '/vendor/bin/phpunit' : 'phpunit';

/**
 * @param list<string> $filters
 */
function run_filters(string $appRoot, string $workspaceRoot, string $appId, string $phpunit, array $filters): int {
	$filter = implode('|', $filters);
	$inside = is_file('/var/www/html/lib/base.php');
	if ($inside) {
		$cmd = 'php -d opcache.enable_cli=0 -d opcache.enable=0 '
			. escapeshellarg($phpunit)
			. ' -c ' . escapeshellarg($appRoot . '/phpunit.xml')
			. ' --do-not-cache-result --filter ' . escapeshellarg($filter);
	} else {
		$cmd = 'docker compose -f ' . escapeshellarg($workspaceRoot . '/docker-compose.yml')
			. ' exec -u www-data -T nextcloud php -d opcache.enable_cli=0 -d opcache.enable=0 '
			. '/var/www/html/custom_apps/' . $appId . '/vendor/bin/phpunit '
			. '-c /var/www/html/custom_apps/' . $appId . '/phpunit.xml '
			. '--do-not-cache-result --filter ' . escapeshellarg($filter);
	}
	passthru($cmd, $code);
	return (int) $code;
}

function restore_file(string $path, string $backup): void {
	if (is_file($backup)) {
		rename($backup, $path);
	}
}

/**
 * @param array{file:string,from:string,to:string,filters:list<string>} $mutation
 */
function apply_and_expect_fail(string $appRoot, string $workspaceRoot, string $appId, string $phpunit, string $name, array $mutation): bool {
	$path = $appRoot . '/' . $mutation['file'];
	$backup = $path . '.mutation-bak';
	$original = file_get_contents($path);
	if ($original === false || !str_contains($original, $mutation['from'])) {
		fwrite(STDERR, "Mutation anchor missing for {$name}\n");
		return false;
	}
	file_put_contents($backup, $original);
	$mutated = str_replace($mutation['from'], $mutation['to'], $original);
	if ($mutated === $original) {
		restore_file($path, $backup);
		fwrite(STDERR, "Mutation replace had no effect for {$name}\n");
		return false;
	}
	file_put_contents($path, $mutated);
	echo "\n== mutation: {$name} ==\n";
	$code = run_filters($appRoot, $workspaceRoot, $appId, $phpunit, $mutation['filters']);
	restore_file($path, $backup);
	if ($code === 0) {
		echo "MUTATION SURVIVED: {$name}\n";
		return false;
	}
	echo "killed {$name}\n";
	return true;
}

$filter = ['GetTheAppPageContractTest::testGetTheAppCopyIsTranslatedInFooterLocales'];
$mutations = [
	'revert_fr_log_time_to_english' => [
		'file' => 'l10n/fr.json',
		'from' => '"Log time from your phone": "Saisir le temps depuis le téléphone"',
		'to' => '"Log time from your phone": "Log time from your phone"',
		'filters' => $filter,
	],
];

echo "== baseline Get the App l10n contract ==\n";
if (run_filters($appRoot, $workspaceRoot, $appId, $phpunit, $filter) !== 0) {
	fwrite(STDERR, "Baseline tests must pass before mutation run\n");
	exit(1);
}

$failed = [];
foreach ($mutations as $name => $mutation) {
	if (!apply_and_expect_fail($appRoot, $workspaceRoot, $appId, $phpunit, $name, $mutation)) {
		$failed[] = $name;
	}
	$bak = $appRoot . '/' . $mutation['file'] . '.mutation-bak';
	if (is_file($bak)) {
		restore_file($appRoot . '/' . $mutation['file'], $bak);
	}
}

if ($failed !== []) {
	fwrite(STDERR, 'Mutations not killed: ' . implode(', ', $failed) . "\n");
	exit(1);
}
echo "\nAll Get the App l10n mutations killed.\n";
exit(0);
