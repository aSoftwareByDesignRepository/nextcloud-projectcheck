<?php

declare(strict_types=1);

/**
 * Qualified column list for `pc_projects` joins.
 *
 * Nextcloud's query builder does not expand `alias.*` on PostgreSQL; it emits
 * invalid SQL (`"p".,`). Always select explicit columns when joining.
 *
 * @internal
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Db;

final class ProjectQueryColumns
{
	/** @var list<string> */
	public const COLUMNS = [
		'id',
		'name',
		'short_description',
		'detailed_description',
		'customer_id',
		'hourly_rate',
		'total_budget',
		'available_hours',
		'category',
		'priority',
		'status',
		'start_date',
		'end_date',
		'tags',
		'created_by',
		'created_at',
		'updated_at',
		'project_type',
		'cost_rate_mode',
		'stl_open_hours',
		'stl_invoiced_hours',
		'stl_paid_hours',
		'stl_excluded_hours',
		'stl_open_amount',
		'stl_invoiced_amount',
		'stl_paid_amount',
		'stl_excluded_amount',
		'stl_updated_at',
	];

	/**
	 * @return list<string>
	 */
	public static function qualified(string $alias = 'p'): array
	{
		return array_map(
			static fn (string $column): string => $alias . '.' . $column,
			self::COLUMNS,
		);
	}

	/**
	 * @param string ...$extra e.g. `c.name as customer_name`
	 * @return list<string>
	 */
	public static function withExtra(string $alias = 'p', string ...$extra): array
	{
		return array_merge(self::qualified($alias), $extra);
	}
}
