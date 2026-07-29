<?php

declare(strict_types=1);

/**
 * Creates core `pc_*` tables when neither legacy nor prefixed tables exist.
 *
 * This covers installs where early migrations were marked complete without
 * creating schema (e.g. a no-op {@see Version2006Date20260505224500} run) while
 * application code already expects `pc_projects` and related tables.
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Migration;

use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;

final class PcCoreSchemaBootstrap
{
	/**
	 * True when the database has no project tables at all (legacy or prefixed).
	 */
	public static function needsBootstrap(ISchemaWrapper $schema): bool
	{
		return !$schema->hasTable('pc_projects') && !$schema->hasTable('projects');
	}

	public static function apply(ISchemaWrapper $schema): bool
	{
		$changed = false;
		$changed = self::ensureCustomers($schema) || $changed;
		$changed = self::ensureProjects($schema) || $changed;
		$changed = self::ensureProjectMembers($schema) || $changed;
		$changed = self::ensureTimeEntries($schema) || $changed;
		$changed = self::ensureProjectFiles($schema) || $changed;
		$changed = self::ensureLicenseTables($schema) || $changed;
		$changed = self::ensureMobileIdempotencyTable($schema) || $changed;
		return $changed;
	}

	/**
	 * PC2 license state + mobile seats (Version2013) — required by REQUIRED_TABLES.
	 * Idempotent repair when migration was marked complete without creating tables.
	 */
	public static function ensureLicenseTables(ISchemaWrapper $schema): bool
	{
		$changed = false;

		if (!$schema->hasTable('pc_license_state')) {
			$t = $schema->createTable('pc_license_state');
			$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
			$t->addColumn('customer_id', Types::STRING, ['length' => 64, 'notnull' => true]);
			$t->addColumn('issued_at', Types::STRING, ['length' => 10, 'notnull' => true]);
			$t->addColumn('valid_until', Types::STRING, ['length' => 10, 'notnull' => true]);
			$t->addColumn('mobile_seats', Types::INTEGER, ['notnull' => true, 'default' => 0]);
			$t->addColumn('payload_b64', Types::TEXT, ['notnull' => true]);
			$t->addColumn('signature_b64', Types::STRING, ['length' => 255, 'notnull' => true]);
			$t->addColumn('applied_at', Types::INTEGER, ['notnull' => true, 'unsigned' => true, 'default' => 0]);
			$t->addColumn('applied_by', Types::STRING, ['length' => 64, 'notnull' => true]);
			$t->setPrimaryKey(['id'], 'pc_lic_pk');
			$changed = true;
		}

		if (!$schema->hasTable('pc_mobile_seats')) {
			$t = $schema->createTable('pc_mobile_seats');
			$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
			$t->addColumn('uid', Types::STRING, ['length' => 64, 'notnull' => true]);
			$t->addColumn('assigned_at', Types::INTEGER, ['notnull' => true, 'unsigned' => true, 'default' => 0]);
			$t->addColumn('assigned_by', Types::STRING, ['length' => 64, 'notnull' => true]);
			$t->setPrimaryKey(['id'], 'pc_seat_pk');
			$t->addUniqueIndex(['uid'], 'pc_seat_uid_uq');
			$changed = true;
		}

		return $changed;
	}

	/**
	 * Mobile offline-create idempotency (Version2015) — required by REQUIRED_TABLES.
	 * Same repair path as license tables when migration was marked complete without effect.
	 */
	public static function ensureMobileIdempotencyTable(ISchemaWrapper $schema): bool
	{
		if ($schema->hasTable('pc_mob_idem')) {
			return false;
		}

		$t = $schema->createTable('pc_mob_idem');
		$t->addColumn('id', Types::BIGINT, [
			'autoincrement' => true,
			'notnull' => true,
		]);
		$t->addColumn('user_id', Types::STRING, [
			'notnull' => true,
			'length' => 64,
		]);
		$t->addColumn('client_request_id', Types::STRING, [
			'notnull' => true,
			'length' => 64,
		]);
		$t->addColumn('time_entry_id', Types::BIGINT, [
			'notnull' => true,
		]);
		$t->addColumn('created_at', Types::INTEGER, [
			'notnull' => true,
			'unsigned' => true,
		]);
		$t->setPrimaryKey(['id'], 'pc_mob_idem_pk');
		$t->addUniqueIndex(['user_id', 'client_request_id'], 'pc_mob_idem_uid_req');
		$t->addIndex(['time_entry_id'], 'pc_mob_idem_te');

		return true;
	}

	/**
	 * Columns that migrations 2007/2009 add — needed when those steps ran before `pc_projects` existed.
	 */
	public static function ensureProjectColumns(ISchemaWrapper $schema): bool
	{
		if (!$schema->hasTable('pc_projects')) {
			return false;
		}

		$table = $schema->getTable('pc_projects');
		$changed = false;

		if (!$table->hasColumn('project_type')) {
			$table->addColumn('project_type', Types::STRING, [
				'notnull' => true,
				'length' => 32,
				'default' => 'client',
			]);
			$changed = true;
		}

		if (!$table->hasIndex('pc_proj_type_idx')) {
			$table->addIndex(['project_type'], 'pc_proj_type_idx');
			$changed = true;
		}

		if (!$table->hasColumn('cost_rate_mode')) {
			$table->addColumn('cost_rate_mode', Types::STRING, [
				'notnull' => true,
				'length' => 32,
				'default' => 'project',
			]);
			$changed = true;
		}

		return $changed;
	}

	/**
	 * Rate history tables from Version2009 — independent of `pc_projects` existing at migration time.
	 */
	public static function ensureRateTables(ISchemaWrapper $schema): bool
	{
		return RateHistoryTables::apply($schema);
	}

	/**
	 * Settlement columns (feature: time-entry billing status, migration 2012).
	 *
	 * `pc_time_entries` gains the mutable billing state; `pc_projects` gains
	 * materialized counters that are always maintained in the same transaction
	 * as the entry write (spec D10) and can be rebuilt from entries at any time
	 * via {@see SettlementRecomputer}.
	 */
	public static function ensureSettlementColumns(ISchemaWrapper $schema): bool
	{
		$changed = false;

		if ($schema->hasTable('pc_time_entries')) {
			$table = $schema->getTable('pc_time_entries');

			if (!$table->hasColumn('billing_status')) {
				$table->addColumn('billing_status', Types::STRING, [
					'notnull' => true,
					'length' => 16,
					'default' => 'open',
				]);
				$changed = true;
			}
			if (!$table->hasColumn('billed_at')) {
				$table->addColumn('billed_at', Types::DATETIME, [
					'notnull' => false,
				]);
				$changed = true;
			}
			if (!$table->hasColumn('paid_at')) {
				$table->addColumn('paid_at', Types::DATETIME, [
					'notnull' => false,
				]);
				$changed = true;
			}
			if (!$table->hasColumn('billing_changed_by')) {
				$table->addColumn('billing_changed_by', Types::STRING, [
					'notnull' => false,
					'length' => 64,
				]);
				$changed = true;
			}
			if (!$table->hasColumn('billing_changed_at')) {
				$table->addColumn('billing_changed_at', Types::DATETIME, [
					'notnull' => false,
				]);
				$changed = true;
			}
			if (!$table->hasIndex('pc_te_bstatus_idx')) {
				$table->addIndex(['billing_status'], 'pc_te_bstatus_idx');
				$changed = true;
			}
			if (!$table->hasIndex('pc_te_proj_bstatus_idx')) {
				$table->addIndex(['project_id', 'billing_status'], 'pc_te_proj_bstatus_idx');
				$changed = true;
			}
		}

		if ($schema->hasTable('pc_projects')) {
			$table = $schema->getTable('pc_projects');

			$hourColumns = ['stl_open_hours', 'stl_invoiced_hours', 'stl_paid_hours', 'stl_excluded_hours'];
			foreach ($hourColumns as $column) {
				if (!$table->hasColumn($column)) {
					$table->addColumn($column, Types::DECIMAL, [
						'notnull' => true,
						'precision' => 12,
						'scale' => 2,
						'default' => 0,
					]);
					$changed = true;
				}
			}

			$amountColumns = ['stl_open_amount', 'stl_invoiced_amount', 'stl_paid_amount', 'stl_excluded_amount'];
			foreach ($amountColumns as $column) {
				if (!$table->hasColumn($column)) {
					$table->addColumn($column, Types::DECIMAL, [
						'notnull' => true,
						'precision' => 14,
						'scale' => 2,
						'default' => 0,
					]);
					$changed = true;
				}
			}

			if (!$table->hasColumn('stl_updated_at')) {
				$table->addColumn('stl_updated_at', Types::DATETIME, [
					'notnull' => false,
				]);
				$changed = true;
			}

			// Filter helper for outstanding / posture predicates (spec §7.2).
			if (!$table->hasIndex('pc_proj_stl_open_idx')) {
				$table->addIndex(['stl_open_hours'], 'pc_proj_stl_open_idx');
				$changed = true;
			}
		}

		return $changed;
	}

	private static function ensureCustomers(ISchemaWrapper $schema): bool
	{
		if (!$schema->hasTable('pc_customers')) {
			$table = $schema->createTable('pc_customers');
			$table->addColumn('id', Types::BIGINT, [
			'autoincrement' => true,
			'notnull' => true,
			]);
			$table->addColumn('name', Types::STRING, [
			'notnull' => true,
			'length' => 100,
			]);
			$table->addColumn('email', Types::STRING, [
			'notnull' => false,
			'length' => 255,
			]);
			$table->addColumn('phone', Types::STRING, [
			'notnull' => false,
			'length' => 50,
			]);
			$table->addColumn('address', Types::TEXT, [
			'notnull' => false,
			]);
			$table->addColumn('contact_person', Types::STRING, [
			'notnull' => false,
			'length' => 100,
			]);
			$table->addColumn('created_by', Types::STRING, [
			'notnull' => true,
			'length' => 64,
			]);
			$table->addColumn('created_at', Types::DATETIME, [
			'notnull' => true,
			]);
			$table->addColumn('updated_at', Types::DATETIME, [
			'notnull' => true,
			]);
			
			$table->setPrimaryKey(['id'], 'pc_customers_pk');
			$table->addUniqueIndex(['name'], 'pc_cus_name_uq');
			$table->addIndex(['email'], 'pc_customers_email_idx');
			$table->addIndex(['created_by'], 'pc_customers_creator_idx');
			return true;
		}

		return false;
	}
	private static function ensureProjects(ISchemaWrapper $schema): bool
	{
		if (!$schema->hasTable('pc_projects')) {
			$table = $schema->createTable('pc_projects');
			$table->addColumn('id', Types::BIGINT, [
			'autoincrement' => true,
			'notnull' => true,
			]);
			$table->addColumn('name', Types::STRING, [
			'notnull' => true,
			'length' => 100,
			]);
			$table->addColumn('short_description', Types::TEXT, [
			'notnull' => true,
			]);
			$table->addColumn('detailed_description', Types::TEXT, [
			'notnull' => false,
			]);
			$table->addColumn('customer_id', Types::BIGINT, [
			'notnull' => true,
			]);
			$table->addColumn('hourly_rate', Types::DECIMAL, [
			'notnull' => true,
			'precision' => 10,
			'scale' => 2,
			]);
			$table->addColumn('total_budget', Types::DECIMAL, [
			'notnull' => true,
			'precision' => 12,
			'scale' => 2,
			]);
			$table->addColumn('available_hours', Types::DECIMAL, [
			'notnull' => true,
			'precision' => 10,
			'scale' => 2,
			]);
			$table->addColumn('category', Types::STRING, [
			'notnull' => false,
			'length' => 50,
			]);
			$table->addColumn('priority', Types::STRING, [
			'notnull' => false,
			'length' => 20,
			]);
			$table->addColumn('status', Types::STRING, [
			'notnull' => true,
			'length' => 20,
			'default' => 'Active',
			]);
			$table->addColumn('start_date', Types::DATE, [
			'notnull' => false,
			]);
			$table->addColumn('end_date', Types::DATE, [
			'notnull' => false,
			]);
			$table->addColumn('tags', Types::TEXT, [
			'notnull' => false,
			]);
			$table->addColumn('created_by', Types::STRING, [
			'notnull' => true,
			'length' => 64,
			]);
			$table->addColumn('created_at', Types::DATETIME, [
			'notnull' => true,
			]);
			$table->addColumn('updated_at', Types::DATETIME, [
			'notnull' => true,
			]);
			$table->addColumn('project_type', Types::STRING, [
			'notnull' => true,
			'length' => 32,
			'default' => 'client',
			]);
			$table->addColumn('cost_rate_mode', Types::STRING, [
			'notnull' => true,
			'length' => 32,
			'default' => 'project',
			]);
			
			$table->setPrimaryKey(['id'], 'pc_projects_pk');
			$table->addIndex(['customer_id'], 'pc_proj_customer_idx');
			$table->addIndex(['status'], 'pc_proj_status_idx');
			$table->addIndex(['created_by'], 'pc_proj_creator_idx');
			$table->addIndex(['name'], 'pc_proj_name_idx');
			$table->addIndex(['category'], 'pc_proj_category_idx');
			$table->addIndex(['priority'], 'pc_proj_priority_idx');
			$table->addIndex(['start_date'], 'pc_proj_start_idx');
			$table->addIndex(['end_date'], 'pc_proj_end_idx');
			$table->addIndex(['project_type'], 'pc_proj_type_idx');
			return true;
		}

		return false;
	}
	private static function ensureProjectMembers(ISchemaWrapper $schema): bool
	{
		if (!$schema->hasTable('pc_project_members')) {
			$table = $schema->createTable('pc_project_members');
			$table->addColumn('id', Types::BIGINT, [
			'autoincrement' => true,
			'notnull' => true,
			]);
			$table->addColumn('project_id', Types::BIGINT, [
			'notnull' => true,
			]);
			$table->addColumn('user_id', Types::STRING, [
			'notnull' => true,
			'length' => 64,
			]);
			$table->addColumn('role', Types::STRING, [
			'notnull' => true,
			'length' => 50,
			]);
			$table->addColumn('hourly_rate', Types::DECIMAL, [
			'notnull' => false,
			'precision' => 10,
			'scale' => 2,
			]);
			$table->addColumn('assigned_at', Types::DATETIME, [
			'notnull' => true,
			]);
			$table->addColumn('assigned_by', Types::STRING, [
			'notnull' => true,
			'length' => 64,
			]);
			$table->addColumn('member_state', Types::STRING, [
			'notnull' => true,
			'length' => 20,
			'default' => 'active',
			]);
			$table->addColumn('archived_at', Types::DATETIME, [
			'notnull' => false,
			]);
			
			$table->setPrimaryKey(['id'], 'pc_members_pk');
			$table->addIndex(['project_id'], 'pc_members_project_idx');
			$table->addIndex(['user_id'], 'pc_members_user_idx');
			$table->addIndex(['role'], 'pc_members_role_idx');
			$table->addUniqueIndex(['project_id', 'user_id'], 'pc_members_unique_idx');
			return true;
		}

		return false;
	}
	private static function ensureTimeEntries(ISchemaWrapper $schema): bool
	{
		if (!$schema->hasTable('pc_time_entries')) {
			$table = $schema->createTable('pc_time_entries');
			$table->addColumn('id', Types::BIGINT, [
			'autoincrement' => true,
			'notnull' => true,
			]);
			$table->addColumn('project_id', Types::BIGINT, [
			'notnull' => true,
			]);
			$table->addColumn('user_id', Types::STRING, [
			'notnull' => true,
			'length' => 64,
			]);
			$table->addColumn('date', Types::DATE, [
			'notnull' => true,
			]);
			$table->addColumn('hours', Types::DECIMAL, [
			'notnull' => true,
			'precision' => 5,
			'scale' => 2,
			]);
			$table->addColumn('description', Types::TEXT, [
			'notnull' => false,
			]);
			$table->addColumn('hourly_rate', Types::DECIMAL, [
			'notnull' => true,
			'precision' => 10,
			'scale' => 2,
			]);
			$table->addColumn('created_at', Types::DATETIME, [
			'notnull' => true,
			]);
			$table->addColumn('updated_at', Types::DATETIME, [
			'notnull' => true,
			]);
			
			$table->setPrimaryKey(['id'], 'pc_time_entries_pk');
			$table->addIndex(['project_id'], 'pc_te_project_idx');
			$table->addIndex(['user_id'], 'pc_te_user_idx');
			$table->addIndex(['date'], 'pc_te_date_idx');
			$table->addIndex(['project_id', 'user_id'], 'pc_te_proj_user_idx');
			$table->addIndex(['project_id', 'date'], 'pc_te_proj_date_idx');
			return true;
		}

		return false;
	}
	private static function ensureProjectFiles(ISchemaWrapper $schema): bool
	{
		if (!$schema->hasTable('pc_project_files')) {
			$table = $schema->createTable('pc_project_files');
			$table->addColumn('id', Types::BIGINT, [
			'autoincrement' => true,
			'notnull' => true,
			]);
			$table->addColumn('project_id', Types::BIGINT, [
			'notnull' => true,
			'unsigned' => true,
			]);
			$table->addColumn('storage_path', Types::STRING, [
			'notnull' => true,
			'length' => 512,
			]);
			$table->addColumn('display_name', Types::STRING, [
			'notnull' => true,
			'length' => 255,
			]);
			$table->addColumn('mime_type', Types::STRING, [
			'notnull' => false,
			'length' => 128,
			]);
			$table->addColumn('size', Types::BIGINT, [
			'notnull' => true,
			'default' => 0,
			'unsigned' => true,
			]);
			$table->addColumn('uploaded_by', Types::STRING, [
			'notnull' => true,
			'length' => 64,
			]);
			$table->addColumn('created_at', Types::DATETIME, [
			'notnull' => true,
			]);
			
			$table->setPrimaryKey(['id'], 'pc_files_pk');
			$table->addIndex(['project_id'], 'pc_files_project_idx');
			$table->addIndex(['uploaded_by'], 'pc_files_user_idx');
			return true;
		}

		return false;
	}
}
