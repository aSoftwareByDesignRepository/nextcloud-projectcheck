<?php

declare(strict_types=1);

/**
 * Mutation gauntlet for ProjectCheck Activity Provider registration + UnknownActivityException.
 *
 * Usage:
 *   docker compose exec -u www-data nextcloud php /var/www/html/custom_apps/projectcheck/tests/Mutation/run-activity-provider-mutations.php
 */

$appRoot = dirname(__DIR__, 2);
$phpunit = $appRoot . '/vendor/bin/phpunit';

/**
 * @param list<string> $filters
 */
function run_filters(string $appRoot, string $phpunit, array $filters): int
{
	$filter = implode('|', $filters);
	$cmd = 'php -d opcache.enable_cli=0 -d opcache.enable=0 '
		. escapeshellarg($phpunit)
		. ' -c ' . escapeshellarg($appRoot . '/phpunit.xml')
		. ' --filter ' . escapeshellarg($filter);
	passthru($cmd, $code);
	return (int)$code;
}

function restore(string $source, string $backup): void
{
	if (is_file($backup)) {
		rename($backup, $source);
	}
}

$filters = [
	'ActivityProviderTest',
	'ActivityProviderRegistrationContractTest',
];

echo "== baseline ==\n";
if (run_filters($appRoot, $phpunit, $filters) !== 0) {
	fwrite(STDERR, "Baseline failed; aborting mutations\n");
	exit(1);
}

$mutations = [
	[
		'file' => $appRoot . '/lib/Activity/Provider.php',
		'from' => "if (\$event->getApp() !== Application::APP_ID) {\n\t\t\tthrow new UnknownActivityException();\n\t\t}",
		'to' => "if (\$event->getApp() !== Application::APP_ID) {\n\t\t\tthrow new \\InvalidArgumentException();\n\t\t}",
		'label' => 'revert foreign-app to InvalidArgumentException',
	],
	[
		'file' => $appRoot . '/lib/Activity/Provider.php',
		'from' => "default:\n\t\t\t\tthrow new UnknownActivityException();",
		'to' => "default:\n\t\t\t\t\$subject = \$event->getSubject();",
		'label' => 'unknown subject returns raw string again',
	],
	[
		'file' => $appRoot . '/appinfo/info.xml',
		'from' => "\t<activity>\n\t\t<providers>\n\t\t\t<provider>OCA\\ProjectCheck\\Activity\\Provider</provider>\n\t\t</providers>\n\t</activity>\n",
		'to' => '',
		'label' => 'drop activity provider registration',
	],
];

$failed = [];
foreach ($mutations as $m) {
	$source = $m['file'];
	$backup = $source . '.mutation-bak';
	echo "\n== mutation: {$m['label']} ==\n";
	$original = file_get_contents($source);
	if ($original === false || !str_contains($original, $m['from'])) {
		$failed[] = $m['label'] . ' (anchor missing)';
		continue;
	}
	file_put_contents($backup, $original);
	file_put_contents($source, str_replace($m['from'], $m['to'], $original));
	$code = run_filters($appRoot, $phpunit, $filters);
	restore($source, $backup);
	if ($code === 0) {
		$failed[] = $m['label'];
		echo "MUTATION SURVIVED: {$m['label']}\n";
	} else {
		echo "killed {$m['label']}\n";
	}
}

if ($failed !== []) {
	fwrite(STDERR, 'Mutations not killed: ' . implode(', ', $failed) . "\n");
	exit(1);
}

echo "\nAll projectcheck activity-provider mutations killed.\n";
exit(0);
