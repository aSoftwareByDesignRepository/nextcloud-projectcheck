<?php

/**
 * Create project_files table for storing project-related uploads
 *
 * @copyright Copyright (c) 2025, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

class Version2001Date202502110001 extends SimpleMigrationStep
{
	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return ISchemaWrapper|null
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options)
	{
		$schema = $schemaClosure();

		if ($schema->hasTable('project_files')) {
			$output->info('project_files table already exists');
			return null;
		}

		$table = $schema->createTable('project_files');
		$table->addColumn('id', 'bigint', [
			'autoincrement' => true,
			'notnull' => true,
		]);
		$table->addColumn('project_id', 'bigint', [
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('storage_path', 'string', [
			'notnull' => true,
			'length' => 512,
		]);
		$table->addColumn('display_name', 'string', [
			'notnull' => true,
			'length' => 255,
		]);
		$table->addColumn('mime_type', 'string', [
			'notnull' => false,
			'length' => 128,
		]);
		$table->addColumn('size', 'bigint', [
			'notnull' => true,
			'default' => 0,
			'unsigned' => true,
		]);
		$table->addColumn('uploaded_by', 'string', [
			'notnull' => true,
			'length' => 64,
		]);
		$table->addColumn('created_at', 'datetime', [
			'notnull' => true,
		]);

		$table->setPrimaryKey(['id'], 'pc_files_pk');
		$table->addIndex(['project_id'], 'pc_files_project_idx');
		$table->addIndex(['uploaded_by'], 'pc_files_user_idx');

		return $schema;
	}
}

