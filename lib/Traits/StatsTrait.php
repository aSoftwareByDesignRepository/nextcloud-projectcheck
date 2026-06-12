<?php

declare(strict_types=1);

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
    /**
     * @param string|null $forUserId When set, only projects/customers this user may access
     */
    protected function getCommonStats(ProjectService $projectService, CustomerService $customerService, ?TimeEntryService $timeEntryService = null, ?string $forUserId = null): array
    {
        try {
            if ($forUserId !== null) {
                $pList = $projectService->getAccessibleProjectIdListForUser($forUserId);
                $totalProjects = 0;
                if ($pList === null) {
                    $totalProjects = $projectService->getTotalProjectCount();
                } else {
                    $totalProjects = count($pList);
                }
                $totalCustomers = $customerService->getVisibleCustomerCountForUser($forUserId);
            } else {
                $totalProjects = $projectService->getTotalProjectCount();
                $totalCustomers = $customerService->getTotalCustomerCount();
            }

            if ($forUserId !== null) {
                $pList = $projectService->getAccessibleProjectIdListForUser($forUserId);
                if ($pList === null) {
                    $allProjects = $projectService->getAllProjects();
                } elseif ($pList === []) {
                    $allProjects = [];
                } else {
                    $allProjects = $projectService->getProjectsByIdList($pList);
                }
            } else {
                $allProjects = $projectService->getAllProjects();
            }

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

                // Org-wide per-project rollups only when no personal user scope is set.
                if ($timeEntryService && $forUserId === null) {
					$projectHours = $timeEntryService->getTotalHoursForProject($project->getId());
					$projectConsumption = $timeEntryService->getTotalCostForProject($project->getId());
                    $totalHours += $projectHours;
                    $totalConsumption += $projectConsumption;
                }
            }

            // Personal sidebar totals: all own entries, including on projects the user
            // has since left (ownership is sufficient — same rule as the list).
            if ($timeEntryService && $forUserId !== null) {
                $totalHours = $timeEntryService->getTotalHoursForUser($forUserId);
                $totalConsumption = $timeEntryService->getTotalCostForUser($forUserId);
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
