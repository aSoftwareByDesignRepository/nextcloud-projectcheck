#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Regenerate l10n/*.js from l10n/*.json (Nextcloud OC.L10N.register format).
 *
 * Usage: php scripts/regenerate-l10n-js.php
 */

$base = __DIR__ . '/../l10n';
$locales = array (
  0 => 'en',
  1 => 'de',
  2 => 'fr',
  3 => 'es',
  4 => 'da',
  5 => 'nl',
  6 => 'it',
  7 => 'pl',
  8 => 'sv',
  9 => 'nb',
);

foreach ($locales as $lang) {
	$jsonPath = $base . '/' . $lang . '.json';
	$jsPath = $base . '/' . $lang . '.js';
	if (!is_file($jsonPath)) {
		fwrite(STDERR, "Missing: $jsonPath\n");
		exit(1);
	}
	$cat = json_decode((string)file_get_contents($jsonPath), true, 512, JSON_THROW_ON_ERROR);
	$translations = $cat['translations'] ?? [];
	$pluralForm = $cat['pluralForm'] ?? null;

	$lines = ["OC.L10N.register(\n", "\t\"projectcheck\",\n", "\t{\n"];
	$first = true;
	foreach ($translations as $key => $val) {
		$first = false;
		$k = json_encode($key, JSON_UNESCAPED_UNICODE);
		if (is_array($val)) {
			$v = json_encode($val, JSON_UNESCAPED_UNICODE);
		} else {
			$v = json_encode((string)$val, JSON_UNESCAPED_UNICODE);
		}
		$lines[] = "\t" . $k . ' : ' . $v . ",\n";
	}
	$last = array_pop($lines);
	$last = rtrim($last, ",\n") . "\n";
	$lines[] = $last;
	if ($pluralForm !== null && $pluralForm !== '') {
		$lines[] = "\t},\n";
		$lines[] = "\t" . json_encode($pluralForm) . "\n";
		$lines[] = ");\n";
	} else {
		$lines[] = "\t}\n";
		$lines[] = ");\n";
	}
	file_put_contents($jsPath, implode('', $lines));
	echo "Wrote $jsPath (" . count($translations) . " keys)\n";
}

echo "l10n JS regeneration OK.\n";
