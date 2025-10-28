<?php

/**
 * Migration from projectcontrol to projectcheck
 * Creates new tables with projectcheck_ prefix and migrates all data
 *
 * @copyright Copyright (c) 2025, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

/**
 * Migration from projectcontrol to projectcheck
 * This migration:
 * 1. Creates new tables with projectcheck_ prefix
 * 2. Copies all data from old tables (projects, customers, time_entries, project_members)
 * 3. Migrates app configuration from 'projectcontrol' to 'projectcheck'
 * 4. Migrates user preferences from 'projectcontrol' to 'projectcheck'
 */
class Version2000Date202501280001 extends SimpleMigrationStep
{
	private IDBConnection $db;

	public function __construct(IDBConnection $db)
	{
		$this->db = $db;
	}

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

		// Create pccheck_projects table
		if (!$schema->hasTable('pccheck_projects')) {
			$table = $schema->createTable('pccheck_projects');
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

			$table->setPrimaryKey(['id'], 'pc_proj_pk');
			$table->addIndex(['customer_id'], 'pc_proj_cust_idx');
			$table->addIndex(['status'], 'pc_proj_status_idx');
			$table->addIndex(['created_by'], 'pc_proj_creator_idx');
			$table->addIndex(['name'], 'pc_proj_name_idx');
			$table->addIndex(['category'], 'pc_proj_cat_idx');
			$table->addIndex(['priority'], 'pc_proj_prior_idx');
			$table->addIndex(['start_date'], 'pc_proj_start_idx');
			$table->addIndex(['end_date'], 'pc_proj_end_idx');
		}

