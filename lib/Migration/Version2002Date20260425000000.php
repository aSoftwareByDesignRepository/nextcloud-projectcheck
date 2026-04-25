<?php

declare(strict_types=1);

/**
 * User account snapshots for time/project history after NC account removal,
 * and project member archival state.
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2002Date20260425000000 extends SimpleMigrationStep
{
	public function __construct(
		private IDBConnection $db
	) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('pc_user_account_snapshots')) {
			$table = $schema->createTable('pc_user_account_snapshots');
			$table->addColumn('id', 'bigint', [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('user_id', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('display_name', 'string', [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('account_deleted_at', 'datetime', [
				'notnull' => true,
			]);
			$table->setPrimaryKey(['id'], 'pc_uas_pk');
			$table->addUniqueIndex(['user_id'], 'pc_uas_user_uidx');
		}

		if ($schema->hasTable('project_members')) {
			$table = $schema->getTable('project_members');
			if (!$table->hasColumn('member_state')) {
				$table->addColumn('member_state', 'string', [
					'notnull' => true,
					'length' => 20,
					'default' => 'active',
				]);
			}
			if (!$table->hasColumn('archived_at')) {
				$table->addColumn('archived_at', 'datetime', [
					'notnull' => false,
				]);
			}
		}

		return $schema;
	}

	/**
	 * Backfill: snapshot rows for time_entry user ids with no `users` row (historical deletions).
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		if (!$this->db->tableExists('pc_user_account_snapshots') || !$this->db->tableExists('time_entries')) {
			return;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('t.user_id')
			->from('time_entries', 't')
			->groupBy('t.user_id');
		$result = $qb->executeQuery();
		$placeholderDate = (new \DateTime('@0'))->format('Y-m-d H:i:s');
		$n = 0;
		while ($row = $result->fetch()) {
			$uid = (string)($row['user_id'] ?? '');
			if ($uid === '') {
				continue;
			}
			$uqb = $this->db->getQueryBuilder();
			$uqb->select('uid')->from('users')->where($uqb->expr()->eq('uid', $uqb->createNamedParameter($uid)))->setMaxResults(1);
			$rsU = $uqb->executeQuery();
			$exists = $rsU->fetch();
			$rsU->closeCursor();
			$uqb2 = $this->db->getQueryBuilder();
			$uqb2->select('user_id')->from('pc_user_account_snapshots')->where($uqb2->expr()->eq('user_id', $uqb2->createNamedParameter($uid)))->setMaxResults(1);
			$rsS = $uqb2->executeQuery();
			$hasSnap = $rsS->fetch();
			$rsS->closeCursor();
			if ($exists !== false || $hasSnap !== false) {
				continue;
			}
			$ins = $this->db->getQueryBuilder();
			$ins->insert('pc_user_account_snapshots')
				->values([
					'user_id' => $ins->createNamedParameter($uid),
					'display_name' => $ins->createNamedParameter($uid),
					'account_deleted_at' => $ins->createNamedParameter($placeholderDate, Types::DATETIME_MUTABLE),
				]);
			$ins->executeStatement();
			$n++;
		}
		$result->closeCursor();
		$output->info("ProjectCheck: backfilled $n user snapshot row(s) for orphan time entry user ids");
	}
}
