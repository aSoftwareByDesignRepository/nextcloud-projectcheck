<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Activity;

use OCA\ProjectCheck\Activity\Provider;
use OCP\Activity\Exceptions\UnknownActivityException;
use OCP\Activity\IEvent;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;

final class ActivityProviderTest extends TestCase
{
	private function provider(): Provider
	{
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $s, array $p = []): string => $s);
		return new Provider(
			$l10n,
			$this->createMock(IURLGenerator::class),
			$this->createMock(IUserManager::class),
		);
	}

	public function testParsesProjectCreated(): void
	{
		$event = $this->createMock(IEvent::class);
		$event->method('getApp')->willReturn('projectcheck');
		$event->method('getSubject')->willReturn('project_created');
		$event->method('getSubjectParameters')->willReturn([
			'actor' => 'alice',
			'project' => 'Acme',
			'project_id' => 7,
		]);
		$event->method('getMessageParameters')->willReturn([]);
		$event->expects($this->once())->method('setParsedSubject')->willReturnSelf();
		$event->expects($this->once())->method('setRichSubject')->willReturnSelf();
		$event->expects($this->once())->method('setParsedMessage')->willReturnSelf();
		$event->expects($this->once())->method('setIcon')->willReturnSelf();

		self::assertSame($event, $this->provider()->parse('en', $event));
	}

	public function testRejectsForeignAppWithUnknownActivityException(): void
	{
		$event = $this->createMock(IEvent::class);
		$event->method('getApp')->willReturn('files');
		$this->expectException(UnknownActivityException::class);
		$this->provider()->parse('en', $event);
	}

	public function testRejectsUnknownSubjectWithUnknownActivityException(): void
	{
		$event = $this->createMock(IEvent::class);
		$event->method('getApp')->willReturn('projectcheck');
		$event->method('getSubject')->willReturn('not_a_real_subject');
		$event->method('getSubjectParameters')->willReturn([]);
		$this->expectException(UnknownActivityException::class);
		$this->provider()->parse('en', $event);
	}
}
