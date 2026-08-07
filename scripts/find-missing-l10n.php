<?php

declare(strict_types=1);

$en = json_decode((string) file_get_contents(__DIR__ . '/../l10n/en.json'), true, 512, JSON_THROW_ON_ERROR)['translations'];
$dirs = [__DIR__ . '/../templates', __DIR__ . '/../lib/Controller', __DIR__ . '/../lib/Service'];
$missing = [];
foreach ($dirs as $dir) {
	$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
	foreach ($it as $f) {
		if (!$f->isFile() || !str_ends_with($f->getPathname(), '.php')) {
			continue;
		}
		$c = (string) file_get_contents($f->getPathname());
		if (preg_match_all('/->t\(\s*\'((?:\\\\\'|[^\'])*)\'/', $c, $m)) {
			foreach ($m[1] as $s) {
				$s = str_replace("\\'", "'", $s);
				if (!isset($en[$s])) {
					$missing[$s] = 1;
				}
			}
		}
	}
}
ksort($missing);
file_put_contents('/tmp/pc-missing-l10n.txt', implode("\n", array_keys($missing)));
echo count($missing) . " missing keys written to /tmp/pc-missing-l10n.txt\n";
