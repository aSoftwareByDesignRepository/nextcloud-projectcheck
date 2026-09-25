<?php

declare(strict_types=1);

/**
 * Short-lived idempotency for HTML/API project create (double-submit / refresh).
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Service;

use OCA\ProjectCheck\Exception\IdempotencyConflictException;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;

/**
 * Maps a user-scoped create nonce to a project id so a replayed POST
 * returns the same project instead of inserting a duplicate.
 *
 * Serialization uses ILockingProvider (works across APCu/Redis/file backends).
 * The distributed cache stores the resulting project id for fast replay.
 *
 * Invariant: once $factory() returns an id, that mapping must never be cleared
 * by a later cache failure — otherwise a refresh re-runs create (duplicate project).
 */
final class FormSubmitIdempotencyService
{
	private const TTL_SECONDS = 3600;

	public function __construct(
		private readonly ICacheFactory $cacheFactory,
		private readonly ILockingProvider $locking,
	) {
	}

	/**
	 * @param callable(): int $factory Must return the new project id
	 * @param string|null $payloadFingerprint Hash of the normalized create
	 *        payload. When a stored mapping carries a fingerprint, a replay with
	 *        a different fingerprint is a 409 conflict — never a silent replay.
	 * @throws IdempotencyConflictException
	 */
	public function rememberCreate(string $userId, ?string $nonce, callable $factory, ?string $payloadFingerprint = null): int
	{
		$nonce = $this->normalizeNonce($nonce);
		if ($nonce === null) {
			return $factory();
		}

		$cache = $this->cache();
		$key = $this->createKey($userId, $nonce);

		$existing = $this->readMapping($cache->get($key));
		if ($existing !== null) {
			$this->assertFingerprintMatches($existing['hash'], $payloadFingerprint);
			return $existing['id'];
		}

		$lockKey = 'projectcheck/form_idem/' . hash('sha256', $userId . "\0" . $nonce);
		try {
			$this->locking->acquireLock($lockKey, ILockingProvider::LOCK_EXCLUSIVE);
		} catch (LockedException) {
			// Another request holds the lock — wait briefly for the id.
			for ($i = 0; $i < 40; $i++) {
				usleep(50_000);
				$existing = $this->readMapping($cache->get($key));
				if ($existing !== null) {
					$this->assertFingerprintMatches($existing['hash'], $payloadFingerprint);
					return $existing['id'];
				}
			}
			throw new \RuntimeException('Create already in progress. Please wait and refresh.');
		}

		$projectId = null;
		try {
			$existing = $this->readMapping($cache->get($key));
			if ($existing !== null) {
				$this->assertFingerprintMatches($existing['hash'], $payloadFingerprint);
				return $existing['id'];
			}
			$projectId = $factory();
			$this->persistMapping($cache, $key, $projectId, $payloadFingerprint);
			return $projectId;
		} catch (IdempotencyConflictException $e) {
			// Never destroy another request's mapping on a payload conflict.
			throw $e;
		} catch (\Throwable $e) {
			// Only clear when create itself failed. After a successful insert,
			// never remove the key — retry persist and surface the created id.
			if ($projectId === null) {
				$cache->remove($key);
				throw $e;
			}
			try {
				$cache->set($key, $this->formatMapping($projectId, $payloadFingerprint), self::TTL_SECONDS);
			} catch (\Throwable) {
				// Best-effort; caller still receives the real project id.
			}
			return $projectId;
		} finally {
			$this->locking->releaseLock($lockKey, ILockingProvider::LOCK_EXCLUSIVE);
		}
	}

	public function normalizeNonce(?string $nonce): ?string
	{
		if ($nonce === null) {
			return null;
		}
		$nonce = trim($nonce);
		if ($nonce === '' || strlen($nonce) > 64) {
			return null;
		}
		if (preg_match('/^[A-Za-z0-9._:-]+$/', $nonce) !== 1) {
			return null;
		}
		return $nonce;
	}

	public function mintNonce(): string
	{
		return bin2hex(random_bytes(16));
	}

	private function cache(): ICache
	{
		return $this->cacheFactory->createDistributed('projectcheck_form_idem');
	}

	private function createKey(string $userId, string $nonce): string
	{
		return 'create:' . $userId . ':' . $nonce;
	}

	/**
	 * Cache value format: "<id>" (legacy) or "<id>:<sha256>" (fingerprinted).
	 *
	 * @return array{id: int, hash: ?string}|null
	 */
	private function readMapping(mixed $raw): ?array
	{
		if (is_int($raw) && $raw > 0) {
			return ['id' => $raw, 'hash' => null];
		}
		if (is_string($raw) && $raw !== '') {
			$idPart = $raw;
			$hash = null;
			$colon = strpos($raw, ':');
			if ($colon !== false) {
				$idPart = substr($raw, 0, $colon);
				$hash = substr($raw, $colon + 1) ?: null;
			}
			if (ctype_digit($idPart)) {
				$id = (int) $idPart;
				return $id > 0 ? ['id' => $id, 'hash' => $hash] : null;
			}
		}
		return null;
	}

	private function formatMapping(int $projectId, ?string $payloadFingerprint): string
	{
		return $payloadFingerprint !== null
			? $projectId . ':' . $payloadFingerprint
			: (string) $projectId;
	}

	/**
	 * A stored NULL fingerprint means the mapping predates fingerprinting —
	 * a mismatch cannot be proven, so it replays (legacy semantics).
	 *
	 * @throws IdempotencyConflictException
	 */
	private function assertFingerprintMatches(?string $stored, ?string $incoming): void
	{
		if ($stored !== null && $incoming !== null && !hash_equals($stored, $incoming)) {
			throw new IdempotencyConflictException(
				'This idempotency key was already used with different form data.'
			);
		}
	}

	/**
	 * Write mapping and verify it round-trips (silent APCu/Redis glitches).
	 */
	private function persistMapping(ICache $cache, string $key, int $projectId, ?string $payloadFingerprint): void
	{
		$cache->set($key, $this->formatMapping($projectId, $payloadFingerprint), self::TTL_SECONDS);
		$read = $this->readMapping($cache->get($key));
		if ($read !== null && $read['id'] === $projectId) {
			return;
		}
		$cache->set($key, $this->formatMapping($projectId, $payloadFingerprint), self::TTL_SECONDS);
		$read = $this->readMapping($cache->get($key));
		if ($read === null || $read['id'] !== $projectId) {
			throw new \RuntimeException('Could not persist create idempotency mapping.');
		}
	}
}
