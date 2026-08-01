<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\License;

use OCA\ProjectCheck\Config\VendorPublicKey;
use OCA\ProjectCheck\License\Pc2Codec;
use PHPUnit\Framework\TestCase;

/**
 * Phase 0.3 / G2-2 — ops PC2 golden must verify on the real consumer codec.
 */
final class GoldenOpsFixtureTest extends TestCase
{
	public static function setUpBeforeClass(): void
	{
		if (!defined('PHPUNIT_RUNNING')) {
			define('PHPUNIT_RUNNING', true);
		}
	}

	public function testOpsGoldenWireVerifies(): void
	{
		$opsFixture = dirname(__DIR__, 4) . '/sbdlicenseops/tests/fixtures/license_pc2_golden.json';
		self::assertFileExists($opsFixture);
		$data = json_decode((string)file_get_contents($opsFixture), true, 512, JSON_THROW_ON_ERROR);
		putenv('PC_VENDOR_PUBLIC_KEY_B64=' . (string)$data['publicKeyB64']);
		putenv('PC_ALLOW_VENDOR_KEY_OVERRIDE=1');
		try {
			self::assertSame('', Pc2Codec::classifyError((string)$data['wireKey']));
			$parsed = Pc2Codec::parseAndVerify((string)$data['wireKey']);
			self::assertNotNull($parsed);
			self::assertSame($data['payload'], $parsed['payload']);
			self::assertSame($data['payloadB64'], $parsed['payloadB64']);
			self::assertSame($data['signatureB64'], $parsed['signatureB64']);
		} finally {
			putenv('PC_VENDOR_PUBLIC_KEY_B64=' . VendorPublicKey::TEST_PUBLIC_KEY_B64);
			putenv('PC_ALLOW_VENDOR_KEY_OVERRIDE=1');
		}
	}

	public function testForeignAzc2Rejected(): void
	{
		$opsFixture = dirname(__DIR__, 4) . '/sbdlicenseops/tests/fixtures/license_azc2_golden.json';
		self::assertFileExists($opsFixture);
		$data = json_decode((string)file_get_contents($opsFixture), true, 512, JSON_THROW_ON_ERROR);
		putenv('PC_VENDOR_PUBLIC_KEY_B64=' . (string)$data['publicKeyB64']);
		putenv('PC_ALLOW_VENDOR_KEY_OVERRIDE=1');
		try {
			$code = Pc2Codec::classifyError((string)$data['wireKey']);
			self::assertSame(Pc2Codec::ERROR_INVALID_FORMAT, $code);
		} finally {
			putenv('PC_VENDOR_PUBLIC_KEY_B64=' . VendorPublicKey::TEST_PUBLIC_KEY_B64);
			putenv('PC_ALLOW_VENDOR_KEY_OVERRIDE=1');
		}
	}
}
