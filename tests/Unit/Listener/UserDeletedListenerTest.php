<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Tests\Unit\Listener;

use OCA\ProjectCheck\Db\UserAccountSnapshot;
use OCA\ProjectCheck\Db\UserAccountSnapshotMapper;
use OCA\ProjectCheck\Listener\UserDeletedListener;
use OCA\ProjectCheck\Service\AccessControlService;
use OCA\ProjectCheck\Service\ProjectService;
use OCP\IUser;
use OCP\User\Events\UserDeletedEvent;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class UserDeletedListenerTest extends TestCase
{
	public function testArchivesMembershipsAndSnapshotsAndReassigns(): void
	{
		$projectService = $this->createMock(ProjectService::class);
		$snapshotMapper = $this->createMock(UserAccountSnapshotMapper::class);
		$accessControl = $this->createMock(AccessControlService::class);
		$logger = $this->createMock(LoggerInterface::class);

		$accessControl->expects($this->once())->method('removeUserIdFromAllLists')->with('alice');
		$projectService->expects($this->once())
			->method('getSuccessorUserIdForReassignment')
			->with('alice')
			->willReturn('admin');
		$projectService->expects($this->once())
			->method('reassignCreatorshipFromDeletedUser')
			->with('alice', 'admin');
		$projectService->expects($this->once())
			->method('archiveProjectMembershipsForDeletedUser')
			->with('alice', $this->isInstanceOf(\DateTimeInterface::class))
			->willReturn(2);

		$saved = $this->createMock(UserAccountSnapshot::class);
		$snapshotMapper->expects($this->once())
			->method('saveDeletedAccountSnapshot')
			->with('alice', 'Alice A.', $this->isInstanceOf(\DateTimeInterface::class))
			->willReturn($saved);

		$logger->expects($this->atLeastOnce())->method('info');

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$user->method('getDisplayName')->willReturn('Alice A.');

		$event = $this->createMock(UserDeletedEvent::class);
		$event->method('getUser')->willReturn($user);

		$listener = new UserDeletedListener(
			$projectService,
			$snapshotMapper,
			$accessControl,
			$logger
		);
		$listener->handle($event);
	}
}
