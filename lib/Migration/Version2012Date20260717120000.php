<?php

declare(strict_types=1);

/**
 * Settlement tracking schema: time-entry billing columns and project counters.
 *
 * Adds `billing_status` (+ timestamps / actor) to `pc_time_entries` and the
 * materialized `stl_*` settlement counters to `pc_projects`. Existing rows
 * default to `open`; the post-migration repair step recomputes the counters
 * from entries and backfills the creator → Manager role, so derived postures
 * are trustworthy immediately after `occ upgrade`.
 *
 * @see PcCoreSchemaBootstrap::ensureSettlementColumns()
 * @see \OCA\ProjectCheck\Repair\EnsureProjectCheckSchema
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2012Date20260717120000 extends SimpleMigrationStep
{
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
	{
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!PcCoreSchemaBootstrap::ensureSettlementColumns($schema)) {
			return null;
		}

		$output->info('ProjectCheck: added settlement columns (pc_time_entries billing status, pc_projects counters).');

		return $schema;
	}
}
