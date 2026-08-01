<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Notification;

use OCA\ProjectCheck\Notification\Notifier;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\IL10N;
use OCP\Notification\INotification;
use OCP\Notification\UnknownNotificationException;
use PHPUnit\Framework\TestCase;

final class NotifierTest extends TestCase
{
	public function testRejectsOtherApps(): void
	{
		$l10n = $this->createMock(IL10N::class);
		$factory = $this->createMock(IFactory::class);
		$factory->method('get')->willReturn($l10n);
		$url = $this->createMock(IURLGenerator::class);
		$notifier = new Notifier($factory, $url);

		$n = $this->createMock(INotification::class);
		$n->method('getApp')->willReturn('files');
		$this->expectException(UnknownNotificationException::class);
		$notifier->prepare($n, 'en');
	}

	public function testPreparesBillingStatusChanged(): void
	{
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static function (string $s, array $a = []) {
			return $a === [] ? $s : vsprintf(str_replace(['%1$s', '%2$s', '%3$s', '%4$s'], '%s', $s), $a);
		});
		$factory = $this->createMock(IFactory::class);
		$factory->method('get')->willReturn($l10n);
		$url = $this->createMock(IURLGenerator::class);
		$url->method('linkToRouteAbsolute')->willReturn('https://example.test/pc/entries');
		$notifier = new Notifier($factory, $url);

		$n = $this->createMock(INotification::class);
		$n->method('getApp')->willReturn('projectcheck');
		$n->method('getSubject')->willReturn('billing_status_changed');
		$n->method('getSubjectParameters')->willReturn([
			'project_name' => 'Acme',
			'from' => 'open',
			'to' => 'invoiced',
			'date' => '2026-07-01',
		]);
		$n->method('getMessageParameters')->willReturn([]);
		$n->expects(self::once())->method('setParsedSubject')->with('Settlement updated')->willReturnSelf();
		$n->expects(self::once())->method('setParsedMessage')->willReturnSelf();
		$n->expects(self::once())->method('setLink')->with('https://example.test/pc/entries')->willReturnSelf();

		$out = $notifier->prepare($n, 'en');
		self::assertSame($n, $out);
	}
}
