<?php

declare(strict_types=1);

/**
 * Mutation gauntlet — ProjectCheck desklet must stay loadable, limited, and theme-safe.
 */

$root = dirname(__DIR__, 2);
$inContainer = file_exists('/.dockerenv');

if ($inContainer) {
	$phpunit = 'php -d opcache.enable_cli=0 -d opcache.enable=0 '
		. escapeshellarg($root . '/vendor/bin/phpunit')
		. ' --colors=never'
		. ' --filter "ProjectWidgetTest|DeskletStylesContractTest|AppIconAssetsContractTest"'
		. ' tests/Unit';
} else {
	$composeDir = dirname($root, 2);
	$phpunit = 'cd ' . escapeshellarg($composeDir)
		. " && docker compose exec -T -u www-data nextcloud bash -lc 'cd /var/www/html/custom_apps/projectcheck"
		. " && php -d opcache.enable_cli=0 -d opcache.enable=0 vendor/bin/phpunit --colors=never"
		. " --filter \"ProjectWidgetTest|DeskletStylesContractTest|AppIconAssetsContractTest\""
		. " tests/Unit'";
}

$runPhp = static function () use ($phpunit): bool {
	passthru($phpunit . ' 2>&1', $code);
	return $code === 0;
};

$runJs = static function () use ($root): bool {
	passthru('node --test ' . escapeshellarg($root . '/tests/js/desklet-chrome.test.mjs') . ' 2>&1', $code);
	return $code === 0;
};

echo "== baseline desklet chrome ==\n";
if (!$runPhp() || !$runJs()) {
	fwrite(STDERR, "Baseline failed\n");
	exit(1);
}

$mutants = [
	[
		'name' => 'drop-style-registration',
		'file' => 'lib/Dashboard/RegistersDeskletStylesTrait.php',
		'suite' => 'php',
		'search' => "Util::addStyle(Application::APP_ID, 'desklet-nextcloud');",
		'replace' => "/* mutant: no desklet CSS */",
	],
	[
		'name' => 'drop-limit-pass-through',
		'file' => 'lib/Dashboard/ProjectWidget.php',
		'suite' => 'php',
		'search' => 'getProjectsByUser($userId, $limit)',
		'replace' => 'getProjectsByUser($userId)',
	],
	[
		'name' => 'swap-widget-button-args',
		'file' => 'lib/Dashboard/ProjectWidget.php',
		'suite' => 'php',
		'search' => "new WidgetButton(\n\t\t\t\tWidgetButton::TYPE_MORE,\n\t\t\t\t\$this->urlGenerator->linkToRouteAbsolute('projectcheck.project.index'),\n\t\t\t\t\$this->l10n->t('View all projects'),\n\t\t\t)",
		'replace' => "new WidgetButton(\n\t\t\t\tWidgetButton::TYPE_MORE,\n\t\t\t\t\$this->l10n->t('View all projects'),\n\t\t\t\t\$this->urlGenerator->linkToRouteAbsolute('projectcheck.project.index'),\n\t\t\t)",
	],
	[
		'name' => 'drop-44px-touch',
		'file' => 'css/desklet-nextcloud.css',
		'suite' => 'js',
		'search' => 'min-height: 44px;',
		'replace' => 'min-height: 24px;',
	],
	[
		'name' => 'drop-invert-filter',
		'file' => 'css/desklet-nextcloud.css',
		'suite' => 'js',
		'search' => 'background-invert-if-dark',
		'replace' => 'background-invert-never',
	],
];

$failed = 0;
foreach ($mutants as $mutant) {
	$path = $root . '/' . $mutant['file'];
	$original = file_get_contents($path);
	if ($original === false || !str_contains($original, $mutant['search'])) {
		fwrite(STDERR, "Needle missing for {$mutant['name']}\n");
		exit(1);
	}
	$count = 0;
	$mutated = str_replace($mutant['search'], $mutant['replace'], $original, $count);
	if ($count < 1) {
		fwrite(STDERR, "No replace for {$mutant['name']}\n");
		exit(1);
	}
	file_put_contents($path, $mutated);
	echo "== mutant: {$mutant['name']} (expect FAIL) ==\n";
	$ok = ($mutant['suite'] === 'js') ? $runJs() : $runPhp();
	file_put_contents($path, $original);
	if ($ok) {
		fwrite(STDERR, "SURVIVOR: {$mutant['name']}\n");
		$failed++;
	} else {
		echo "killed: {$mutant['name']}\n";
	}
}

echo "== post baseline ==\n";
if (!$runPhp() || !$runJs()) {
	fwrite(STDERR, "Post baseline failed\n");
	exit(1);
}

if ($failed > 0) {
	fwrite(STDERR, "Mutation gauntlet failed: {$failed} survivors\n");
	exit(1);
}

echo "Mutation gauntlet OK (" . count($mutants) . " killed)\n";
