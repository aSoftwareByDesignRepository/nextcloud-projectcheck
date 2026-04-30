<?php

declare(strict_types=1);

/**
 * Dashboard controller for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCA\ProjectCheck\Db\Project;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\IURLGenerator;
use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Service\TimeEntryService;
use OCA\ProjectCheck\Service\CustomerService;
use OCA\ProjectCheck\Service\BudgetService;
use OCA\ProjectCheck\Service\CSPService;
use OCA\ProjectCheck\Traits\StatsTrait;
use OCP\IL10N;

/**
 * Dashboard controller for project overview and statistics
 */
class DashboardController extends Controller
{
	use CSPTrait;
	use StatsTrait;

	/** @var IUserSession */
	private $userSession;

	/** @var ProjectService */
	private $projectService;

	/** @var TimeEntryService */
	private $timeEntryService;

	/** @var CustomerService */
	private $customerService;

	/** @var BudgetService */
	private $budgetService;

	/** @var IURLGenerator */
	private $urlGenerator;

	/** @var IL10N */
	private $l;

	/**
	 * DashboardController constructor
	 *
	 * @param string $appName
	 * @param IRequest $request
	 * @param IUserSession $userSession
	 * @param ProjectService $projectService
	 * @param TimeEntryService $timeEntryService
	 * @param CustomerService $customerService
	 * @param BudgetService $budgetService
	 * @param IURLGenerator $urlGenerator
	 * @param CSPService $cspService
	 */
	public function __construct(
		$appName,
		IRequest $request,
		IUserSession $userSession,
		ProjectService $projectService,
		TimeEntryService $timeEntryService,
		CustomerService $customerService,
		BudgetService $budgetService,
		IURLGenerator $urlGenerator,
		CSPService $cspService,
		IL10N $l
	) {
		parent::__construct($appName, $request);
		$this->userSession = $userSession;
		$this->projectService = $projectService;
		$this->timeEntryService = $timeEntryService;
		$this->customerService = $customerService;
		$this->budgetService = $budgetService;
		$this->urlGenerator = $urlGenerator;
		$this->l = $l;
		$this->setCspService($cspService);
	}

