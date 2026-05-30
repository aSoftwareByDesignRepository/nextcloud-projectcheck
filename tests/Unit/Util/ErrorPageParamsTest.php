<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Util;

use OCA\ProjectCheck\Util\ErrorPageParams;
use OCP\IL10N;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;

class ErrorPageParamsTest extends TestCase
{
	public function testBuildIncludesSecondaryDashboardLink(): void
	{
		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnArgument(0);
		$url = $this->createMock(IURLGenerator::class);
		$url->method('linkToDefaultPageUrl')->willReturn('/home');
		$url->method('linkToRoute')->with('projectcheck.dashboard.index')->willReturn('/dash');

		$params = ErrorPageParams::build($l, $url, 'Test message');

		self::assertSame('Test message', $params['message']);
		self::assertSame('/home', $params['homeUrl']);
		self::assertSame('/dash', $params['secondaryUrl']);
		self::assertSame('Back to Dashboard', $params['secondaryLabel']);
	}

	public function testForGuestOmitsSecondaryLink(): void
	{
		$l = $this->createMock(IL10N::class);
		$url = $this->createMock(IURLGenerator::class);
		$url->method('linkToDefaultPageUrl')->willReturn('/home');

		$params = ErrorPageParams::forGuest($l, $url, 'Login required');

		self::assertNull($params['secondaryUrl']);
		self::assertNull($params['secondaryLabel']);
	}
}
