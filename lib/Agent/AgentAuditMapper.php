<?php
/** Synced from _shared/agent-auth — do not edit app-side. */

declare(strict_types=1);

namespace OCA\ProjectCheck\Agent;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @extends QBMapper<AgentAudit>
 */
class AgentAuditMapper extends QBMapper
{
	public const TABLE = 'pc_agent_audit';

	public function __construct(IDBConnection $db)
	{
		parent::__construct($db, self::TABLE, AgentAudit::class);
	}

	public function record(
		int $tokenId,
		string $uid,
		string $tool,
		string $route,
		?string $clientRequestId,
		string $result,
		int $now,
	): AgentAudit {
		$row = new AgentAudit();
		$row->setTokenId($tokenId);
		$row->setUserId($uid);
		$row->setActorType('agent');
		$row->setTool(mb_substr($tool, 0, 190));
		$row->setRoute(mb_substr($route, 0, 190));
		$row->setClientRequestId(
			$clientRequestId !== null ? mb_substr($clientRequestId, 0, 190) : null
		);
		$row->setResult(mb_substr($result, 0, 32));
		$row->setCreatedAt($now);
		return $this->insert($row);
	}

	/**
	 * @return list<AgentAudit>
	 */
	public function findByToken(int $tokenId, int $limit = 100): array
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('token_id', $qb->createNamedParameter($tokenId)))
			->orderBy('created_at', 'DESC')->addOrderBy('id', 'DESC')
			->setMaxResults($limit);
		return $this->findEntities($qb);
	}

	/**
	 * @return list<AgentAudit>
	 */
	public function findByUser(string $uid, int $limit = 100): array
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($uid)))
			->orderBy('created_at', 'DESC')->addOrderBy('id', 'DESC')
			->setMaxResults($limit);
		return $this->findEntities($qb);
	}
}
