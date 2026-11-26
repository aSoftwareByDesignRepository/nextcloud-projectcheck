<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Agent;

use OCA\ProjectCheck\Service\LicenseService;

/**
 * Per-request seat entitlement for agent requests (BQ-3). Delegates to the
 * app's mobile-seat authority — a revoked seat kills the token on its next
 * call, without waiting for token expiry. Any entitlement failure collapses
 * to one uniform 403 — no seat/license existence oracle.
 */
final class ProjectCheckAgentSeatGate implements AgentSeatGate
{
	public function __construct(
		private readonly LicenseService $license,
	) {
	}

	public function assertEntitled(string $uid): void
	{
		try {
			$this->license->assertMobileAccess($uid);
		} catch (AgentException $e) {
			throw $e;
		} catch (\Throwable $e) {
			throw new AgentException(
				'agent_seat_revoked',
				'This agent token has no active entitlement.',
				403,
			);
		}
	}
}
