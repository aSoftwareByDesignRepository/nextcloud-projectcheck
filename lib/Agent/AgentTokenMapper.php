<?php
/** Synced from _shared/agent-auth — do not edit app-side. */

declare(strict_types=1);

namespace OCA\ProjectCheck\Agent;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @extends QBMapper<AgentToken>
 */
class AgentTokenMapper extends QBMapper
{
	public const TABLE = 'pc_agent_tokens';

	public function __construct(IDBConnection $db)
	{
		parent::__construct($db, self::TABLE, AgentToken::class);
	}

	/**
	 * Hash lookup is the hot path — token_hash carries a UNIQUE index.
	 * Returns revoked/expired rows too; the caller decides state.
	 */
	public function findByHash(string $tokenHash): ?AgentToken
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('token_hash', $qb->createNamedParameter($tokenHash)));
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	public function findById(int $id): ?AgentToken
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
	 * @return list<AgentToken>
	 */
	public function findByUser(string $uid): array
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($uid)))
			->orderBy('created_at', 'DESC')->addOrderBy('id', 'DESC');
		return $this->findEntities($qb);
	}

	/**
	 * @return list<AgentToken>
	 */
	public function findAll(): array
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->orderBy('created_at', 'DESC')->addOrderBy('id', 'DESC');
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
	 * Stamp last_used_at / last_tool — one row write per agent request.
	 */
	public function touch(int $id, string $tool, int $now): void
	{
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('last_used_at', $qb->createNamedParameter($now))
			->set('last_tool', $qb->createNamedParameter($tool))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));
		$qb->executeStatement();
	}

	/**
	 * Fixed-window mutation counter (60 mut/min/token).
	 *
	 * Single conditional UPDATE — never a read-modify-write, so two parallel
	 * calls cannot both "win" the same slot. The window resets when it is
	 * stale; the fresh count is read back afterwards. A parallel increment
	 * landing between UPDATE and SELECT can only make the returned count
	 * HIGHER (fail-safe direction: over-deny, never over-allow).
	 *
	 * @return int the post-increment mutation count in the current window
	 */
	public function consumeMutationSlot(int $id, int $now, int $windowSeconds): int
	{
		$qb = $this->db->getQueryBuilder();
		$cutoff = $now - $windowSeconds;
		// rate_window_start IS NULL OR < cutoff  →  start a fresh window (count=1)
		// otherwise                              →  increment inside the window
		$qb->update($this->getTableName())
			->set('rate_count', $qb->createFunction(
				'CASE WHEN (rate_window_start IS NULL OR rate_window_start < ' . $qb->createNamedParameter($cutoff) . ')'
				. ' THEN 1 ELSE rate_count + 1 END'
			))
			->set('rate_window_start', $qb->createFunction(
				'CASE WHEN (rate_window_start IS NULL OR rate_window_start < ' . $qb->createNamedParameter($cutoff) . ')'
				. ' THEN ' . $qb->createNamedParameter($now) . ' ELSE rate_window_start END'
			))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));
		$qb->executeStatement();

		$qb2 = $this->db->getQueryBuilder();
		$qb2->select('rate_count')->from($this->getTableName())
			->where($qb2->expr()->eq('id', $qb2->createNamedParameter($id)));
		$result = $qb2->executeQuery();
		$count = (int)($result->fetchOne() ?: 0);
		$result->closeCursor();
		return $count;
	}

	/**
	 * Free all tokens when the Nextcloud account is deleted (GDPR / least
	 * privilege — a deleted identity must not leave live credentials).
	 * Audit rows are kept: they are the forensic trail.
	 */
	public function deleteByUserId(string $uid): void
	{
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($uid)));
		$qb->executeStatement();
	}
}
