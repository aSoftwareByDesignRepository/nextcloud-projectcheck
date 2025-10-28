<?php

/**
 * User deleted listener for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Listener;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\UserDeletedEvent;
use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Service\TimeEntryService;
use OCA\ProjectCheck\Service\CustomerService;

/**
 * Listener for user deletion events
 */
class UserDeletedListener implements IEventListener
{
    /** @var ProjectService */
    private $projectService;

    /** @var TimeEntryService */
    private $timeEntryService;

    /** @var CustomerService */
    private $customerService;

    /**
     * UserDeletedListener constructor
     *
     * @param ProjectService $projectService
     * @param TimeEntryService $timeEntryService
     * @param CustomerService $customerService
     */
    public function __construct(
        ProjectService $projectService,
        TimeEntryService $timeEntryService,
        CustomerService $customerService
    ) {
        $this->projectService = $projectService;
        $this->timeEntryService = $timeEntryService;
        $this->customerService = $customerService;
    }

    /**
     * Handle user deletion event
     *
     * @param Event $event
     */
    public function handle(Event $event): void
    {
        if (!$event instanceof UserDeletedEvent) {
            return;
        }

        $user = $event->getUser();
        $userId = $user->getUID();

        try {
            // Clean up user's time entries
            $this->cleanupUserTimeEntries($userId);

            // Clean up user's project memberships
            $this->cleanupUserProjectMemberships($userId);

            // Clean up user's customers (if they created any)
            $this->cleanupUserCustomers($userId);

            // Clean up user's projects (transfer to admin or delete)
            $this->cleanupUserProjects($userId);
        } catch (\Exception $e) {
            // Log error but don't fail the user deletion
            \OC::$server->getLogger()->error('Error cleaning up projectcontrol data for user ' . $userId, [
                'exception' => $e,
                'app' => 'projectcheck'
            ]);
        }
    }

    /**
     * Clean up user's time entries
     *
     * @param string $userId
     */
    private function cleanupUserTimeEntries(string $userId): void
    {
        // Get all time entries for the user
        $timeEntries = $this->timeEntryService->getTimeEntriesByUser($userId);

        foreach ($timeEntries as $timeEntry) {
            try {
                $this->timeEntryService->deleteTimeEntry($timeEntry->getId(), $userId);
            } catch (\Exception $e) {
                // Log error but continue
                \OC::$server->getLogger()->error('Error deleting time entry ' . $timeEntry->getId(), [
                    'exception' => $e,
                    'app' => 'projectcheck'
                ]);
            }
        }
    }

    /**
     * Clean up user's project memberships
     *
     * @param string $userId
     */
    private function cleanupUserProjectMemberships(string $userId): void
    {
        // This would require a ProjectMemberService to handle properly
        // For now, we'll leave this as a placeholder
        // TODO: Implement project membership cleanup
    }

    /**
     * Clean up user's customers
     *
     * @param string $userId
     */
    private function cleanupUserCustomers(string $userId): void
    {
        // Get all customers created by the user
        $customers = $this->customerService->getCustomersByUser($userId);

        foreach ($customers as $customer) {
            try {
                $this->customerService->deleteCustomer($customer->getId(), $userId);
            } catch (\Exception $e) {
                // Log error but continue
                \OC::$server->getLogger()->error('Error deleting customer ' . $customer->getId(), [
                    'exception' => $e,
                    'app' => 'projectcheck'
                ]);
            }
        }
    }

    /**
     * Clean up user's projects
     *
     * @param string $userId
     */
    private function cleanupUserProjects(string $userId): void
    {
        // Get all projects created by the user
        $projects = $this->projectService->getProjectsByUser($userId);

        foreach ($projects as $project) {
            try {
                // For now, we'll just mark the project as cancelled
                // In a real implementation, you might want to transfer ownership
                $this->projectService->updateProject($project->getId(), [
                    'status' => 'Cancelled',
                    'updated_at' => new \DateTime()
                ], $userId);
            } catch (\Exception $e) {
                // Log error but continue
                \OC::$server->getLogger()->error('Error updating project ' . $project->getId(), [
                    'exception' => $e,
                    'app' => 'projectcheck'
                ]);
            }
        }
    }
}
