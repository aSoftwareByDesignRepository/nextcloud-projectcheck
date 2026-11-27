<?php
/** Synced from _shared/agent-auth — do not edit app-side. */

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Agent;

use OCA\ProjectCheck\Agent\AgentException;
use OCA\ProjectCheck\Agent\WebhookUrlGuard;
use OCP\IConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * SSRF gate for outbound webhook targets. Every rejection must be a 422
 * AgentException carrying a stable field code on `url`; every acceptance
 * must be silent. Dev escape hatch only via explicit app config flag.
 */
class WebhookUrlGuardTest extends TestCase
{
	private IConfig&MockObject $config;
	private WebhookUrlGuard $guard;

	protected function setUp(): void
	{
		$this->config = $this->createMock(IConfig::class);
		$this->guard = new WebhookUrlGuard($this->config);
	}

	private function dev(bool $on): void
	{
		$this->config->method('getAppValue')->willReturnCallback(
			static fn (string $app, string $key, string $default = '') =>
				$key === WebhookUrlGuard::DEV_FLAG ? ($on ? '1' : '0') : $default
		);
	}

	private function assertRejected(string $url, ?string $expectedCode = null): void
	{
		try {
			$this->guard->assertAllowed($url);
			$this->fail('expected AgentException for ' . $url);
		} catch (AgentException $e) {
			$this->assertSame(422, $e->getHttpStatus());
			if ($expectedCode !== null) {
				$this->assertSame($expectedCode, $e->getDetails()['fields']['url'] ?? null);
			}
		}
	}

	public function testRejectsEmptyAndOversize(): void
	{
		$this->dev(false);
		$this->assertRejected('', 'invalid_length');
		$this->assertRejected(str_repeat('a', 501), 'invalid_length');
		// Boundary: 501 chars is invalid_length even though it is otherwise
		// a well-formed https URL — the length gate fires before parsing.
		$this->assertRejected('https://93.184.216.34/' . str_repeat('p', 479), 'invalid_length');
	}

	public function testRejectsControlChars(): void
	{
		$this->dev(false);
		$this->assertRejected("https://example.com/\npath", 'invalid_chars');
		// NUL must be interior — trim() legitimately strips a trailing NUL
		// before validation, and the stored value is the trimmed one.
		$this->assertRejected("https://exa\x00mple.com/path", 'invalid_chars');
		$this->assertRejected("https://example.com/\x1Fpath", 'invalid_chars');
		$this->assertRejected("https://example.com/\x7F", 'invalid_chars');
	}

	public function testRejectsNonAbsoluteAndWrongScheme(): void
	{
		$this->dev(false);
		$this->assertRejected('example.com/hook', 'invalid_format');
		$this->assertRejected('/relative/path', 'invalid_format');
		// file:// has a scheme but no host — the format gate fires first.
		$this->assertRejected('file:///etc/passwd', 'invalid_format');
		$this->assertRejected('http://example.com/hook', 'https_required');
		$this->assertRejected('ftp://example.com/hook', 'https_required');
	}

	public function testRejectsUserinfoAndQueryAndFragment(): void
	{
		$this->dev(false);
		$this->assertRejected('https://user:pass@example.com/hook', 'no_userinfo');
		$this->assertRejected('https://user@example.com/hook', 'no_userinfo');
		$this->assertRejected('https://example.com/hook?secret=1', 'no_query');
		$this->assertRejected('https://example.com/hook#frag', 'no_query');
	}

	public function testRejectsPrivateAndReservedLiteralIps(): void
	{
		$this->dev(false);
		foreach ([
			'127.0.0.1', '127.53.1.1', '10.0.0.8', '172.16.5.5',
			'192.168.1.1', '169.254.169.254', '0.0.0.0',
		] as $ip) {
			$this->assertRejected('https://' . $ip . '/hook', 'private_target');
		}
		$this->assertRejected('https://[::1]/hook', 'private_target');
		$this->assertRejected('https://[fd00::1]/hook', 'private_target');
		$this->assertRejected('https://[fe80::1]/hook', 'private_target');
	}

	public function testRejectsUnresolvableOrHijackedHost(): void
	{
		$this->dev(false);
		// Whatever the resolver does with .invalid — NXDOMAIN (unresolvable)
		// or a captive-portal/embedded-DNS answer (almost always private)
		// — the result must be rejection either way.
		$this->assertRejected('https://does-not-exist.invalid/hook');
		$this->assertRejected('https://localhost/hook');
	}

	public function testAcceptsPublicIpLiteral(): void
	{
		$this->dev(false);
		// example.com documentation range — public, static, no DNS needed.
		$this->guard->assertAllowed('https://93.184.216.34/hook');
		$this->guard->assertAllowed('https://93.184.216.34:8443/hook');
		$this->addToAssertionCount(2);
	}

	public function testDevFlagAllowsHttpAndPrivate(): void
	{
		$this->dev(true);
		$this->guard->assertAllowed('http://127.0.0.1:8080/hook');
		$this->guard->assertAllowed('https://192.168.1.10/hook');
		$this->addToAssertionCount(2);
	}

	public function testDevFlagStillRejectsGarbage(): void
	{
		$this->dev(true);
		$this->assertRejected('', 'invalid_length');
		$this->assertRejected('ftp://127.0.0.1/hook', 'https_required');
		$this->assertRejected('http://user:pass@127.0.0.1/hook', 'no_userinfo');
		$this->assertRejected('http://127.0.0.1/hook?x=1', 'no_query');
	}
}
