<?php
/** Synced from _shared/agent-auth — do not edit app-side. */

declare(strict_types=1);

namespace OCA\ProjectCheck\Agent;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @extends QBMapper<AgentWebhook>
 */
class AgentWebhookMapper extends QBMapper
{
	public const TABLE = 'pc_agent_webhooks';

	public function __construct(IDBConnection $db)
	{
		parent::__construct($db, self::TABLE, AgentWebhook::class);
	}

	public function findById(int $id): ?AgentWebhook
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/**
	 * @return list<AgentWebhook>
	 */
	public function findByUser(string $uid): array
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($uid)))
			->orderBy('created_at', 'DESC')->addOrderBy('id', 'DESC');
		return $this->findEntities($qb);
	}

	public function countActiveByUser(string $uid): int
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'c'))
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->isNull('revoked_at'))
			->andWhere($qb->expr()->isNull('disabled_at'));
		$result = $qb->executeQuery();
		$count = (int)($result->fetchOne() ?: 0);
		$result->closeCursor();
		return $count;
	}

	/**
	 * One owner's rows that could match an event — active subs, filtered
	 * in PHP by subscribesTo() (the events column is a space-list; a LIKE
	 * index would be a lie about semantics). Fan-out is per-owner: an
	 * event emitted by uid X must never reach a subscription owned by
	 * uid Y — subscriptions see only their owner's actions.
	 *
	 * @return list<AgentWebhook>
	 */
	public function findActiveByUser(string $uid): array
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->isNull('revoked_at'))
			->andWhere($qb->expr()->isNull('disabled_at'));
		return $this->findEntities($qb);
	}

	public function revoke(int $id, int $now): void
	{
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('revoked_at', $qb->createNamedParameter($now))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
			->andWhere($qb->expr()->isNull('revoked_at'));
		$qb->executeStatement();
	}

	/**
	 * Delivery outcome bookkeeping — single conditional UPDATE, never
	 * read-modify-write (two deliveries racing must not lose a fail count).
	 */
	public function recordDelivery(int $id, int $now, bool $ok, bool $disable): void
	{
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('last_delivery_at', $qb->createNamedParameter($now))
			->set('last_status', $qb->createNamedParameter($ok ? AgentWebhook::STATUS_OK : AgentWebhook::STATUS_FAILED))
			->set('fail_count', $qb->createFunction($ok ? '0' : 'fail_count + 1'));
		if ($disable) {
			$qb->set('disabled_at', $qb->createNamedParameter($now));
		}
		$qb->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));
		$qb->executeStatement();
	}

	/**
	 * Purge subscriptions when the owning NC account is deleted — same rule
	 * as tokens: a deleted identity must not leave live delivery targets.
	 */
	public function deleteByUserId(string $uid): void
	{
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($uid)));
		$qb->executeStatement();
	}
}
