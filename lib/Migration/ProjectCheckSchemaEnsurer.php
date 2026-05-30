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

		if ($changed) {
			$this->db->migrateToSchema($schemaWrapper->getWrappedSchema());
			$output->info('ProjectCheck: applied missing tables/columns via schema migration.');
		}

		$this->assertReady($output);
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
