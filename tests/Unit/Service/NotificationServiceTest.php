<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Service;

use OCA\ProjectCheck\Db\Project;
use OCA\ProjectCheck\Db\TimeEntry;
use OCA\ProjectCheck\Service\NotificationService;
use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Util\BillingStatus;
use OCP\Notification\IManager;
use OCP\Notification\INotification;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class NotificationServiceTest extends TestCase
{
	public function testSkipsWhenActorIsOwner(): void
	{
		$manager = $this->createMock(IManager::class);
		$manager->expects(self::never())->method('createNotification');
		$projects = $this->createMock(ProjectService::class);
		$logger = $this->createMock(LoggerInterface::class);
		$svc = new NotificationService($manager, $projects, $logger);

		$entry = new TimeEntry();
		$entry->setId(1);
		$entry->setUserId('alice');
		$entry->setProjectId(5);
		$entry->setDate(new \DateTime('2026-07-01'));
		$entry->setBillingStatus(BillingStatus::OPEN);

		$svc->notifyBillingStatusChanged('alice', $entry, BillingStatus::OPEN, BillingStatus::INVOICED);
	}

	public function testNotifiesOwnerWhenSettlerDiffers(): void
	{
		$notification = $this->createMock(INotification::class);
		$notification->method('setApp')->willReturnSelf();
		$notification->method('setUser')->with('bob')->willReturnSelf();
		$notification->method('setDateTime')->willReturnSelf();
		$notification->method('setObject')->willReturnSelf();
		$notification->method('setSubject')->willReturnSelf();
		$notification->method('setMessage')->willReturnSelf();

		$manager = $this->createMock(IManager::class);
		$manager->expects(self::once())->method('createNotification')->willReturn($notification);
		$manager->expects(self::once())->method('notify')->with($notification);

		$project = new Project();
		$project->setId(5);
		$project->setName('Acme');
		$projects = $this->createMock(ProjectService::class);
		$projects->method('getProject')->with(5)->willReturn($project);
		$logger = $this->createMock(LoggerInterface::class);

		$svc = new NotificationService($manager, $projects, $logger);
		$entry = new TimeEntry();
		$entry->setId(9);
		$entry->setUserId('bob');
		$entry->setProjectId(5);
		$entry->setDate(new \DateTime('2026-07-01'));
		$entry->setBillingStatus(BillingStatus::OPEN);

		$svc->notifyBillingStatusChanged('mgr', $entry, BillingStatus::OPEN, BillingStatus::INVOICED);
	}
}
