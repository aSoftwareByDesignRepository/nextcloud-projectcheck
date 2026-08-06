#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Mutation gauntlet for keep-focused-visible.js (soft-keyboard / note visibility).
 * Runs the node unit suite against source mutants; expects survivors = 0.
 */

$root = dirname(__DIR__, 2);
$source = $root . '/js/common/keep-focused-visible.js';
$test = $root . '/tests/js/keep-focused-visible.test.cjs';
$backup = $source . '.mutation-bak';

if (!is_file($source) || !is_file($test)) {
	fwrite(STDERR, "Missing source or test\n");
	exit(1);
}

/**
 * @return bool true if tests pass
 */
function runNodeTests(string $testFile): bool
{
	$cmd = 'node --test ' . escapeshellarg($testFile) . ' 2>&1';
	exec($cmd, $out, $code);
	return $code === 0;
}

/**
 * @param list<array{name:string,find:string,replace:string}> $mutants
 */
function runMutations(string $source, string $backup, string $test, array $mutants): int
{
	if (!copy($source, $backup)) {
		fwrite(STDERR, "Cannot backup source\n");
		return 1;
	}

	$killed = 0;
	$survived = 0;

	foreach ($mutants as $m) {
		$src = file_get_contents($backup);
		if ($src === false) {
			fwrite(STDERR, "Cannot read backup\n");
			copy($backup, $source);
			return 1;
		}
		if (!str_contains($src, $m['find'])) {
			fwrite(STDERR, "SKIP {$m['name']}: find needle missing\n");
			continue;
		}
		$mutated = str_replace($m['find'], $m['replace'], $src);
		file_put_contents($source, $mutated);
		$pass = runNodeTests($test);
		if ($pass) {
			fwrite(STDERR, "SURVIVED {$m['name']}\n");
			$survived++;
		} else {
			fwrite(STDOUT, "killed {$m['name']}\n");
			$killed++;
		}
	}

	copy($backup, $source);
	unlink($backup);

	fwrite(STDOUT, "Mutation summary: killed={$killed} survived={$survived}\n");
	return $survived === 0 ? 0 : 1;
}

// Baseline must be green
if (!runNodeTests($test)) {
	fwrite(STDERR, "Baseline keep-focused-visible tests failed\n");
	exit(1);
}
fwrite(STDOUT, "baseline green\n");

$mutants = [
	[
		'name' => 'edge-pad-zero',
		'find' => 'const EDGE_PAD = 20;',
		'replace' => 'const EDGE_PAD = 0;',
	],
	[
		'name' => 'never-pad-room',
		'find' => 'ensureKeyboardScrollRoom(document, padNeed > 0 ? padNeed + EDGE_PAD : 0, el);',
		'replace' => 'ensureKeyboardScrollRoom(document, 0, el);',
	],
	[
		'name' => 'skip-scroll-when-covered',
		'find' => 'if (delta === 0) {' . "\n" . "\t\t\treturn { moved: false, delta: 0 };" . "\n" . "\t\t}",
		'replace' => 'if (delta !== 0) {' . "\n" . "\t\t\treturn { moved: false, delta: 0 };" . "\n" . "\t\t}",
	],
	[
		'name' => 'vv-height-ignored',
		'find' => 'return { top: top, bottom: top + vv.height };',
		'replace' => 'return { top: top, bottom: top };',
	],
	[
		'name' => 'pad-height-always-zero',
		'find' => 'host.style.paddingBottom = h + \'px\';',
		'replace' => 'host.style.paddingBottom = \'0px\';',
	],
	[
		'name' => 'install-skips-focusin',
		'find' => "doc.addEventListener('focusin', onFocusIn, true);",
		'replace' => "/* mutated: no focusin */",
	],
	[
		'name' => 'chrome-inset-always-zero',
		'find' => 'return Math.min(Math.max(0, Math.round(inset)), Math.max(0, usable - 80));',
		'replace' => 'return 0;',
	],
	[
		'name' => 'dialog-host-skipped',
		'find' => 'const dialog = nearEl.closest(DIALOG_HOST_SEL);',
		'replace' => 'const dialog = null; nearEl.closest(DIALOG_HOST_SEL);',
	],
	[
		'name' => 'ancestor-chrome-skipped',
		'find' => 'const nodes = chromeCandidates(doc, nearEl);',
		'replace' => 'const nodes = chromeCandidates(doc, null);',
	],
];

exit(runMutations($source, $backup, $test, $mutants));
