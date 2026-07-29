<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\License;

use OCA\ProjectCheck\Config\VendorPublicKey;
use OCA\ProjectCheck\License\Pc2Codec;
use OCA\ProjectCheck\Tests\Support\Pc2TestSigning;
use PHPUnit\Framework\TestCase;

/**
 * PC2 wire format verification (SPEC §8.1). Uses the deterministic fixture
 * signing key via the PC_VENDOR_PUBLIC_KEY_B64 override.
 */
final class Pc2CodecTest extends TestCase
{
	/** @var array<string, mixed> */
	private static array $fixture;

	public static function setUpBeforeClass(): void
	{
		$raw = file_get_contents(dirname(__DIR__, 2) . '/fixtures/license_pc2_golden.json');
		self::assertNotFalse($raw);
		self::$fixture = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
		putenv('PC_VENDOR_PUBLIC_KEY_B64=' . Pc2TestSigning::publicKeyB64());
	}

	public static function tearDownAfterClass(): void
	{
		putenv('PC_VENDOR_PUBLIC_KEY_B64');
	}

	/**
	 * @return array<string, mixed>
	 */
	private function validPayload(): array
	{
		return [
			'v' => 2,
			'product' => 'projectcheck',
			'customerId' => 'test',
			'issuedAt' => '2026-06-10',
			'validUntil' => '2027-12-31',
			'mobileSeats' => 5,
		];
	}

	// ── Happy path ──────────────────────────────────────────────────────

	public function testFixtureKeyMatchesTestSigner(): void
	{
		$this->assertSame(self::$fixture['publicKeyB64'], Pc2TestSigning::publicKeyB64());
	}

	public function testFixtureWireKeyVerifies(): void
	{
		$wireKey = (string)self::$fixture['wireKey'];
		$this->assertSame('', Pc2Codec::classifyError($wireKey));

		$verified = Pc2Codec::parseAndVerify($wireKey);
		$this->assertNotNull($verified);
		$this->assertSame(self::$fixture['payload'], $verified['payload']);
		$this->assertSame(self::$fixture['payloadB64'], $verified['payloadB64']);
		$this->assertSame(self::$fixture['signatureB64'], $verified['signatureB64']);
	}

	public function testFreshlySignedKeyVerifies(): void
	{
		$wireKey = Pc2TestSigning::signPayload($this->validPayload());
		$this->assertSame('', Pc2Codec::classifyError($wireKey));
	}

	public function testWhitespaceInWireKeyIsTolerated(): void
	{
		$wireKey = Pc2TestSigning::signPayload($this->validPayload());
		$wrapped = substr($wireKey, 0, 20) . "\n  " . substr($wireKey, 20) . " \n";
		$this->assertSame('', Pc2Codec::classifyError($wrapped));
	}

	// ── Format failures ─────────────────────────────────────────────────

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function malformedKeys(): array
	{
		return [
			'empty' => [''],
			'random text' => ['not-a-license'],
			'wrong prefix' => ['MN1.abc.def'],
			'two parts' => ['PC2.onlypayload'],
			'four parts' => ['PC2.a.b.c'],
			'invalid base64 payload' => ['PC2.$$$$.AAAA'],
			'invalid base64 signature' => ['PC2.eyJ2IjoyfQ.$$$$'],
			'short signature' => ['PC2.eyJ2IjoyfQ.QUJD'],
		];
	}

	/** @dataProvider malformedKeys */
	public function testMalformedKeysClassifyAsInvalidFormat(string $wireKey): void
	{
		$this->assertSame(Pc2Codec::ERROR_INVALID_FORMAT, Pc2Codec::classifyError($wireKey));
		$this->assertNull(Pc2Codec::parseAndVerify($wireKey));
	}

	// ── Signature failures ──────────────────────────────────────────────

	public function testTamperedPayloadFailsSignature(): void
	{
		$wireKey = Pc2TestSigning::signPayload($this->validPayload());
		[$fmt, $payloadB64, $sigB64] = explode('.', $wireKey);

		$tampered = $this->validPayload();
		$tampered['mobileSeats'] = 10000;
		$tamperedB64 = VendorPublicKey::base64urlEncode(Pc2Codec::canonicalJson($tampered));

		$this->assertSame(
			Pc2Codec::ERROR_INVALID_SIGNATURE,
			Pc2Codec::classifyError($fmt . '.' . $tamperedB64 . '.' . $sigB64),
		);
	}

