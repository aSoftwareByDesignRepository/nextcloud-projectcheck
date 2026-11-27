<?php
/** Synced from _shared/agent-auth — do not edit app-side. */

declare(strict_types=1);

namespace OCA\ProjectCheck\Agent;

use OCP\AppFramework\Db\Entity;

/**
 * Outbound webhook subscription row (pc_agent_webhooks).
 *
 * The `secret` column holds the raw HMAC signing secret — it is required
 * on every delivery, so it must be retrievable, but it is NEVER re-served
 * by any API after the create response (same once-only pattern as tokens).
 *
 * @method string getUserId()
 * @method void setUserId(string $v)
 * @method string getUrl()
 * @method void setUrl(string $v)
 * @method string getSecret()
 * @method void setSecret(string $v)
 * @method string getEvents()
 * @method void setEvents(string $v)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $v)
 * @method int|null getLastDeliveryAt()
 * @method void setLastDeliveryAt(?int $v)
 * @method string getLastStatus()
 * @method void setLastStatus(string $v)
 * @method int getFailCount()
 * @method void setFailCount(int $v)
 * @method int|null getDisabledAt()
 * @method void setDisabledAt(?int $v)
 * @method int|null getRevokedAt()
 * @method void setRevokedAt(?int $v)
 */
class AgentWebhook extends Entity
{
	public const STATUS_NEVER = 'never';
	public const STATUS_OK = 'ok';
	public const STATUS_FAILED = 'failed';

	/** Event wildcard — a subscription to '*' receives every app event. */
	public const ALL_EVENTS = '*';

	protected string $userId = '';
	protected string $url = '';
	protected string $secret = '';
	protected string $events = '';
	protected int $createdAt = 0;
	protected ?int $lastDeliveryAt = null;
	protected string $lastStatus = '';
	protected int $failCount = 0;
	protected ?int $disabledAt = null;
	protected ?int $revokedAt = null;

	public function __construct()
	{
		$this->addType('userId', 'string');
		$this->addType('url', 'string');
		$this->addType('secret', 'string');
		$this->addType('events', 'string');
		$this->addType('createdAt', 'integer');
		$this->addType('lastDeliveryAt', 'integer');
		$this->addType('lastStatus', 'string');
		$this->addType('failCount', 'integer');
		$this->addType('disabledAt', 'integer');
		$this->addType('revokedAt', 'integer');
	}

	/**
	 * @return list<string>
	 */
	public function eventList(): array
	{
		$raw = trim($this->getEvents());
		if ($raw === '') {
			return [];
		}
		return array_values(array_filter(explode(' ', $raw), static fn (string $s): bool => $s !== ''));
	}

	public function subscribesTo(string $event): bool
	{
		$events = $this->eventList();
		return in_array(self::ALL_EVENTS, $events, true) || in_array($event, $events, true);
	}

	public function isActive(): bool
	{
		return $this->getRevokedAt() === null && $this->getDisabledAt() === null;
	}
}
