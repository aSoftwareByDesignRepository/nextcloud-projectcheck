<?php

/**
 * Budget Service for project budget tracking and warnings
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Service;

use OCA\ProjectCheck\Db\Project;
use OCA\ProjectCheck\Db\TimeEntryMapper;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

/**
 * Class BudgetService
 *
 * @package OCA\ProjectControl\Service
 */
class BudgetService
{
    /** @var TimeEntryMapper */
    private $timeEntryMapper;

    /** @var IConfig */
    private $config;

    /** @var LoggerInterface */
    private $logger;

    /** @var string */
    private $appName;

    /**
     * BudgetService constructor
     */
    public function __construct(
        TimeEntryMapper $timeEntryMapper,
        IConfig $config,
        LoggerInterface $logger,
        string $appName
    ) {
        $this->timeEntryMapper = $timeEntryMapper;
        $this->config = $config;
        $this->logger = $logger;
        $this->appName = $appName;
    }

    /**
     * Get comprehensive budget information for a project
     *
     * @param Project $project
     * @param string|null $userId
     * @return array
     */
    public function getProjectBudgetInfo(Project $project, ?string $userId = null): array
    {
        $projectId = $project->getId();
        $totalBudget = $project->getTotalBudget() ?? 0;
        $hourlyRate = $project->getHourlyRate() ?? 0;

        // Get actual spent hours and cost regardless of budget
        $spentData = $this->getProjectSpentData($projectId);
        $usedHours = $spentData['total_hours'];
        $usedBudget = $spentData['total_cost'];

        if ($totalBudget <= 0) {
            // For projects without budget, still return actual spent data
            return [
                'total_budget' => 0,
                'used_budget' => $usedBudget,
                'remaining_budget' => 0,
                'consumption_percentage' => 0,
                'available_hours' => 0,
                'used_hours' => $usedHours,
                'remaining_hours' => 0,
                'warning_level' => 'none',
                'is_over_budget' => false,
                'alerts' => []
            ];
        }

        // Calculate remaining values
        $remainingBudget = max(0, $totalBudget - $usedBudget);
        $consumptionPercentage = ($usedBudget / $totalBudget) * 100;

        // Calculate available and remaining hours
        $availableHours = $hourlyRate > 0 ? $totalBudget / $hourlyRate : 0;
        $remainingHours = max(0, $availableHours - $usedHours);

        // Determine warning level
        $warningLevel = $this->getWarningLevel($consumptionPercentage, $userId);
        $isOverBudget = $consumptionPercentage > 100;

        // Generate alerts if needed
        $alerts = $this->generateBudgetAlerts($project, $consumptionPercentage, $usedBudget, $totalBudget, $userId);

        return [
            'total_budget' => $totalBudget,
            'used_budget' => $usedBudget,
            'remaining_budget' => $remainingBudget,
            'consumption_percentage' => round($consumptionPercentage, 2),
            'available_hours' => round($availableHours, 2),
            'used_hours' => round($usedHours, 2),
            'remaining_hours' => round($remainingHours, 2),
            'warning_level' => $warningLevel,
            'is_over_budget' => $isOverBudget,
            'alerts' => $alerts
        ];
    }

