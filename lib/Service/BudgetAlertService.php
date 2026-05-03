<?php

declare(strict_types=1);

/**
 * Budget alert service for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Service;

use OCP\IConfig;
use OCP\IL10N;
use OCP\IUserSession;
use OCP\Notification\IManager as NotificationManager;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

/**
 * Service for handling budget alerts and notifications
 */
class BudgetAlertService
{
    /** @var IConfig */
    private $config;

    /** @var IUserSession */
    private $userSession;

    /** @var NotificationManager */
    private $notificationManager;

    /** @var IUserManager */
    private $userManager;

    /** @var LoggerInterface */
    private $logger;

    /** @var ProjectService */
    private $projectService;

    /** @var TimeEntryService */
    private $timeEntryService;

    /** @var IL10N */
    private $l10n;

    /** @var LocaleFormatService */
    private $localeFormat;

    /** @var string */
    private $appName = 'projectcheck';

    /**
     * BudgetAlertService constructor
     *
     * @param IConfig $config
     * @param IUserSession $userSession
     * @param NotificationManager $notificationManager
     * @param IUserManager $userManager
     * @param LoggerInterface $logger
     * @param ProjectService $projectService
     * @param TimeEntryService $timeEntryService
     * @param IL10N $l10n
     * @param LocaleFormatService $localeFormat
     */
    public function __construct(
        IConfig $config,
        IUserSession $userSession,
        NotificationManager $notificationManager,
        IUserManager $userManager,
        LoggerInterface $logger,
        ProjectService $projectService,
        TimeEntryService $timeEntryService,
        IL10N $l10n,
        LocaleFormatService $localeFormat
    ) {
        $this->config = $config;
        $this->userSession = $userSession;
        $this->notificationManager = $notificationManager;
        $this->userManager = $userManager;
        $this->logger = $logger;
        $this->projectService = $projectService;
        $this->timeEntryService = $timeEntryService;
        $this->l10n = $l10n;
        $this->localeFormat = $localeFormat;
    }

    /**
     * Check all projects for budget alerts
     *
     * @param string|null $userId
     * @return array
     */
    public function checkBudgetAlerts($userId = null)
    {
        $alerts = [];

        try {
            // Get all projects for the user
            $projects = $this->projectService->getProjectsByUser($userId, 1000);

            foreach ($projects as $project) {
                $projectAlerts = $this->checkProjectBudget($project, $userId);
                $alerts = array_merge($alerts, $projectAlerts);
            }
        } catch (\Exception $e) {
            $this->logger->error('Error checking budget alerts: ' . $e->getMessage(), [
                'app' => $this->appName,
                'userId' => $userId
            ]);
        }

        return $alerts;
    }

    /**
     * Check budget for a specific project
     *
     * @param object $project
     * @param string|null $userId
     * @return array
     */
    public function checkProjectBudget($project, $userId = null)
    {
        $alerts = [];

        try {
            // Get project budget
            $budget = $project->getBudget();
            if (!$budget || $budget <= 0) {
                return $alerts; // No budget set, no alerts
            }

            // Calculate spent amount
            $spentAmount = $this->calculateProjectSpentAmount($project->getId());

            // Calculate percentage used
            $percentageUsed = ($spentAmount / $budget) * 100;

            // Get user's alert thresholds
            $warningThreshold = $this->getUserBudgetWarningThreshold($userId);
            $criticalThreshold = $this->getUserBudgetCriticalThreshold($userId);

            // Check for alerts
            if ($percentageUsed >= 100) {
                $alerts[] = $this->createBudgetExceededAlert($project, $spentAmount, $budget, $percentageUsed);
            } elseif ($percentageUsed >= $criticalThreshold) {
                $alerts[] = $this->createBudgetCriticalAlert($project, $spentAmount, $budget, $percentageUsed);
            } elseif ($percentageUsed >= $warningThreshold) {
                $alerts[] = $this->createBudgetWarningAlert($project, $spentAmount, $budget, $percentageUsed);
            }
        } catch (\Exception $e) {
            $this->logger->error('Error checking project budget: ' . $e->getMessage(), [
                'app' => $this->appName,
                'projectId' => $project->getId(),
                'userId' => $userId
            ]);
        }

        return $alerts;
    }

    /**
     * Calculate spent amount for a project
     *
     * @param int $projectId
     * @return float
     */
    private function calculateProjectSpentAmount($projectId)
    {
        try {
            // Get all time entries for the project
            $timeEntries = $this->timeEntryService->getTimeEntriesByProject($projectId);

            $totalSpent = 0;
            foreach ($timeEntries as $entry) {
                $hours = $entry->getHours();
                $hourlyRate = $entry->getHourlyRate();
                $totalSpent += $hours * $hourlyRate;
            }

            return $totalSpent;
        } catch (\Exception $e) {
            $this->logger->error('Error calculating project spent amount: ' . $e->getMessage(), [
                'app' => $this->appName,
                'projectId' => $projectId
            ]);
            return 0;
        }
    }

