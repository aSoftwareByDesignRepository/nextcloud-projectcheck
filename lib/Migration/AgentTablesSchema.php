<?php
/** Synced from _shared/agent-auth — do not edit app-side. */

declare(strict_types=1);

namespace OCA\ProjectCheck\Migration;

use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;

/**
 * Idempotent schema for the agent-auth pack:
 *   pc_agent_tokens — scoped credentials (hash at rest)
 *   pc_agent_audit  — forensic trail of agent writes
 *
 * nextcloud-db-standards: index-covered strings ≤190 chars (utf8mb4 index
 * limit), named keys/indexes ≤30 chars, epoch timestamps as unsigned ints.
 */
final class AgentTablesSchema
{
	public const TOKENS_TABLE = 'pc_agent_tokens';
	public const AUDIT_TABLE = 'pc_agent_audit';

	public const TABLES = [self::TOKENS_TABLE, self::AUDIT_TABLE];

	public static function apply(ISchemaWrapper $schema): bool
	{
		$changed = false;

		if (!$schema->hasTable(self::TOKENS_TABLE)) {
			$table = $schema->createTable(self::TOKENS_TABLE);
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('label', Types::STRING, ['notnull' => true, 'length' => 190]);
			$table->addColumn('token_hash', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('scopes', Types::STRING, ['notnull' => true, 'length' => 500]);
			$table->addColumn('created_at', Types::INTEGER, ['notnull' => true, 'unsigned' => true]);
			$table->addColumn('expires_at', Types::INTEGER, ['notnull' => true, 'unsigned' => true]);
			$table->addColumn('last_used_at', Types::INTEGER, ['notnull' => false, 'unsigned' => true]);
			$table->addColumn('last_tool', Types::STRING, ['notnull' => false, 'length' => 190]);
			$table->addColumn('rate_window_start', Types::INTEGER, ['notnull' => false, 'unsigned' => true]);
			$table->addColumn('rate_count', Types::INTEGER, ['notnull' => true, 'default' => 0, 'unsigned' => true]);
			$table->addColumn('revoked_at', Types::INTEGER, ['notnull' => false, 'unsigned' => true]);
			$table->setPrimaryKey(['id'], 'pc_agent_tok_pk');
			$table->addUniqueIndex(['token_hash'], 'pc_agent_tok_hash_uq');
			$table->addIndex(['user_id'], 'pc_agent_tok_uid');
			$changed = true;
		}

		if (!$schema->hasTable(self::AUDIT_TABLE)) {
			$table = $schema->createTable(self::AUDIT_TABLE);
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('token_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
			$table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('actor_type', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => 'agent']);
			$table->addColumn('tool', Types::STRING, ['notnull' => true, 'length' => 190]);
			$table->addColumn('route', Types::STRING, ['notnull' => true, 'length' => 190]);
			$table->addColumn('client_request_id', Types::STRING, ['notnull' => false, 'length' => 190]);
			$table->addColumn('result', Types::STRING, ['notnull' => true, 'length' => 32]);
			$table->addColumn('created_at', Types::INTEGER, ['notnull' => true, 'unsigned' => true]);
			$table->setPrimaryKey(['id'], 'pc_agent_aud_pk');
			$table->addIndex(['token_id'], 'pc_agent_aud_tok');
			$table->addIndex(['user_id'], 'pc_agent_aud_uid');
			$table->addIndex(['created_at'], 'pc_agent_aud_ts');
			$changed = true;
		}

		return $changed;
	}
}
