<?php

declare(strict_types=1);

/**
 * Portable SQL fragments for supported Nextcloud database backends.
 *
 * Nextcloud officially targets MySQL/MariaDB, PostgreSQL, SQLite, and Oracle.
 * Raw SQL inside {@see \OCP\DB\QueryBuilder\IQueryBuilder::createFunction()} must
 * never use MySQL-only constructs (e.g. {@see YEAR()}) or MySQL identifier
 * backticks — PostgreSQL rejects both.
 *
 * @internal
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Db;

use OCP\IDBConnection;

final class SqlPortableExpressions
{
	/**
	 * Calendar year from a date/timestamp column (SQL standard on PG/MySQL/MariaDB/Oracle).
	 *
	 * SQLite has no portable EXTRACT(); use strftime and cast to integer so
	 * grouped statistics behave like other engines.
	 */
	public static function yearFromColumn(IDBConnection $db, string $qualifiedColumn): string
	{
		return match ($db->getDatabaseProvider()) {
			IDBConnection::PLATFORM_SQLITE => "CAST(strftime('%Y', " . $qualifiedColumn . ') AS INTEGER)',
			default => 'EXTRACT(YEAR FROM ' . $qualifiedColumn . ')',
		};
	}

	/**
	 * Single-row display label: snapshot, live profile, or uid fallback.
	 * Join aliases must be s (snapshots), u (users), t (time entries).
	 */
	public static function coalesceUserDisplayName(): string
	{
		return 'COALESCE(s.display_name, u.displayname, t.user_id)';
	}

	/**
	 * Aggregated display label for queries grouped by t.user_id.
	 */
	public static function coalesceUserDisplayNameAggregated(): string
	{
		return 'COALESCE(MAX(s.display_name), MAX(u.displayname), t.user_id)';
	}

	/**
	 * MAX(...) wrapper for grouped employee display ordering.
	 */
	public static function maxCoalesceUserDisplayName(): string
	{
		return 'MAX(COALESCE(s.display_name, u.displayname, t.user_id))';
	}
}