    /**
     * Create budget warning alert
     *
     * @param object $project
     * @param float $spentAmount
     * @param float $budget
     * @param float $percentageUsed
     * @return array
     */
    private function createBudgetWarningAlert($project, $spentAmount, $budget, $percentageUsed)
    {
        return [
            'type' => 'warning',
            'project_id' => $project->getId(),
            'project_name' => $project->getName(),
            'spent_amount' => $spentAmount,
            'budget' => $budget,
            'percentage_used' => $percentageUsed,
            'remaining_budget' => $budget - $spentAmount,
            'message' => $this->l10n->t(
                'Project "%1$s" budget consumption is at %2$s',
                [
                    $project->getName(),
                    $this->localeFormat->percent((float)$percentageUsed, 1),
                ]
            )
        ];
    }

    /**
     * Create budget critical alert
     *
     * @param object $project
     * @param float $spentAmount
     * @param float $budget
     * @param float $percentageUsed
     * @return array
     */
    private function createBudgetCriticalAlert($project, $spentAmount, $budget, $percentageUsed)
    {
        return [
            'type' => 'critical',
            'project_id' => $project->getId(),
            'project_name' => $project->getName(),
            'spent_amount' => $spentAmount,
            'budget' => $budget,
            'percentage_used' => $percentageUsed,
            'remaining_budget' => $budget - $spentAmount,
            'message' => $this->l10n->t(
                'Project "%1$s" is approaching budget limit (%2$s used)',
                [
                    $project->getName(),
                    $this->localeFormat->percent((float)$percentageUsed, 1),
                ]
            )
        ];
    }

    /**
     * Create budget exceeded alert
     *
     * @param object $project
     * @param float $spentAmount
     * @param float $budget
     * @param float $percentageUsed
     * @return array
     */
    private function createBudgetExceededAlert($project, $spentAmount, $budget, $percentageUsed)
    {
        return [
            'type' => 'exceeded',
            'project_id' => $project->getId(),
            'project_name' => $project->getName(),
            'spent_amount' => $spentAmount,
            'budget' => $budget,
            'percentage_used' => $percentageUsed,
            'remaining_budget' => $budget - $spentAmount,
            'message' => $this->l10n->t(
                'Project "%1$s" has exceeded its budget by %2$s (%3$s over)',
                [
                    $project->getName(),
                    $this->localeFormat->currency((float)($spentAmount - $budget)),
                    $this->localeFormat->percent((float)($percentageUsed - 100), 1),
                ]
            )
        ];
    }

    /**
     * Send budget alert notifications
     *
     * @param array $alerts
     * @param string|null $userId
     * @return void
     */
    public function sendBudgetAlertNotifications($alerts, $userId = null)
    {
        foreach ($alerts as $alert) {
            $this->sendBudgetAlertNotification($alert, $userId);
        }
    }

    /**
     * Send a single budget alert notification
     *
     * @param array $alert
     * @param string|null $userId
     * @return void
     */
    private function sendBudgetAlertNotification($alert, $userId = null)
    {
        try {
            // Check if user has budget alerts enabled
            if (!$this->isBudgetAlertsEnabled($userId)) {
                return;
            }

            $notification = $this->notificationManager->createNotification();
            $notification->setApp($this->appName)
                ->setUser($userId)
                ->setDateTime(new \DateTime())
                ->setObject('project', $alert['project_id'])
                ->setSubject('budget_' . $alert['type'], [
                    'project_id' => $alert['project_id'],
                    'project_name' => $alert['project_name'],
                    'percentage_used' => $alert['percentage_used'],
                    'remaining_budget' => $alert['remaining_budget']
                ])
                ->setMessage('budget_' . $alert['type'], [
                    'project_name' => $alert['project_name'],
                    'percentage_used' => $alert['percentage_used'],
                    'remaining_budget' => $alert['remaining_budget']
                ]);

            $this->notificationManager->notify($notification);
        } catch (\Exception $e) {
            $this->logger->error('Error sending budget alert notification: ' . $e->getMessage(), [
                'app' => $this->appName,
                'alert' => $alert,
                'userId' => $userId
            ]);
        }
    }

    /**
     * Get user's budget warning threshold
     *
     * @param string|null $userId
     * @return int
     */
    private function getUserBudgetWarningThreshold($userId = null)
    {
        if ($userId === null) {
            $user = $this->userSession->getUser();
            $userId = $user ? $user->getUID() : null;
        }

        $fallback = $this->config->getAppValue($this->appName, 'budget_warning_threshold', '80');
        if (!$userId) {
            return (int) $fallback;
        }

        return (int) $this->config->getUserValue($userId, $this->appName, 'budget_warning_threshold', $fallback);
    }

    /**
     * Get user's budget critical threshold
     *
     * @param string|null $userId
     * @return int
     */
    private function getUserBudgetCriticalThreshold($userId = null)
    {
        if ($userId === null) {
            $user = $this->userSession->getUser();
            $userId = $user ? $user->getUID() : null;
        }

        $fallback = $this->config->getAppValue($this->appName, 'budget_critical_threshold', '90');
        if (!$userId) {
            return (int) $fallback;
        }

        return (int) $this->config->getUserValue($userId, $this->appName, 'budget_critical_threshold', $fallback);
    }

    /**
     * Check if budget alerts are enabled for user
     *
     * @param string|null $userId
     * @return bool
     */
    private function isBudgetAlertsEnabled($userId = null)
    {
        if ($userId === null) {
            $user = $this->userSession->getUser();
            $userId = $user ? $user->getUID() : null;
        }

        if (!$userId) {
            return true; // Default enabled
        }

        return $this->config->getUserValue($userId, $this->appName, 'budget_alerts', 'true') === 'true';
    }
}
