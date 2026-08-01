<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Service;

use OCA\ProjectCheck\Exception\MobileGateException;
use OCA\ProjectCheck\Service\LicenseService;
use OCA\ProjectCheck\Service\MobileGateService;
use PHPUnit\Framework\TestCase;

final class MobileGateServiceTest extends TestCase
{
	/**
	 * @param array<string, mixed> $overrides
	 */
	private function gate(array $overrides): MobileGateService
	{
		$defaults = [
			'hasLicense' => true,
			'licenseValid' => true,
			'seatAssigned' => true,
			'seatWithinLimit' => true,
			'payloadB64' => 'payload',
			'signatureB64' => 'sig',
		];
		$state = array_merge($defaults, $overrides);
		$license = $this->createMock(LicenseService::class);
		$license->method('gateState')->willReturn($state);
		$license->method('status')->willReturn([
			'state' => $state['hasLicense'] ? [
				'customerId' => 'acme',
				'validUntil' => '2027-12-31',
				'valid' => $state['licenseValid'],
			] : null,
			'seats' => ['assigned' => 0, 'limit' => 0],
			'envelope' => null,
			'mobileAppStatus' => LicenseService::MOBILE_APP_STATUS,
		]);
		return new MobileGateService($license);
	}

	public function testAssertPassesWhenFullyLicensed(): void
	{
		$this->gate([])->assertGatePassed('alice');
		$this->addToAssertionCount(1);
	}

	/**
	 * @dataProvider gateFailureProvider
	 */
	public function testAssertMapsFailures(string $code, array $overrides): void
	{
		try {
			$this->gate($overrides)->assertGatePassed('alice');
			$this->fail('Expected MobileGateException ' . $code);
		} catch (MobileGateException $e) {
			self::assertSame($code, $e->getErrorCode());
		}
	}

	/**
	 * @return list<array{0: string, 1: array<string, mixed>}>
	 */
	public static function gateFailureProvider(): array
	{
		return [
			['license_missing', ['hasLicense' => false, 'licenseValid' => false, 'seatAssigned' => false, 'seatWithinLimit' => false, 'payloadB64' => null, 'signatureB64' => null]],
			['license_expired', ['licenseValid' => false]],
			['seat_required', ['seatAssigned' => false, 'seatWithinLimit' => false]],
			['seat_limit_exceeded', ['seatWithinLimit' => false]],
		];
	}

	public function testBootstrapReportsWithoutGating(): void
	{
		$payload = $this->gate([])->bootstrapPayload('alice', 'Alice', '2.0.83');
		self::assertSame('projectcheck', $payload['appId']);
		self::assertSame(1, $payload['apiVersion']);
		self::assertTrue($payload['seatAssigned']);
		self::assertTrue($payload['seatWithinLimit']);
		self::assertSame('PC2', $payload['licensing']['format']);
		self::assertSame('payload', $payload['licensing']['envelope']['payloadB64']);
		self::assertSame('alice', $payload['user']['userId']);
		self::assertFalse($payload['capabilities']['timerServer']);
		self::assertTrue($payload['capabilities']['settlement']);
		self::assertTrue($payload['capabilities']['offlineCreate']);
		self::assertFalse($payload['capabilities']['push']);
		self::assertFalse($payload['pushAvailable']);
		self::assertFalse($payload['canSettle']);
		self::assertTrue($payload['licensing']['mobile']['enabledForUser']);
		self::assertSame('2027-12-31', $payload['licensing']['mobile']['expiresAt']);

		$withFlags = $this->gate([])->bootstrapPayload('alice', 'Alice', '2.0.86', true, true);
		self::assertTrue($withFlags['pushAvailable']);
		self::assertTrue($withFlags['capabilities']['push']);
		self::assertTrue($withFlags['canSettle']);
	}

	public function testBootstrapSeatLimitExceedReportsRawSeatAndDisabledUser(): void
	{
		$payload = $this->gate([
			'seatAssigned' => true,
			'seatWithinLimit' => false,
		])->bootstrapPayload('alice', 'Alice', '2.0.83');
		self::assertTrue($payload['seatAssigned']);
		self::assertFalse($payload['seatWithinLimit']);
		self::assertFalse($payload['licensing']['mobile']['enabledForUser']);
	}

	public function testBootstrapNullLicensingWithoutLicense(): void
	{
		$payload = $this->gate([
			'hasLicense' => false,
			'licenseValid' => false,
			'seatAssigned' => false,
			'seatWithinLimit' => false,
			'payloadB64' => null,
			'signatureB64' => null,
		])->bootstrapPayload('bob', 'Bob', '2.0.83');
		self::assertNull($payload['licensing']);
		self::assertFalse($payload['seatAssigned']);
	}
}