	/**
	 * Show dashboard page
	 *
	 * @return TemplateResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index()
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			$response = new TemplateResponse($this->appName, 'error', [
				'message' => $this->l->t('User not authenticated')
			], 'guest');
			return $this->configureCSP($response, 'guest');
		}

		$userId = $user->getUID();
		$accessibleProjectIds = $this->projectService->getAccessibleProjectIdListForUser($userId);
		$isGlobalViewer = $accessibleProjectIds === null;

		// Get comprehensive statistics
		$comprehensiveStats = $this->getComprehensiveStats($userId, $accessibleProjectIds);

		// Get detailed yearly statistics grouped by customer and project
		$detailedYearlyStats = $this->timeEntryService->getDetailedYearlyStats($accessibleProjectIds, $isGlobalViewer ? null : [$userId]);

		// Get project type statistics
		$projectTypeStats = $this->timeEntryService->getYearlyStatsByProjectType($accessibleProjectIds, $isGlobalViewer ? null : [$userId]);
		$detailedProjectTypeStats = $this->timeEntryService->getDetailedYearlyStatsByProjectType($accessibleProjectIds, $isGlobalViewer ? null : [$userId]);
		$productivityAnalysis = $this->timeEntryService->getProductivityAnalysis($accessibleProjectIds, $isGlobalViewer ? null : [$userId]);

		// Get common stats for the sidebar
		$commonStats = $this->getCommonStats($this->projectService, $this->customerService, null, $userId);

		// Merge stats, with comprehensive stats taking precedence
		$stats = array_merge($commonStats, $comprehensiveStats);
		$stats['detailedYearlyStats'] = $detailedYearlyStats;
		$stats['projectTypeStats'] = $projectTypeStats;
		$stats['detailedProjectTypeStats'] = $detailedProjectTypeStats;
		$stats['productivityAnalysis'] = $productivityAnalysis;

		// Get budget alerts for active projects
		$allProjects = $this->getDashboardProjects($accessibleProjectIds, $userId);
		$activeProjects = array_filter($allProjects, function ($project) {
			return $project->getStatus() === 'Active';
		});
		$budgetAlerts = $this->budgetService->getBudgetAlertsForProjects($activeProjects, $userId);

		$response = new TemplateResponse($this->appName, 'dashboard', [
			'stats' => $stats,
			'budgetAlerts' => $budgetAlerts,
			'isGlobalViewer' => $isGlobalViewer,
			'userId' => $userId,
			'urlGenerator' => $this->urlGenerator,
			'dashboardUrl' => $this->urlGenerator->linkToRoute('projectcheck.dashboard.index'),
			'projectsUrl' => $this->urlGenerator->linkToRoute('projectcheck.project.index'),
			'customersUrl' => $this->urlGenerator->linkToRoute('projectcheck.customer.index'),
			'timeEntriesUrl' => $this->urlGenerator->linkToRoute('projectcheck.timeentry.index'),
			'settingsUrl' => $this->urlGenerator->linkToRoute('projectcheck.app_config.settingsIndex'),
			'canCreateProject' => $this->projectService->canUserCreateProject($userId),
		]);

		return $this->configureCSP($response);
	}

	/**
	 * Get dashboard statistics via API
	 *
	 * @return JSONResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getStats()
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new JSONResponse(['error' => $this->l->t('User not authenticated')], 401);
		}

		$userId = $user->getUID();
		$accessibleProjectIds = $this->projectService->getAccessibleProjectIdListForUser($userId);
		$isGlobalViewer = $accessibleProjectIds === null;

		// Get comprehensive statistics
		$comprehensiveStats = $this->getComprehensiveStats($userId, $accessibleProjectIds);

		// Get yearly statistics
		$yearlyStats = $this->timeEntryService->getYearlyStatsForAllProjects($accessibleProjectIds, $isGlobalViewer ? null : [$userId]);

		// Get detailed yearly statistics grouped by customer and project
		$detailedYearlyStats = $this->timeEntryService->getDetailedYearlyStats($accessibleProjectIds, $isGlobalViewer ? null : [$userId]);

		// Get project type statistics
		$projectTypeStats = $this->timeEntryService->getYearlyStatsByProjectType($accessibleProjectIds, $isGlobalViewer ? null : [$userId]);
		$detailedProjectTypeStats = $this->timeEntryService->getDetailedYearlyStatsByProjectType($accessibleProjectIds, $isGlobalViewer ? null : [$userId]);
		$productivityAnalysis = $this->timeEntryService->getProductivityAnalysis($accessibleProjectIds, $isGlobalViewer ? null : [$userId]);

		// Get common stats for the sidebar
		$commonStats = $this->getCommonStats($this->projectService, $this->customerService, null, $userId);

		// Merge stats, with comprehensive stats taking precedence
		$stats = array_merge($commonStats, $comprehensiveStats);
		$stats['yearlyStats'] = $yearlyStats;
		$stats['detailedYearlyStats'] = $detailedYearlyStats;
		$stats['projectTypeStats'] = $projectTypeStats;
		$stats['detailedProjectTypeStats'] = $detailedProjectTypeStats;
		$stats['productivityAnalysis'] = $productivityAnalysis;
		$stats['isGlobalViewer'] = $isGlobalViewer;

		return new JSONResponse($stats);
	}

	/**
	 * Get comprehensive statistics for the dashboard
	 *
	 * @param string $userId
	 * @return array
	 */
	private function getComprehensiveStats(string $userId, ?array $accessibleProjectIds): array
	{
		try {
			$isGlobalViewer = $accessibleProjectIds === null;
			$allProjects = $this->getDashboardProjects($accessibleProjectIds, $userId);
			$customers = $isGlobalViewer
				? $this->customerService->getAllCustomers()
				: $this->customerService->getCustomers(
					$this->customerService->getCustomerListFiltersForUser($userId, [])
				);

			// Calculate statistics
			$totalProjects = count($allProjects);
			$activeProjects = 0;
			$completedProjects = 0;
			$totalBudget = 0;
			$totalConsumption = 0;
			$totalHours = 0;

			foreach ($allProjects as $project) {
				if ($project->getStatus() === 'Active') {
					$activeProjects++;
				} elseif ($project->getStatus() === 'Completed') {
					$completedProjects++;
				}

				$totalBudget += $project->getTotalBudget();

				// Calculate actual consumption from time entries for this project
				if ($isGlobalViewer) {
					$projectSpentData = $this->budgetService->getProjectBudgetInfo($project, $userId);
					$totalConsumption += $projectSpentData['used_budget'];
				} else {
					$totalConsumption += $this->timeEntryService->getTotalCostForProjectAndUser((int) $project->getId(), $userId);
				}
			}

			$timeEntryScopeUserIds = $isGlobalViewer ? null : [$userId];
			$yearlyStats = $this->timeEntryService->getYearlyStatsForAllProjects($accessibleProjectIds, $timeEntryScopeUserIds);
			foreach ($yearlyStats as $yearData) {
				$totalHours += (float) ($yearData['total_hours'] ?? 0);
			}

			$timeEntriesWithProjectInfo = $this->getRecentTimeEntries($accessibleProjectIds, $isGlobalViewer ? null : $userId);

			$consumptionPercentage = $totalBudget > 0 ? ($totalConsumption / $totalBudget) * 100 : 0;

			return [
				'total_projects' => $totalProjects,
				'total_customers' => count($customers),
				'totalProjects' => $totalProjects, // Keep both for backward compatibility
				'activeProjects' => $activeProjects,
				'completedProjects' => $completedProjects,
				'totalBudget' => $totalBudget,
				'totalConsumption' => $totalConsumption,
				'consumptionPercentage' => round($consumptionPercentage, 2),
				'totalHours' => $totalHours,
				'totalCustomers' => count($customers),
				'recentProjects' => $this->enrichProjectsWithBudgetInfo(array_slice($allProjects, 0, 5), $userId), // Last 5 projects
				'recentTimeEntries' => $timeEntriesWithProjectInfo
			];
		} catch (\Exception $e) {
			return [
				'totalProjects' => 0,
				'activeProjects' => 0,
				'completedProjects' => 0,
				'totalBudget' => 0,
				'totalConsumption' => 0,
				'consumptionPercentage' => 0,
				'totalHours' => 0,
				'totalCustomers' => 0,
				'recentProjects' => [],
				'recentTimeEntries' => [],
				'error' => $e->getMessage()
			];
		}
	}

