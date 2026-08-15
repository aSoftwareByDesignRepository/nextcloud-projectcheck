<?php

declare(strict_types=1);

/**
 * Ensures translated strings keep the same printf-style placeholders as their msgid.
 * Named placeholders like {project} are ignored (Nextcloud notifications / JS tPl).
 *
 * Exit 0 = OK, 1 = mismatch printed to STDERR.
 */

$base = __DIR__ . '/../l10n';

/**
 * @return list<string>
 */
function pcPrintfPlaceholders(string $s): array {
	preg_match_all('/%%|%(?:\d+\$)?[sd]/', $s, $m);

	return $m[0];
}

$localeFiles = ['en.json', 'de.json', 'pt_BR.json'];
foreach ($localeFiles as $file) {
	$path = $base . '/' . $file;
	if (!is_file($path)) {
		fwrite(STDERR, "Missing locale file: $path\n");
		exit(1);
	}
}

$en = json_decode((string)file_get_contents($base . '/en.json'), true, 512, JSON_THROW_ON_ERROR);
$enT = $en['translations'] ?? [];

$failed = false;

foreach ($localeFiles as $file) {
	$lang = basename($file, '.json');
	$cat = json_decode((string)file_get_contents($base . '/' . $file), true, 512, JSON_THROW_ON_ERROR);
	$langT = $cat['translations'] ?? [];

	foreach ($enT as $key => $enVal) {
		$keyPh = pcPrintfPlaceholders($key);
		if ($keyPh === []) {
			continue;
		}
		if (!isset($langT[$key])) {
			continue;
		}
		$val = $langT[$key];
		if (is_array($val)) {
			foreach ($val as $idx => $form) {
				$formPh = pcPrintfPlaceholders((string)$form);
				// Plural forms may omit %n in some languages; require msgid placeholders ⊆ form or equal when present.
				if ($formPh !== [] && $formPh !== $keyPh && $formPh !== pcPrintfPlaceholders((string)$enVal)) {
					$failed = true;
					fwrite(STDERR, "{$lang}.json plural placeholder mismatch for key: $key [{$idx}]\n");
					fwrite(STDERR, "  expected: " . implode(', ', $keyPh) . "\n");
					fwrite(STDERR, "  got:      " . implode(', ', $formPh) . "\n");
				}
			}
			continue;
		}
		$langPh = pcPrintfPlaceholders((string)$val);
		if ($langPh !== $keyPh) {
			$failed = true;
			fwrite(STDERR, "{$lang}.json placeholder mismatch for key: $key\n");
			fwrite(STDERR, "  expected: " . implode(', ', $keyPh) . "\n");
			fwrite(STDERR, "  got:      " . implode(', ', $langPh) . "\n");
		}
	}
}

if ($failed) {
	fwrite(STDERR, "\nl10n placeholder check FAILED.\n");
	exit(1);
}

echo "l10n placeholder check OK (en/de/pt_BR printf placeholders match msgids).\n";
exit(0);
