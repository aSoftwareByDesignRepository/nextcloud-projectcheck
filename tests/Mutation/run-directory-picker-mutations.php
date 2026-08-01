<?php

declare(strict_types=1);

/**
 * Mutation gauntlet: ProjectCheck org settings must not reintroduce Manual entry.
 *
 * Usage (Docker from nextcloud/):
 *   docker compose exec -u www-data nextcloud php /var/www/html/custom_apps/projectcheck/tests/Mutation/run-directory-picker-mutations.php
 */

$appRoot = dirname(__DIR__, 2);
$phpunit = $appRoot . '/vendor/bin/phpunit';
if (!is_file($phpunit)) {
	$phpunit = 'phpunit';
}

/**
 * @return list<array{file:string,from:string,to:string,label:string}>
 */
function projectcheck_directory_mutations(string $appRoot): array
{
	return [
		[
			'file' => $appRoot . '/templates/parts/settings/_access-fields.php',
			'from' => 'Directory search is unavailable',
			'to' => 'Manual entry: one user ID per line',
			'label' => 'unavailable_message_becomes_manual_entry',
		],
		[
			'file' => $appRoot . '/templates/parts/settings/_access-fields.php',
			'from' => 'Never type a raw user id',
			'to' => 'Type one account login name per line',
			'label' => 'never_raw_id_hint_removed',
		],
		[
			'file' => $appRoot . '/lib/Service/AccessControlService.php',
			'from' => "public function isAppAdmin(string \$userId): bool\n\t{\n\t\treturn \$this->canManageAppConfiguration(\$userId);\n\t}",
			'to' => "public function isAppAdmin(string \$userId): bool\n\t{\n\t\treturn \$this->isSystemAdministrator(\$userId);\n\t}",
			'label' => 'is_app_admin_drops_dedicated_list',
		],
		[
			'file' => $appRoot . '/lib/Service/SavePolicyUiStrings.php',
			'from' => 'No matches. Try another search — never type a raw user or group id.',
			'to' => 'No matches. Try another search, or use manual entry below.',
			'label' => 'picker_no_results_reintroduces_manual_entry',
		],
		[
			'file' => $appRoot . '/lib/Service/SavePolicyUiStrings.php',
			'from' => "'addButton' => \$l->t('Add'),",
			'to' => "'addButton' => \$l->t('Add'),\n\t\t\t'manualUserIds' => \$l->t('Manual entry: one user ID per line'),",
			'label' => 'manual_user_ids_key_reintroduced',
		],
	];
}

function run_phpunit(string $phpunit, string $appRoot): int
{
	$cmd = escapeshellarg($phpunit)
		. ' -c ' . escapeshellarg($appRoot . '/phpunit.xml')
		. ' --filter "DirectoryPickerContractTest|DedicatedAppAdminContractTest|SavePolicyUiStringsTest"';
	passthru($cmd, $code);
	return (int)$code;
}

$mutations = projectcheck_directory_mutations($appRoot);
$failed = 0;
$killed = 0;

echo "Baseline…\n";
if (run_phpunit($phpunit, $appRoot) !== 0) {
	fwrite(STDERR, "Baseline failed — aborting mutations.\n");
	exit(1);
}

foreach ($mutations as $m) {
	$original = (string)file_get_contents($m['file']);
	if (!str_contains($original, $m['from'])) {
		fwrite(STDERR, "SKIP (needle missing): {$m['label']}\n");
		$failed++;
		continue;
	}
	file_put_contents($m['file'], str_replace($m['from'], $m['to'], $original));
	echo "Mutant {$m['label']}…\n";
	$code = run_phpunit($phpunit, $appRoot);
	file_put_contents($m['file'], $original);
	if ($code === 0) {
		fwrite(STDERR, "SURVIVED: {$m['label']}\n");
		$failed++;
	} else {
		echo "Killed: {$m['label']}\n";
		$killed++;
	}
}

echo "Done: killed={$killed} failed={$failed} total=" . count($mutations) . "\n";
exit($failed === 0 ? 0 : 1);
