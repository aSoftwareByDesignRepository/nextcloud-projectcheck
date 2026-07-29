<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @extends QBMapper<MobileSeat>
 */
class MobileSeatMapper extends QBMapper
{
	public const TABLE = 'pc_mobile_seats';

	public function __construct(IDBConnection $db)
	{
		parent::__construct($db, self::TABLE, MobileSeat::class);
	}

	public function findByUid(string $uid): ?MobileSeat
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)));
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/**
	 * Rank order per SPEC §8.4: assigned_at ASC, id ASC.
	 *
	 * @return list<MobileSeat>
	 */
	public function findAllRanked(): array
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->orderBy('assigned_at', 'ASC')->addOrderBy('id', 'ASC');
		return $this->findEntities($qb);
	}

	public function countAll(): int
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('id', 'cnt'))->from($this->getTableName());
		$result = $qb->executeQuery();
		$count = (int)($result->fetchOne() ?: 0);
		$result->closeCursor();
		return $count;
	}

	/**
	 * Free a seat when the Nextcloud account is deleted (GDPR / seat accounting).
	 */
	public function deleteByUserId(string $uid): void
	{
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)));
		$qb->executeStatement();
	}

	/** Clear all seats (license remove / wipe). */
	public function deleteAll(): void
	{
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName());
		$qb->executeStatement();
	}
}
