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

    /**
     * CleanupCommand constructor
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
        parent::__construct();
        $this->projectService = $projectService;
        $this->timeEntryService = $timeEntryService;
        $this->customerService = $customerService;
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
        $output->writeln('Cleaning up old time entries...');

        $cutoffDate = new \DateTime();
        $cutoffDate->modify('-2 years');

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
                    $this->timeEntryService->deleteTimeEntry($timeEntry->getId(), 'system');
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

        $projects = $this->projectService->getProjectsByUser('system', 1000);
        $orphanedCount = 0;

        foreach ($projects as $project) {
            $recentTimeEntries = $this->timeEntryService->getTimeEntriesByProject(
                $project->getId(),
                $cutoffDate->format('Y-m-d')
            );

            if (empty($recentTimeEntries) && $project->getStatus() === 'Completed') {
                $orphanedCount++;
                if (!$dryRun) {
                    try {
                        $this->projectService->deleteProject($project->getId(), 'system');
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

        $cutoffDate = new \DateTime();
        $cutoffDate->modify('-1 year');

        $customers = $this->customerService->getCustomersByUser('system', 1000);
        $orphanedCount = 0;

        foreach ($customers as $customer) {
            $recentProjects = $this->projectService->getProjectsByCustomer(
                $customer->getId(),
                $cutoffDate->format('Y-m-d')
            );

            if (empty($recentProjects)) {
                $orphanedCount++;
                if (!$dryRun) {
                    try {
                        $this->customerService->deleteCustomer($customer->getId(), 'system');
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

        $projects = $this->projectService->getProjectsByUser('system', 1000);
        $updatedCount = 0;

        foreach ($projects as $project) {
            if (!$dryRun) {
                try {
                    // Update project statistics (simplified for now)
                    $this->projectService->updateProject($project->getId(), [
                        'updated_at' => new \DateTime()
                    ], 'system');
                    $updatedCount++;
                } catch (\Exception $e) {
                    $output->writeln("<error>Error updating project {$project->getId()}: {$e->getMessage()}</error>");
                }
            } else {
                $output->writeln("Would update statistics for project: {$project->getName()}");
                $updatedCount++;
            }
        }

        $output->writeln("Updated statistics for {$updatedCount} projects");
    }
}
