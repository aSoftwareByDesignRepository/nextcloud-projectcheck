<?php

declare(strict_types=1);

/**
 * Mutation gauntlet for ProjectFileController upload redirect safety + route naming.
 *
 * Usage (from app root, host with Docker):
 *   php tests/Mutation/run-project-file-upload-mutations.php
 */

$appRoot = dirname(__DIR__, 2);

function run_project_file_tests(string $appRoot, string $filter): int
{
	$nextcloudRoot = dirname($appRoot, 2);
	if (!is_file('/.dockerenv') && is_file($nextcloudRoot . '/docker-compose.yml')) {
		passthru(
			'cd ' . escapeshellarg($nextcloudRoot)
			. ' && docker compose exec -u www-data -w /var/www/html/custom_apps/projectcheck nextcloud'
			. ' php -d opcache.enable_cli=0 -d opcache.enable=0 vendor/bin/phpunit -c phpunit.xml --filter '
			. escapeshellarg($filter),
			$code,
		);
		return (int) $code;
	}
	$phpunit = $appRoot . '/vendor/bin/phpunit';
	passthru(
		'php -d opcache.enable_cli=0 ' . escapeshellarg($phpunit)
		. ' -c ' . escapeshellarg($appRoot . '/phpunit.xml')
		. ' --filter ' . escapeshellarg($filter),
		$code,
	);
	return (int) $code;
}

$phpFilter = 'ProjectFileControllerTest|RoutesControllerNamingContractTest|ProjectFileRouteDispatchIntegrationTest';

$mutations = [
	[
		'name' => 'restore_open_redirect_on_success',
		'file' => 'lib/Controller/ProjectFileController.php',
		'search' => "return new RedirectResponse(\$this->urlGenerator->linkToRoute(\n\t\t\t'projectcheck.project.show',\n\t\t\t[\n\t\t\t\t'id' => \$projectId,\n\t\t\t\t'message' => 'success',\n\t\t\t]\n\t\t));",
		'replace' => "\$url = \$this->request->getParam('redirect', \$this->urlGenerator->linkToRoute('projectcheck.project.show', ['id' => \$projectId]));\n\t\treturn new RedirectResponse(\$url . '?message=success');",
	],
	[
		'name' => 'restore_open_redirect_on_error',
		'file' => 'lib/Controller/ProjectFileController.php',
		'search' => "return new RedirectResponse(\$this->urlGenerator->linkToRoute(\n\t\t\t\t'projectcheck.project.show',\n\t\t\t\t[\n\t\t\t\t\t'id' => \$projectId,\n\t\t\t\t\t'message' => 'error',\n\t\t\t\t\t'error_text' => \$this->l->t('File upload failed. Please check your input and try again.'),\n\t\t\t\t]\n\t\t\t));",
		'replace' => "\$url = \$this->request->getParam('redirect', 'https://evil.example/phish');\n\t\t\treturn new RedirectResponse(\$url);",
	],
	[
		'name' => 'ajax_success_returns_false',
		'file' => 'lib/Controller/ProjectFileController.php',
		'search' => "return new DataResponse(['success' => true]);",
		'replace' => "return new DataResponse(['success' => false]);",
	],
	[
		'name' => 'compact_projectfile_route',
		'file' => 'appinfo/routes.php',
		'search' => "'name' => 'project_file#",
		'replace' => "'name' => 'projectfile#",
	],
];

$backupDir = $appRoot . '/tests/Mutation/.originals';
if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
	fwrite(STDERR, "Cannot create mutation backup dir\n");
	exit(2);
}

/** @var array<string, string> $originals */
$originals = [];
foreach ($mutations as $mutant) {
	$path = $appRoot . '/' . $mutant['file'];
	if (!isset($originals[$path])) {
		$content = file_get_contents($path);
		if ($content === false) {
			fwrite(STDERR, "Cannot read {$path}\n");
			exit(2);
		}
		$originals[$path] = $content;
		file_put_contents(
			$backupDir . '/' . str_replace(['/', '\\'], '__', $mutant['file']) . '.bak',
			$content
		);
	}
}

$restore = static function () use ($originals): void {
	foreach ($originals as $path => $content) {
		file_put_contents($path, $content);
	}
};
register_shutdown_function($restore);

echo "Baseline…\n";
if (run_project_file_tests($appRoot, $phpFilter) !== 0) {
	fwrite(STDERR, "Baseline failed\n");
	$restore();
	exit(1);
}

$killed = 0;
$survived = [];
foreach ($mutations as $mutant) {
	$path = $appRoot . '/' . $mutant['file'];
	$original = $originals[$path];
	$mutated = str_replace($mutant['search'], $mutant['replace'], $original);
	if ($mutated === $original) {
		fwrite(STDERR, "MISS: {$mutant['name']}\n");
		$survived[] = $mutant['name'] . ' (miss)';
		continue;
	}
	file_put_contents($path, $mutated);
	$code = run_project_file_tests($appRoot, $phpFilter);
	file_put_contents($path, $original);
	if ($code === 0) {
		$survived[] = $mutant['name'];
		fwrite(STDERR, "SURVIVED: {$mutant['name']}\n");
	} else {
		$killed++;
		echo "Killed: {$mutant['name']}\n";
	}
}

$restore();
echo "Killed {$killed} / " . count($mutations) . "\n";
exit($survived === [] ? 0 : 1);
