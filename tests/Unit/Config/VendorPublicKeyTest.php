<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Config;

use OCA\ProjectCheck\Config\VendorPublicKey;
use PHPUnit\Framework\TestCase;

final class VendorPublicKeyTest extends TestCase
{
	public function testProductionDefaultKey(): void
	{
		$this->assertSame('naLgi4THUgwJCRoUehq20QU4uJsLVHzuKV04NhkITn8', VendorPublicKey::DEFAULT_PUBLIC_KEY_B64);
	}

	public function testGoldenFixtureMatchesTestKey(): void
	{
		$path = dirname(__DIR__, 2) . '/fixtures/license_pc2_golden.json';
		$data = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
		$this->assertSame(VendorPublicKey::TEST_PUBLIC_KEY_B64, $data['publicKeyB64']);
	}
}
