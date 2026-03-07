<?php

declare(strict_types=1);

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
use Psr\Log\LoggerInterface;

/**
 * Listener for user deletion events
 *
 * @template-implements IEventListener<UserDeletedEvent>
 */
class UserDeletedListener implements IEventListener
{
    public function __construct(
        private readonly ProjectService $projectService,
        private readonly TimeEntryService $timeEntryService,
        private readonly CustomerService $customerService,
        private readonly LoggerInterface $logger,
    ) {
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
            $this->logger->error('Error cleaning up projectcheck data for deleted user', [
                'exception' => $e,
                'userId' => $userId,
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
                $this->logger->error('Error deleting time entry on user deletion', [
                    'exception' => $e,
                    'timeEntryId' => $timeEntry->getId(),
                    'userId' => $userId,
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
                $this->logger->error('Error deleting customer on user deletion', [
                    'exception' => $e,
                    'customerId' => $customer->getId(),
                    'userId' => $userId,
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
        $projects = $this->projectService->getProjectsCreatedByUser($userId);

        foreach ($projects as $project) {
            try {
                if (!$project->isCompleted() && !$project->isCancelled()) {
                    $this->projectService->updateProject($project->getId(), ['status' => 'Cancelled']);
                }
            } catch (\Exception $e) {
                $this->logger->error('Error cancelling project on user deletion', [
                    'exception' => $e,
                    'projectId' => $project->getId(),
                    'userId' => $userId,
                ]);
            }
        }
    }
}
