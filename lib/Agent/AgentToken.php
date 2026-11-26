<?php
/** Synced from _shared/agent-auth — do not edit app-side. */

declare(strict_types=1);

namespace OCA\ProjectCheck\Agent;

use OCP\AppFramework\Db\Entity;

/**
 * Scoped agent token row (pc_agent_tokens).
 *
 * The raw token is never stored — only sha256(raw). The wire format
 * presented to the user once is `ckst_projectcheck_<32urlsafe>` (~192 bits of
 * entropy; a high-entropy lookup key, not a password — no KDF needed).
 *
 * @method string getUserId()
 * @method void setUserId(string $v)
 * @method string getLabel()
 * @method void setLabel(string $v)
 * @method string getTokenHash()
 * @method void setTokenHash(string $v)
 * @method string getScopes()
 * @method void setScopes(string $v)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $v)
 * @method int getExpiresAt()
 * @method void setExpiresAt(int $v)
 * @method int|null getLastUsedAt()
 * @method void setLastUsedAt(?int $v)
 * @method string|null getLastTool()
 * @method void setLastTool(?string $v)
 * @method int|null getRateWindowStart()
 * @method void setRateWindowStart(?int $v)
 * @method int getRateCount()
 * @method void setRateCount(int $v)
 * @method int|null getRevokedAt()
 * @method void setRevokedAt(?int $v)
 */
class AgentToken extends Entity
{
	protected string $userId = '';
	protected string $label = '';
	protected string $tokenHash = '';
	protected string $scopes = '';
	protected int $createdAt = 0;
	protected int $expiresAt = 0;
	protected ?int $lastUsedAt = null;
	protected ?string $lastTool = null;
	protected ?int $rateWindowStart = null;
	protected int $rateCount = 0;
	protected ?int $revokedAt = null;

	public function __construct()
	{
		$this->addType('userId', 'string');
		$this->addType('label', 'string');
		$this->addType('tokenHash', 'string');
		$this->addType('scopes', 'string');
		$this->addType('createdAt', 'integer');
		$this->addType('expiresAt', 'integer');
		$this->addType('lastUsedAt', 'integer');
		$this->addType('lastTool', 'string');
		$this->addType('rateWindowStart', 'integer');
		$this->addType('rateCount', 'integer');
		$this->addType('revokedAt', 'integer');
	}

	/**
	 * @return list<string>
	 */
	public function scopeList(): array
	{
		$raw = trim($this->getScopes());
		if ($raw === '') {
			return [];
		}
		return array_values(array_filter(explode(' ', $raw), static fn (string $s): bool => $s !== ''));
	}

	public function isRevoked(): bool
	{
		return $this->getRevokedAt() !== null;
	}

	public function isExpired(int $now): bool
	{
		return $this->getExpiresAt() <= $now;
	}
}