    /**
     * Get spent data for a project
     *
     * @param int $projectId
     * @return array
     */
    private function getProjectSpentData(int $projectId): array
    {
        try {
            $totalCost = $this->timeEntryMapper->getTotalCostForProject($projectId);
            $totalHours = $this->timeEntryMapper->getTotalHoursForProject($projectId);

            return [
                'total_cost' => $totalCost,
                'total_hours' => $totalHours
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error getting project spent data: ' . $e->getMessage(), [
                'app' => $this->appName,
                'projectId' => $projectId
            ]);
            return [
                'total_cost' => 0,
                'total_hours' => 0
            ];
        }
    }

    /**
     * Get warning level based on consumption percentage and user settings
     *
     * @param float $consumptionPercentage
     * @param string|null $userId
     * @return string
     */
    private function getWarningLevel(float $consumptionPercentage, ?string $userId = null): string
    {
        if ($consumptionPercentage >= 100) {
            return 'critical';
        }

        $criticalThreshold = $this->getBudgetCriticalThreshold($userId);
        $warningThreshold = $this->getBudgetWarningThreshold($userId);

        if ($consumptionPercentage >= $criticalThreshold) {
            return 'critical';
        } elseif ($consumptionPercentage >= $warningThreshold) {
            return 'warning';
        }

        return 'none';
    }

    /**
     * Generate budget alerts for a project
     *
     * @param Project $project
     * @param float $consumptionPercentage
     * @param float $usedBudget
     * @param float $totalBudget
     * @param string|null $userId
     * @return array
     */
    private function generateBudgetAlerts(Project $project, float $consumptionPercentage, float $usedBudget, float $totalBudget, ?string $userId = null): array
    {
        $alerts = [];
        $warningLevel = $this->getWarningLevel($consumptionPercentage, $userId);

        if ($warningLevel === 'none') {
            return $alerts;
        }

        $alertData = [
            'project_id' => $project->getId(),
            'project_name' => $project->getName(),
            'consumption_percentage' => round($consumptionPercentage, 2),
            'used_budget' => $usedBudget,
            'total_budget' => $totalBudget,
            'remaining_budget' => $totalBudget - $usedBudget,
            'level' => $warningLevel
        ];

        if ($consumptionPercentage >= 100) {
            $alerts[] = array_merge($alertData, [
                'type' => 'budget_exceeded',
                'title' => 'Budget Exceeded',
                'message' => sprintf(
                    'Project "%s" has exceeded its budget by €%.2f (%.1f%% over)',
                    $project->getName(),
                    $usedBudget - $totalBudget,
                    $consumptionPercentage - 100
                )
            ]);
        } elseif ($warningLevel === 'critical') {
            $alerts[] = array_merge($alertData, [
                'type' => 'budget_critical',
                'title' => 'Budget Critical',
                'message' => sprintf(
                    'Project "%s" is approaching budget limit (%.1f%% used)',
                    $project->getName(),
                    $consumptionPercentage
                )
            ]);
        } elseif ($warningLevel === 'warning') {
            $alerts[] = array_merge($alertData, [
                'type' => 'budget_warning',
                'title' => 'Budget Warning',
                'message' => sprintf(
                    'Project "%s" budget consumption is at %.1f%%',
                    $project->getName(),
                    $consumptionPercentage
                )
            ]);
        }

        return $alerts;
    }

    /**
     * Get budget warning threshold for user
     *
     * @param string|null $userId
     * @return float
     */
    private function getBudgetWarningThreshold(?string $userId = null): float
    {
        if ($userId) {
            $threshold = $this->config->getUserValue($userId, $this->appName, 'budget_warning_threshold', '80');
        } else {
            $threshold = $this->config->getAppValue($this->appName, 'budget_warning_threshold', '80');
        }

        return (float) $threshold;
    }

    /**
     * Get budget critical threshold for user
     *
     * @param string|null $userId
     * @return float
     */
    private function getBudgetCriticalThreshold(?string $userId = null): float
    {
        if ($userId) {
            $threshold = $this->config->getUserValue($userId, $this->appName, 'budget_critical_threshold', '90');
        } else {
            $threshold = $this->config->getAppValue($this->appName, 'budget_critical_threshold', '90');
        }

        return (float) $threshold;
    }

    /**
     * Check if time entry would cause budget to be exceeded
     *
     * @param Project $project
     * @param float $additionalHours
     * @param float $additionalRate
     * @return array
     */
    public function checkTimeEntryBudgetImpact(Project $project, float $additionalHours, float $additionalRate): array
    {
        $budgetInfo = $this->getProjectBudgetInfo($project);
        $additionalCost = $additionalHours * $additionalRate;
        $newUsedBudget = $budgetInfo['used_budget'] + $additionalCost;
        $newConsumptionPercentage = ($newUsedBudget / $budgetInfo['total_budget']) * 100;

        return [
            'current_consumption' => $budgetInfo['consumption_percentage'],
            'new_consumption' => round($newConsumptionPercentage, 2),
            'additional_cost' => $additionalCost,
            'remaining_budget_after' => $budgetInfo['total_budget'] - $newUsedBudget,
            'would_exceed_budget' => $newConsumptionPercentage > 100,
            'warning_level_after' => $this->getWarningLevel($newConsumptionPercentage)
        ];
    }

    /**
     * Get budget alerts for multiple projects
     *
     * @param array $projects
     * @param string|null $userId
     * @return array
     */
    public function getBudgetAlertsForProjects(array $projects, ?string $userId = null): array
    {
        $allAlerts = [];

        foreach ($projects as $project) {
            if ($project instanceof Project) {
                $budgetInfo = $this->getProjectBudgetInfo($project, $userId);
                $allAlerts = array_merge($allAlerts, $budgetInfo['alerts']);
            }
        }

        // Sort by severity and consumption percentage
        usort($allAlerts, function ($a, $b) {
            $severityOrder = ['critical' => 3, 'warning' => 2, 'none' => 1];
            $aSeverity = $severityOrder[$a['level']] ?? 0;
            $bSeverity = $severityOrder[$b['level']] ?? 0;

            if ($aSeverity !== $bSeverity) {
                return $bSeverity - $aSeverity;
            }

            return $b['consumption_percentage'] - $a['consumption_percentage'];
        });

        return $allAlerts;
    }
}