	/**
	 * @param list<int>|null $accessibleProjectIds
	 * @return list<Project>
	 */
	private function getDashboardProjects(?array $accessibleProjectIds, string $userId): array
	{
		if ($accessibleProjectIds === null) {
			return $this->projectService->getAllProjects();
		}
		if ($accessibleProjectIds === []) {
			return [];
		}
		return $this->projectService->getProjectsByIdList($accessibleProjectIds);
	}

	/**
	 * @param list<int>|null $accessibleProjectIds
	 * @return array<int, array<string, mixed>>
	 */
	private function getRecentTimeEntries(?array $accessibleProjectIds, ?string $entryOwnerUserId = null): array
	{
		$filters = ['limit' => 5];
		if ($accessibleProjectIds !== null) {
			$filters['project_ids'] = $accessibleProjectIds;
		}
		if ($entryOwnerUserId !== null) {
			$filters['user_id'] = $entryOwnerUserId;
		}

		return $this->timeEntryService->getTimeEntriesWithProjectInfo($filters);
	}

	/**
	 * Enrich projects with budget information
	 *
	 * @param array $projects
	 * @param string $userId
	 * @return array
	 */
	private function enrichProjectsWithBudgetInfo(array $projects, string $userId): array
	{
		$enrichedProjects = [];

		foreach ($projects as $project) {
			try {
				$budgetInfo = $this->budgetService->getProjectBudgetInfo($project, $userId);
				$enrichedProjects[] = [
					'project' => $project,
					'budgetInfo' => $budgetInfo
				];
			} catch (\Exception $e) {
				// If budget info fails, include project without budget data
				$enrichedProjects[] = [
					'project' => $project,
					'budgetInfo' => [
						'total_budget' => $project->getTotalBudget() ?? 0,
						'used_budget' => 0,
						'remaining_budget' => $project->getTotalBudget() ?? 0,
						'consumption_percentage' => 0,
						'warning_level' => 'safe',
						'used_hours' => 0
					]
				];
			}
		}

		return $enrichedProjects;
	}
}
