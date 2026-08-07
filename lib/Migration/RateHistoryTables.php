<?php

declare(strict_types=1);

/**
 * Portable schema for effective-dated hourly rate history tables.
 *
 * Logical names are kept short so prefixed identifiers stay within Nextcloud's
 * 30-character Oracle portability budget (see nextcloud-db-standards skill).
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Migration;

use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;

final class RateHistoryTables
{
	public const EMPLOYEE = 'pc_emp_rates';

	public const PROJECT_MEMBER = 'pc_pm_rates';

	/** @var array<string, string> Long names from early 2.0.x builds — renamed by Version2011. */
	public const LEGACY_RENAMES = [
		'pc_employee_hourly_rates' => self::EMPLOYEE,
		'pc_project_member_hourly_rates' => self::PROJECT_MEMBER,
	];

	public static function apply(ISchemaWrapper $schema): bool
	{
		$changed = false;
		$changed = self::ensureEmployeeTable($schema) || $changed;
		$changed = self::ensureProjectMemberTable($schema) || $changed;

		return $changed;
	}

	private static function ensureEmployeeTable(ISchemaWrapper $schema): bool
	{
		if ($schema->hasTable(self::EMPLOYEE)) {
			return false;
		}

		$table = $schema->createTable(self::EMPLOYEE);
		$table->addColumn('id', Types::BIGINT, [
			'autoincrement' => true,
			'notnull' => true,
			'length' => 20,
			'unsigned' => true,
		]);
		$table->addColumn('user_id', Types::STRING, [
			'notnull' => true,
			'length' => 64,
		]);
		$table->addColumn('hourly_rate', Types::DECIMAL, [
			'notnull' => true,
			'precision' => 12,
			'scale' => 4,
			'default' => '0',
		]);
		$table->addColumn('effective_from', Types::DATE, ['notnull' => true]);
		$table->addColumn('created_by', Types::STRING, [
			'notnull' => true,
			'length' => 64,
		]);
		$table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
		$table->setPrimaryKey(['id'], 'pc_emp_rates_pk');
		$table->addUniqueIndex(['user_id', 'effective_from'], 'pc_emp_rates_uq');
		$table->addIndex(['user_id'], 'pc_emp_rates_uidx');

		return true;
	}

	private static function ensureProjectMemberTable(ISchemaWrapper $schema): bool
	{
		if ($schema->hasTable(self::PROJECT_MEMBER)) {
			return false;
		}

		$table = $schema->createTable(self::PROJECT_MEMBER);
		$table->addColumn('id', Types::BIGINT, [
			'autoincrement' => true,
			'notnull' => true,
			'length' => 20,
			'unsigned' => true,
		]);
		$table->addColumn('project_id', Types::BIGINT, [
			'notnull' => true,
			'length' => 20,
			'unsigned' => true,
		]);
		$table->addColumn('user_id', Types::STRING, [
			'notnull' => true,
			'length' => 64,
		]);
		$table->addColumn('hourly_rate', Types::DECIMAL, [
			'notnull' => true,
			'precision' => 12,
			'scale' => 4,
			'default' => '0',
		]);
		$table->addColumn('effective_from', Types::DATE, ['notnull' => true]);
		$table->addColumn('created_by', Types::STRING, [
			'notnull' => true,
			'length' => 64,
		]);
		$table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
		$table->setPrimaryKey(['id'], 'pc_pm_rates_pk');
		$table->addUniqueIndex(['project_id', 'user_id', 'effective_from'], 'pc_pm_rates_uq');
		$table->addIndex(['project_id', 'user_id'], 'pc_pm_rates_puidx');

		return true;
	}
}
