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
