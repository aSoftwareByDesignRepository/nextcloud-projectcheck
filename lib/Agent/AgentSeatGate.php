<?php
/** Synced from _shared/agent-auth — do not edit app-side. */

declare(strict_types=1);

namespace OCA\ProjectCheck\Agent;

/**
 * Per-app seam: the per-request entitlement re-check (BQ-3 — an agent
 * token is valid only while the owning user holds a valid mobile seat).
 *
 * Implementations wrap the app's existing license gate
 * (LicenseService::assertMobileAccess / MobileGateService::assertGatePassed
 * / MobileSeatService::hasSeat / …) and MUST throw AgentException with a
 * license-family error_code (license_required / seat_required / …) and
 * HTTP 402 when the seat is absent. Mint-time checks are NOT sufficient —
 * this runs on every agent request so a revoked seat kills the token on
 * the next call.
 */
interface AgentSeatGate
{
	/**
	 * @throws AgentException 402-family when the uid holds no valid seat
	 */
	public function assertEntitled(string $uid): void;
}
