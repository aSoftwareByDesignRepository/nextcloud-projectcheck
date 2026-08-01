<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Mobile offline-create idempotency keys (v1.1 companion).
 *
 * Logical name `pc_mob_idem` keeps Oracle-style ≤30 with default PK.
 * Unique (user_id, client_request_id) prevents double-create on queue drain retries.
 */
class Version2015Date20260727120000 extends SimpleMigrationStep
{
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
	{
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('pc_mob_idem')) {
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
		}

		return $schema;
	}
}
