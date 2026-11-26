<?php
/** Synced from _shared/agent-auth — do not edit app-side. */

declare(strict_types=1);

namespace OCA\ProjectCheck\Agent;

/**
 * Typed agent-auth failure. error_code is the stable machine contract the
 * MCP adapter maps to typed MCP errors — never translate or reword codes.
 *
 * Codes (pinned):
 *   agent_token_invalid    401  missing/unknown/revoked/expired token,
 *                               or a ckst_ value smuggled into Basic/Bearer
 *   agent_scope_denied     403  valid token, missing scope or hard-denied path
 *   confirmation_required  422  risk-class ≥2 tool without confirm=true
 *   rate_limited           429  >60 mutating calls/minute on one token
 *   validation             422  bad mint request (label/scopes/expiry)
 *   forbidden              403  session caller lacks admin/mint rights
 *   not_found              404  token id unknown to this user (uniform-404:
 *                               never distinguish "exists but not yours")
 */
class AgentException extends \Exception
{
	public function __construct(
		private readonly string $errorCode,
		string $message,
		private readonly int $httpStatus,
		private readonly array $details = [],
	) {
		parent::__construct($message);
	}

	public function getErrorCode(): string
	{
		return $this->errorCode;
	}

	public function getHttpStatus(): int
	{
		return $this->httpStatus;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getDetails(): array
	{
		return $this->details;
	}
}
