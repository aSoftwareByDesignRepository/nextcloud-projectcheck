<?php

declare(strict_types=1);

/**
 * Cron job for cleanup tasks in projectcontrol app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Cron;

use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\Job;
use OCP\BackgroundJob\IJob;
use Psr\Log\LoggerInterface;
use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Service\TimeEntryService;
use OCA\ProjectCheck\Service\CustomerService;
use OCA\ProjectCheck\Service\BudgetAlertService;
use OCP\IConfig;

/**
 * Cron job for cleanup tasks
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

	/** @var BudgetAlertService */
	private $budgetAlertService;

	/** @var IConfig */
	private $config;

	/**
	 * CleanupJob constructor
	 *
	 * @param LoggerInterface $logger
	 * @param ProjectService $projectService
	 * @param TimeEntryService $timeEntryService
	 * @param CustomerService $customerService
	 * @param BudgetAlertService $budgetAlertService
	 * @param IConfig $config
	 */
	public function __construct(
		LoggerInterface $logger,
		ProjectService $projectService,
		TimeEntryService $timeEntryService,
		CustomerService $customerService,
		BudgetAlertService $budgetAlertService,
		IConfig $config
	) {
		parent::__construct();
		$this->logger = $logger;
		$this->projectService = $projectService;
		$this->timeEntryService = $timeEntryService;
		$this->customerService = $customerService;
		$this->budgetAlertService = $budgetAlertService;
		$this->config = $config;
	}

	/**
	 * Run the job
	 *
	 * @param array $argument
	 */
	public function run($argument)
	{
		$this->logger->info('Starting ProjectControl cron cleanup job', ['app' => 'projectcheck']);

		try {
			// Clean up old time entries (older than 2 years)
			$this->cleanupOldTimeEntries();

			// Clean up orphaned projects (no time entries for 1 year)
			$this->cleanupOrphanedProjects();

			// Clean up orphaned customers (no projects for 1 year)
			$this->cleanupOrphanedCustomers();

			// Update project statistics
			$this->updateProjectStatistics();

			// Check for budget warnings
			$this->checkBudgetWarnings();

			// Check for deadline warnings
			$this->checkDeadlineWarnings();

			$this->logger->info('ProjectControl cron cleanup job completed successfully', ['app' => 'projectcheck']);
		} catch (\Exception $e) {
			$this->logger->error('Error in ProjectControl cron cleanup job: ' . $e->getMessage(), [
				'app' => 'projectcheck',
				'exception' => $e
			]);
		}
	}

	/**
	 * Clean up old time entries. Disabled when retention_time_entries_years is 0 or not set (default: keep forever).
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

	/**
	 * Check for budget warnings
	 */
	private function checkBudgetWarnings()
	{
		try {
			$alerts = $this->budgetAlertService->checkBudgetAlerts('system');

			$warningCount = count($alerts);

			// Send notifications for alerts
			foreach ($alerts as $alert) {
				$this->logger->warning($alert['message'], [
					'app' => 'projectcheck',
					'projectId' => $alert['project_id'],
					'alertType' => $alert['type'],
					'percentageUsed' => $alert['percentage_used']
				]);
			}

			$this->logger->info("Found {$warningCount} projects with budget alerts", ['app' => 'projectcheck']);
		} catch (\Exception $e) {
			$this->logger->error('Error checking budget warnings: ' . $e->getMessage(), [
				'app' => 'projectcheck'
			]);
		}
	}

	/**
	 * Check for deadline warnings
	 */
	private function checkDeadlineWarnings()
	{
		$projects = $this->projectService->getProjectsByUser('system', 1000);
		$warningCount = 0;

		foreach ($projects as $project) {
			try {
				$endDate = $project->getEndDate();
				if ($endDate) {
					$endDateTime = \DateTime::createFromInterface($endDate);
					$now = new \DateTime();
					$daysUntilDeadline = $now->diff($endDateTime)->days;

					if ($daysUntilDeadline <= 7 && $daysUntilDeadline >= 0) {
						// Log deadline warning
						$this->logger->warning("Project {$project->getName()} is due in {$daysUntilDeadline} days", [
							'app' => 'projectcheck',
							'projectId' => $project->getId(),
							'daysUntilDeadline' => $daysUntilDeadline
						]);
						$warningCount++;
					} elseif ($daysUntilDeadline < 0) {
						// Log overdue warning
						$this->logger->error("Project {$project->getName()} is overdue by " . abs($daysUntilDeadline) . " days", [
							'app' => 'projectcheck',
							'projectId' => $project->getId(),
							'daysOverdue' => abs($daysUntilDeadline)
						]);
						$warningCount++;
					}
				}
			} catch (\Exception $e) {
				$this->logger->error('Error checking deadline for project: ' . $e->getMessage(), [
					'app' => 'projectcheck',
					'projectId' => $project->getId()
				]);
			}
		}

		$this->logger->info("Found {$warningCount} projects with deadline warnings", ['app' => 'projectcheck']);
	}
}
