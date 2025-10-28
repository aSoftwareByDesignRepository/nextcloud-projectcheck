<?php

/**
 * Migration to add customers table for projectcheck app
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
 * Migration to add customers table
 */
class Version1001Date202401010001 extends SimpleMigrationStep
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

		if (!$schema->hasTable('customers')) {
			$table = $schema->createTable('customers');
			$table->addColumn('id', 'bigint', [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('name', 'string', [
				'notnull' => true,
				'length' => 100,
			]);
			$table->addColumn('email', 'string', [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('phone', 'string', [
				'notnull' => false,
				'length' => 50,
			]);
			$table->addColumn('address', 'text', [
				'notnull' => false,
			]);
			$table->addColumn('contact_person', 'string', [
				'notnull' => false,
				'length' => 100,
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

			$table->setPrimaryKey(['id'], 'customers_pk');
			$table->addIndex(['name'], 'customers_name_idx');
			$table->addIndex(['email'], 'customers_email_idx');
			$table->addIndex(['created_by'], 'customers_creator_idx');
		}

		// Add a default customer if projects table exists and has data
		if ($schema->hasTable('projects')) {
			$output->info('Adding default customer for existing projects...');
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
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		// Add a default customer if needed
		if ($schema->hasTable('customers') && $schema->hasTable('projects')) {
			$connection = \OC::$server->getDatabaseConnection();
			
			// Check if there are any projects without a valid customer_id
			$qb = $connection->getQueryBuilder();
			$qb->select('p.id')
			   ->from('projects', 'p')
			   ->leftJoin('p', 'customers', 'c', $qb->expr()->eq('p.customer_id', 'c.id'))
			   ->where($qb->expr()->isNull('c.id'));
			
			$result = $qb->execute();
			$orphanedProjects = $result->fetchAll();
			
			if (!empty($orphanedProjects)) {
				$output->info('Found projects without valid customers. Creating default customer...');
				
				// Create a default customer
				$qb = $connection->getQueryBuilder();
				$qb->insert('customers')
				   ->values([
					   'name' => $qb->createNamedParameter('Default Customer'),
					   'email' => $qb->createNamedParameter('default@example.com'),
					   'contact_person' => $qb->createNamedParameter('System Administrator'),
					   'created_by' => $qb->createNamedParameter('system'),
					   'created_at' => $qb->createNamedParameter(date('Y-m-d H:i:s')),
					   'updated_at' => $qb->createNamedParameter(date('Y-m-d H:i:s'))
				   ]);
				
				$qb->execute();
				$defaultCustomerId = $connection->lastInsertId('customers');
				
				// Update orphaned projects to use the default customer
				$qb = $connection->getQueryBuilder();
				$qb->update('projects')
				   ->set('customer_id', $qb->createNamedParameter($defaultCustomerId))
				   ->where($qb->expr()->eq('customer_id', $qb->createNamedParameter(0)))
				   ->orWhere($qb->expr()->isNull('customer_id'));
				
				$qb->execute();
				
				$output->info('Updated ' . count($orphanedProjects) . ' projects to use default customer.');
			}
		}
	}
}
