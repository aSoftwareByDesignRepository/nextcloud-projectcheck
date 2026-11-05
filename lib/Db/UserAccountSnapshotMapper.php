<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception as DbException;
use OCP\IDBConnection;

class UserAccountSnapshotMapper extends QBMapper
{
	// unique-conflict:ok the update() below re-writes the same user_id that
	// the unique lookup just fetched — it cannot violate pc_uas_user_uidx.
	// The insert() above it carries the REASON_UNIQUE catch for the race.

	public function __construct(IDBConnection $db)
	{
		parent::__construct($db, 'pc_user_account_snapshots', UserAccountSnapshot::class);
	}

	/**
	 * @throws DoesNotExistException|MultipleObjectsReturnedException
	 */
	public function getByUserId(string $userId): UserAccountSnapshot
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
		return $this->findEntity($qb);
	}

	public function findByUserId(string $userId): ?UserAccountSnapshot
	{
		try {
			return $this->getByUserId($userId);
		} catch (DoesNotExistException|MultipleObjectsReturnedException) {
			return null;
		}
	}

	/**
	 * Idempotent: insert, or if row exists, update only when new delete time is not older.
	 */
	public function saveDeletedAccountSnapshot(string $userId, string $displayName, \DateTimeInterface $accountDeletedAt): UserAccountSnapshot
	{
		$existing = $this->findByUserId($userId);
		if ($existing === null) {
			$e = new UserAccountSnapshot();
			$e->setUserId($userId);
			$e->setDisplayName($displayName);
			$e->setAccountDeletedAt(\DateTime::createFromInterface($accountDeletedAt));
			try {
				return $this->insert($e);
			} catch (DbException $ex) {
				// Concurrent snapshot write for the same user_id:
				// pc_uas_user_uidx serializes the race — the loser re-reads
				// and takes the update branch below.
				if ($ex->getReason() !== DbException::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
					throw $ex;
				}
				$existing = $this->findByUserId($userId);
				if ($existing === null) {
					throw $ex;
				}
			}
		}
		$e = $existing;
		$e->setDisplayName($displayName);
		$e->setAccountDeletedAt(\DateTime::createFromInterface($accountDeletedAt));
		return $this->update($e);
	}
}
