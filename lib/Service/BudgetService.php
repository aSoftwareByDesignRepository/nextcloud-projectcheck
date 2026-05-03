<?php

declare(strict_types=1);

/**
 * Budget Service for project budget tracking and warnings
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Service;

use OCA\ProjectCheck\Db\Project;
use OCA\ProjectCheck\Db\TimeEntryMapper;
use OCA\ProjectCheck\Util\Money;
use OCP\IConfig;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

/**
 * Class BudgetService
 *
 * @package OCA\ProjectCheck\Service
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

    /** @var IL10N */
    private $l10n;

    /** @var LocaleFormatService */
    private $localeFormat;

    /**
     * BudgetService constructor.
     *
     * Audit ref. AUDIT-FINDINGS B10/H28: budget alert messages are user-visible HTML,
     * so monetary and percentage values are routed through {@see LocaleFormatService}
     * (locale + org-currency aware) instead of being hard-coded with the euro glyph
     * and en_US-style number_format() output.
     */
    public function __construct(
        TimeEntryMapper $timeEntryMapper,
        IConfig $config,
        LoggerInterface $logger,
        IL10N $l10n,
        LocaleFormatService $localeFormat,
        string $appName
    ) {
        $this->timeEntryMapper = $timeEntryMapper;
        $this->config = $config;
        $this->logger = $logger;
        $this->l10n = $l10n;
        $this->localeFormat = $localeFormat;
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

        // Fixed-point math (audit ref. A5): every operation goes through
        // Money so the displayed totals never drift due to IEEE-754.
        $remainingBudget = Money::asFloat(Money::sub($totalBudget, $usedBudget, Money::MONEY_SCALE), Money::MONEY_SCALE);
        if ($remainingBudget < 0) {
            $remainingBudget = 0.0;
        }
        // Bounded percentage: never let display rounding push a 99.999%
        // value to "100% / exceeded" or vice-versa.
        $consumptionPercentage = Money::asFloat(
            Money::percentageBounded($usedBudget, $totalBudget, Money::MONEY_SCALE),
            Money::MONEY_SCALE
        );

        $availableHours = $hourlyRate > 0
            ? Money::asFloat(Money::div($totalBudget, $hourlyRate, Money::HOUR_SCALE), Money::HOUR_SCALE)
            : 0.0;
        $remainingHoursVal = Money::asFloat(Money::sub($availableHours, $usedHours, Money::HOUR_SCALE), Money::HOUR_SCALE);
        if ($remainingHoursVal < 0) {
            $remainingHoursVal = 0.0;
        }

        $warningLevel = $this->getWarningLevel($consumptionPercentage, $userId);
        $isOverBudget = Money::compare($consumptionPercentage, '100', Money::MONEY_SCALE) > 0;

        $alerts = $this->generateBudgetAlerts($project, $consumptionPercentage, $usedBudget, $totalBudget, $userId);

        return [
            'total_budget' => Money::asFloat(Money::normalize($totalBudget, Money::MONEY_SCALE), Money::MONEY_SCALE),
            'used_budget' => Money::asFloat(Money::normalize($usedBudget, Money::MONEY_SCALE), Money::MONEY_SCALE),
            'remaining_budget' => $remainingBudget,
            'consumption_percentage' => $consumptionPercentage,
            'available_hours' => $availableHours,
            'used_hours' => Money::asFloat(Money::normalize($usedHours, Money::HOUR_SCALE), Money::HOUR_SCALE),
            'remaining_hours' => $remainingHoursVal,
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
     * Generate budget alerts for a project.
     *
     * Audit ref. AUDIT-FINDINGS B10/H28: the message strings keep neutral
     * `%s` placeholders (no embedded `€` glyph, no embedded format
     * specifiers) so translators can reorder them and the values that
     * fill them are pre-formatted with {@see LocaleFormatService}.
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
            'consumption_percentage' => Money::asFloat(Money::normalize($consumptionPercentage, Money::MONEY_SCALE), Money::MONEY_SCALE),
            'used_budget' => Money::asFloat(Money::normalize($usedBudget, Money::MONEY_SCALE), Money::MONEY_SCALE),
            'total_budget' => Money::asFloat(Money::normalize($totalBudget, Money::MONEY_SCALE), Money::MONEY_SCALE),
            'remaining_budget' => Money::asFloat(Money::sub($totalBudget, $usedBudget, Money::MONEY_SCALE), Money::MONEY_SCALE),
            'level' => $warningLevel
        ];

        $overBudgetAmount = Money::asFloat(Money::sub($usedBudget, $totalBudget, Money::MONEY_SCALE), Money::MONEY_SCALE);
        $overBudgetPercentage = Money::asFloat(Money::sub($consumptionPercentage, '100', Money::MONEY_SCALE), Money::MONEY_SCALE);

        if ($consumptionPercentage >= 100) {
            $alerts[] = array_merge($alertData, [
                'type' => 'budget_exceeded',
                'title' => $this->l10n->t('Budget Exceeded'),
                'message' => $this->l10n->t(
                    'Project "%1$s" has exceeded its budget by %2$s (%3$s over)',
                    [
                        $project->getName(),
                        $this->localeFormat->currency($overBudgetAmount),
                        $this->localeFormat->percent($overBudgetPercentage, 1),
                    ]
                )
            ]);
        } elseif ($warningLevel === 'critical') {
            $alerts[] = array_merge($alertData, [
                'type' => 'budget_critical',
                'title' => $this->l10n->t('Budget Critical'),
                'message' => $this->l10n->t(
                    'Project "%1$s" is approaching budget limit (%2$s used)',
                    [
                        $project->getName(),
                        $this->localeFormat->percent($consumptionPercentage, 1),
                    ]
                )
            ]);
        } elseif ($warningLevel === 'warning') {
            $alerts[] = array_merge($alertData, [
                'type' => 'budget_warning',
                'title' => $this->l10n->t('Budget Warning'),
                'message' => $this->l10n->t(
                    'Project "%1$s" budget consumption is at %2$s',
                    [
                        $project->getName(),
                        $this->localeFormat->percent($consumptionPercentage, 1),
                    ]
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
        $additionalCostFloat = Money::asFloat(Money::mul($additionalHours, $additionalRate, Money::MONEY_SCALE), Money::MONEY_SCALE);

        if ($budgetInfo['total_budget'] <= 0) {
            return [
                'has_budget' => false,
                'current_consumption' => 0,
                'new_consumption' => 0,
                'additional_cost' => $additionalCostFloat,
                'remaining_budget_after' => 0,
                'would_exceed_budget' => false,
                'warning_level_after' => 'none'
            ];
        }

        $newUsedBudget = Money::add($budgetInfo['used_budget'], $additionalCostFloat, Money::MONEY_SCALE);
        $newConsumptionPercentage = Money::asFloat(
            Money::percentageBounded($newUsedBudget, $budgetInfo['total_budget'], Money::MONEY_SCALE),
            Money::MONEY_SCALE
        );
        $remainingAfter = Money::asFloat(Money::sub($budgetInfo['total_budget'], $newUsedBudget, Money::MONEY_SCALE), Money::MONEY_SCALE);

        return [
            'has_budget' => true,
            'current_consumption' => $budgetInfo['consumption_percentage'],
            'new_consumption' => $newConsumptionPercentage,
            'additional_cost' => $additionalCostFloat,
            'remaining_budget_after' => $remainingAfter,
            'would_exceed_budget' => Money::compare($newConsumptionPercentage, '100', Money::MONEY_SCALE) > 0,
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
