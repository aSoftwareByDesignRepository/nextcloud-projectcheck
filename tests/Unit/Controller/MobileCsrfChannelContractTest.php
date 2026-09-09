<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Controller;

use PHPUnit\Framework\TestCase;

/**
 * Mobile mutations must not treat Authorization *shape* as proof of an app
 * password. Official companion sends Basic loginName:appPassword; a cookie
 * session plus `Authorization: Bearer garbage` must not skip CSRF.
 * App passwords are verified via IProvider::getToken (checkPassword alone is
 * insufficient — Nextcloud rejects app tokens there).
 */
final class MobileCsrfChannelContractTest extends TestCase
{
	public function testMutationChannelDoesNotTrustAuthorizationShape(): void
	{
		$src = (string)file_get_contents(dirname(__DIR__, 3) . '/lib/Controller/MobileController.php');
		$start = strpos($src, 'function assertSafeMutationChannel');
		$this->assertNotFalse($start, 'assertSafeMutationChannel must exist');
		$end = strpos($src, "\n\tprivate function ", $start + 10);
		$channel = $end === false ? substr($src, $start) : substr($src, $start, $end - $start);

		$this->assertStringContainsString('passesCSRFCheck', $channel);
		$this->assertStringContainsString('authorizationBasicAuthenticatesCurrentUser', $channel);
		$this->assertDoesNotMatchRegularExpression(
			'/preg_match\s*\(\s*[\'"]\/\^\(Basic\|Bearer\)/',
			$channel,
			'Must not accept Authorization presence alone as a CSRF bypass',
		);
		$this->assertStringContainsString('checkPassword', $src);
		$this->assertStringContainsString('getToken', $src);
		$this->assertStringContainsString('hash_equals', $src);
		$this->assertStringContainsString('IProvider', $src);
	}

	/**
	 * Momos: every mutating mobile method must call assertSafeMutationChannel.
	 * Framework CSRF is intentionally disabled (NoCSRFRequired); forgetting the
	 * manual channel check is a cookie-session CSRF hole.
	 */
	public function testEveryMutatingMobileMethodCallsSafeChannel(): void
	{
		$src = (string)file_get_contents(dirname(__DIR__, 3) . '/lib/Controller/MobileController.php');
		$mutating = [
			'createTimeEntry',
			'updateTimeEntry',
			'deleteTimeEntry',
			'changeEntryBilling',
			'projectSettlementPreview',
			'projectSettlementApply',
		];
		foreach ($mutating as $method) {
			$start = strpos($src, "function {$method}");
			$this->assertNotFalse($start, "{$method} must exist");
			$end = strpos($src, "\n\tpublic function ", $start + 10);
			if ($end === false) {
				$end = strpos($src, "\n\tprivate function ", $start + 10);
			}
			$body = $end === false ? substr($src, $start) : substr($src, $start, $end - $start);
			$this->assertStringContainsString(
				'assertSafeMutationChannel',
				$body,
				"{$method} must call assertSafeMutationChannel before mutating",
			);
		}
	}
}
