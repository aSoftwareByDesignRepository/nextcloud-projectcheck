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
		'name' => 'desktop-gate-drops-keyboard-check',
		'find' => 'return needsImeReveal(el) && softKeyboardLikelyOpen(win);',
		'replace' => 'return needsImeReveal(el);',
	],
	[
		'name' => 'select-still-revealed',
		'find' => "if (typeof HTMLSelectElement !== 'undefined' && el instanceof HTMLSelectElement) {\n\t\t\treturn false;\n\t\t}",
		'replace' => "if (false && typeof HTMLSelectElement !== 'undefined' && el instanceof HTMLSelectElement) {\n\t\t\treturn false;\n\t\t}",
	],
	[
		'name' => 'keyboard-shrink-zero',
		'find' => 'const KEYBOARD_SHRINK_PX = 120;',
		'replace' => 'const KEYBOARD_SHRINK_PX = 0;',
	],
	[
		'name' => 'scroll-uses-center',
		'find' => "el.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: 'auto' });",
		'replace' => "el.scrollIntoView({ block: 'center', inline: 'nearest', behavior: 'auto' });",
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
		'name' => 'keyboard-close-skips-pad-clear',
		'find' => "// Keyboard dismissed / desktop: never leave sticky IME padding behind.\n\t\t\t\tensureKeyboardScrollRoom(doc, 0, null);\n\t\t\t\treturn;",
		'replace' => "// mutated: leave pad\n\t\t\t\treturn;",
	],
];

exit(runMutations($source, $backup, $test, $mutants));
