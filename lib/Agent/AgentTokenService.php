<?php
/** Synced from _shared/agent-auth — do not edit app-side. */

declare(strict_types=1);

namespace OCA\ProjectCheck\Agent;

/**
 * Token lifecycle: mint (one-time raw display, hash-only at rest), resolve,
 * revoke, rotate, fixed-window mutation budget.
 *
 * Policy (pinned):
 *   - wire format `ckst_projectcheck_<32urlsafe>` = prefix + base64url(24 bytes)
 *     (~192 bits entropy; recognizable ckst_ prefix for secret scanners)
 *   - sha256(raw) at rest — high-entropy lookup key, no KDF needed
 *   - default/hard-max expiry 1 year; ALWAYS set (write-scoped tokens must
 *     never be permanent credentials)
 *   - revocation is effective on the next request (no resolve cache)
 *   - mutation budget: 60/min/token, fixed window on the token row
 */
class AgentTokenService
{
	public const TOKEN_PREFIX = 'ckst_projectcheck_';
	public const EXPIRY_SECONDS = 31536000; // 365d — hard max, no override
	public const MUTATION_BUDGET_PER_WINDOW = 60;
	public const RATE_WINDOW_SECONDS = 60;
	public const MAX_TOKENS_PER_USER = 25;
	// Mirrors AgentTablesSchema::SCOPES_LENGTH — the joined scope string must
	// fit the column; fail with a clean 422, never a DB length error.
	public const SCOPES_MAX_LENGTH = 2000;

	public function __construct(
		private readonly AgentTokenMapper $tokens,
		private readonly AgentAuditMapper $audit,
		private readonly AgentManifest $manifest,
	) {
	}

	public static function hashToken(string $raw): string
	{
		return hash('sha256', $raw);
	}

	public static function isWireFormat(string $value): bool
	{
		return str_starts_with($value, 'ckst_');
	}

	/**
	 * Mint a token for $uid. Returns [raw (shown ONCE), entity].
	 *
	 * @param list<string> $scopes exact scopes — already validated against
	 *                             the manifest universe by the caller path
	 *                             (re-validated here; mint is a trust boundary)
	 * @return array{0: string, 1: AgentToken}
	 */
	public function mint(string $uid, string $label, array $scopes, int $now, ?AgentToken $replacing = null): array
	{
		$label = trim($label);
		if ($label === '' || mb_strlen($label) > 190) {
			throw new AgentException('validation', 'Label must be 1–190 characters.', 422, [
				'fields' => ['label' => 'invalid_length'],
			]);
		}
		$grants = AgentScopeChecker::validateGrantRequest($scopes, $this->manifest->scopeSet());
		if (strlen(implode(' ', $grants)) > self::SCOPES_MAX_LENGTH) {
			throw new AgentException('validation', 'Scope selection exceeds the storage limit.', 422, [
				'fields' => ['scopes' => 'too_long'],
			]);
		}

		// $replacing: the source row of a rotate() still counts as live until
		// revoked below — rotation at the cap must not deadlock itself.
		$live = $this->tokens->countActiveByUser($uid, $now) - ($replacing !== null ? 1 : 0);
		if ($live >= self::MAX_TOKENS_PER_USER) {
			throw new AgentException('validation', 'Token limit reached — revoke an existing token first.', 422, [
				'fields' => ['tokens' => 'limit_reached'],
			]);
		}

		// 24 bytes → base64url → exactly 32 urlsafe chars (~192 bits).
		$raw = self::TOKEN_PREFIX . rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');

		$entity = new AgentToken();
		$entity->setUserId($uid);
		$entity->setLabel($label);
		$entity->setTokenHash(self::hashToken($raw));
		$entity->setScopes(implode(' ', $grants));
		$entity->setCreatedAt($now);
		$entity->setExpiresAt($now + self::EXPIRY_SECONDS);
		$entity->setRateCount(0);
		$entity = $this->tokens->insert($entity);

		return [$raw, $entity];
	}

	/**
	 * Resolve a presented credential to its row. Missing/unknown/revoked/
	 * expired all collapse to the same 401 — an attacker probing token
	 * states gets one undifferentiated answer.
	 */
	public function resolve(string $raw, int $now): AgentToken
	{
		if (!self::isWireFormat($raw) || strlen($raw) > 200) {
			throw new AgentException('agent_token_invalid', 'Invalid agent token.', 401);
		}
		// A missing table (mid-upgrade, disabled-but-enabled state) must not
		// surface as a 500 — no token can be valid, so fail closed on the
		// same undifferentiated 401.
		try {
			$token = $this->tokens->findByHash(self::hashToken($raw));
		} catch (\OCP\DB\Exception) {
			$token = null;
		}
		if ($token === null || $token->isRevoked() || $token->isExpired($now)) {
			throw new AgentException('agent_token_invalid', 'Invalid agent token.', 401);
		}
		return $token;
	}

	/**
	 * Rotate (NH-07): mint a replacement keeping label+scopes+owner, then
	 * revoke the source. The new token is created FIRST so a failure leaves
	 * the old credential alive — never strand the user with two dead keys.
	 * Brief overlap where both are valid is bounded to the call duration.
	 *
	 * @return array{0: string, 1: AgentToken}
	 */
	public function rotate(AgentToken $source, int $now): array
	{
		[$raw, $entity] = $this->mint($source->getUserId(), $source->getLabel(), $source->scopeList(), $now, replacing: $source);
		$this->tokens->revoke($source->getId(), $now);
		return [$raw, $entity];
	}

	public function revoke(AgentToken $token, int $now): void
	{
		$this->tokens->revoke($token->getId(), $now);
	}

	/**
	 * Fixed-window mutation budget. Returns Retry-After seconds on breach.
	 *
	 * @return int|null retry-after seconds when over budget, null when allowed
	 */
	public function consumeMutationBudget(AgentToken $token, int $now): ?int
	{
		$count = $this->tokens->consumeMutationSlot($token->getId(), $now, self::RATE_WINDOW_SECONDS);
		if ($count <= self::MUTATION_BUDGET_PER_WINDOW) {
			return null;
		}
		// The entity was read before the conditional UPDATE — if its window
		// start is stale, the row just rolled into a fresh window (full 60s).
		$windowStart = $token->getRateWindowStart();
		if ($windowStart === null || $windowStart < $now - self::RATE_WINDOW_SECONDS) {
			return self::RATE_WINDOW_SECONDS;
		}
		return max(1, self::RATE_WINDOW_SECONDS - ($now - $windowStart));
	}

	/**
	 * "Last used" bookkeeping — one cheap row write per agent request,
	 * delegated to the mapper's conditional UPDATE.
	 */
	public function touch(int $id, string $tool, int $now): void
	{
		$this->tokens->touch($id, $tool, $now);
	}

	/**
	 * Serialize for the settings/API list — NEVER includes hash or raw.
	 *
	 * @return array<string, mixed>
	 */
	public function serialize(AgentToken $token, int $now): array
	{
		return [
			'id' => $token->getId(),
			'userId' => $token->getUserId(),
			'label' => $token->getLabel(),
			'scopes' => $token->scopeList(),
			'createdAt' => $token->getCreatedAt(),
			'expiresAt' => $token->getExpiresAt(),
			'lastUsedAt' => $token->getLastUsedAt(),
			'lastTool' => $token->getLastTool(),
			'state' => $token->isRevoked() ? 'revoked' : ($token->isExpired($now) ? 'expired' : 'active'),
		];
	}
}
