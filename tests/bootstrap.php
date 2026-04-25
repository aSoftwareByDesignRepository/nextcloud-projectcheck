<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap: Composer autoload + nextcloud/ocp (no DB, no full Nextcloud init).
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) {
	throw new RuntimeException(
		'Run "composer install" in apps/projectcheck before running PHPUnit.'
	);
}

require_once $autoload;

/* Nextcloud’s OCP\Util::addStyle() delegates to \OC_Util, which is not present in this unit-test
 * environment. Provide a no-op so listeners that register styles (e.g. common/colors.css) can run. */
if (!\class_exists(\OC_Util::class, false)) {
	// phpcs:ignore
	class OC_Util {
		public static function addStyle(string $application, ?string $file = null, bool $prepend = false): void {
		}
	}
}
