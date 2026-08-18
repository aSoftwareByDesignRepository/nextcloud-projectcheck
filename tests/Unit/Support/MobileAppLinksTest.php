<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Support;

use OCA\ProjectCheck\Support\MobileAppLinks;
use PHPUnit\Framework\TestCase;

final class MobileAppLinksTest extends TestCase
{
	public function testPlayStoreUrlIsCanonicalPackage(): void
	{
		$links = new MobileAppLinks();
		self::assertSame(
			'https://play.google.com/store/apps/details?id=de.softwarebydesign.projectcheck',
			$links->playStoreUrl(),
		);
		self::assertSame('de.softwarebydesign.projectcheck', $links->playStorePackageId());
		self::assertTrue($links->playListed());
	}

	public function testProductAndPrivacyUrlsAreHttpsOnVendorOrigin(): void
	{
		$links = new MobileAppLinks();
		$enProduct = $links->productPageUrl('en');
		$deProduct = $links->productPageUrl('de_DE');
		$enPrivacy = $links->privacyPageUrl('en');
		$dePrivacy = $links->privacyPageUrl('de');

		foreach ([$enProduct, $deProduct, $enPrivacy, $dePrivacy] as $url) {
			self::assertStringStartsWith('https://nextcloud.software-by-design.de/', $url);
		}
		self::assertStringContainsString('/en/apps/projectcheck.html#mobile', $enProduct);
		self::assertStringContainsString('/de/apps/projectcheck.html#mobile', $deProduct);
		self::assertStringContainsString('privacy-projectcheck-mobile', $enPrivacy);
		self::assertStringContainsString('datenschutz-projectcheck-mobile', $dePrivacy);
	}

	public function testGermanLocaleDetection(): void
	{
		$links = new MobileAppLinks();
		self::assertTrue($links->isGermanLocale('de'));
		self::assertTrue($links->isGermanLocale('de_DE'));
		self::assertFalse($links->isGermanLocale('en'));
		self::assertFalse($links->isGermanLocale('den'));
	}
}
