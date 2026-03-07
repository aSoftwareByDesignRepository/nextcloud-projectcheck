<?php

declare(strict_types=1);

/**
 * Background job for cleanup tasks in projectcontrol app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\BackgroundJob;

use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\Job;
use OCP\BackgroundJob\IJob;
use Psr\Log\LoggerInterface;
use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Service\TimeEntryService;
use OCA\ProjectCheck\Service\CustomerService;

/**
 * Background job for cleanup tasks
 */
class CleanupJob extends Job implements IJob
{
    /** @var LoggerInterface */
    private $logger;

    /** @var ProjectService */
    private $projectService;

    /** @var TimeEntryService */
    private $timeEntryService;

    /** @var CustomerService */
    private $customerService;

    /**
     * CleanupJob constructor
     *
     * @param LoggerInterface $logger
     * @param ProjectService $projectService
     * @param TimeEntryService $timeEntryService
     * @param CustomerService $customerService
     */
    public function __construct(
        LoggerInterface $logger,
        ProjectService $projectService,
        TimeEntryService $timeEntryService,
        CustomerService $customerService
    ) {
        parent::__construct();
        $this->logger = $logger;
        $this->projectService = $projectService;
        $this->timeEntryService = $timeEntryService;
        $this->customerService = $customerService;
    }

    /**
     * Run the job
     *
     * @param array $argument
     */
    public function run($argument)
    {
        $this->logger->info('Starting ProjectControl cleanup job', ['app' => 'projectcheck']);

        try {
            // Clean up old time entries (older than 2 years)
            $this->cleanupOldTimeEntries();

            // Clean up orphaned projects (no time entries for 1 year)
            $this->cleanupOrphanedProjects();

            // Clean up orphaned customers (no projects for 1 year)
            $this->cleanupOrphanedCustomers();

            // Update project statistics
            $this->updateProjectStatistics();

            $this->logger->info('ProjectControl cleanup job completed successfully', ['app' => 'projectcheck']);
        } catch (\Exception $e) {
            $this->logger->error('Error in ProjectControl cleanup job: ' . $e->getMessage(), [
                'app' => 'projectcheck',
                'exception' => $e
            ]);
        }
    }

    /**
     * Clean up old time entries
     */
    private function cleanupOldTimeEntries()
    {
        $cutoffDate = new \DateTime();
        $cutoffDate->modify('-2 years');

        // Get old time entries
        $oldTimeEntries = $this->timeEntryService->getTimeEntriesByDateRange(
            null,
            $cutoffDate->format('Y-m-d'),
            'system'
        );

        $deletedCount = 0;
        foreach ($oldTimeEntries as $timeEntry) {
            try {
                $this->timeEntryService->deleteTimeEntry($timeEntry->getId(), 'system');
                $deletedCount++;
            } catch (\Exception $e) {
                $this->logger->error('Error deleting old time entry: ' . $e->getMessage(), [
                    'app' => 'projectcheck',
                    'timeEntryId' => $timeEntry->getId()
                ]);
            }
        }

        $this->logger->info("Cleaned up {$deletedCount} old time entries", ['app' => 'projectcheck']);
    }

    /**
     * Clean up orphaned projects
     */
    private function cleanupOrphanedProjects()
    {
        $cutoffDate = new \DateTime();
        $cutoffDate->modify('-1 year');

        // Get projects with no recent activity
        $projects = $this->projectService->getProjectsByUser('system', 1000);
        $deletedCount = 0;

        foreach ($projects as $project) {
            // Check if project has recent time entries
            $recentTimeEntries = $this->timeEntryService->getTimeEntriesByProject(
                $project->getId(),
                $cutoffDate->format('Y-m-d')
            );

            if (empty($recentTimeEntries) && $project->getStatus() === 'Completed') {
                try {
                    $this->projectService->deleteProject($project->getId(), 'system');
                    $deletedCount++;
                } catch (\Exception $e) {
                    $this->logger->error('Error deleting orphaned project: ' . $e->getMessage(), [
                        'app' => 'projectcheck',
                        'projectId' => $project->getId()
                    ]);
                }
            }
        }

        $this->logger->info("Cleaned up {$deletedCount} orphaned projects", ['app' => 'projectcheck']);
    }

    /**
     * Clean up orphaned customers
     */
    private function cleanupOrphanedCustomers()
    {
        $cutoffDate = new \DateTime();
        $cutoffDate->modify('-1 year');

        // Get customers with no recent projects
        $customers = $this->customerService->getCustomersByUser('system', 1000);
        $deletedCount = 0;

        foreach ($customers as $customer) {
            // Check if customer has recent projects
            $recentProjects = $this->projectService->getProjectsByCustomer(
                $customer->getId(),
                $cutoffDate->format('Y-m-d')
            );

            if (empty($recentProjects)) {
                try {
                    $this->customerService->deleteCustomer($customer->getId(), 'system');
                    $deletedCount++;
                } catch (\Exception $e) {
                    $this->logger->error('Error deleting orphaned customer: ' . $e->getMessage(), [
                        'app' => 'projectcheck',
                        'customerId' => $customer->getId()
                    ]);
                }
            }
        }

        $this->logger->info("Cleaned up {$deletedCount} orphaned customers", ['app' => 'projectcheck']);
    }

    /**
     * Update project statistics
     */
    private function updateProjectStatistics()
    {
        $projects = $this->projectService->getProjectsByUser('system', 1000);
        $updatedCount = 0;

        foreach ($projects as $project) {
            try {
                // Calculate actual budget consumption from time entries
                $projectId = $project->getId();
                $totalBudget = $project->getTotalBudget() ?? 0;
                $totalCost = $this->timeEntryService->getTotalCostForProject($projectId);

                // Calculate consumption percentage
                $budgetConsumption = 0;
                if ($totalBudget > 0) {
                    $budgetConsumption = round(($totalCost / $totalBudget) * 100, 2);
                }

                // Update project with new statistics
                $this->projectService->updateProject($projectId, [
                    'budget_consumption' => $budgetConsumption,
                    'updated_at' => new \DateTime()
                ], 'system');

                $updatedCount++;
            } catch (\Exception $e) {
                $this->logger->error('Error updating project statistics: ' . $e->getMessage(), [
                    'app' => 'projectcheck',
                    'projectId' => $project->getId()
                ]);
            }
        }

        $this->logger->info("Updated statistics for {$updatedCount} projects", ['app' => 'projectcheck']);
    }
}
