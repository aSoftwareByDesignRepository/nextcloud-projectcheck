<?php
/** Synced from _shared/agent-auth — do not edit app-side. */

declare(strict_types=1);

/**
 * Request-scoped agent principal holder.
 *
 * Populated by AgentAuthMiddleware before the controller runs; consulted by
 * the app's requireUid()-style helper BEFORE the session user so an agent
 * request carrying a foreign session cookie still resolves as the token
 * user (explicit credential wins — cookie-shadows-basic regression guard).
 *
 * Registered via registerService so the NC DI container caches ONE instance
 * per request lifecycle: the instance the middleware arms is the instance
 * the controller reads. Under PHP-FPM each request builds fresh state —
 * there is no cross-request persistence. The per-app isolation test MUST
 * assert two sequential requests cannot see each other's context (a
 * shared/static instance would be a cross-user auth bug — the single most
 * dangerous wiring mistake in this pack).
 *
 * SECURITY: this class never touches the session. Arming a context must
 * never create a persistent NC session, cookie, or device token (NN-10).
 */

namespace OCA\ProjectCheck\Agent;

final class AgentContext
{
	private ?string $uid = null;
	private ?int $tokenId = null;
	private ?string $tool = null;

	/** @var list<string> */
	private array $scopes = [];

	/**
	 * @param list<string> $scopes
	 */
	public function arm(string $uid, int $tokenId, array $scopes): void
	{
		$this->uid = $uid;
		$this->tokenId = $tokenId;
		$this->scopes = $scopes;
	}

	public function isArmed(): bool
	{
		return $this->uid !== null;
	}

	public function uid(): ?string
	{
		return $this->uid;
	}

	public function tokenId(): ?int
	{
		return $this->tokenId;
	}

	/**
	 * @return list<string>
	 */
	public function scopes(): array
	{
		return $this->scopes;
	}

	public function hasScope(string $scope): bool
	{
		return in_array($scope, $this->scopes, true);
	}

	/**
	 * Manifest tool name matched for this request (audit attribution).
	 */
	public function setTool(string $tool): void
	{
		$this->tool = $tool;
	}

	public function tool(): ?string
	{
		return $this->tool;
	}
}
