<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception as DbException;
use OCP\IDBConnection;

/**
 * @extends QBMapper<MobileIdempotency>
 */
class MobileIdempotencyMapper extends QBMapper
{
	public const TABLE = 'pc_mob_idem';

	public function __construct(IDBConnection $db)
	{
		parent::__construct($db, self::TABLE, MobileIdempotency::class);
	}

	public function findByUserAndRequestId(string $userId, string $clientRequestId): ?MobileIdempotency
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('client_request_id', $qb->createNamedParameter($clientRequestId)));
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/**
	 * Insert mapping. Returns false on unique-constraint race (caller must re-read).
	 */
	public function tryInsert(string $userId, string $clientRequestId, int $timeEntryId, int $createdAt): bool
	{
		$entity = new MobileIdempotency();
		$entity->setUserId($userId);
		$entity->setClientRequestId($clientRequestId);
		$entity->setTimeEntryId($timeEntryId);
		$entity->setCreatedAt($createdAt);
		try {
			$this->insert($entity);
			return true;
		} catch (DbException $e) {
			// Concurrent drain of the same clientRequestId — unique index conflict.
			if ($e->getReason() === DbException::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
				return false;
			}
			throw $e;
		}
	}

	public function deleteByUserId(string $userId): void
	{
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
		$qb->executeStatement();
	}

	public function deleteByUserAndRequestId(string $userId, string $clientRequestId): void
	{
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('client_request_id', $qb->createNamedParameter($clientRequestId)));
		$qb->executeStatement();
	}
}
