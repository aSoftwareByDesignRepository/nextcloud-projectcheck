<?php

declare(strict_types=1);

/**
 * Background job for cleanup tasks in projectcontrol app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\BackgroundJob;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\Job;
use OCP\BackgroundJob\IJob;
use Psr\Log\LoggerInterface;
use OCA\ProjectCheck\Service\CustomerService;
use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Service\SchemaGuardService;
use OCA\ProjectCheck\Service\TimeEntryService;
use OCA\ProjectCheck\Service\UpgradeBackupService;
use OCP\IConfig;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;

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

	/** @var SchemaGuardService */
	private $schemaGuard;

	/** @var ILockingProvider */
	private $locking;

    /**
     * CleanupJob constructor
     *
     * @param ITimeFactory $time
     * @param LoggerInterface $logger
     * @param ProjectService $projectService
     * @param TimeEntryService $timeEntryService
     * @param CustomerService $customerService
	 * @param IConfig $config
	 * @param SchemaGuardService $schemaGuard
	 * @param ILockingProvider $locking
     */
    public function __construct(
		ITimeFactory $time,
        LoggerInterface $logger,
        ProjectService $projectService,
        TimeEntryService $timeEntryService,
        CustomerService $customerService,
		IConfig $config,
		SchemaGuardService $schemaGuard,
		ILockingProvider $locking
    ) {
        parent::__construct($time);
        $this->logger = $logger;
        $this->projectService = $projectService;
        $this->timeEntryService = $timeEntryService;
        $this->customerService = $customerService;
		$this->config = $config;
		$this->schemaGuard = $schemaGuard;
		$this->locking = $locking;
    }

    /**
     * Run the job
     *
     * @param array $argument
     */
    public function run($argument)
    {
		// Shared lock on the upgrade-backup key: a restore holds it
		// exclusively (UpgradeBackupService::runExclusive) — app-table
		// scans/writes must not interleave with a mid-restore
		// truncate+reinsert. A skip is not a failure: cron re-runs the
		// job on its interval.
		try {
			$this->locking->acquireLock(
				UpgradeBackupService::LOCK_KEY,
				ILockingProvider::LOCK_SHARED,
				'cleanup',
			);
		} catch (LockedException) {
			$this->logger->warning(
				'ProjectCheck: skipping cleanup — upgrade backup/restore in progress.',
				['app' => 'projectcheck'],
			);
			return;
		}
		try {
			$this->doRun($argument);
		} finally {
			$this->locking->releaseLock(UpgradeBackupService::LOCK_KEY, ILockingProvider::LOCK_SHARED);
		}
    }

    private function doRun($argument)
    {
        $this->logger->info('Starting ProjectControl cleanup job', ['app' => 'projectcheck']);

        try {
            $this->schemaGuard->ensureReady();
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
