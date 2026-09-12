<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Service;

use OCA\ProjectCheck\Config\VendorPublicKey;
use OCA\ProjectCheck\Exception\MobileGateException;
use OCA\ProjectCheck\License\Pc2Codec;

/**
 * License/seat gate for /mobile/v1/* (SERVER-MOBILE-API §2).
 *
 * Rungs 1 (NC auth) and 2 (canUseApp) are enforced by Nextcloud + AppAccessMiddleware.
 * This service enforces rungs 3–6:
 *   3 license row exists           → 402 license_missing
 *   4 today ≤ valid_until          → 402 license_expired
 *   5 caller has a seat            → 402 seat_required
 *   6 seat within limit            → 402 seat_limit_exceeded
 *
 * `bootstrap` skips 3–6 and reports state so the official app can render LicenseGate.
 */
class MobileGateService
{
	public function __construct(
		private readonly LicenseService $license,
	) {
	}

	public function assertGatePassed(string $uid): void
	{
		$state = $this->license->gateState($uid);
		if (!$state['hasLicense']) {
			throw new MobileGateException('license_missing');
		}
		if (!$state['licenseValid']) {
			throw new MobileGateException('license_expired');
		}
		if (!$state['seatAssigned']) {
			throw new MobileGateException('seat_required');
		}
		if (!$state['seatWithinLimit']) {
			throw new MobileGateException('seat_limit_exceeded');
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	public function bootstrapPayload(
		string $uid,
		string $displayName,
		string $appVersion,
		bool $pushAvailable = false,
		bool $canSettle = false,
	): array {
		$state = $this->license->gateState($uid);
		$enabledForUser = $state['seatAssigned'] && $state['seatWithinLimit'] && $state['licenseValid'];
		$status = $this->license->status();
		$expiresAt = is_array($status['state'] ?? null) ? ($status['state']['validUntil'] ?? null) : null;
		$licensing = null;
		if ($state['hasLicense'] && $state['payloadB64'] !== null && $state['signatureB64'] !== null) {
			$licensing = [
				'format' => Pc2Codec::FORMAT,
				'payloadB64' => $state['payloadB64'],
				'signatureB64' => $state['signatureB64'],
				'envelope' => [
					'format' => Pc2Codec::FORMAT,
					'payloadB64' => $state['payloadB64'],
					'signatureB64' => $state['signatureB64'],
				],
				// Lets the official app distinguish corrupt envelopes from app↔server key mismatch.
				'vendorPublicKeyB64' => VendorPublicKey::publicKeyB64(),
				// AZC-shaped mobile block for LicenseGate / Settings copy.
				'mobile' => [
					'enabledForUser' => $enabledForUser,
					'expiresAt' => is_string($expiresAt) ? $expiresAt : null,
				],
			];
		}

		return [
			'appId' => 'projectcheck',
			'serverVersion' => $appVersion,
			'apiVersion' => 1,
			'capabilities' => [
				'timeEntries' => true,
				'timerServer' => false,
				'ratePreview' => true,
				// v1.1 companion capabilities (clients hide UI when false/absent).
				'settlement' => true,
				'offlineCreate' => true,
				// Advertise push only when the notifications app can deliver it.
				'push' => $pushAvailable,
			],
			'licensing' => $licensing,
			// Raw seat membership (not the enabled composite). Clients must also honour
			// seatWithinLimit + licensing.mobile.enabledForUser for the full ladder.
			'seatAssigned' => $state['seatAssigned'],
			'seatWithinLimit' => $state['seatWithinLimit'],
			'pushAvailable' => $pushAvailable,
			'canSettle' => $canSettle,
			'mobileAppStatus' => LicenseService::MOBILE_APP_STATUS,
			'user' => [
				'userId' => $uid,
				'uid' => $uid,
				'displayName' => $displayName,
			],
		];
	}
}
