<?php

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
use OCP\Activity\IManager;

/**
 * Activity service for logging deletion events
 */
class ActivityService
{
    /** @var IManager */
    private $activityManager;

    /**
     * ActivityService constructor
     *
     * @param IManager $activityManager
     */
    public function __construct(IManager $activityManager)
    {
        $this->activityManager = $activityManager;
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
            ->setObject('project', $project->getId(), $project->getName())
            ->setSubject('project_deleted', [
                'project_name' => $project->getName(),
                'time_entries' => $impact['time_entries'] ?? 0,
                'project_members' => $impact['project_members'] ?? 0
            ]);

        $this->activityManager->publish($event);
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
            ->setObject('customer', $customer->getId(), $customer->getName())
            ->setSubject('customer_deleted', [
                'customer_name' => $customer->getName(),
                'projects' => $impact['projects'] ?? 0,
                'time_entries' => $impact['time_entries'] ?? 0,
                'project_members' => $impact['project_members'] ?? 0
            ]);

        $this->activityManager->publish($event);
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
            ->setObject('time_entry', $timeEntry->getId(), $timeEntry->getDescription() ?: 'Time entry')
            ->setSubject('time_entry_deleted', [
                'project_id' => $timeEntry->getProjectId(),
                'hours' => $timeEntry->getHours(),
                'date' => $timeEntry->getFormattedDate()
            ]);

        $this->activityManager->publish($event);
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
            ->setObject('project_member', $member->getId(), $member->getUserId())
            ->setSubject('member_removed', [
                'member_user_id' => $member->getUserId(),
                'project_id' => $member->getProjectId(),
                'role' => $member->getRole()
            ]);

        $this->activityManager->publish($event);
    }
}
