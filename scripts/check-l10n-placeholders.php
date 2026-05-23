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

foreach (['en.json', 'de.json'] as $file) {
	$path = $base . '/' . $file;
	if (!is_file($path)) {
		fwrite(STDERR, "Missing locale file: $path\n");
		exit(1);
	}
}

$en = json_decode((string)file_get_contents($base . '/en.json'), true, 512, JSON_THROW_ON_ERROR);
$de = json_decode((string)file_get_contents($base . '/de.json'), true, 512, JSON_THROW_ON_ERROR);

$enT = $en['translations'] ?? [];
$deT = $de['translations'] ?? [];

$failed = false;

foreach ($enT as $key => $enVal) {
	$keyPh = pcPrintfPlaceholders($key);
	if ($keyPh === []) {
		continue;
	}
	if (!isset($deT[$key])) {
		continue;
	}
	$enPh = pcPrintfPlaceholders((string)$enVal);
	$dePh = pcPrintfPlaceholders((string)$deT[$key]);
	if ($enPh !== $keyPh) {
		$failed = true;
		fwrite(STDERR, "en.json placeholder mismatch for key: $key\n");
		fwrite(STDERR, "  expected: " . implode(', ', $keyPh) . "\n");
		fwrite(STDERR, "  got:      " . implode(', ', $enPh) . "\n");
	}
	if ($dePh !== $keyPh) {
		$failed = true;
		fwrite(STDERR, "de.json placeholder mismatch for key: $key\n");
		fwrite(STDERR, "  expected: " . implode(', ', $keyPh) . "\n");
		fwrite(STDERR, "  got:      " . implode(', ', $dePh) . "\n");
	}
}

if ($failed) {
	fwrite(STDERR, "\nl10n placeholder check FAILED.\n");
	exit(1);
}

echo "l10n placeholder check OK (en/de printf placeholders match msgids).\n";
exit(0);
