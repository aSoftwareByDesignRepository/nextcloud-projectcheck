<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Service;

use OCA\ProjectCheck\Service\LocaleFormatService;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see LocaleFormatService}.
 *
 * Audit reference: AUDIT-FINDINGS B10/H28 - server-rendered numbers and
 * currency must respect the user's locale and the org-configured currency
 * code. The previous behaviour was to render `1,234.56` for German users.
 */
class LocaleFormatServiceTest extends TestCase
{
	private function build(string $userLocale, string $appCurrency = 'EUR', bool $hasUser = true): LocaleFormatService
	{
		$config = $this->createMock(IConfig::class);
		$factory = $this->createMock(IFactory::class);
		$session = $this->createMock(IUserSession::class);

		if ($hasUser) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn('alice');
			$session->method('getUser')->willReturn($user);
			$config->method('getUserValue')->willReturnCallback(function ($uid, $app, $key) use ($userLocale) {
				if ($app === 'core' && $key === 'locale') {
					return $userLocale;
				}
				if ($app === 'core' && $key === 'lang') {
					return $userLocale;
				}
				return '';
			});
		} else {
			$session->method('getUser')->willReturn(null);
		}

		$config->method('getAppValue')
			->with('projectcheck', 'currency', 'EUR')
			->willReturn($appCurrency);
		$config->method('getSystemValueString')
			->willReturnCallback(function ($key, $default) use ($userLocale) {
				if ($key === 'default_locale' || $key === 'default_language') {
					return $userLocale ?: $default;
				}
				return $default;
			});

		$l = $this->createMock(IL10N::class);
		$l->method('l')->willReturnCallback(function ($type, $value, $opts = []) {
			if ($type === 'date' && $value instanceof \DateTime) {
				return $value->format('d.m.Y');
			}
			if ($type === 'datetime' && $value instanceof \DateTime) {
				return $value->format('d.m.Y H:i');
			}
			return false;
		});
		$factory->method('get')->with('projectcheck')->willReturn($l);

		return new LocaleFormatService($config, $factory, $session);
	}

	public function testCurrencyDefaultsToEur(): void
	{
		$svc = $this->build('de_DE');
		$this->assertSame('EUR', $svc->getCurrency());
	}

	public function testCurrencyRejectsInvalidConfigValue(): void
	{
		$svc = $this->build('de_DE', '<<not a code>>');
		$this->assertSame('EUR', $svc->getCurrency());
	}

	public function testCurrencyAcceptsConfiguredCode(): void
	{
		$svc = $this->build('en_GB', 'gbp');
		$this->assertSame('GBP', $svc->getCurrency());
	}

	public function testNumberWithoutIntlFallbackUsesGermanGrouping(): void
	{
		$svc = $this->build('de_DE');
		$out = $svc->number(1234567.89, 0, 2);
		// Either intl or fallback path must produce a comma-decimal output.
		$this->assertStringContainsString(',', $out);
		$this->assertStringNotContainsString('.89', $out);
	}

	public function testNumberWithEnglishLocaleUsesPeriodDecimal(): void
	{
		$svc = $this->build('en_GB');
		$out = $svc->number(1234.5, 0, 2);
		$this->assertStringContainsString('.', $out);
		$this->assertStringNotContainsString(',5', $out);
	}

	public function testCurrencyRendersAsCurrency(): void
	{
		$svc = $this->build('de_DE', 'EUR');
		$out = $svc->currency(1234.56);
		// Either intl or fallback must include EUR or the € sign.
		$matches = (strpos($out, '€') !== false) || (strpos($out, 'EUR') !== false);
		$this->assertTrue($matches, 'Expected EUR / € in: ' . $out);
	}

	public function testPercentRendersWithUnitSuffix(): void
	{
		$svc = $this->build('de_DE');
		$out = $svc->percent(12.5, 1);
		$this->assertStringContainsString('%', $out);
		$this->assertStringContainsString("\u{00A0}", $out, 'Should use a non-breaking space');
	}

	public function testHoursRendersWithHSuffix(): void
	{
		$svc = $this->build('en_GB');
		$out = $svc->hours(1.5);
		$this->assertStringEndsWith('h', $out);
	}

	public function testDateUsesL10nFormatter(): void
	{
		$svc = $this->build('de_DE');
		$dt = new \DateTime('2026-04-30');
		$this->assertSame('30.04.2026', $svc->date($dt));
	}

	public function testDateAcceptsString(): void
	{
		$svc = $this->build('de_DE');
		$this->assertSame('30.04.2026', $svc->date('2026-04-30'));
	}

	public function testDateAcceptsTimestamp(): void
	{
		$svc = $this->build('de_DE');
		$ts = (new \DateTime('2026-04-30'))->getTimestamp();
		$this->assertSame('30.04.2026', $svc->date($ts));
	}

	public function testNullValuesDegradeGracefully(): void
	{
		$svc = $this->build('de_DE');
		$this->assertSame('—', $svc->number(null));
		$this->assertSame('—', $svc->currency(''));
		$this->assertSame('—', $svc->percent('not-a-number'));
		$this->assertSame('—', $svc->hours(NAN));
		$this->assertSame('—', $svc->date(null));
	}

	public function testCommaDecimalInputIsAccepted(): void
	{
		$svc = $this->build('de_DE');
		$out = $svc->number('1,5', 0, 2);
		// Must successfully parse "1,5" as 1.5 and produce a non-dash result.
		$this->assertNotSame('—', $out);
	}

	public function testNoUserSessionFallsBackToSystemDefault(): void
	{
		$svc = $this->build('en_GB', 'EUR', false);
		// Should not throw, and should still produce a string.
		$out = $svc->number(1.5, 0, 1);
		$this->assertIsString($out);
	}
}
