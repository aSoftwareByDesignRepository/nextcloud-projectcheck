<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Service;

use OCA\ProjectCheck\Service\LicenseUiStrings;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;

final class LicenseUiStringsTest extends TestCase
{
	public function testForPanelProvidesSafeArgumentsForLazyPlaceholderStrings(): void
	{
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static function (string $text, array $params = []): string {
			if (str_contains($text, '%') && $params === []) {
				throw new \RuntimeException('placeholder strings must provide explicit params for lazy translation safety');
			}

			return $params !== []
				? vsprintf($text, $params)
				: $text;
		});

		$strings = LicenseUiStrings::forPanel($l10n);

		self::assertSame('%1$s of %2$s seats used', $strings['seatsUsedText']);
		self::assertSame('Remove seat for %s', $strings['seatRemoveAria']);
		self::assertStringContainsString('ProjectCheck mobile app is available', $strings['mobileAppAvailable']);
	}
}
