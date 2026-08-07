<?php

declare(strict_types=1);

/**
 * Final step of the legacy → app-prefixed table normalization.
 *
 * @see LegacyTableRenamer for engine semantics, idempotency, and recovery rules.
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2006Date20260505224500 extends SimpleMigrationStep
{
	public function __construct(
		private IDBConnection $db,
		private IConfig $config,
	) {
	}

	/**
	 * Intentionally a no-op: renames are raw DDL in {@see LegacyTableRenamer}.
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
	{
		return null;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void
	{
		(new LegacyTableRenamer($this->db, $this->config))->run($output);
	}
}