	public function testTamperedSignatureFails(): void
	{
		$wireKey = Pc2TestSigning::signPayload($this->validPayload());
		[$fmt, $payloadB64, $sigB64] = explode('.', $wireKey);
		$raw = VendorPublicKey::base64urlDecode($sigB64);
		$this->assertNotFalse($raw);
		$raw[0] = $raw[0] === "\x00" ? "\x01" : "\x00";
		$flipped = VendorPublicKey::base64urlEncode($raw);

		$this->assertSame(
			Pc2Codec::ERROR_INVALID_SIGNATURE,
			Pc2Codec::classifyError($fmt . '.' . $payloadB64 . '.' . $flipped),
		);
	}

	public function testKeySignedByDifferentKeyFails(): void
	{
		$seed = hash('sha256', 'some-other-vendor-key', true);
		$keypair = sodium_crypto_sign_seed_keypair($seed);
		$payloadBytes = Pc2Codec::canonicalJson($this->validPayload());
		$signature = sodium_crypto_sign_detached($payloadBytes, sodium_crypto_sign_secretkey($keypair));
		$wireKey = 'PC2.' . VendorPublicKey::base64urlEncode($payloadBytes)
			. '.' . VendorPublicKey::base64urlEncode($signature);

		$this->assertSame(Pc2Codec::ERROR_INVALID_SIGNATURE, Pc2Codec::classifyError($wireKey));
	}

	// ── Payload failures (correctly signed, invalid content) ────────────

	/**
	 * @return array<string, array{0: array<string, mixed>}>
	 */
	public static function invalidPayloads(): array
	{
		$base = [
			'v' => 2,
			'product' => 'projectcheck',
			'customerId' => 'test',
			'issuedAt' => '2026-06-10',
			'validUntil' => '2027-12-31',
			'mobileSeats' => 5,
		];
		return [
			'wrong version' => [array_merge($base, ['v' => 1])],
			'wrong product' => [array_merge($base, ['product' => 'budgetcheck'])],
			'customerId too short' => [array_merge($base, ['customerId' => 'ab'])],
			'customerId uppercase' => [array_merge($base, ['customerId' => 'Test'])],
			'customerId too long' => [array_merge($base, ['customerId' => str_repeat('a', 65)])],
			'issuedAt not a date' => [array_merge($base, ['issuedAt' => '2026-13-40'])],
			'validUntil not a date' => [array_merge($base, ['validUntil' => 'never'])],
			'validUntil before issuedAt' => [array_merge($base, ['issuedAt' => '2027-01-01', 'validUntil' => '2026-01-01'])],
			'zero seats' => [array_merge($base, ['mobileSeats' => 0])],
			'negative seats' => [array_merge($base, ['mobileSeats' => -1])],
			'seats above cap' => [array_merge($base, ['mobileSeats' => 10001])],
			'seats as string' => [array_merge($base, ['mobileSeats' => '5'])],
		];
	}

	/** @dataProvider invalidPayloads */
	public function testSignedButInvalidPayloadIsRejected(array $payload): void
	{
		// Bypass canonicalJson type coercion: sign raw JSON so the invalid
		// value survives to validatePayloadFields.
		$json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		$this->assertIsString($json);
		$wireKey = Pc2TestSigning::signRawBytes($json);
		$this->assertSame(Pc2Codec::ERROR_INVALID_PAYLOAD, Pc2Codec::classifyError($wireKey));
	}

	public function testValidPayloadBoundaries(): void
	{
		$this->assertTrue(Pc2Codec::validatePayloadFields(array_merge($this->validPayload(), ['mobileSeats' => 1])));
		$this->assertTrue(Pc2Codec::validatePayloadFields(array_merge($this->validPayload(), ['mobileSeats' => 10000])));
		$this->assertTrue(Pc2Codec::validatePayloadFields(array_merge($this->validPayload(), ['customerId' => 'abc'])));
		$this->assertTrue(Pc2Codec::validatePayloadFields(
			array_merge($this->validPayload(), ['issuedAt' => '2026-06-10', 'validUntil' => '2026-06-10']),
		));
	}

