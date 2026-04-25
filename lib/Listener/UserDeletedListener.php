<?php

declare(strict_types=1);

/**
 * User deleted listener for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Listener;

use OCA\ProjectCheck\Db\UserAccountSnapshotMapper;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\UserDeletedEvent;
use OCA\ProjectCheck\Service\AccessControlService;
use OCA\ProjectCheck\Service\ProjectService;
use Psr\Log\LoggerInterface;

/**
 * On account removal: keep time entries and project member history; snapshot display name; reassign ownership.
 *
 * @template-implements IEventListener<UserDeletedEvent>
 */
class UserDeletedListener implements IEventListener
{
	public function __construct(
		private readonly ProjectService $projectService,
		private readonly UserAccountSnapshotMapper $userAccountSnapshotMapper,
		private readonly AccessControlService $accessControlService,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * @param Event $event
	 */
	public function handle(Event $event): void
	{
		if (!$event instanceof UserDeletedEvent) {
			return;
		}

		$user = $event->getUser();
		$userId = $user->getUID();
		$deletedAt = new \DateTime();
		$displayName = $user->getDisplayName() !== '' ? $user->getDisplayName() : $userId;

		try {
			$this->userAccountSnapshotMapper->saveDeletedAccountSnapshot($userId, $displayName, $deletedAt);
		} catch (\Exception $e) {
			$this->logger->error('ProjectCheck: could not store user account snapshot', [
				'app' => 'projectcheck',
				'userId' => $userId,
				'exception' => $e,
			]);
		}

		try {
			$successor = $this->projectService->getSuccessorUserIdForReassignment($userId);
			$this->projectService->reassignCreatorshipFromDeletedUser($userId, $successor);
			$this->logger->info('ProjectCheck: reassigned project/customer created_by from deleted user', [
				'app' => 'projectcheck',
				'from' => $userId,
				'to' => $successor,
			]);
		} catch (\Exception $e) {
			$this->logger->error('ProjectCheck: reassign creatorship on user delete failed', [
				'userId' => $userId,
				'exception' => $e,
			]);
		}

		try {
			$archived = $this->projectService->archiveProjectMembershipsForDeletedUser($userId, $deletedAt);
			if ($archived > 0) {
				$this->logger->info('ProjectCheck: archived project team memberships for deleted user', [
					'app' => 'projectcheck',
					'event' => 'user_deleted_project_memberships_archived',
					'rowsUpdated' => $archived,
				]);
			}
		} catch (\Exception $e) {
			$this->logger->error('ProjectCheck: archive memberships on user delete failed', [
				'userId' => $userId,
				'exception' => $e,
			]);
		}

		try {
			$this->accessControlService->removeUserIdFromAllLists($userId);
		} catch (\Exception $e) {
			$this->logger->error('ProjectCheck: access list cleanup on user delete failed', [
				'userId' => $userId,
				'exception' => $e,
			]);
		}
	}
}