		// Create pccheck_customers table
		if (!$schema->hasTable('pccheck_customers')) {
			$table = $schema->createTable('pccheck_customers');
			$table->addColumn('id', 'bigint', [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('name', 'string', [
				'notnull' => true,
				'length' => 100,
			]);
			$table->addColumn('contact_person', 'string', [
				'notnull' => false,
				'length' => 100,
			]);
			$table->addColumn('email', 'string', [
				'notnull' => false,
				'length' => 100,
			]);
			$table->addColumn('phone', 'string', [
				'notnull' => false,
				'length' => 50,
			]);
			$table->addColumn('address', 'text', [
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

			$table->setPrimaryKey(['id'], 'pc_cust_pk');
			$table->addIndex(['name'], 'pc_cust_name_idx');
			$table->addIndex(['created_by'], 'pc_cust_creator_idx');
		}

		// Create pccheck_time_entries table
		if (!$schema->hasTable('pccheck_time_entries')) {
			$table = $schema->createTable('pccheck_time_entries');
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
				'precision' => 10,
				'scale' => 2,
			]);
			$table->addColumn('hourly_rate', 'decimal', [
				'notnull' => true,
				'precision' => 10,
				'scale' => 2,
			]);
			$table->addColumn('description', 'text', [
				'notnull' => false,
			]);
			$table->addColumn('created_at', 'datetime', [
				'notnull' => true,
			]);
			$table->addColumn('updated_at', 'datetime', [
				'notnull' => true,
			]);

			$table->setPrimaryKey(['id'], 'pc_time_pk');
			$table->addIndex(['project_id'], 'pc_time_proj_idx');
			$table->addIndex(['user_id'], 'pc_time_user_idx');
			$table->addIndex(['date'], 'pc_time_date_idx');
		}

		// Create pccheck_project_members table
		if (!$schema->hasTable('pccheck_project_members')) {
			$table = $schema->createTable('pccheck_project_members');
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

			$table->setPrimaryKey(['id'], 'pc_memb_pk');
			$table->addIndex(['project_id'], 'pc_memb_proj_idx');
			$table->addIndex(['user_id'], 'pc_memb_user_idx');
			$table->addIndex(['role'], 'pc_memb_role_idx');
			$table->addUniqueIndex(['project_id', 'user_id'], 'pc_memb_uniq_idx');
		}

		return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options)
	{
		// Migrate data from old tables to new tables
		$this->migrateProjects($output);
		$this->migrateCustomers($output);
		$this->migrateTimeEntries($output);
		$this->migrateProjectMembers($output);

		// Migrate app configuration and user preferences
		$this->migrateAppConfig($output);
		$this->migrateUserPreferences($output);
	}

	/**
	 * Migrate projects data
	 */
	private function migrateProjects(IOutput $output)
	{
		// Check if old table exists
		if (!$this->tableExists('projects')) {
			$output->info('Old projects table does not exist, skipping migration');
			return;
		}

		// Check if data already migrated
		$qb = $this->db->getQueryBuilder();
		$count = $qb->select($qb->createFunction('COUNT(*)'))
			->from('projectcheck_projects')
			->executeQuery()
			->fetchOne();

		if ($count > 0) {
			$output->info('Projects data already migrated, skipping');
			return;
		}

		// Copy all data
		$selectQb = $this->db->getQueryBuilder();
		$selectQb->select('*')->from('projects');
		$result = $selectQb->executeQuery();

		$migrated = 0;
		while ($row = $result->fetch()) {
			$insertQb = $this->db->getQueryBuilder();
			$insertQb->insert('projectcheck_projects')
				->values([
					'id' => $insertQb->createNamedParameter($row['id'], IQueryBuilder::PARAM_INT),
					'name' => $insertQb->createNamedParameter($row['name']),
					'short_description' => $insertQb->createNamedParameter($row['short_description']),
					'detailed_description' => $insertQb->createNamedParameter($row['detailed_description']),
					'customer_id' => $insertQb->createNamedParameter($row['customer_id'], IQueryBuilder::PARAM_INT),
					'hourly_rate' => $insertQb->createNamedParameter($row['hourly_rate']),
					'total_budget' => $insertQb->createNamedParameter($row['total_budget']),
					'available_hours' => $insertQb->createNamedParameter($row['available_hours']),
					'category' => $insertQb->createNamedParameter($row['category']),
					'priority' => $insertQb->createNamedParameter($row['priority']),
					'status' => $insertQb->createNamedParameter($row['status']),
					'start_date' => $insertQb->createNamedParameter($row['start_date']),
					'end_date' => $insertQb->createNamedParameter($row['end_date']),
					'tags' => $insertQb->createNamedParameter($row['tags']),
					'created_by' => $insertQb->createNamedParameter($row['created_by']),
					'created_at' => $insertQb->createNamedParameter($row['created_at']),
					'updated_at' => $insertQb->createNamedParameter($row['updated_at']),
				]);
			$insertQb->executeStatement();
			$migrated++;
		}
		$result->closeCursor();

		$output->info("Migrated $migrated projects");
	}

	/**
	 * Migrate customers data
	 */
	private function migrateCustomers(IOutput $output)
	{
		// Check if old table exists
		if (!$this->tableExists('customers')) {
			$output->info('Old customers table does not exist, skipping migration');
			return;
		}

		// Check if data already migrated
		$qb = $this->db->getQueryBuilder();
		$count = $qb->select($qb->createFunction('COUNT(*)'))
			->from('projectcheck_customers')
			->executeQuery()
			->fetchOne();

		if ($count > 0) {
			$output->info('Customers data already migrated, skipping');
			return;
		}

		// Copy all data
		$selectQb = $this->db->getQueryBuilder();
		$selectQb->select('*')->from('customers');
		$result = $selectQb->executeQuery();

		$migrated = 0;
		while ($row = $result->fetch()) {
			$insertQb = $this->db->getQueryBuilder();
			$insertQb->insert('projectcheck_customers')
				->values([
					'id' => $insertQb->createNamedParameter($row['id'], IQueryBuilder::PARAM_INT),
					'name' => $insertQb->createNamedParameter($row['name']),
					'contact_person' => $insertQb->createNamedParameter($row['contact_person']),
					'email' => $insertQb->createNamedParameter($row['email']),
					'phone' => $insertQb->createNamedParameter($row['phone']),
					'address' => $insertQb->createNamedParameter($row['address']),
					'created_by' => $insertQb->createNamedParameter($row['created_by']),
					'created_at' => $insertQb->createNamedParameter($row['created_at']),
					'updated_at' => $insertQb->createNamedParameter($row['updated_at']),
				]);
			$insertQb->executeStatement();
			$migrated++;
		}
		$result->closeCursor();

		$output->info("Migrated $migrated customers");
	}

	/**
	 * Migrate time entries data
	 */
	private function migrateTimeEntries(IOutput $output)
	{
		// Check if old table exists
		if (!$this->tableExists('time_entries')) {
			$output->info('Old time_entries table does not exist, skipping migration');
			return;
		}

		// Check if data already migrated
		$qb = $this->db->getQueryBuilder();
		$count = $qb->select($qb->createFunction('COUNT(*)'))
			->from('projectcheck_time_entries')
			->executeQuery()
			->fetchOne();

		if ($count > 0) {
			$output->info('Time entries data already migrated, skipping');
			return;
		}

		// Copy all data
		$selectQb = $this->db->getQueryBuilder();
		$selectQb->select('*')->from('time_entries');
		$result = $selectQb->executeQuery();

		$migrated = 0;
		while ($row = $result->fetch()) {
			$insertQb = $this->db->getQueryBuilder();
			$insertQb->insert('projectcheck_time_entries')
				->values([
					'id' => $insertQb->createNamedParameter($row['id'], IQueryBuilder::PARAM_INT),
					'project_id' => $insertQb->createNamedParameter($row['project_id'], IQueryBuilder::PARAM_INT),
					'user_id' => $insertQb->createNamedParameter($row['user_id']),
					'date' => $insertQb->createNamedParameter($row['date']),
					'hours' => $insertQb->createNamedParameter($row['hours']),
					'hourly_rate' => $insertQb->createNamedParameter($row['hourly_rate']),
					'description' => $insertQb->createNamedParameter($row['description']),
					'created_at' => $insertQb->createNamedParameter($row['created_at']),
					'updated_at' => $insertQb->createNamedParameter($row['updated_at']),
				]);
			$insertQb->executeStatement();
			$migrated++;
		}
		$result->closeCursor();

		$output->info("Migrated $migrated time entries");
	}

	/**
	 * Migrate project members data
	 */
	private function migrateProjectMembers(IOutput $output)
	{
		// Check if old table exists
		if (!$this->tableExists('project_members')) {
			$output->info('Old project_members table does not exist, skipping migration');
			return;
		}

		// Check if data already migrated
		$qb = $this->db->getQueryBuilder();
		$count = $qb->select($qb->createFunction('COUNT(*)'))
			->from('projectcheck_project_members')
			->executeQuery()
			->fetchOne();

		if ($count > 0) {
			$output->info('Project members data already migrated, skipping');
			return;
		}

		// Copy all data
		$selectQb = $this->db->getQueryBuilder();
		$selectQb->select('*')->from('project_members');
		$result = $selectQb->executeQuery();

		$migrated = 0;
		while ($row = $result->fetch()) {
			$insertQb = $this->db->getQueryBuilder();
			$insertQb->insert('projectcheck_project_members')
				->values([
					'id' => $insertQb->createNamedParameter($row['id'], IQueryBuilder::PARAM_INT),
					'project_id' => $insertQb->createNamedParameter($row['project_id'], IQueryBuilder::PARAM_INT),
					'user_id' => $insertQb->createNamedParameter($row['user_id']),
					'role' => $insertQb->createNamedParameter($row['role']),
					'hourly_rate' => $insertQb->createNamedParameter($row['hourly_rate']),
					'assigned_at' => $insertQb->createNamedParameter($row['assigned_at']),
					'assigned_by' => $insertQb->createNamedParameter($row['assigned_by']),
				]);
			$insertQb->executeStatement();
			$migrated++;
		}
		$result->closeCursor();

		$output->info("Migrated $migrated project members");
	}

	/**
	 * Migrate app configuration from projectcontrol to projectcheck
	 */
	private function migrateAppConfig(IOutput $output)
	{
		// Check if config already migrated
		$qb = $this->db->getQueryBuilder();
		$existingConfig = $qb->select('configkey')
			->from('appconfig')
			->where($qb->expr()->eq('appid', $qb->createNamedParameter('projectcheck')))
			->executeQuery()
			->fetchOne();

		if ($existingConfig) {
			$output->info('App config already migrated, skipping');
			return;
		}

		// Copy app config
		$selectQb = $this->db->getQueryBuilder();
		$selectQb->select('configkey', 'configvalue')
			->from('appconfig')
			->where($selectQb->expr()->eq('appid', $selectQb->createNamedParameter('projectcontrol')));
		$result = $selectQb->executeQuery();

		$migrated = 0;
		while ($row = $result->fetch()) {
			// Skip the installed_version - it will be set automatically
			if ($row['configkey'] === 'installed_version' || $row['configkey'] === 'enabled') {
				continue;
			}

			$insertQb = $this->db->getQueryBuilder();
			$insertQb->insert('appconfig')
				->values([
					'appid' => $insertQb->createNamedParameter('projectcheck'),
					'configkey' => $insertQb->createNamedParameter($row['configkey']),
					'configvalue' => $insertQb->createNamedParameter($row['configvalue']),
				]);
			try {
				$insertQb->executeStatement();
				$migrated++;
			} catch (\Exception $e) {
				// Config key might already exist, skip
			}
		}
		$result->closeCursor();

		$output->info("Migrated $migrated app config values");
	}

	/**
	 * Migrate user preferences from projectcontrol to projectcheck
	 */
	private function migrateUserPreferences(IOutput $output)
	{
		// Check if preferences already migrated
		$qb = $this->db->getQueryBuilder();
		$existingPrefs = $qb->select($qb->createFunction('COUNT(*)'))
			->from('preferences')
			->where($qb->expr()->eq('appid', $qb->createNamedParameter('projectcheck')))
			->executeQuery()
			->fetchOne();

		if ($existingPrefs > 0) {
			$output->info('User preferences already migrated, skipping');
			return;
		}

		// Copy user preferences
		$selectQb = $this->db->getQueryBuilder();
		$selectQb->select('userid', 'configkey', 'configvalue')
			->from('preferences')
			->where($selectQb->expr()->eq('appid', $selectQb->createNamedParameter('projectcontrol')));
		$result = $selectQb->executeQuery();

		$migrated = 0;
		while ($row = $result->fetch()) {
			$insertQb = $this->db->getQueryBuilder();
			$insertQb->insert('preferences')
				->values([
					'userid' => $insertQb->createNamedParameter($row['userid']),
					'appid' => $insertQb->createNamedParameter('projectcheck'),
					'configkey' => $insertQb->createNamedParameter($row['configkey']),
					'configvalue' => $insertQb->createNamedParameter($row['configvalue']),
				]);
			try {
				$insertQb->executeStatement();
				$migrated++;
			} catch (\Exception $e) {
				// Preference might already exist, skip
			}
		}
		$result->closeCursor();

		$output->info("Migrated $migrated user preferences");
	}

	/**
	 * Check if a table exists
	 */
	private function tableExists(string $tableName): bool
	{
		try {
			$qb = $this->db->getQueryBuilder();
			$qb->select($qb->createFunction('COUNT(*)'))
				->from($tableName)
				->setMaxResults(1);
			$qb->executeQuery();
			return true;
		} catch (\Exception $e) {
			return false;
		}
	}
}

