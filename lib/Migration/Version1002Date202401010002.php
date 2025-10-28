<?php

/**
 * Migration to add time tracking tables for projectcheck app
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
 * Migration to add time tracking tables
 */
class Version1002Date202401010002 extends SimpleMigrationStep
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

		if (!$schema->hasTable('time_entries')) {
			$table = $schema->createTable('time_entries');
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
			$table->addColumn('date', 'date', [
				'notnull' => true,
			]);
			$table->addColumn('hours', 'decimal', [
				'notnull' => true,
				'precision' => 5,
				'scale' => 2,
			]);
			$table->addColumn('description', 'text', [
				'notnull' => false,
			]);
			$table->addColumn('hourly_rate', 'decimal', [
				'notnull' => true,
				'precision' => 10,
				'scale' => 2,
			]);
			$table->addColumn('created_at', 'datetime', [
				'notnull' => true,
			]);
			$table->addColumn('updated_at', 'datetime', [
				'notnull' => true,
			]);

			$table->setPrimaryKey(['id']);
			$table->addIndex(['project_id'], 'time_entries_project_idx');
			$table->addIndex(['user_id'], 'time_entries_user_idx');
			$table->addIndex(['date'], 'time_entries_date_idx');
			$table->addIndex(['project_id', 'user_id'], 'time_entries_project_user_idx');
			$table->addIndex(['project_id', 'date'], 'time_entries_project_date_idx');
		}

		return $schema;
	}
}
