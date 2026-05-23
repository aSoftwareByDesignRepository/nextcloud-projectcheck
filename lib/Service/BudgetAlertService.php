<?php

declare(strict_types=1);

/**
 * Budget alert service for projectcheck app
 *
 * Cron and notification helpers delegate to {@see BudgetService} for all
 * spent/cost/percentage math (Money-safe, frozen entry rates).
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Service;

use OCA\ProjectCheck\Db\Project;
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

	/** @var BudgetService */
	private $budgetService;

	/** @var IL10N */
	private $l10n;

	/** @var string */
	private $appName = 'projectcheck';

	public function __construct(
		IConfig $config,
		IUserSession $userSession,
		NotificationManager $notificationManager,
		IUserManager $userManager,
		LoggerInterface $logger,
		ProjectService $projectService,
		BudgetService $budgetService,
		IL10N $l10n,
	) {
		$this->config = $config;
		$this->userSession = $userSession;
		$this->notificationManager = $notificationManager;
		$this->userManager = $userManager;
		$this->logger = $logger;
		$this->projectService = $projectService;
		$this->budgetService = $budgetService;
		$this->l10n = $l10n;
	}

	/**
	 * Check all projects for budget alerts
	 *
	 * @param string|null $userId
	 * @return array<int, array<string, mixed>>
	 */
	public function checkBudgetAlerts($userId = null): array
	{
		$alerts = [];

		try {
			$projects = $this->projectService->getProjectsByUser($userId, 1000);

			foreach ($projects as $project) {
				$projectAlerts = $this->checkProjectBudget($project, $userId);
				$alerts = array_merge($alerts, $projectAlerts);
			}
		} catch (\Exception $e) {
			$this->logger->error('Error checking budget alerts: ' . $e->getMessage(), [
				'app' => $this->appName,
				'userId' => $userId,
			]);
		}

		return $alerts;
	}

	/**
	 * Check budget for a specific project using BudgetService (Money-safe totals).
	 *
	 * @param object $project
	 * @param string|null $userId
	 * @return array<int, array<string, mixed>>
	 */
	public function checkProjectBudget($project, $userId = null): array
	{
		if (!$project instanceof Project) {
			return [];
		}

		try {
			$info = $this->budgetService->getProjectBudgetInfo($project, $userId);
			if (($info['total_budget'] ?? 0) <= 0 || empty($info['alerts'])) {
				return [];
			}

			$mapped = [];
			foreach ($info['alerts'] as $alert) {
				$type = (string)($alert['type'] ?? 'budget_warning');
				$mapped[] = [
					'type' => $this->legacyAlertType($type),
					'project_id' => (int)($alert['project_id'] ?? $project->getId()),
					'project_name' => (string)($alert['project_name'] ?? $project->getName()),
					'spent_amount' => (float)($alert['used_budget'] ?? $info['used_budget']),
					'budget' => (float)($alert['total_budget'] ?? $info['total_budget']),
					'percentage_used' => (float)($alert['consumption_percentage'] ?? $info['consumption_percentage']),
					'remaining_budget' => (float)($alert['remaining_budget'] ?? $info['remaining_budget']),
					'message' => (string)($alert['message'] ?? ''),
				];
			}

			return $mapped;
		} catch (\Exception $e) {
			$this->logger->error('Error checking project budget: ' . $e->getMessage(), [
				'app' => $this->appName,
				'projectId' => $project->getId(),
				'userId' => $userId,
			]);

			return [];
		}
	}

	/**
	 * Map BudgetService alert types to legacy cron log keys.
	 */
	private function legacyAlertType(string $type): string
	{
		return match ($type) {
			'budget_exceeded' => 'exceeded',
			'budget_critical' => 'critical',
			default => 'warning',
		};
	}

	/**
	 * Send budget alert notifications
	 *
	 * @param array<int, array<string, mixed>> $alerts
	 * @param string|null $userId
	 */
	public function sendBudgetAlertNotifications(array $alerts, $userId = null): void
	{
		foreach ($alerts as $alert) {
			$this->sendBudgetAlertNotification($alert, $userId);
		}
	}

	/**
	 * Send a single budget alert notification
	 *
	 * @param array<string, mixed> $alert
	 * @param string|null $userId
	 */
	private function sendBudgetAlertNotification(array $alert, $userId = null): void
	{
		try {
			if (!$this->isBudgetAlertsEnabled($userId)) {
				return;
			}

			$notification = $this->notificationManager->createNotification();
			$notification->setApp($this->appName)
				->setUser($userId)
				->setDateTime(new \DateTime())
				->setObject('project', (string)($alert['project_id'] ?? ''))
				->setSubject('budget_' . ($alert['type'] ?? 'warning'), [
					'project_id' => $alert['project_id'] ?? null,
					'project_name' => $alert['project_name'] ?? '',
					'percentage_used' => $alert['percentage_used'] ?? 0,
					'remaining_budget' => $alert['remaining_budget'] ?? 0,
				])
				->setMessage('budget_' . ($alert['type'] ?? 'warning'), [
					'project_name' => $alert['project_name'] ?? '',
					'percentage_used' => $alert['percentage_used'] ?? 0,
					'remaining_budget' => $alert['remaining_budget'] ?? 0,
				]);

			$this->notificationManager->notify($notification);
		} catch (\Exception $e) {
			$this->logger->error('Error sending budget alert notification: ' . $e->getMessage(), [
				'app' => $this->appName,
				'alert' => $alert,
				'userId' => $userId,
			]);
		}
	}

	private function isBudgetAlertsEnabled($userId = null): bool
	{
		if ($userId === null) {
			$user = $this->userSession->getUser();
			$userId = $user ? $user->getUID() : null;
		}

		if (!$userId) {
			return true;
		}

		return $this->config->getUserValue($userId, $this->appName, 'budget_alerts', 'true') === 'true';
	}
}
