<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap: Composer autoload + nextcloud/ocp (no DB, no full Nextcloud init).
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

$candidates = [];
$nextcloudRoot = getenv('NEXTCLOUD_ROOT') ?: '';
if ($nextcloudRoot !== '') {
	$candidates[] = rtrim($nextcloudRoot, '/\\') . '/lib/base.php';
}
$candidates[] = __DIR__ . '/../../lib/base.php';
$candidates[] = __DIR__ . '/../../../lib/base.php';

$base = null;
foreach ($candidates as $candidate) {
	if (is_file($candidate)) {
		$base = $candidate;
		break;
	}
}

if ($base !== null) {
	require_once $base;
	$integrationBootstrap = dirname(__DIR__, 3) . '/scripts/phpunit-integration-bootstrap.php';
	if (is_file($integrationBootstrap)) {
		require_once $integrationBootstrap;
	}
}

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) {
	throw new RuntimeException(
		'Run "composer install" in apps/projectcheck before running PHPUnit.'
	);
}

require_once $autoload;

if ($base === null) {
	$ocpStubs = dirname(__DIR__, 3) . '/scripts/phpunit-ocp-doctrine-stubs.php';
	if (is_file($ocpStubs)) {
		require_once $ocpStubs;
	}
}

if (!class_exists(\Test\TestCase::class)) {
	$shim = __DIR__ . '/shim/TestCase.php';
	if (is_file($shim)) {
		require_once $shim;
	}
}

/* Allow autoloading here: inside a full Nextcloud checkout the real Symfony Console
 * is available via 3rdparty and must win, otherwise the eval'd stub (no constructor)
 * shadows it and `parent::__construct()` in occ commands fatals. */
if (!class_exists(\Symfony\Component\Console\Command\Command::class)) {
	eval('namespace Symfony\Component\Console\Command; class Command { public function __construct() {} }');
}

/* Nextcloud’s OCP\Util::addStyle() delegates to \OC_Util, which is not present in this unit-test
 * environment. Provide a no-op so listeners that register styles (e.g. common/colors.css) can run. */
if (!\class_exists(\OC_Util::class, false)) {
	// phpcs:ignore
	class OC_Util {
		public static function addStyle(string $application, ?string $file = null, bool $prepend = false): void {
		}
	}
}
