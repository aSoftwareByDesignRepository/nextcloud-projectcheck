<?php
/** Synced from _shared/agent-auth — do not edit app-side. */

declare(strict_types=1);

namespace OCA\ProjectCheck\Agent;

use OCP\AppFramework\Db\Entity;

/**
 * Agent-write audit row (pc_agent_audit).
 *
 * Every mutating agent call appends one row: actor_type=agent, token_id,
 * tool, route, clientRequestId, result. This is the forensic trail for
 * "the AI booked the wrong thing" — which token, which tool, which request.
 *
 * @method int getTokenId()
 * @method void setTokenId(int $v)
 * @method string getUserId()
 * @method void setUserId(string $v)
 * @method string getActorType()
 * @method void setActorType(string $v)
 * @method string getTool()
 * @method void setTool(string $v)
 * @method string getRoute()
 * @method void setRoute(string $v)
 * @method string|null getClientRequestId()
 * @method void setClientRequestId(?string $v)
 * @method string getResult()
 * @method void setResult(string $v)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $v)
 */
class AgentAudit extends Entity
{
	public const RESULT_OK = 'ok';
	public const RESULT_DENIED = 'denied';
	public const RESULT_ERROR = 'error';
	public const RESULT_DRYRUN = 'dryrun';

	protected int $tokenId = 0;
	protected string $userId = '';
	protected string $actorType = '';
	protected string $tool = '';
	protected string $route = '';
	protected ?string $clientRequestId = null;
	protected string $result = '';
	protected int $createdAt = 0;

	public function __construct()
	{
		$this->addType('tokenId', 'integer');
		$this->addType('userId', 'string');
		$this->addType('actorType', 'string');
		$this->addType('tool', 'string');
		$this->addType('route', 'string');
		$this->addType('clientRequestId', 'string');
		$this->addType('result', 'string');
		$this->addType('createdAt', 'integer');
	}
}
