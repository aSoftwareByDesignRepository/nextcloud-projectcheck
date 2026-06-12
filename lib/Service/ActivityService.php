<?php

declare(strict_types=1);

/**
 * Activity service for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Service;

use OCA\ProjectCheck\Db\Project;
use OCA\ProjectCheck\Db\Customer;
use OCA\ProjectCheck\Db\TimeEntry;
use OCA\ProjectCheck\Db\ProjectMember;
use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use Psr\Log\LoggerInterface;

/**
 * Activity service for logging deletion events.
 *
 * Publishing is strictly best-effort: the activity stream must never break the
 * user action that triggered it (a delete that succeeds in the database has to
 * be reported as a success even if the stream rejects the event).
 */
class ActivityService
{
    /** @var IManager */
    private $activityManager;

    /** @var LoggerInterface */
    private $logger;

    /**
     * ActivityService constructor
     *
     * @param IManager $activityManager
     * @param LoggerInterface $logger
     */
    public function __construct(IManager $activityManager, LoggerInterface $logger)
    {
        $this->activityManager = $activityManager;
        $this->logger = $logger;
    }

    /**
     * Log project deletion
     *
     * @param string $userId
     * @param Project $project
     * @param array $impact
     */
    public function logProjectDeleted(string $userId, Project $project, array $impact = []): void
    {
        $event = $this->activityManager->generateEvent();
        $event->setApp('projectcheck')
            ->setType('projectcheck')
            ->setAuthor($userId)
            ->setAffectedUser($userId)
            ->setObject('project', $project->getId(), $project->getName())
            ->setSubject('project_deleted', [
                'project_name' => $project->getName(),
                'time_entries' => $impact['time_entries'] ?? 0,
                'project_members' => $impact['project_members'] ?? 0
            ]);

        $this->publishSafely($event, 'project_deleted');
    }

    /**
     * Log project workflow status change (audit / activity stream).
     *
     * @param string $userId Acting user
     * @param Project $project Project after the status update
     * @param string $previousStatus Prior workflow status
     * @param string $newStatus New workflow status (must match persisted value)
     * @param string|null $note Optional user note (plain text, already bounded by caller)
     */
    public function logProjectStatusChanged(
        string $userId,
        Project $project,
        string $previousStatus,
        string $newStatus,
        ?string $note = null
    ): void {
        $event = $this->activityManager->generateEvent();
        $event->setApp('projectcheck')
            ->setType('projectcheck')
            ->setAuthor($userId)
            ->setAffectedUser($userId)
            ->setObject('project', $project->getId(), $project->getName())
            ->setSubject('project_status_changed', [
                'actor' => $userId,
                'project' => $project->getName(),
                'project_id' => $project->getId(),
                'status' => $newStatus,
                'previous_status' => $previousStatus,
            ]);

        if ($note !== null && $note !== '') {
            $event->setMessage('status_change_note', [
                'note' => $note,
            ]);
        }

        $this->publishSafely($event, 'project_status_changed');
    }

    /**
     * Log customer deletion
     *
     * @param string $userId
     * @param Customer $customer
     * @param array $impact
     */
    public function logCustomerDeleted(string $userId, Customer $customer, array $impact = []): void
    {
        $event = $this->activityManager->generateEvent();
        $event->setApp('projectcheck')
            ->setType('projectcheck')
            ->setAuthor($userId)
            ->setAffectedUser($userId)
            ->setObject('customer', $customer->getId(), $customer->getName())
            ->setSubject('customer_deleted', [
                'customer_name' => $customer->getName(),
                'projects' => $impact['projects'] ?? 0,
                'time_entries' => $impact['time_entries'] ?? 0,
                'project_members' => $impact['project_members'] ?? 0
            ]);

        $this->publishSafely($event, 'customer_deleted');
    }

    /**
     * Log time entry deletion
     *
     * @param string $userId
     * @param TimeEntry $timeEntry
     */
    public function logTimeEntryDeleted(string $userId, TimeEntry $timeEntry): void
    {
        $event = $this->activityManager->generateEvent();
        $event->setApp('projectcheck')
            ->setType('projectcheck')
            ->setAuthor($userId)
            ->setAffectedUser($userId)
            ->setObject('time_entry', $timeEntry->getId(), $timeEntry->getDescription() ?: 'Time entry')
            ->setSubject('time_entry_deleted', [
                'project_id' => $timeEntry->getProjectId(),
                'hours' => $timeEntry->getHours(),
                'date' => $timeEntry->getFormattedDate()
            ]);

        $this->publishSafely($event, 'time_entry_deleted');
    }

    /**
     * Log member removal
     *
     * @param string $userId
     * @param ProjectMember $member
     */
    public function logMemberRemoved(string $userId, ProjectMember $member): void
    {
        $event = $this->activityManager->generateEvent();
        $event->setApp('projectcheck')
            ->setType('projectcheck')
            ->setAuthor($userId)
            ->setAffectedUser($member->getUserId() ?: $userId)
            ->setObject('project_member', $member->getId(), $member->getUserId())
            ->setSubject('member_removed', [
                'member_user_id' => $member->getUserId(),
                'project_id' => $member->getProjectId(),
                'role' => $member->getRole()
            ]);

        $this->publishSafely($event, 'member_removed');
    }

    /**
     * Publish without ever propagating stream errors to the caller.
     */
    private function publishSafely(IEvent $event, string $context): void
    {
        try {
            $this->activityManager->publish($event);
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to publish projectcheck activity event', [
                'context' => $context,
                'exception' => $e,
            ]);
        }
    }
}
