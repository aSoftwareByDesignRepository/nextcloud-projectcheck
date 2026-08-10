<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Service;

use OCA\ProjectCheck\Service\FormSubmitIdempotencyService;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;
use PHPUnit\Framework\TestCase;

final class FormSubmitIdempotencyServiceTest extends TestCase
{
	private function lockingMock(bool $acquireOk = true): ILockingProvider
	{
		$locking = $this->createMock(ILockingProvider::class);
		if ($acquireOk) {
			$locking->method('acquireLock')->willReturnCallback(static function (): void {
			});
		} else {
			$locking->method('acquireLock')->willThrowException(new LockedException('busy'));
		}
		$locking->method('releaseLock')->willReturnCallback(static function (): void {
		});
		return $locking;
	}

	public function testWithoutNonceAlwaysCreates(): void
	{
		$cacheFactory = $this->createMock(ICacheFactory::class);
		$cacheFactory->expects(self::never())->method('createDistributed');
		$svc = new FormSubmitIdempotencyService($cacheFactory, $this->lockingMock());
		$calls = 0;
		$id = $svc->rememberCreate('alice', null, static function () use (&$calls): int {
			$calls++;
			return 42;
		});
		self::assertSame(42, $id);
		self::assertSame(1, $calls);
	}

	public function testReplayReturnsCachedProjectId(): void
	{
		$cache = $this->createMock(ICache::class);
		$cache->method('get')->with('create:alice:nonce-1')->willReturn(77);
		$locking = $this->lockingMock();
		$locking->expects(self::never())->method('acquireLock');
		$cacheFactory = $this->createMock(ICacheFactory::class);
		$cacheFactory->method('createDistributed')->willReturn($cache);
		$svc = new FormSubmitIdempotencyService($cacheFactory, $locking);
		$calls = 0;
		$id = $svc->rememberCreate('alice', 'nonce-1', static function () use (&$calls): int {
			$calls++;
			return 1;
		});
		self::assertSame(77, $id);
		self::assertSame(0, $calls);
	}

	public function testClaimCreatesAndStores(): void
	{
		$cache = $this->createMock(ICache::class);
		$cache->method('get')->willReturnOnConsecutiveCalls(null, null, 9);
		$cache->expects(self::once())->method('set')->with('create:alice:n2', 9, 3600);
		$locking = $this->lockingMock();
		$locking->expects(self::once())->method('acquireLock');
		$locking->expects(self::once())->method('releaseLock');
		$cacheFactory = $this->createMock(ICacheFactory::class);
		$cacheFactory->method('createDistributed')->willReturn($cache);
		$svc = new FormSubmitIdempotencyService($cacheFactory, $locking);
		$id = $svc->rememberCreate('alice', 'n2', static fn (): int => 9);
		self::assertSame(9, $id);
	}

	public function testFactoryFailureReleasesClaimAndRemovesCache(): void
	{
		$cache = $this->createMock(ICache::class);
		$cache->method('get')->willReturn(null);
		$cache->expects(self::once())->method('remove')->with('create:alice:n3');
		$locking = $this->lockingMock();
		$locking->expects(self::once())->method('releaseLock');
		$cacheFactory = $this->createMock(ICacheFactory::class);
		$cacheFactory->method('createDistributed')->willReturn($cache);
		$svc = new FormSubmitIdempotencyService($cacheFactory, $locking);
		$this->expectException(\RuntimeException::class);
		$svc->rememberCreate('alice', 'n3', static function (): int {
			throw new \RuntimeException('boom');
		});
	}

	public function testCachePersistFailureAfterCreateStillReturnsIdAndKeepsMapping(): void
	{
		$cache = $this->createMock(ICache::class);
		$cache->method('get')->willReturn(null);
		$setCalls = 0;
		$cache->method('set')->willReturnCallback(static function () use (&$setCalls): void {
			$setCalls++;
			if ($setCalls === 1) {
				throw new \RuntimeException('cache down');
			}
		});
		$cache->expects(self::never())->method('remove');
		$locking = $this->lockingMock();
		$locking->expects(self::once())->method('releaseLock');
		$cacheFactory = $this->createMock(ICacheFactory::class);
		$cacheFactory->method('createDistributed')->willReturn($cache);
		$svc = new FormSubmitIdempotencyService($cacheFactory, $locking);
		$id = $svc->rememberCreate('alice', 'n4', static fn (): int => 88);
		self::assertSame(88, $id);
		self::assertGreaterThanOrEqual(2, $setCalls);
	}

	public function testNormalizeRejectsGarbage(): void
	{
		$svc = new FormSubmitIdempotencyService(
			$this->createMock(ICacheFactory::class),
			$this->lockingMock()
		);
		self::assertNull($svc->normalizeNonce(''));
		self::assertNull($svc->normalizeNonce('bad nonce!'));
		self::assertSame('ok-1', $svc->normalizeNonce('ok-1'));
	}

	public function testMintNonceIsOpaqueHex(): void
	{
		$svc = new FormSubmitIdempotencyService(
			$this->createMock(ICacheFactory::class),
			$this->lockingMock()
		);
		$n = $svc->mintNonce();
		self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $n);
	}

	public function testLockedWaitThenReplays(): void
	{
		$cache = $this->createMock(ICache::class);
		$calls = 0;
		$cache->method('get')->willReturnCallback(static function () use (&$calls) {
			$calls++;
			// First miss (before lock); subsequent polls during wait.
			return $calls >= 2 ? 55 : null;
		});
		$cacheFactory = $this->createMock(ICacheFactory::class);
		$cacheFactory->method('createDistributed')->willReturn($cache);
		$svc = new FormSubmitIdempotencyService($cacheFactory, $this->lockingMock(false));
		$factoryCalls = 0;
		$id = $svc->rememberCreate('alice', 'wait-me', static function () use (&$factoryCalls): int {
			$factoryCalls++;
			return 1;
		});
		self::assertSame(55, $id);
		self::assertSame(0, $factoryCalls);
	}
}
