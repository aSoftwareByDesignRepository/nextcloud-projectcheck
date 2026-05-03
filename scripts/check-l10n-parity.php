<?php

declare(strict_types=1);

/**
 * Verifies that `l10n/en.json` and `l10n/de.json` contain exactly the same
 * set of msgid keys. The audit (AUDIT-FINDINGS H27) found drift between
 * locales that produced fragmented UX. We run this script in CI / pre-push
 * to keep the catalogs in step.
 *
 * Exit codes:
 *   0  parity OK
 *   1  drift detected (printed to STDERR)
 *
 * Usage:
 *   php scripts/check-l10n-parity.php
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

$base = __DIR__ . '/../l10n';
$enPath = $base . '/en.json';
$dePath = $base . '/de.json';

foreach ([$enPath, $dePath] as $path) {
	if (!is_file($path)) {
		fwrite(STDERR, "Missing locale file: $path\n");
		exit(1);
	}
}

$en = json_decode((string)file_get_contents($enPath), true, 512, JSON_THROW_ON_ERROR);
$de = json_decode((string)file_get_contents($dePath), true, 512, JSON_THROW_ON_ERROR);

$enKeys = array_keys($en['translations'] ?? []);
$deKeys = array_keys($de['translations'] ?? []);

$missingInDe = array_values(array_diff($enKeys, $deKeys));
$missingInEn = array_values(array_diff($deKeys, $enKeys));

sort($missingInDe);
sort($missingInEn);

$ok = true;
if ($missingInDe !== []) {
	$ok = false;
	fwrite(STDERR, "Keys missing in de.json (" . count($missingInDe) . "):\n");
	foreach ($missingInDe as $key) {
		fwrite(STDERR, "  - " . $key . "\n");
	}
}
if ($missingInEn !== []) {
	$ok = false;
	fwrite(STDERR, "Keys missing in en.json (" . count($missingInEn) . "):\n");
	foreach ($missingInEn as $key) {
		fwrite(STDERR, "  - " . $key . "\n");
	}
}

if (!$ok) {
	fwrite(STDERR, "\nl10n parity check FAILED.\n");
	exit(1);
}

echo "l10n parity OK (" . count($enKeys) . " keys).\n";
exit(0);
