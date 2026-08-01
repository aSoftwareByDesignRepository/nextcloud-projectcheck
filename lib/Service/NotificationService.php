<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Service;

use OCA\ProjectCheck\Db\TimeEntry;
use OCP\Notification\IManager as INotificationManager;
use Psr\Log\LoggerInterface;

/**
 * Emits Nextcloud notifications (bell + push via notifications app) for settlement events.
 */
class NotificationService
{
	public function __construct(
		private readonly INotificationManager $notificationManager,
		private readonly ProjectService $projects,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * Notify the entry owner when a settler changes billing status (not when they settle themselves).
	 */
	public function notifyBillingStatusChanged(
		string $actorUid,
		TimeEntry $entry,
		string $fromStatus,
		string $toStatus,
	): void {
		$owner = (string)$entry->getUserId();
		if ($owner === '' || $owner === $actorUid) {
			return;
		}

		$projectName = '';
		$project = $this->projects->getProject((int)$entry->getProjectId());
		if ($project !== null) {
			$projectName = (string)$project->getName();
		}

		try {
			$notification = $this->notificationManager->createNotification();
			$notification->setApp('projectcheck')
				->setUser($owner)
				->setDateTime(new \DateTime())
				->setObject('time_entry', (string)$entry->getId())
				->setSubject('billing_status_changed', [
					'project_name' => $projectName,
					'from' => $fromStatus,
					'to' => $toStatus,
					'date' => $entry->getFormattedDate(),
					'entry_id' => (int)$entry->getId(),
					'actor' => $actorUid,
				])
				->setMessage('billing_status_changed', [
					'project_name' => $projectName,
					'from' => $fromStatus,
					'to' => $toStatus,
					'date' => $entry->getFormattedDate(),
				]);
			$this->notificationManager->notify($notification);
		} catch (\Throwable $e) {
			$this->logger->warning('ProjectCheck: failed to emit billing notification', [
				'exception' => $e,
				'entryId' => $entry->getId(),
				'owner' => $owner,
			]);
		}
	}
}
