<?php

declare(strict_types=1);

/**
 * Shared targeted mutation harness (no xdebug/pcov required).
 *
 * Each runner defines mutants as literal search/replace pairs against one or
 * more source files, then calls runMutations(). A mutant is "killed" when the
 * filtered PHPUnit run fails while the mutant is applied. Originals are always
 * restored (shutdown handler covers fatals/CTRL-C).
 *
 * @param list<array{name: string, file: string, search: string, replace: string}> $mutants
 */
function runMutations(string $appRoot, string $testFilter, array $mutants): never
{
	$phpunit = $appRoot . '/vendor/bin/phpunit';
	$config = $appRoot . '/phpunit.xml';
	$backupDir = $appRoot . '/tests/Mutation/.originals';
	if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
		fwrite(STDERR, "Cannot create mutation backup dir\n");
		exit(2);
	}

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
			// Durable backup — SIGKILL (OOM) skips shutdown handlers.
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
		$cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($phpunit)
			. ' -c ' . escapeshellarg($config)
			. ' --filter ' . escapeshellarg($testFilter)
			. ' 2>&1';
		$out = [];
		exec($cmd, $out, $code);
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
	echo "Killed {$killed} / " . count($mutants) . "\n";
	if ($survived !== []) {
		fwrite(STDERR, 'Surviving mutants: ' . implode(', ', $survived) . "\n");
	}
	exit($survived === [] ? 0 : 1);
}

/**
 * Restore source files from durable mutation backups (after OOM/SIGKILL).
 */
function restoreMutationOriginals(string $appRoot): int
{
	$backupDir = $appRoot . '/tests/Mutation/.originals';
	if (!is_dir($backupDir)) {
		return 0;
	}
	$restored = 0;
	foreach (glob($backupDir . '/*.bak') ?: [] as $bak) {
		$base = basename($bak, '.bak');
		$rel = str_replace('__', '/', $base);
		$target = $appRoot . '/' . $rel;
		$content = file_get_contents($bak);
		if ($content === false || !is_file($target)) {
			continue;
		}
		if (file_get_contents($target) !== $content) {
			file_put_contents($target, $content);
			$restored++;
			fwrite(STDERR, "Restored after interrupted mutation: {$rel}\n");
		}
	}
	return $restored;
}
