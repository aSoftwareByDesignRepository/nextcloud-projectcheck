#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Mutation checks for pt_BR l10n integrity.
 *
 * Mutates a copy of the catalog (missing key / broken placeholder / product-name mangling)
 * and asserts the contract detectors would fail — proving tests catch real breakage.
 *
 * Exit 0 = all mutants killed; 1 = survivor.
 */

$appRoot = dirname(__DIR__, 2);
$enPath = $appRoot . '/l10n/en.json';
$ptPath = $appRoot . '/l10n/pt_BR.json';

if (!is_file($enPath) || !is_file($ptPath)) {
	fwrite(STDERR, "Missing en.json or pt_BR.json\n");
	exit(1);
}

/**
 * @param array<string, mixed> $enT
 * @param array<string, mixed> $ptT
 * @return list<string>
 */
function pcDetectPtBrDefects(array $enT, array $ptT): array
{
	$defects = [];
	$enKeys = array_keys($enT);
	$ptKeys = array_keys($ptT);
	sort($enKeys);
	sort($ptKeys);
	if ($enKeys !== $ptKeys) {
		$defects[] = 'key-parity';
	}
	foreach ($enT as $key => $enVal) {
		if (!array_key_exists($key, $ptT)) {
			$defects[] = 'missing:' . $key;
			continue;
		}
		$ptVal = $ptT[$key];
		if (is_string($enVal) && is_string($ptVal)) {
			preg_match_all('/%%|%(?:\d+\$)?[sd]|%n/', $enVal, $enPh);
			preg_match_all('/%%|%(?:\d+\$)?[sd]|%n/', $ptVal, $ptPh);
			if ($enPh[0] !== $ptPh[0]) {
				$defects[] = 'printf:' . $key;
			}
			preg_match_all('/\{[a-zA-Z_][a-zA-Z0-9_]*\}/', $enVal, $enNamed);
			preg_match_all('/\{[a-zA-Z_][a-zA-Z0-9_]*\}/', $ptVal, $ptNamed);
			if ($enNamed[0] !== $ptNamed[0]) {
				$defects[] = 'named:' . $key;
			}
		}
	}
	if (($ptT['ProjectCheck'] ?? null) !== 'ProjectCheck') {
		$defects[] = 'product-name';
	}
	if (!isset($ptT['Access denied']) || !is_string($ptT['Access denied']) || !str_contains(mb_strtolower($ptT['Access denied']), 'negado')) {
		$defects[] = 'access-denied-pt';
	}

	return $defects;
}

$en = json_decode((string)file_get_contents($enPath), true, 512, JSON_THROW_ON_ERROR);
$pt = json_decode((string)file_get_contents($ptPath), true, 512, JSON_THROW_ON_ERROR);
$enT = $en['translations'];
$ptT = $pt['translations'];

$baseline = pcDetectPtBrDefects($enT, $ptT);
if ($baseline !== []) {
	fwrite(STDERR, "Baseline pt_BR already defective:\n- " . implode("\n- ", $baseline) . "\n");
	exit(1);
}

$mutants = [
	'drop-key' => static function (array $t): array {
		unset($t['Access denied']);
		return $t;
	},
	'break-printf' => static function (array $t): array {
		$t['%1$s of %2$s seats used'] = '%s de %s assentos usados';
		return $t;
	},
	'mangle-named' => static function (array $t): array {
		foreach ($t as $k => $v) {
			if (is_string($v) && str_contains($v, '{') && preg_match('/\{[a-zA-Z_][a-zA-Z0-9_]*\}/', $v)) {
				$t[$k] = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '{$1_x}', $v) ?? $v;
				break;
			}
		}
		return $t;
	},
	'mangle-product' => static function (array $t): array {
		$t['ProjectCheck'] = 'Verificação do Projeto';
		return $t;
	},
];

$failed = false;
foreach ($mutants as $name => $mutator) {
	$mutated = $mutator($ptT);
	$defects = pcDetectPtBrDefects($enT, $mutated);
	if ($defects === []) {
		$failed = true;
		fwrite(STDERR, "SURVIVOR mutant: {$name}\n");
	} else {
		echo "Killed mutant {$name} via: " . $defects[0] . "\n";
	}
}

if ($failed) {
	fwrite(STDERR, "pt_BR mutation check FAILED\n");
	exit(1);
}

echo "pt_BR mutation check OK (" . count($mutants) . " mutants killed).\n";
exit(0);
