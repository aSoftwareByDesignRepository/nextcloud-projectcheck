<?php

/**
 * Dashboard controller for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\IURLGenerator;
use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Service\TimeEntryService;
use OCA\ProjectCheck\Service\CustomerService;
use OCA\ProjectCheck\Service\BudgetService;
use OCA\ProjectCheck\Service\CSPService;
use OCA\ProjectCheck\Traits\StatsTrait;

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
		CSPService $cspService
	) {
		parent::__construct($appName, $request);
		$this->userSession = $userSession;
		$this->projectService = $projectService;
		$this->timeEntryService = $timeEntryService;
		$this->customerService = $customerService;
		$this->budgetService = $budgetService;
		$this->urlGenerator = $urlGenerator;
		$this->setCspService($cspService);
	}

	/**
	 * Show dashboard page
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @return TemplateResponse
	 */
	public function index()
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			$response = new TemplateResponse($this->appName, 'error', [
				'message' => 'User not authenticated'
			], 'guest');
			return $this->configureCSP($response, 'guest');
		}

		$userId = $user->getUID();

		// Get comprehensive statistics
		$comprehensiveStats = $this->getComprehensiveStats($userId);

		// Get detailed yearly statistics grouped by customer and project
		$detailedYearlyStats = $this->timeEntryService->getDetailedYearlyStats();

		// Get project type statistics
		$projectTypeStats = $this->timeEntryService->getYearlyStatsByProjectType();
		$detailedProjectTypeStats = $this->timeEntryService->getDetailedYearlyStatsByProjectType();
		$productivityAnalysis = $this->timeEntryService->getProductivityAnalysis();

		// Get common stats for the sidebar
		$commonStats = $this->getCommonStats($this->projectService, $this->customerService);

		// Merge stats, with comprehensive stats taking precedence
		$stats = array_merge($commonStats, $comprehensiveStats);
		$stats['detailedYearlyStats'] = $detailedYearlyStats;
		$stats['projectTypeStats'] = $projectTypeStats;
		$stats['detailedProjectTypeStats'] = $detailedProjectTypeStats;
		$stats['productivityAnalysis'] = $productivityAnalysis;

		// Get budget alerts for active projects
		$allProjects = $this->projectService->getProjectsByUser($userId);
		$activeProjects = array_filter($allProjects, function ($project) {
			return $project->getStatus() === 'Active';
		});
		$budgetAlerts = $this->budgetService->getBudgetAlertsForProjects($activeProjects, $userId);

		$response = new TemplateResponse($this->appName, 'dashboard', [
			'stats' => $stats,
			'budgetAlerts' => $budgetAlerts,
			'userId' => $userId,
			'urlGenerator' => $this->urlGenerator,
			'dashboardUrl' => $this->urlGenerator->linkToRoute('projectcheck.dashboard.index'),
			'projectsUrl' => $this->urlGenerator->linkToRoute('projectcheck.project.index'),
			'customersUrl' => $this->urlGenerator->linkToRoute('projectcheck.customer.index'),
			'timeEntriesUrl' => $this->urlGenerator->linkToRoute('projectcheck.timeentry.index'),
			'settingsUrl' => $this->urlGenerator->linkToRoute('projectcheck.settings.index'),
		]);

		return $this->configureCSP($response);
	}

	/**
	 * Get dashboard statistics via API
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @return JSONResponse
	 */
	public function getStats()
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new JSONResponse(['error' => 'User not authenticated'], 401);
		}

		$userId = $user->getUID();

		// Get comprehensive statistics
		$comprehensiveStats = $this->getComprehensiveStats($userId);

		// Get yearly statistics
		$yearlyStats = $this->timeEntryService->getYearlyStatsForAllProjects();

		// Get detailed yearly statistics grouped by customer and project
		$detailedYearlyStats = $this->timeEntryService->getDetailedYearlyStats();

		// Get project type statistics
		$projectTypeStats = $this->timeEntryService->getYearlyStatsByProjectType();
		$detailedProjectTypeStats = $this->timeEntryService->getDetailedYearlyStatsByProjectType();
		$productivityAnalysis = $this->timeEntryService->getProductivityAnalysis();

		// Get common stats for the sidebar
		$commonStats = $this->getCommonStats($this->projectService, $this->customerService);

		// Merge stats, with comprehensive stats taking precedence
		$stats = array_merge($commonStats, $comprehensiveStats);
		$stats['yearlyStats'] = $yearlyStats;
		$stats['detailedYearlyStats'] = $detailedYearlyStats;
		$stats['projectTypeStats'] = $projectTypeStats;
		$stats['detailedProjectTypeStats'] = $detailedProjectTypeStats;
		$stats['productivityAnalysis'] = $productivityAnalysis;

		return new JSONResponse($stats);
	}

	/**
	 * Get comprehensive statistics for the dashboard
	 *
	 * @param string $userId
	 * @return array
	 */
	private function getComprehensiveStats($userId)
	{
		try {
			// Get all projects in the system
			$allProjects = $this->projectService->getAllProjects();

			// Get all time entries in the system
			$allTimeEntries = $this->timeEntryService->getAllTimeEntries();
			$recentTimeEntries = array_slice($allTimeEntries, 0, 5);

			// Add project information to time entries
			$timeEntriesWithProjectInfo = [];
			foreach ($recentTimeEntries as $entry) {
				$project = $this->projectService->getProject($entry->getProjectId());
				$timeEntriesWithProjectInfo[] = [
					'timeEntry' => $entry,
					'projectName' => $project ? $project->getName() : 'Unknown Project',
					'customerName' => $project ? $project->getCustomerName() : ''
				];
			}

			// Get all customers
			$customers = $this->customerService->getAllCustomers();

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
				$projectSpentData = $this->budgetService->getProjectBudgetInfo($project, $userId);
				$totalConsumption += $projectSpentData['used_budget'];
			}

			// Calculate total hours from time entries
			foreach ($allTimeEntries as $entry) {
				$totalHours += $entry->getHours();
			}

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
