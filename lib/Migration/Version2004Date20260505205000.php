<?php

declare(strict_types=1);

/**
 * Normalize legacy index names to portable, app-prefixed identifiers.
 *
 * Why:
 * - Legacy tables are intentionally kept (`projects`, `customers`, `time_entries`,
 *   `project_members`, `project_files`) for backward compatibility with historical
 *   installs and migration paths.
 * - Several legacy index names are not app-prefixed. While functional, this is not
 *   ideal for cross-app clarity and stricter database portability expectations.
 *
 * This migration is data-safe:
 * - no table renames
 * - no column changes
 * - no semantic index changes (same indexed columns)
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2004Date20260505205000 extends SimpleMigrationStep
{
	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options)
	{
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$this->renameIndexIfPresent($schema, 'projects', 'projects_customer_idx', 'pc_proj_customer_idx', ['customer_id']);
		$this->renameIndexIfPresent($schema, 'projects', 'projects_status_idx', 'pc_proj_status_idx', ['status']);
		$this->renameIndexIfPresent($schema, 'projects', 'projects_creator_idx', 'pc_proj_creator_idx', ['created_by']);
		$this->renameIndexIfPresent($schema, 'projects', 'projects_name_idx', 'pc_proj_name_idx', ['name']);
		$this->renameIndexIfPresent($schema, 'projects', 'projects_category_idx', 'pc_proj_category_idx', ['category']);
		$this->renameIndexIfPresent($schema, 'projects', 'projects_priority_idx', 'pc_proj_priority_idx', ['priority']);
		$this->renameIndexIfPresent($schema, 'projects', 'projects_start_date_idx', 'pc_proj_start_idx', ['start_date']);
		$this->renameIndexIfPresent($schema, 'projects', 'projects_end_date_idx', 'pc_proj_end_idx', ['end_date']);
		// Index left over from the projectcontrol-era schema (added on the
		// shared `projects` table). Normalize to the `pc_` namespace.
		$this->renameIndexIfPresent($schema, 'projects', 'projects_project_type_idx', 'pc_proj_type_idx', ['project_type']);

		$this->renameIndexIfPresent($schema, 'project_members', 'members_project_idx', 'pc_mem_project_idx', ['project_id']);
		$this->renameIndexIfPresent($schema, 'project_members', 'members_user_idx', 'pc_mem_user_idx', ['user_id']);
		$this->renameIndexIfPresent($schema, 'project_members', 'members_role_idx', 'pc_mem_role_idx', ['role']);
		$this->renameUniqueIndexIfPresent($schema, 'project_members', 'members_unique_idx', 'pc_mem_proj_user_uidx', ['project_id', 'user_id']);

		$this->renameIndexIfPresent($schema, 'customers', 'customers_name_idx', 'pc_cus_name_idx', ['name']);
		$this->renameIndexIfPresent($schema, 'customers', 'customers_email_idx', 'pc_cus_email_idx', ['email']);
		$this->renameIndexIfPresent($schema, 'customers', 'customers_creator_idx', 'pc_cus_creator_idx', ['created_by']);

		$this->renameIndexIfPresent($schema, 'time_entries', 'time_entries_project_idx', 'pc_te_project_idx', ['project_id']);
		$this->renameIndexIfPresent($schema, 'time_entries', 'time_entries_user_idx', 'pc_te_user_idx', ['user_id']);
		$this->renameIndexIfPresent($schema, 'time_entries', 'time_entries_date_idx', 'pc_te_date_idx', ['date']);
		$this->renameIndexIfPresent($schema, 'time_entries', 'time_entries_project_user_idx', 'pc_te_proj_user_idx', ['project_id', 'user_id']);
		$this->renameIndexIfPresent($schema, 'time_entries', 'time_entries_project_date_idx', 'pc_te_proj_date_idx', ['project_id', 'date']);

		return $schema;
	}

	/**
	 * Idempotent index rename. Behaviour matrix:
	 *
	 *   old?  new?  cols-exist?  → action
	 *    Y     N      Y           add new, drop old (rename)
	 *    Y     N      N           drop old (cols missing on this install -
	 *                              fresh installs without the legacy column,
	 *                              the legacy index won't exist either)
	 *    Y     Y      *           drop old (already migrated, dedupe)
	 *    N     N      Y           add new (steady-state on fresh installs)
	 *    N     N      N           no-op (column missing → index N/A)
	 *    N     Y      *           no-op
	 *
	 * @param list<string> $columns
	 */
	private function renameIndexIfPresent(
		ISchemaWrapper $schema,
		string $tableName,
		string $oldName,
		string $newName,
		array $columns
	): void {
		if (!$schema->hasTable($tableName)) {
			return;
		}

		$table = $schema->getTable($tableName);
		$hasOld = $table->hasIndex($oldName);
		$hasNew = $table->hasIndex($newName);
		$hasAllCols = $this->columnsExist($table, $columns);

		if (!$hasNew && $hasAllCols) {
			$table->addIndex($columns, $newName);
		}

		if ($hasOld) {
			$table->dropIndex($oldName);
		}
	}

	/**
	 * @param list<string> $columns
	 */
	private function renameUniqueIndexIfPresent(
		ISchemaWrapper $schema,
		string $tableName,
		string $oldName,
		string $newName,
		array $columns
	): void {
		if (!$schema->hasTable($tableName)) {
			return;
		}

		$table = $schema->getTable($tableName);
		$hasOld = $table->hasIndex($oldName);
		$hasNew = $table->hasIndex($newName);
		$hasAllCols = $this->columnsExist($table, $columns);

		if (!$hasNew && $hasAllCols) {
			$table->addUniqueIndex($columns, $newName);
		}

		if ($hasOld) {
			$table->dropIndex($oldName);
		}
	}

	/**
	 * @param list<string> $columns
	 */
	private function columnsExist($table, array $columns): bool
	{
		foreach ($columns as $col) {
			if (!$table->hasColumn($col)) {
				return false;
			}
		}
		return true;
	}
}
