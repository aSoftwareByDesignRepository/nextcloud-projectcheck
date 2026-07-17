<?php

declare(strict_types=1);

/**
 * Idempotent schema guard for ProjectCheck core tables.
 *
 * Used by migration {@see Version2010Date20260601120000} and the post-migration
 * repair step so every `occ upgrade` reconciles legacy names, missing tables,
 * and columns skipped when earlier migrations ran against an empty schema.
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Migration;

use OC\DB\Connection;
use OC\DB\ConnectionAdapter;
use OC\DB\SchemaWrapper;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use RuntimeException;

final class ProjectCheckSchemaEnsurer
{
	/** @var list<string> */
	public const REQUIRED_TABLES = ProjectCheckTableCatalog::REQUIRED_TABLES;

	public function __construct(
		private IDBConnection $db,
		private IConfig $config,
	) {
	}

	/**
	 * @throws RuntimeException when schema cannot be brought to a ready state
	 */
	public function ensure(IOutput $output): void
	{
		(new LegacyTableRenamer($this->db, $this->config))->run($output);
		(new RateTableRenamer($this->db, $this->config))->run($output);

		$schemaWrapper = $this->createSchemaWrapper();
		$schema = $schemaWrapper;
		$changed = PcCoreSchemaBootstrap::apply($schema);
		$changed = PcCoreSchemaBootstrap::ensureProjectColumns($schema) || $changed;
		$changed = PcCoreSchemaBootstrap::ensureRateTables($schema) || $changed;
		$settlementChanged = PcCoreSchemaBootstrap::ensureSettlementColumns($schema);
		$changed = $settlementChanged || $changed;

		if ($changed) {
			$this->db->migrateToSchema($schemaWrapper->getWrappedSchema());
			$output->info('ProjectCheck: applied missing tables/columns via schema migration.');
		}

		$this->assertReady($output);

		// Fresh settlement columns (or a cleared ready flag) require a full
		// counter recompute before list filters / posture chips are trustworthy.
		if ($settlementChanged) {
			$this->config->deleteAppValue(SettlementBootstrap::APP_ID, SettlementBootstrap::READY_FLAG);
		}

		// One-time settlement data bootstrap (counter recompute + creator →
		// Manager role backfill + optional overhead excluded backfill).
		// Flag-guarded, so this is a cheap no-op on every subsequent run.
		(new SettlementBootstrap($this->db, $this->config))->runOnce($output);
	}

	public function isReady(): bool
	{
		foreach (self::REQUIRED_TABLES as $table) {
			if (!$this->db->tableExists($table)) {
				return false;
			}
		}

		foreach (array_keys(LegacyTableRenamer::RENAMES) as $legacy) {
			if ($this->db->tableExists($legacy)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * True when settlement columns from migration 2012 are present.
	 * Used by {@see \OCA\ProjectCheck\Service\SchemaGuardService} so HTTP
	 * traffic never serves DEFAULT-0 counters before the schema lands.
	 */
	public function hasSettlementSchema(): bool
	{
		if (!$this->db->tableExists('pc_time_entries') || !$this->db->tableExists('pc_projects')) {
			return false;
		}

		try {
			$schema = $this->createSchemaWrapper();
			if (!$schema->hasTable('pc_time_entries') || !$schema->hasTable('pc_projects')) {
				return false;
			}
			$entries = $schema->getTable('pc_time_entries');
			$projects = $schema->getTable('pc_projects');
			return $entries->hasColumn('billing_status')
				&& $entries->hasColumn('billed_at')
				&& $entries->hasColumn('paid_at')
				&& $projects->hasColumn('stl_open_hours')
				&& $projects->hasColumn('stl_invoiced_hours')
				&& $projects->hasColumn('stl_paid_hours')
				&& $projects->hasColumn('stl_excluded_hours')
				&& $projects->hasColumn('stl_open_amount')
				&& $projects->hasColumn('stl_invoiced_amount')
				&& $projects->hasColumn('stl_paid_amount')
				&& $projects->hasColumn('stl_excluded_amount');
		} catch (\Throwable $e) {
			return false;
		}
	}

	/**
	 * @throws RuntimeException
	 */
	private function assertReady(IOutput $output): void
	{
		$missing = [];
		foreach (self::REQUIRED_TABLES as $table) {
			if (!$this->db->tableExists($table)) {
				$missing[] = $table;
			}
		}

		if ($missing !== []) {
			throw new RuntimeException(
				'ProjectCheck schema incomplete after repair — missing table(s): '
				. implode(', ', $missing)
				. '. Run `occ upgrade` as the web-server user and check '
				. '`occ migrations:status projectcheck`.'
			);
		}

		$output->info('ProjectCheck: core schema verified (' . implode(', ', self::REQUIRED_TABLES) . ').');
	}

	private function createSchemaWrapper(): SchemaWrapper
	{
		$connection = $this->db;
		if ($connection instanceof ConnectionAdapter) {
			$connection = $connection->getInner();
		}
		if (!$connection instanceof Connection) {
			throw new RuntimeException(
				'ProjectCheck schema repair requires OC\DB\Connection (got '
				. get_debug_type($connection) . ').'
			);
		}

		return new SchemaWrapper($connection);
	}
}
