<?php
/** Synced from _shared/agent-auth — do not edit app-side. */

declare(strict_types=1);

namespace OCA\ProjectCheck\Repair;

use OC\DB\Connection;
use OC\DB\ConnectionAdapter;
use OC\DB\SchemaWrapper;
use OCA\ProjectCheck\Migration\AgentTablesSchema;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use RuntimeException;

/**
 * Creates the agent-auth tables idempotently (install + post-migration
 * repair, registered in appinfo/info.xml next to the app's own steps).
 * No-op when both tables already exist.
 */
final class EnsureAgentTables implements IRepairStep
{
	public function __construct(
		private readonly IDBConnection $db,
	) {
	}

	public function getName(): string
	{
		return 'Ensure ProjectCheck agent token/audit tables';
	}

	public function run(IOutput $output): void
	{
		$schema = $this->createSchemaWrapper();
		if (AgentTablesSchema::apply($schema)) {
			$this->db->migrateToSchema($schema->getWrappedSchema());
			$output->info('ProjectCheck: agent tables created.');
		}
	}

	private function createSchemaWrapper(): SchemaWrapper
	{
		$connection = $this->db;
		if ($connection instanceof ConnectionAdapter) {
			$connection = $connection->getInner();
		}
		if (!$connection instanceof Connection) {
			throw new RuntimeException(
				'ProjectCheck agent schema repair requires OC\DB\Connection (got '
				. get_debug_type($connection) . ').'
			);
		}
		return new SchemaWrapper($connection);
	}
}
