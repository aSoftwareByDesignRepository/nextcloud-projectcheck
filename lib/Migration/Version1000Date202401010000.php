<?php

/**
 * Initial migration for the projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

/**
 * Initial migration for projectcontrol (legacy)
 */
class Version1000Date202401010000 extends SimpleMigrationStep
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

		if (!$schema->hasTable('projects')) {
			$table = $schema->createTable('projects');
			$table->addColumn('id', 'bigint', [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('name', 'string', [
				'notnull' => true,
				'length' => 100,
			]);
			$table->addColumn('short_description', 'text', [
				'notnull' => true,
			]);
			$table->addColumn('detailed_description', 'text', [
				'notnull' => false,
			]);
			$table->addColumn('customer_id', 'bigint', [
				'notnull' => true,
			]);
			$table->addColumn('hourly_rate', 'decimal', [
				'notnull' => true,
				'precision' => 10,
				'scale' => 2,
			]);
			$table->addColumn('total_budget', 'decimal', [
				'notnull' => true,
				'precision' => 12,
				'scale' => 2,
			]);
			$table->addColumn('available_hours', 'decimal', [
				'notnull' => true,
				'precision' => 10,
				'scale' => 2,
			]);
			$table->addColumn('category', 'string', [
				'notnull' => false,
				'length' => 50,
			]);
			$table->addColumn('priority', 'string', [
				'notnull' => false,
				'length' => 20,
			]);
			$table->addColumn('status', 'string', [
				'notnull' => true,
				'length' => 20,
				'default' => 'Active',
			]);
			$table->addColumn('start_date', 'date', [
				'notnull' => false,
			]);
			$table->addColumn('end_date', 'date', [
				'notnull' => false,
			]);
			$table->addColumn('tags', 'text', [
				'notnull' => false,
			]);
			$table->addColumn('created_by', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('created_at', 'datetime', [
				'notnull' => true,
			]);
			$table->addColumn('updated_at', 'datetime', [
				'notnull' => true,
			]);

			$table->setPrimaryKey(['id'], 'projects_pk');
			$table->addIndex(['customer_id'], 'projects_customer_idx');
			$table->addIndex(['status'], 'projects_status_idx');
			$table->addIndex(['created_by'], 'projects_creator_idx');
			$table->addIndex(['name'], 'projects_name_idx');
			$table->addIndex(['category'], 'projects_category_idx');
			$table->addIndex(['priority'], 'projects_priority_idx');
			$table->addIndex(['start_date'], 'projects_start_date_idx');
			$table->addIndex(['end_date'], 'projects_end_date_idx');
		}

		if (!$schema->hasTable('project_members')) {
			$table = $schema->createTable('project_members');
			$table->addColumn('id', 'bigint', [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('project_id', 'bigint', [
				'notnull' => true,
			]);
			$table->addColumn('user_id', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('role', 'string', [
				'notnull' => true,
				'length' => 50,
			]);
			$table->addColumn('hourly_rate', 'decimal', [
				'notnull' => false,
				'precision' => 10,
				'scale' => 2,
			]);
			$table->addColumn('assigned_at', 'datetime', [
				'notnull' => true,
			]);
			$table->addColumn('assigned_by', 'string', [
				'notnull' => true,
				'length' => 64,
			]);

			$table->setPrimaryKey(['id'], 'members_pk');
			$table->addIndex(['project_id'], 'members_project_idx');
			$table->addIndex(['user_id'], 'members_user_idx');
			$table->addIndex(['role'], 'members_role_idx');

			// Add unique constraint to prevent duplicate assignments
			$table->addUniqueIndex(['project_id', 'user_id'], 'members_unique_idx');

			// Foreign key constraint will be added in a later migration if needed
		}

		return $schema;
	}
}
