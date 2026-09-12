<?php

declare(strict_types=1);

/**
 * Mutation: CleanupJob must keep ITimeFactory → parent::__construct($time).
 * Kills GH #12 regressions that empty parent::__construct().
 *
 * Usage (from nextcloud/):
 *   docker compose exec -u www-data -T nextcloud bash -lc \
 *     'cd /var/www/html/custom_apps/projectcheck && php -d opcache.enable_cli=0 tests/Mutation/run-cleanup-job-itime-mutations.php'
 */

$appRoot = dirname(__DIR__, 2);
$phpunit = $appRoot . '/vendor/bin/phpunit';
$testPath = $appRoot . '/tests/Unit/BackgroundJob/CleanupJobConstructTest.php';
$backupDir = $appRoot . '/tests/Mutation/.originals';
if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
	fwrite(STDERR, "Cannot create mutation backup dir\n");
	exit(2);
}

/** @var list<array{name: string, file: string, search: string, replace: string}> $mutants */
$mutants = [
	[
		'name' => 'bg-empty-parent-construct',
		'file' => 'lib/BackgroundJob/CleanupJob.php',
		'search' => 'parent::__construct($time);',
		'replace' => 'parent::__construct();',
	],
	[
		'name' => 'cron-empty-parent-construct',
		'file' => 'lib/Cron/CleanupJob.php',
		'search' => 'parent::__construct($time);',
		'replace' => 'parent::__construct();',
	],
	[
		'name' => 'bg-drop-itime-import',
		'file' => 'lib/BackgroundJob/CleanupJob.php',
		'search' => "use OCP\\AppFramework\\Utility\\ITimeFactory;\n",
		'replace' => '',
	],
];

/** @var array<string, string> $originals */
$originals = [];
foreach ($mutants as $mutant) {
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

$killed = 0;
$survived = [];

foreach ($mutants as $mutant) {
	$path = $appRoot . '/' . $mutant['file'];
	$original = $originals[$path];
	$mutated = str_replace($mutant['search'], $mutant['replace'], $original);
	if ($mutated === $original) {
		fwrite(STDERR, "MISS (search string not found): {$mutant['name']}\n");
		$survived[] = $mutant['name'] . ' (miss)';
		continue;
	}
	file_put_contents($path, $mutated);
	$cmd = escapeshellarg(PHP_BINARY)
		. ' -d opcache.enable_cli=0 -d opcache.enable=0 '
		. escapeshellarg($phpunit) . ' '
		. escapeshellarg($testPath) . ' 2>&1';
	$out = [];
	exec($cmd, $out, $code);
	file_put_contents($path, $original);
	if ($code === 0) {
		$survived[] = $mutant['name'];
		fwrite(STDERR, "SURVIVED: {$mutant['name']}\n");
		fwrite(STDERR, implode("\n", array_slice($out, -20)) . "\n");
	} else {
		$killed++;
		echo "Killed: {$mutant['name']}\n";
	}
}

$restore();
echo "Killed {$killed} / " . count($mutants) . "\n";
if ($survived !== []) {
	fwrite(STDERR, 'Surviving mutants: ' . implode(', ', $survived) . "\n");
	exit(1);
}
exit(0);
