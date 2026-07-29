<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\License;

use OCA\ProjectCheck\Config\VendorPublicKey;

/**
 * PC2 wire format (SPEC §8.1): `PC2.<payload_b64url>.<signature_b64url>`,
 * Ed25519 detached signature over the canonical payload bytes.
 *
 * Canonical payload key order: v, product, customerId, issuedAt, validUntil,
 * mobileSeats — encoded with JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES.
 *
 * Dates never block acceptance (S16); expiry is evaluated at gate time.
 */
final class Pc2Codec
{
	public const FORMAT = 'PC2';
	public const VERSION = 2;
	public const PRODUCT = 'projectcheck';

	public const ERROR_INVALID_FORMAT = 'invalid_format';
	public const ERROR_INVALID_SIGNATURE = 'invalid_signature';
	public const ERROR_INVALID_PAYLOAD = 'invalid_payload';

	public static function normalizeWireKey(string $wireKey): string
	{
		return preg_replace('/\s+/u', '', $wireKey) ?? trim($wireKey);
	}

	/**
	 * Full verification. Returns payload + wire parts, or null when any
	 * check fails (use {@see classifyError} for the failure reason).
	 *
	 * @return array{payload: array<string, mixed>, payloadB64: string, signatureB64: string}|null
	 */
	public static function parseAndVerify(string $wireKey): ?array
	{
		return self::classifyError($wireKey) === '' ? self::decodeUnchecked($wireKey) : null;
	}

	/**
	 * Returns '' when the key is fully valid, else the failed check
	 * (`invalid_format` | `invalid_signature` | `invalid_payload`).
	 */
	public static function classifyError(string $wireKey): string
	{
		$wireKey = self::normalizeWireKey($wireKey);
		$parts = explode('.', $wireKey);
		if (count($parts) !== 3 || $parts[0] !== self::FORMAT) {
			return self::ERROR_INVALID_FORMAT;
		}

		$payloadBytes = VendorPublicKey::base64urlDecode($parts[1]);
		$signature = VendorPublicKey::base64urlDecode($parts[2]);
		if ($payloadBytes === false || $signature === false || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
			return self::ERROR_INVALID_FORMAT;
		}

		if (!sodium_crypto_sign_verify_detached($signature, $payloadBytes, VendorPublicKey::bytes())) {
			return self::ERROR_INVALID_SIGNATURE;
		}

		try {
			$payload = json_decode($payloadBytes, true, 16, JSON_THROW_ON_ERROR);
		} catch (\JsonException) {
			return self::ERROR_INVALID_PAYLOAD;
		}
		if (!is_array($payload) || !self::validatePayloadFields($payload)) {
			return self::ERROR_INVALID_PAYLOAD;
		}

		// Re-serialisation equality: signed bytes must be the canonical form.
		if (!hash_equals(self::canonicalJson($payload), $payloadBytes)) {
			return self::ERROR_INVALID_PAYLOAD;
		}

		return '';
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	public static function validatePayloadFields(array $payload): bool
	{
		if (($payload['v'] ?? null) !== self::VERSION) {
			return false;
		}
		if (($payload['product'] ?? null) !== self::PRODUCT) {
			return false;
		}
		$customerId = $payload['customerId'] ?? null;
		if (!is_string($customerId) || !preg_match('/^[a-z0-9-]{3,64}$/', $customerId)) {
			return false;
		}
		foreach (['issuedAt', 'validUntil'] as $dateField) {
			$val = $payload[$dateField] ?? null;
			if (!is_string($val) || !self::isValidYmd($val)) {
				return false;
			}
		}
		if ($payload['validUntil'] < $payload['issuedAt']) {
			return false;
		}
		$seats = $payload['mobileSeats'] ?? null;
		if (!is_int($seats) || $seats < 1 || $seats > 10000) {
			return false;
		}
		return true;
	}

	/**
	 * Gate-time validity (SPEC §5.3): valid iff today ≤ valid_until (inclusive).
	 */
	public static function isValidOn(string $validUntil, string $today): bool
	{
		return $today <= $validUntil;
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	public static function canonicalJson(array $payload): string
	{
		$ordered = [
			'v' => (int)$payload['v'],
			'product' => (string)$payload['product'],
			'customerId' => (string)$payload['customerId'],
			'issuedAt' => (string)$payload['issuedAt'],
			'validUntil' => (string)$payload['validUntil'],
			'mobileSeats' => (int)$payload['mobileSeats'],
		];
		$json = json_encode($ordered, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if ($json === false) {
			throw new \RuntimeException('PC2 canonical JSON encode failed.');
		}
		return $json;
	}

	/**
	 * @return array{payload: array<string, mixed>, payloadB64: string, signatureB64: string}|null
	 */
	private static function decodeUnchecked(string $wireKey): ?array
	{
		$wireKey = self::normalizeWireKey($wireKey);
		$parts = explode('.', $wireKey);
		$payloadBytes = VendorPublicKey::base64urlDecode($parts[1]);
		if ($payloadBytes === false) {
			return null;
		}
		$payload = json_decode($payloadBytes, true);
		if (!is_array($payload)) {
			return null;
		}
		return [
			'payload' => $payload,
			'payloadB64' => $parts[1],
			'signatureB64' => $parts[2],
		];
	}

	private static function isValidYmd(string $value): bool
	{
		if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
			return false;
		}
		return checkdate((int)$m[2], (int)$m[3], (int)$m[1]);
	}
}