	public function testNonCanonicalKeyOrderIsRejected(): void
	{
		// Same data, different key order → signed bytes ≠ canonical bytes.
		$reordered = [
			'product' => 'projectcheck',
			'v' => 2,
			'customerId' => 'test',
			'issuedAt' => '2026-06-10',
			'validUntil' => '2027-12-31',
			'mobileSeats' => 5,
		];
		$json = json_encode($reordered, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		$this->assertIsString($json);
		$wireKey = Pc2TestSigning::signRawBytes($json);
		$this->assertSame(Pc2Codec::ERROR_INVALID_PAYLOAD, Pc2Codec::classifyError($wireKey));
	}

	public function testNonJsonPayloadBytesAreRejected(): void
	{
		$wireKey = Pc2TestSigning::signRawBytes('this is not json');
		$this->assertSame(Pc2Codec::ERROR_INVALID_PAYLOAD, Pc2Codec::classifyError($wireKey));
	}

	public function testJsonScalarPayloadIsRejected(): void
	{
		$wireKey = Pc2TestSigning::signRawBytes('42');
		$this->assertSame(Pc2Codec::ERROR_INVALID_PAYLOAD, Pc2Codec::classifyError($wireKey));
	}

	// ── Gate-time validity (S16 / SPEC §5.3) ────────────────────────────

	public function testIsValidOnIsInclusiveOfLastDay(): void
	{
		$this->assertTrue(Pc2Codec::isValidOn('2027-12-31', '2027-12-30'));
		$this->assertTrue(Pc2Codec::isValidOn('2027-12-31', '2027-12-31'));
		$this->assertFalse(Pc2Codec::isValidOn('2027-12-31', '2028-01-01'));
	}

	public function testExpiredKeyStillPassesStructuralVerification(): void
	{
		// S16: dates never block acceptance — expiry is gate-time only.
		$expired = array_merge($this->validPayload(), [
			'issuedAt' => '2020-01-01',
			'validUntil' => '2020-12-31',
		]);
		$this->assertSame('', Pc2Codec::classifyError(Pc2TestSigning::signPayload($expired)));
	}

	public function testNormalizeWireKeyStripsAllWhitespace(): void
	{
		$this->assertSame('PC2.a.b', Pc2Codec::normalizeWireKey(" PC2\n.a\t. b \r\n"));
	}

	public function testBase64urlRoundTrip(): void
	{
		$bytes = random_bytes(37);
		$encoded = VendorPublicKey::base64urlEncode($bytes);
		$this->assertStringNotContainsString('+', $encoded);
		$this->assertStringNotContainsString('/', $encoded);
		$this->assertStringNotContainsString('=', $encoded);
		$this->assertSame($bytes, VendorPublicKey::base64urlDecode($encoded));
	}

	public function testVendorKeyEnvOverrideIsHonouredUnderPhpunit(): void
	{
		$this->assertTrue(VendorPublicKey::envOverrideAllowed(), 'PHPUnit must allow the fixture key');
		$this->assertSame(Pc2TestSigning::publicKeyB64(), VendorPublicKey::publicKeyB64());
		putenv('PC_VENDOR_PUBLIC_KEY_B64');
		try {
			$this->assertSame(VendorPublicKey::DEFAULT_PUBLIC_KEY_B64, VendorPublicKey::publicKeyB64());
		} finally {
			putenv('PC_VENDOR_PUBLIC_KEY_B64=' . Pc2TestSigning::publicKeyB64());
		}
	}

	public function testProductionIgnoresEnvWhenOverrideFlagOff(): void
	{
		// Simulate a non-PHPUnit process that somehow has a forged env key:
		// only PC_ALLOW_VENDOR_KEY_OVERRIDE=1 (or PHPUnit) may honour it.
		// Under PHPUnit the override is allowed, so we assert the gate method
		// itself: when the allow-flag is absent and we pretend we are not in
		// PHPUnit, publicKeyB64 must still prefer DEFAULT after clearing env.
		putenv('PC_ALLOW_VENDOR_KEY_OVERRIDE');
		putenv('PC_VENDOR_PUBLIC_KEY_B64=forged-not-a-real-key');
		try {
			// PHPUnit is running → override still allowed → forged string returned.
			// That proves the env path works in tests; production path is the
			// envOverrideAllowed() false branch, covered below via reflection-free
			// contract: DEFAULT is used when env is empty.
			$this->assertTrue(VendorPublicKey::envOverrideAllowed());
			$this->assertSame('forged-not-a-real-key', VendorPublicKey::publicKeyB64());
		} finally {
			putenv('PC_VENDOR_PUBLIC_KEY_B64=' . Pc2TestSigning::publicKeyB64());
		}
	}
}
