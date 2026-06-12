<?php

declare(strict_types=1);

/**
 * Fails when PHP/JS source references translation strings that are missing from l10n/en.json.
 *
 * By default only security-critical paths are checked (middleware, access gate, API errors).
 * Pass --all to scan lib/, templates/, and js/ (broader catalog drift audit).
 *
 * Exit codes:
 *   0  all scanned runtime strings present in en.json
 *   1  missing strings (printed to STDERR)
 *
 * Usage:
 *   php scripts/check-l10n-runtime.php
 *   php scripts/check-l10n-runtime.php --all
 */

$appRoot = dirname(__DIR__);
$scanAll = in_array('--all', $argv ?? [], true);
$enPath = $appRoot . '/l10n/en.json';
$en = json_decode((string)file_get_contents($enPath), true, 512, JSON_THROW_ON_ERROR);
$catalog = $en['translations'] ?? [];

// (?<![a-zA-Z]) avoids matching Util::addScript('projectcheck', 'common/…') as t().
$patterns = [
	'/\$this->l10n->t\(\s*\'((?:\\\\\'|[^\'])*)\'\s*\)/',
	'/\$this->l10n->t\(\s*"((?:\\\\"|[^"])*)"\s*\)/',
	'/\$this->l10n->t\(\s*\'((?:\\\\\'|[^\'])*)\'\s*,/',
	'/\$this->l10n->t\(\s*"((?:\\\\"|[^"])*)"\s*,/',
	'/\$l->t\(\s*\'((?:\\\\\'|[^\'])*)\'\s*\)/',
	'/\$l->t\(\s*"((?:\\\\"|[^"])*)"\s*\)/',
	'/\$l->t\(\s*\'((?:\\\\\'|[^\'])*)\'\s*,/',
	'/\$l->t\(\s*"((?:\\\\"|[^"])*)"\s*,/',
	'/(?<![a-zA-Z])window\.t\(\s*[\'"]projectcheck[\'"]\s*,\s*\'((?:\\\\\'|[^\'])*)\'\s*\)/',
	'/(?<![a-zA-Z])window\.t\(\s*[\'"]projectcheck[\'"]\s*,\s*"((?:\\\\"|[^"])*)"\s*\)/',
	'/(?<![a-zA-Z])window\.t\(\s*[\'"]projectcheck[\'"]\s*,\s*\'((?:\\\\\'|[^\'])*)\'\s*,/',
	'/(?<![a-zA-Z])window\.t\(\s*[\'"]projectcheck[\'"]\s*,\s*"((?:\\\\"|[^"])*)"\s*,/',
	'/(?<![a-zA-Z])t\(\s*[\'"]projectcheck[\'"]\s*,\s*\'((?:\\\\\'|[^\'])*)\'\s*\)/',
	'/(?<![a-zA-Z])t\(\s*[\'"]projectcheck[\'"]\s*,\s*"((?:\\\\"|[^"])*)"\s*\)/',
	'/(?<![a-zA-Z])t\(\s*[\'"]projectcheck[\'"]\s*,\s*\'((?:\\\\\'|[^\'])*)\'\s*,/',
	'/(?<![a-zA-Z])t\(\s*[\'"]projectcheck[\'"]\s*,\s*"((?:\\\\"|[^"])*)"\s*,/',
];

$scanDirs = $scanAll
	? [$appRoot . '/lib', $appRoot . '/templates', $appRoot . '/js']
	: [$appRoot . '/lib/Middleware', $appRoot . '/templates/access-denied.php', $appRoot . '/js/common/api.js'];

$found = [];
$scanFiles = function (string $path) use ($patterns, &$found): void {
	if (!is_file($path)) {
		fwrite(STDERR, "Missing scan path: {$path}\n");
		exit(1);
	}
	$content = (string)file_get_contents($path);
	foreach ($patterns as $pattern) {
		if (preg_match_all($pattern, $content, $matches)) {
			foreach ($matches[1] as $raw) {
				$msgid = stripcslashes($raw);
				$found[$msgid] = true;
			}
		}
	}
};

foreach ($scanDirs as $dir) {
	if (is_file($dir)) {
		$scanFiles($dir);
		continue;
	}
	if (!is_dir($dir)) {
		continue;
	}
	$iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
	foreach ($iter as $file) {
		if (!$file->isFile()) {
			continue;
		}
		$path = $file->getPathname();
		if (!preg_match('/\.(php|js)$/', $path)) {
			continue;
		}
		$scanFiles($path);
	}
}

$missing = [];
foreach (array_keys($found) as $msgid) {
	if (!array_key_exists($msgid, $catalog)) {
		$missing[] = $msgid;
	}
}
sort($missing);

if ($missing !== []) {
	fwrite(STDERR, 'Runtime strings missing from l10n/en.json (' . count($missing) . "):\n");
	foreach ($missing as $msgid) {
		fwrite(STDERR, "  - {$msgid}\n");
	}
	fwrite(STDERR, "\nl10n runtime check FAILED.\n");
	exit(1);
}

$scope = $scanAll ? 'full' : 'security-critical';
echo 'l10n runtime OK (' . count($found) . " unique strings, {$scope} scope, all in en.json).\n";
exit(0);
