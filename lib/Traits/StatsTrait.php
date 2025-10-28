<?php

/**
 * Stats trait for providing common statistics to templates
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Traits;

use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Service\CustomerService;
use OCA\ProjectCheck\Service\TimeEntryService;

trait StatsTrait
{
    /**
     * Get common stats for templates
     *
     * @param ProjectService $projectService
     * @param CustomerService $customerService
     * @param TimeEntryService|null $timeEntryService
     * @return array
     */
    protected function getCommonStats(ProjectService $projectService, CustomerService $customerService, TimeEntryService $timeEntryService = null): array
    {
        try {
            // Get total counts (not filtered)
            $totalProjects = $projectService->getTotalProjectCount();
            $totalCustomers = $customerService->getTotalCustomerCount();

            // Get all projects for detailed stats
            $allProjects = $projectService->getAllProjects();

            // Initialize counters
            $activeProjects = 0;
            $completedProjects = 0;
            $totalBudget = 0;
            $totalHours = 0;
            $totalConsumption = 0;

            foreach ($allProjects as $project) {
                if ($project->getStatus() === 'Active') {
                    $activeProjects++;
                } elseif ($project->getStatus() === 'Completed') {
                    $completedProjects++;
                }

                // Get project budget
                $projectBudget = $project->getTotalBudget() ?? 0;
                $totalBudget += $projectBudget;

                // Get hours and consumption if TimeEntryService is available
                if ($timeEntryService) {
                    $projectHours = $timeEntryService->getTotalHoursForProject($project->getId());
                    $projectConsumption = $timeEntryService->getTotalCostForProject($project->getId());
                    $totalHours += $projectHours;
                    $totalConsumption += $projectConsumption;
                }
            }

            // Calculate consumption percentage
            $consumptionPercentage = $totalBudget > 0 ? ($totalConsumption / $totalBudget) * 100 : 0;

            return [
                'totalProjects' => $totalProjects,
                'totalCustomers' => $totalCustomers,
                'activeProjects' => $activeProjects,
                'completedProjects' => $completedProjects,
                'totalBudget' => $totalBudget,
                'totalHours' => $totalHours,
                'totalConsumption' => $totalConsumption,
                'consumptionPercentage' => round($consumptionPercentage, 1),
            ];
        } catch (\Exception $e) {
            // Fallback to 0 if there's an error
            return [
                'totalProjects' => 0,
                'totalCustomers' => 0,
                'activeProjects' => 0,
                'completedProjects' => 0,
                'totalBudget' => 0,
                'totalHours' => 0,
                'totalConsumption' => 0,
                'consumptionPercentage' => 0,
            ];
        }
    }
}
