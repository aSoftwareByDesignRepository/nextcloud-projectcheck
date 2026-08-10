<?php

declare(strict_types=1);

/**
 * Short-lived idempotency for HTML/API project create (double-submit / refresh).
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Service;

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
	 */
	public function rememberCreate(string $userId, ?string $nonce, callable $factory): int
	{
		$nonce = $this->normalizeNonce($nonce);
		if ($nonce === null) {
			return $factory();
		}

		$cache = $this->cache();
		$key = $this->createKey($userId, $nonce);

		$existing = $this->readProjectId($cache->get($key));
		if ($existing !== null) {
			return $existing;
		}

		$lockKey = 'projectcheck/form_idem/' . hash('sha256', $userId . "\0" . $nonce);
		try {
			$this->locking->acquireLock($lockKey, ILockingProvider::LOCK_EXCLUSIVE);
		} catch (LockedException) {
			// Another request holds the lock — wait briefly for the id.
			for ($i = 0; $i < 40; $i++) {
				usleep(50_000);
				$existing = $this->readProjectId($cache->get($key));
				if ($existing !== null) {
					return $existing;
				}
			}
			throw new \RuntimeException('Create already in progress. Please wait and refresh.');
		}

		$projectId = null;
		try {
			$existing = $this->readProjectId($cache->get($key));
			if ($existing !== null) {
				return $existing;
			}
			$projectId = $factory();
			$this->persistMapping($cache, $key, $projectId);
			return $projectId;
		} catch (\Throwable $e) {
			// Only clear when create itself failed. After a successful insert,
			// never remove the key — retry persist and surface the created id.
			if ($projectId === null) {
				$cache->remove($key);
				throw $e;
			}
			try {
				$cache->set($key, $projectId, self::TTL_SECONDS);
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

	private function readProjectId(mixed $raw): ?int
	{
		if (is_int($raw) && $raw > 0) {
			return $raw;
		}
		if (is_string($raw) && ctype_digit($raw)) {
			$id = (int) $raw;
			return $id > 0 ? $id : null;
		}
		return null;
	}

	/**
	 * Write mapping and verify it round-trips (silent APCu/Redis glitches).
	 */
	private function persistMapping(ICache $cache, string $key, int $projectId): void
	{
		$cache->set($key, $projectId, self::TTL_SECONDS);
		if ($this->readProjectId($cache->get($key)) === $projectId) {
			return;
		}
		$cache->set($key, $projectId, self::TTL_SECONDS);
		if ($this->readProjectId($cache->get($key)) !== $projectId) {
			throw new \RuntimeException('Could not persist create idempotency mapping.');
		}
	}
}
