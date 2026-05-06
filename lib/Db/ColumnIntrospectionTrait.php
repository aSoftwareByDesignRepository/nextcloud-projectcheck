<?php

declare(strict_types=1);

/**
 * Shared, portable, request-cached column existence probe.
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Db;

use OCP\IDBConnection;
use Throwable;

/**
 * Provides `columnExists($table, $column)` to mappers / services that need to
 * defensively guard against pre-migration schema states (notably for
 * `pc_projects.project_type`, which has historically been added by an old
 * projectcontrol-era migration that is no longer in the tree, so older
 * installs may still be missing it before `Version2007` runs).
 *
 * Why this trait exists
 * ---------------------
 *  Two private implementations of the same idea grew in `TimeEntryMapper`
 *  and `ProjectService`. Both were either incorrect or expensive:
 *
 *  - `TimeEntryMapper` ran `SELECT * FROM <table> LIMIT 1` and checked the
 *    fetched row for the column key. On an empty table the helper returned
 *    `false` even when the column was present - a silent false negative.
 *  - `ProjectService` queried `sqlite_master`, which does not exist on
 *    MySQL/MariaDB or PostgreSQL. The primary branch always threw on every
 *    production engine, falling through to a `try/catch SELECT $col` probe.
 *
 *  Both were called many times per request, each issuing real SQL.
 *
 * Implementation notes
 * --------------------
 *  - We probe with `SELECT <col> FROM <table> WHERE 1=0`. The query plans
 *    on every supported engine without scanning rows, but parses the column
 *    list, so a missing column produces a portable error we can map to
 *    `false`.
 *  - Results are cached for the lifetime of the request via a static map.
 *    The cache key includes the class name so unit tests that rebind a
 *    mocked `IDBConnection` per case do not see stale entries from an
 *    unrelated suite.
 *  - The using class declares how to obtain its `IDBConnection` by
 *    implementing `getColumnIntrospectionConnection()`. We deliberately
 *    do not assume a `$this->db` property: making the contract explicit
 *    keeps static analysis honest and the audit trail trivially correct.
 *
 * @internal Not part of the app's public surface.
 */
trait ColumnIntrospectionTrait
{
	/**
	 * Per-request result cache keyed by `<className>::<table>::<column>`.
	 *
	 * @var array<string, bool>
	 */
	private static array $columnExistenceCache = [];

	/**
	 * Return the database connection used to introspect the schema.
	 *
	 * Implementations are typically a one-liner returning the existing
	 * `$this->db` already injected into the mapper or service.
	 */
	abstract protected function getColumnIntrospectionConnection(): IDBConnection;

	/**
	 * @return bool `true` iff a column with the given (logical) name exists
	 *              on the given (logical, un-prefixed) table.
	 */
	protected function columnExists(string $table, string $column): bool
	{
		$cacheKey = static::class . '::' . $table . '::' . $column;
		if (array_key_exists($cacheKey, self::$columnExistenceCache)) {
			return self::$columnExistenceCache[$cacheKey];
		}

		try {
			$qb = $this->getColumnIntrospectionConnection()->getQueryBuilder();
			$qb->select($column)
				->from($table)
				->where('1 = 0');
			$stmt = $qb->executeQuery();
			$stmt->closeCursor();
			return self::$columnExistenceCache[$cacheKey] = true;
		} catch (Throwable $e) {
			return self::$columnExistenceCache[$cacheKey] = false;
		}
	}

	/**
	 * Test-only helper to clear the per-request memoization. Production code
	 * should not need this; it exists so unit tests that rebind mocked
	 * connections between scenarios do not see results from a previous case.
	 *
	 * @internal
	 */
	public static function resetColumnExistenceCacheForTesting(): void
	{
		self::$columnExistenceCache = [];
	}
}
