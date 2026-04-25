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
use OCP\IConfig;

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

	/** @var IConfig */
	private $config;

    /**
     * CleanupJob constructor
     *
     * @param LoggerInterface $logger
     * @param ProjectService $projectService
     * @param TimeEntryService $timeEntryService
     * @param CustomerService $customerService
	 * @param IConfig $config
     */
    public function __construct(
        LoggerInterface $logger,
        ProjectService $projectService,
        TimeEntryService $timeEntryService,
        CustomerService $customerService,
		IConfig $config
    ) {
        parent::__construct();
        $this->logger = $logger;
        $this->projectService = $projectService;
        $this->timeEntryService = $timeEntryService;
        $this->customerService = $customerService;
		$this->config = $config;
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
		$years = (int) $this->config->getAppValue('projectcheck', 'retention_time_entries_years', '0');
		if ($years <= 0) {
			$this->logger->info('Time entry age cleanup skipped (retention_time_entries_years is 0 = unlimited)', ['app' => 'projectcheck']);
			return;
		}
        $cutoffDate = new \DateTime();
        $cutoffDate->modify('-' . $years . ' years');

        // Get old time entries
        $oldTimeEntries = $this->timeEntryService->getTimeEntriesByDateRange(
            null,
            $cutoffDate->format('Y-m-d'),
            'system'
        );

        $deletedCount = 0;
        foreach ($oldTimeEntries as $timeEntry) {
            try {
                $this->timeEntryService->deleteTimeEntryForMaintenance($timeEntry->getId());
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

        $projects = $this->projectService->getAllProjects();
        $deletedCount = 0;
        $cutoffTs = $cutoffDate->getTimestamp();

        foreach ($projects as $project) {
            $entries = $this->timeEntryService->getTimeEntriesByProject($project->getId());
            $hasRecent = false;
            foreach ($entries as $e) {
                if ($e->getDate() && $e->getDate()->getTimestamp() >= $cutoffTs) {
                    $hasRecent = true;
                    break;
                }
            }

            if (!$hasRecent && $project->getStatus() === 'Completed') {
                try {
                    $this->projectService->deleteProject($project->getId());
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
        $customers = $this->customerService->getAllCustomers();
        $deletedCount = 0;

        foreach ($customers as $customer) {
            $customerProjects = $this->projectService->getProjectsByCustomer($customer->getId());

            if (empty($customerProjects)) {
                try {
                    $this->customerService->deleteCustomer($customer->getId());
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
        $projects = $this->projectService->getAllProjects();
        $updatedCount = 0;

        foreach ($projects as $project) {
            try {
                $this->projectService->touchProjectRowTimestampForMaintenance($project->getId());
                $updatedCount++;
            } catch (\Exception $e) {
                $this->logger->error('Error updating project statistics: ' . $e->getMessage(), [
                    'app' => 'projectcheck',
                    'projectId' => $project->getId()
                ]);
            }
        }

        $this->logger->info("Updated project timestamps for {$updatedCount} projects (maintenance)", ['app' => 'projectcheck']);
    }
}
