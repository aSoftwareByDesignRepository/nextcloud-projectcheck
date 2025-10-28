<?php

/**
 * Migration from projectcontrol to projectcheck
 * This migration ONLY migrates app configuration and user preferences
 * It does NOT create new tables - both apps use the same tables!
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
 * 
 * IMPORTANT: This migration does NOT create new tables!
 * Both projectcontrol and projectcheck use the SAME database tables:
 * - oc_projects
 * - oc_customers
 * - oc_time_entries
 * - oc_project_members
 * 
 * This migration ONLY:
 * 1. Migrates app configuration from 'projectcontrol' to 'projectcheck'
 * 2. Migrates user preferences from 'projectcontrol' to 'projectcheck'
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
		// NO SCHEMA CHANGES!
		// Both apps use the same tables, so we don't need to create new tables
		$output->info('No schema changes needed - using existing tables');
		return null;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options)
	{
		// Only migrate configuration, not data
		$this->migrateAppConfig($output);
		$this->migrateUserPreferences($output);
	}

	/**
	 * Migrate app configuration from projectcontrol to projectcheck
	 */
	private function migrateAppConfig(IOutput $output)
	{
		// Check if projectcontrol config exists
		$qb = $this->db->getQueryBuilder();
		$oldConfigCount = $qb->select($qb->createFunction('COUNT(*)'))
			->from('appconfig')
			->where($qb->expr()->eq('appid', $qb->createNamedParameter('projectcontrol')))
			->executeQuery()
			->fetchOne();

		if ($oldConfigCount == 0) {
			$output->info('No projectcontrol config found, skipping app config migration');
			return;
		}

		// Check if config already migrated
		$qb = $this->db->getQueryBuilder();
		$existingConfig = $qb->select($qb->createFunction('COUNT(*)'))
			->from('appconfig')
			->where($qb->expr()->eq('appid', $qb->createNamedParameter('projectcheck')))
			->executeQuery()
			->fetchOne();

		if ($existingConfig > 0) {
			$output->info('ProjectCheck config already exists, skipping migration');
			return;
		}

		// Copy app config from projectcontrol to projectcheck
		$selectQb = $this->db->getQueryBuilder();
		$selectQb->select('configkey', 'configvalue')
			->from('appconfig')
			->where($selectQb->expr()->eq('appid', $selectQb->createNamedParameter('projectcontrol')));
		$result = $selectQb->executeQuery();

		$migrated = 0;
		while ($row = $result->fetch()) {
			// Skip system-managed keys
			if (in_array($row['configkey'], ['installed_version', 'enabled', 'types'])) {
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
				$output->warning("Could not migrate config key: {$row['configkey']}");
			}
		}
		$result->closeCursor();

		$output->info("Migrated $migrated app config values from projectcontrol to projectcheck");
	}

	/**
	 * Migrate user preferences from projectcontrol to projectcheck
	 */
	private function migrateUserPreferences(IOutput $output)
	{
		// Check if projectcontrol preferences exist
		$qb = $this->db->getQueryBuilder();
		$oldPrefsCount = $qb->select($qb->createFunction('COUNT(*)'))
			->from('preferences')
			->where($qb->expr()->eq('appid', $qb->createNamedParameter('projectcontrol')))
			->executeQuery()
			->fetchOne();

		if ($oldPrefsCount == 0) {
			$output->info('No projectcontrol preferences found, skipping user preferences migration');
			return;
		}

		// Check if preferences already migrated
		$qb = $this->db->getQueryBuilder();
		$existingPrefs = $qb->select($qb->createFunction('COUNT(*)'))
			->from('preferences')
			->where($qb->expr()->eq('appid', $qb->createNamedParameter('projectcheck')))
			->executeQuery()
			->fetchOne();

		if ($existingPrefs > 0) {
			$output->info('ProjectCheck preferences already exist, skipping migration');
			return;
		}

		// Copy user preferences from projectcontrol to projectcheck
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
				$output->warning("Could not migrate preference for user {$row['userid']}: {$row['configkey']}");
			}
		}
		$result->closeCursor();

		$output->info("Migrated $migrated user preferences from projectcontrol to projectcheck");
	}
}

