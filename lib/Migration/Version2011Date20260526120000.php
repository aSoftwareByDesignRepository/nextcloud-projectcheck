<?php

declare(strict_types=1);

/**
 * Rename overlong rate-history tables from early 2.0.x builds to portable names.
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Migration;

use Closure;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2011Date20260526120000 extends SimpleMigrationStep
{
	public function __construct(
		private IDBConnection $db,
		private IConfig $config,
	) {
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void
	{
		$renamer = new RateTableRenamer($this->db, $this->config);
		$renamer->run($output);
	}
}
