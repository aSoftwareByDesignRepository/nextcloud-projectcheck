<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Service;

use OCA\ProjectCheck\Db\LicenseState;
use OCA\ProjectCheck\Db\LicenseStateMapper;
use OCA\ProjectCheck\Db\MobileSeat;
use OCA\ProjectCheck\Db\MobileSeatMapper;
use OCA\ProjectCheck\Exception\MobileGateException;
use OCA\ProjectCheck\Service\LicenseService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IDBConnection;
use OCP\IUserManager;
use OCP\Lock\ILockingProvider;
use PHPUnit\Framework\TestCase;

final class LicenseServiceGateTest extends TestCase
{
	private function service(?LicenseState $state, bool $seated = false): LicenseService
	{
		$licenseState = $this->createMock(LicenseStateMapper::class);
		$licenseState->method('findSingleton')->willReturn($state);
		$seats = $this->createMock(MobileSeatMapper::class);
		$seat = null;
		if ($seated) {
			$seat = new MobileSeat();
			$seat->setId(1);
			$seat->setUid('alice');
			$seat->setAssignedAt(1_700_000_000);
			$seat->setAssignedBy('admin');
		}
		$seats->method('findByUid')->willReturn($seat);
		$seats->method('countAll')->willReturn($seated ? 1 : 0);
		$seats->method('findAllRanked')->willReturn($seated && $seat !== null ? [$seat] : []);
		$time = $this->createMock(ITimeFactory::class);
		$time->method('getDateTime')->willReturn(new \DateTime('2026-07-26'));
		$time->method('getTime')->willReturn(1_700_000_000);

		return new LicenseService(
			$this->createMock(IDBConnection::class),
			$licenseState,
			$seats,
			$time,
			$this->createMock(IUserManager::class),
			$this->createMock(ILockingProvider::class),
		);
	}

	private function state(string $until, int $seats = 5): LicenseState
	{
		$s = new LicenseState();
		$s->setCustomerId('acme');
		$s->setIssuedAt('2026-01-01');
		$s->setValidUntil($until);
		$s->setMobileSeats($seats);
		$s->setPayloadB64('payload');
		$s->setSignatureB64('sig');
		$s->setAppliedAt(1);
		$s->setAppliedBy('admin');
		return $s;
	}

	public function testBuildEnvelopeNullWithoutLicense(): void
	{
		self::assertNull($this->service(null)->buildEnvelope());
	}

	public function testBuildEnvelopeNullWhenExpired(): void
	{
		self::assertNull($this->service($this->state('2026-01-01'))->buildEnvelope());
	}

	public function testBuildEnvelopeWhenActive(): void
	{
		$env = $this->service($this->state('2027-07-26'))->buildEnvelope();
		self::assertNotNull($env);
		self::assertSame('PC2', $env['format']);
		self::assertSame('payload', $env['payloadB64']);
		self::assertSame('sig', $env['signatureB64']);
	}

	public function testAssertMobileAccessRequiresLicense(): void
	{
		$this->expectException(MobileGateException::class);
		try {
			$this->service(null)->assertMobileAccess('alice');
		} catch (MobileGateException $e) {
			self::assertSame('license_missing', $e->getErrorCode());
			throw $e;
		}
	}

	public function testAssertMobileAccessRequiresSeat(): void
	{
		$this->expectException(MobileGateException::class);
		try {
			$this->service($this->state('2027-07-26'), false)->assertMobileAccess('alice');
		} catch (MobileGateException $e) {
			self::assertSame('seat_required', $e->getErrorCode());
			throw $e;
		}
	}

	public function testAssertMobileAccessSeatLimitExceeded(): void
	{
		$this->expectException(MobileGateException::class);
		try {
			$this->service($this->state('2027-07-26', 0), true)->assertMobileAccess('alice');
		} catch (MobileGateException $e) {
			self::assertSame('seat_limit_exceeded', $e->getErrorCode());
			throw $e;
		}
	}

	public function testAssertMobileAccessOkWhenSeated(): void
	{
		$this->service($this->state('2027-07-26'), true)->assertMobileAccess('alice');
		$this->addToAssertionCount(1);
	}

	public function testIsMobilePlanActiveRespectsExpiry(): void
	{
		self::assertFalse($this->service($this->state('2026-01-01'))->isMobilePlanActive());
		self::assertTrue($this->service($this->state('2027-07-26'))->isMobilePlanActive());
	}

	public function testGateStateReportsSeatWithinLimit(): void
	{
		$state = $this->service($this->state('2027-07-26'), true)->gateState('alice');
		self::assertTrue($state['hasLicense']);
		self::assertTrue($state['licenseValid']);
		self::assertTrue($state['seatAssigned']);
		self::assertTrue($state['seatWithinLimit']);
		self::assertSame('payload', $state['payloadB64']);
	}

	public function testGateStateSeatOverLimit(): void
	{
		$state = $this->service($this->state('2027-07-26', 0), true)->gateState('alice');
		self::assertTrue($state['seatAssigned']);
		self::assertFalse($state['seatWithinLimit']);
	}

	public function testMobileAppStatusAvailable(): void
	{
		self::assertSame('available', LicenseService::MOBILE_APP_STATUS);
	}

	public function testRemoveClearsSeatsAndLicense(): void
	{
		$licenseState = $this->createMock(LicenseStateMapper::class);
		$seats = $this->createMock(MobileSeatMapper::class);
		$db = $this->createMock(IDBConnection::class);
		$locking = $this->createMock(ILockingProvider::class);
		$time = $this->createMock(ITimeFactory::class);
		$time->method('getDateTime')->willReturn(new \DateTime('2026-07-26'));

		$db->expects(self::once())->method('beginTransaction');
		$db->expects(self::once())->method('commit');
		$seats->expects(self::once())->method('deleteAll');
		$licenseState->expects(self::once())->method('deleteAll');
		$licenseState->method('findSingleton')->willReturn(null);
		$seats->method('countAll')->willReturn(0);
		$locking->method('acquireLock');
		$locking->method('releaseLock');

		$svc = new LicenseService(
			$db,
			$licenseState,
			$seats,
			$time,
			$this->createMock(IUserManager::class),
			$locking,
		);
		$status = $svc->remove();
		self::assertNull($status['state']);
		self::assertSame(0, $status['seats']['assigned']);
	}
}
