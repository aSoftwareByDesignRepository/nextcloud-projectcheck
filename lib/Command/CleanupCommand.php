<?php

declare(strict_types=1);

/**
 * Console command for cleanup tasks in projectcontrol app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Service\TimeEntryService;
use OCA\ProjectCheck\Service\CustomerService;
use OCP\IConfig;

/**
 * Console command for cleanup tasks
 */
class CleanupCommand extends Command
{
    /** @var ProjectService */
    private $projectService;

    /** @var TimeEntryService */
    private $timeEntryService;

    /** @var CustomerService */
    private $customerService;

	/** @var IConfig */
	private $config;

    /**
     * CleanupCommand constructor
     *
     * @param ProjectService $projectService
     * @param TimeEntryService $timeEntryService
     * @param CustomerService $customerService
	 * @param IConfig $config
     */
    public function __construct(
        ProjectService $projectService,
        TimeEntryService $timeEntryService,
        CustomerService $customerService,
		IConfig $config
    ) {
        parent::__construct();
        $this->projectService = $projectService;
        $this->timeEntryService = $timeEntryService;
        $this->customerService = $customerService;
		$this->config = $config;
    }

    /**
     * Configure the command
     */
    protected function configure()
    {
        $this
            ->setName('projectcontrol:cleanup')
            ->setDescription('Clean up old data in ProjectControl app')
            ->addOption(
                'old-time-entries',
                null,
                InputOption::VALUE_NONE,
                'Clean up old time entries (older than 2 years)'
            )
            ->addOption(
                'orphaned-projects',
                null,
                InputOption::VALUE_NONE,
                'Clean up orphaned projects (no activity for 1 year)'
            )
            ->addOption(
                'orphaned-customers',
                null,
                InputOption::VALUE_NONE,
                'Clean up orphaned customers (no projects for 1 year)'
            )
            ->addOption(
                'update-statistics',
                null,
                InputOption::VALUE_NONE,
                'Update project statistics'
            )
            ->addOption(
                'all',
                null,
                InputOption::VALUE_NONE,
                'Run all cleanup tasks'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Show what would be cleaned up without actually doing it'
            );
    }

    /**
     * Execute the command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<info>Starting ProjectControl cleanup...</info>');

        $dryRun = $input->getOption('dry-run');
        $runAll = $input->getOption('all');

        if ($dryRun) {
            $output->writeln('<comment>DRY RUN MODE - No changes will be made</comment>');
        }

        try {
            if ($runAll || $input->getOption('old-time-entries')) {
                $this->cleanupOldTimeEntries($output, $dryRun);
            }

            if ($runAll || $input->getOption('orphaned-projects')) {
                $this->cleanupOrphanedProjects($output, $dryRun);
            }

            if ($runAll || $input->getOption('orphaned-customers')) {
                $this->cleanupOrphanedCustomers($output, $dryRun);
            }

            if ($runAll || $input->getOption('update-statistics')) {
                $this->updateProjectStatistics($output, $dryRun);
            }

            $output->writeln('<info>Cleanup completed successfully!</info>');
            return 0;
        } catch (\Exception $e) {
            $output->writeln('<error>Error during cleanup: ' . $e->getMessage() . '</error>');
            return 1;
        }
    }

    /**
     * Clean up old time entries
     *
     * @param OutputInterface $output
     * @param bool $dryRun
     */
    private function cleanupOldTimeEntries(OutputInterface $output, bool $dryRun)
    {
		$years = (int) $this->config->getAppValue('projectcheck', 'retention_time_entries_years', '0');
		if ($years <= 0) {
			$output->writeln('Skipping old time entries (config retention_time_entries_years=0, unlimited retention).');
			return;
		}
        $output->writeln('Cleaning up old time entries...');

        $cutoffDate = new \DateTime();
        $cutoffDate->modify('-' . $years . ' years');

        $oldTimeEntries = $this->timeEntryService->getTimeEntriesByDateRange(
            null,
            $cutoffDate->format('Y-m-d'),
            'system'
        );

        $count = count($oldTimeEntries);
        $output->writeln("Found {$count} old time entries to clean up");

        if (!$dryRun && $count > 0) {
            $deletedCount = 0;
            foreach ($oldTimeEntries as $timeEntry) {
                try {
                    $this->timeEntryService->deleteTimeEntryForMaintenance($timeEntry->getId());
                    $deletedCount++;
                } catch (\Exception $e) {
                    $output->writeln("<error>Error deleting time entry {$timeEntry->getId()}: {$e->getMessage()}</error>");
                }
            }
            $output->writeln("Deleted {$deletedCount} old time entries");
        }
    }

    /**
     * Clean up orphaned projects
     *
     * @param OutputInterface $output
     * @param bool $dryRun
     */
    private function cleanupOrphanedProjects(OutputInterface $output, bool $dryRun)
    {
        $output->writeln('Cleaning up orphaned projects...');

        $cutoffDate = new \DateTime();
        $cutoffDate->modify('-1 year');
        $cutoffTs = $cutoffDate->getTimestamp();

        $projects = $this->projectService->getAllProjects();
        $orphanedCount = 0;

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
                $orphanedCount++;
                if (!$dryRun) {
                    try {
                        $this->projectService->deleteProject($project->getId());
                        $output->writeln("Deleted orphaned project: {$project->getName()}");
                    } catch (\Exception $e) {
                        $output->writeln("<error>Error deleting project {$project->getId()}: {$e->getMessage()}</error>");
                    }
                } else {
                    $output->writeln("Would delete orphaned project: {$project->getName()}");
                }
            }
        }

        $output->writeln("Found {$orphanedCount} orphaned projects");
    }

    /**
     * Clean up orphaned customers
     *
     * @param OutputInterface $output
     * @param bool $dryRun
     */
    private function cleanupOrphanedCustomers(OutputInterface $output, bool $dryRun)
    {
        $output->writeln('Cleaning up orphaned customers...');

        $customers = $this->customerService->getAllCustomers();
        $orphanedCount = 0;

        foreach ($customers as $customer) {
            $customerProjects = $this->projectService->getProjectsByCustomer($customer->getId());

            if (empty($customerProjects)) {
                $orphanedCount++;
                if (!$dryRun) {
                    try {
                        $this->customerService->deleteCustomer($customer->getId());
                        $output->writeln("Deleted orphaned customer: {$customer->getName()}");
                    } catch (\Exception $e) {
                        $output->writeln("<error>Error deleting customer {$customer->getId()}: {$e->getMessage()}</error>");
                    }
                } else {
                    $output->writeln("Would delete orphaned customer: {$customer->getName()}");
                }
            }
        }

        $output->writeln("Found {$orphanedCount} orphaned customers");
    }

    /**
     * Update project statistics
     *
     * @param OutputInterface $output
     * @param bool $dryRun
     */
    private function updateProjectStatistics(OutputInterface $output, bool $dryRun)
    {
        $output->writeln('Updating project statistics...');

        $projects = $this->projectService->getAllProjects();
        $updatedCount = 0;

        foreach ($projects as $project) {
            if (!$dryRun) {
                try {
                    $this->projectService->touchProjectRowTimestampForMaintenance($project->getId());
                    $updatedCount++;
                } catch (\Exception $e) {
                    $output->writeln("<error>Error updating project {$project->getId()}: {$e->getMessage()}</error>");
                }
            } else {
                $output->writeln("Would update project row timestamp: {$project->getName()}");
                $updatedCount++;
            }
        }

        $output->writeln("Touched project timestamps (maintenance) for {$updatedCount} projects");
    }
}
