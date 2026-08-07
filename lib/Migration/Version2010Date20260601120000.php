<?php

declare(strict_types=1);

/**
 * Repair migration: ensure `pc_*` tables exist and legacy names are renamed.
 *
 * @see ProjectCheckSchemaEnsurer
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

class Version2010Date20260601120000 extends SimpleMigrationStep
{
	public function __construct(
		private IDBConnection $db,
		private IConfig $config,
	) {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
	{
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!PcCoreSchemaBootstrap::needsBootstrap($schema)) {
			return null;
		}

		$output->info('ProjectCheck: bootstrapping core pc_* tables (no legacy or prefixed project schema found).');
		PcCoreSchemaBootstrap::apply($schema);

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void
	{
		(new ProjectCheckSchemaEnsurer($this->db, $this->config))->ensure($output);
	}
}
