#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Add runtime translation strings (from PHP/JS sources) to all l10n/*.json catalogs.
 *
 * Missing keys are appended after existing entries.
 * English msgids are stored as values in en.json; other locales default to English
 * unless --translations=PATH points at JSON: { "de": { "msgid": "…" }, … }.
 *
 * Usage (from app root):
 *   php scripts/sync-l10n-from-runtime.php
 *   php scripts/sync-l10n-from-runtime.php --translations=l10n/_runtime_translations.json
 *   php scripts/sync-l10n-from-runtime.php --dry-run
 */

$appRoot = dirname(__DIR__);
$dryRun = in_array('--dry-run', $argv ?? [], true);
$translationsPath = null;
foreach ($argv ?? [] as $arg) {
	if (str_starts_with($arg, '--translations=')) {
		$translationsPath = substr($arg, strlen('--translations='));
	}
}

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

$scanDirs = [$appRoot . '/lib', $appRoot . '/templates', $appRoot . '/js'];
$found = [];
$scanFiles = function (string $path) use ($patterns, &$found): void {
	$content = (string)file_get_contents($path);
	foreach ($patterns as $pattern) {
		if (preg_match_all($pattern, $content, $matches)) {
			foreach ($matches[1] as $raw) {
				$found[stripcslashes($raw)] = true;
			}
		}
	}
};

foreach ($scanDirs as $dir) {
	if (!is_dir($dir)) {
		continue;
	}
	$iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
	foreach ($iter as $file) {
		if (!$file->isFile()) {
			continue;
		}
		if (!preg_match('/\.(php|js)$/', $file->getPathname())) {
			continue;
		}
		$scanFiles($file->getPathname());
	}
}

$missing = [];
foreach (array_keys($found) as $msgid) {
	if (!array_key_exists($msgid, $catalog)) {
		$missing[] = $msgid;
	}
}
sort($missing);

if ($missing === []) {
	echo "No missing runtime strings — l10n catalogs are up to date.\n";
	exit(0);
}

$overrides = [];
if ($translationsPath !== null) {
	$fullPath = str_starts_with($translationsPath, '/') ? $translationsPath : $appRoot . '/' . $translationsPath;
	if (!is_file($fullPath)) {
		fwrite(STDERR, "Translations file not found: {$fullPath}\n");
		exit(1);
	}
	$overrides = json_decode((string)file_get_contents($fullPath), true, 512, JSON_THROW_ON_ERROR);
}

echo ($dryRun ? '[dry-run] ' : '') . 'Adding ' . count($missing) . " missing runtime string(s) to l10n catalogs.\n";

if ($dryRun) {
	foreach ($missing as $msgid) {
		echo "  + {$msgid}\n";
	}
	exit(0);
}

foreach ($locales as $lang) {
	$path = $appRoot . '/l10n/' . $lang . '.json';
	if (!is_file($path)) {
		fwrite(STDERR, "Missing locale file: {$path}\n");
		exit(1);
	}
	$data = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
	$trans = $data['translations'] ?? [];
	foreach ($missing as $msgid) {
		if ($lang === 'en') {
			$trans[$msgid] = $msgid;
		} elseif (isset($overrides[$lang][$msgid])) {
			$trans[$msgid] = $overrides[$lang][$msgid];
		} else {
			$trans[$msgid] = $msgid;
		}
	}
	$data['translations'] = $trans;
	$encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
	file_put_contents($path, $encoded . "\n");
	echo "Updated {$path} (+ " . count($missing) . " keys, total " . count($trans) . ")\n";
}

echo "Run: php scripts/regenerate-l10n-js.php && php scripts/check-l10n-runtime.php --all && php scripts/check-l10n-parity.php\n";
