<?php

/**
 * ProjectController for the projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Controller;

use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Service\CustomerService;
use OCA\ProjectCheck\Service\TimeEntryService;
use OCA\ProjectCheck\Service\BudgetService;
use OCA\ProjectCheck\Service\DeletionService;
use OCA\ProjectCheck\Service\ActivityService;
use OCA\ProjectCheck\Service\ProjectFileService;
use OCA\ProjectCheck\Service\CSPService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\IURLGenerator;
use OCP\IConfig;
use OCA\ProjectCheck\Traits\StatsTrait;

/**
 * Class ProjectController
 *
 * @package OCA\ProjectControl\Controller
 */
class ProjectController extends Controller
{
	use CSPTrait;
	use StatsTrait;

	/** @var ProjectService */
	private $projectService;

	/** @var CustomerService */
	private $customerService;

	/** @var TimeEntryService */
	private $timeEntryService;

	/** @var BudgetService */
	private $budgetService;

	/** @var DeletionService */
	private $deletionService;

	/** @var ActivityService */
	private $activityService;

	/** @var ProjectFileService */
	private $projectFileService;

	/** @var IUserSession */
	private $userSession;

	/** @var IURLGenerator */
	private $urlGenerator;

	/** @var IConfig */
	private $config;

	/**
	 * ProjectController constructor
	 *
	 * @param string $appName
	 * @param IRequest $request
	 * @param ProjectService $projectService
	 * @param CustomerService $customerService
	 * @param TimeEntryService $timeEntryService
	 * @param BudgetService $budgetService
	 * @param DeletionService $deletionService
	 * @param ActivityService $activityService
	 * @param ProjectFileService $projectFileService
	 * @param IUserSession $userSession
	 * @param IURLGenerator $urlGenerator
	 * @param IConfig $config
	 * @param CSPService $cspService
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		ProjectService $projectService,
		CustomerService $customerService,
		TimeEntryService $timeEntryService,
		BudgetService $budgetService,
		DeletionService $deletionService,
		ActivityService $activityService,
		ProjectFileService $projectFileService,
		IUserSession $userSession,
		IURLGenerator $urlGenerator,
		IConfig $config,
		CSPService $cspService
	) {
		parent::__construct($appName, $request);
		$this->projectService = $projectService;
		$this->customerService = $customerService;
		$this->timeEntryService = $timeEntryService;
		$this->budgetService = $budgetService;
		$this->deletionService = $deletionService;
		$this->activityService = $activityService;
		$this->projectFileService = $projectFileService;
		$this->userSession = $userSession;
		$this->urlGenerator = $urlGenerator;
		$this->config = $config;
		$this->setCspService($cspService);
	}

	/**
	 * Display project list
	 *
	 * @return TemplateResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): TemplateResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			$response = new TemplateResponse($this->appName, 'error', ['error' => 'User not authenticated'], 'guest');
			return $this->configureCSP($response, 'guest');
		}

		// Get user's default items per page setting
		$userId = $user->getUID();
		$defaultItemsPerPage = $this->config->getUserValue($userId, $this->appName, 'items_per_page', '20');

		$statusParam = $this->request->getParam('status', null);
		$page = max(1, (int)$this->request->getParam('page', 1));

		$filters = [
			'search' => $this->request->getParam('search', ''),
			// Default to Active when no status is supplied; allow explicit "all" to show everything
			'status' => $statusParam === null ? 'Active' : $statusParam,
			'priority' => $this->request->getParam('priority', ''),
			'project_type' => $this->request->getParam('project_type', ''),
			'customer_id' => $this->request->getParam('customer_id', ''),
			'limit' => $defaultItemsPerPage ? intval($defaultItemsPerPage) : 20,
			'offset' => ($page - 1) * ($defaultItemsPerPage ? intval($defaultItemsPerPage) : 20),
		];

		$projects = $this->projectService->getProjects($filters);

		$totalProjects = $this->projectService->countProjects($filters);
		$totalPages = (int)max(1, ceil($totalProjects / $filters['limit']));
		if ($page > $totalPages) {
			$page = $totalPages;
			$filters['offset'] = ($page - 1) * $filters['limit'];
			$projects = $this->projectService->getProjects($filters);
		}

		// Enrich projects with budget information
		$enrichedProjects = $this->enrichProjectsWithBudgetInfo($projects, $userId);

		// Sort projects by remaining budget (ascending). Over budget (negative remaining) floats to the top.
		$computeRemaining = static function (array $item): float {
			if (isset($item['budgetInfo']['remaining_budget'])) {
				return (float)$item['budgetInfo']['remaining_budget'];
			}
			if (isset($item['project']) && method_exists($item['project'], 'getTotalBudget')) {
				$total = $item['project']->getTotalBudget();
				if ($total !== null) {
					return (float)$total;
				}
			}
			// No budget info; push to bottom
			return PHP_FLOAT_MAX;
		};

		usort($enrichedProjects, static function (array $a, array $b) use ($computeRemaining) {
			$remainingA = $computeRemaining($a);
			$remainingB = $computeRemaining($b);
			if ($remainingA === $remainingB) {
				return 0;
			}
			return ($remainingA < $remainingB) ? -1 : 1;
		});

		// Get common stats for the sidebar
		$stats = $this->getCommonStats($this->projectService, $this->customerService, $this->timeEntryService);

		// Get customers for the filter dropdown
		$customers = $this->customerService->getCustomersForSelect();

		$response = new TemplateResponse($this->appName, 'projects', [
			'projects' => $enrichedProjects,
			'filters' => $filters,
			'stats' => $stats,
			'customers' => $customers,
			'pagination' => [
				'page' => $page,
				'perPage' => $filters['limit'],
				'totalEntries' => $totalProjects,
				'totalPages' => $totalPages,
			],
			'createUrl' => $this->urlGenerator->linkToRoute('projectcheck.project.create'),
			'projectsUrl' => $this->urlGenerator->linkToRoute('projectcheck.project.index'),
			'showUrl' => $this->urlGenerator->linkToRoute('projectcheck.project.show', ['id' => 'PROJECT_ID']),
			'editUrl' => $this->urlGenerator->linkToRoute('projectcheck.project.edit', ['id' => 'PROJECT_ID']),
			'customersUrl' => $this->urlGenerator->linkToRoute('projectcheck.customer.index'),
			'timeEntriesUrl' => $this->urlGenerator->linkToRoute('projectcheck.timeentry.index'),
			'dashboardUrl' => $this->urlGenerator->linkToRoute('projectcheck.dashboard.index'),
		]);

		return $this->configureCSP($response);
	}

	/**
	 * Display project creation form
	 *
	 * @return TemplateResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function create(): TemplateResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			$response = new TemplateResponse($this->appName, 'error', ['error' => 'User not authenticated'], 'guest');
			return $this->configureCSP($response, 'guest');
		}

		$userId = $user->getUID();

		// Get user's default settings for pre-filling the form
		$defaultSettings = [
			'hourly_rate' => $this->config->getUserValue($userId, $this->appName, 'default_hourly_rate', '50.00'),
			'status' => $this->config->getUserValue($userId, $this->appName, 'default_project_status', 'Active'),
			'priority' => $this->config->getUserValue($userId, $this->appName, 'default_project_priority', 'Medium')
		];

		// Get customers for the dropdown
		$customers = $this->customerService->getCustomersForSelect();

		// Get pre-selected customer ID from URL parameter
		$selectedCustomerId = $this->request->getParam('customer_id', null);

		// Get common stats for the sidebar
		$stats = $this->getCommonStats($this->projectService, $this->customerService);

		$response = new TemplateResponse($this->appName, 'project-form', [
			'project' => null,
			'mode' => 'create',
			'customers' => $customers,
			'selectedCustomerId' => $selectedCustomerId,
			'defaultSettings' => $defaultSettings,
			'stats' => $stats,
			'indexUrl' => $this->urlGenerator->linkToRoute('projectcheck.project.index'),
			'formAction' => $this->urlGenerator->linkToRoute('projectcheck.project.store'),
			'urlGenerator' => $this->urlGenerator,
		]);

		return $this->configureCSP($response);
	}

	/**
	 * Store new project
	 *
	 * @return RedirectResponse
	 */
	#[NoAdminRequired]
	public function store(): RedirectResponse|DataResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			if ($this->request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
				return new DataResponse(['error' => 'User not authenticated'], 401);
			}
			return new RedirectResponse($this->urlGenerator->linkToRoute('projectcheck.project.index'));
		}

		try {
			$data = $this->request->getParams();
			$project = $this->projectService->createProject($data);

			$uploads = $this->request->getUploadedFile('project_files');
			if ($uploads) {
				$this->projectFileService->addFilesFromUpload($project->getId(), $uploads, $user->getUID());
			}

			// Return appropriate response based on request type
			if ($this->request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
				return new DataResponse(['success' => true, 'message' => 'Project created successfully', 'project' => $project->getId()]);
			}

			// Redirect to projects list with success message
			$url = $this->urlGenerator->linkToRoute('projectcheck.project.index', ['message' => 'success', 'project_name' => $project->getName()]);
			return new RedirectResponse($url);
		} catch (\Exception $e) {
			// Return appropriate response based on request type
			if ($this->request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
				return new DataResponse(['error' => $e->getMessage()], 400);
			}

			// Redirect to projects list with error message
			$url = $this->urlGenerator->linkToRoute('projectcheck.project.index', ['message' => 'error', 'error_text' => $e->getMessage()]);
			return new RedirectResponse($url);
		}
	}

	/**
	 * Display project details
	 *
	 * @param int $id
	 * @return TemplateResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function show(int $id): TemplateResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			$response = new TemplateResponse($this->appName, 'error', ['error' => 'User not authenticated'], 'guest');
			return $this->configureCSP($response, 'guest');
		}

		$project = $this->projectService->getProject($id);
		if (!$project) {
			$response = new TemplateResponse($this->appName, 'error', ['error' => 'Project not found'], 'guest');
			return $this->configureCSP($response, 'guest');
		}

		// Get additional project data
		$teamMembers = $this->projectService->getProjectTeam($id);

		// Get real time entry data
		$timeEntries = $this->timeEntryService->getTimeEntriesByProject($id);
		$totalHours = $this->timeEntryService->getTotalHoursForProject($id);
		$budgetConsumption = $this->timeEntryService->getTotalCostForProject($id);
		$timeEntriesCount = count($timeEntries);

		// Get comprehensive budget information
		$budgetInfo = $this->budgetService->getProjectBudgetInfo($project, $user->getUID());

		// Get yearly statistics for the project
		$yearlyStats = $this->timeEntryService->getYearlyStatsForProject($id);

		// Get recent time entries (last 5)
		$recentTimeEntries = array_slice($timeEntries, 0, 5);

		// Get customer name from the project data
		$customerName = $project->getCustomerName() ?: 'Customer #' . $project->getCustomerId();
		$createdBy = $project->getCreatedBy();
		$teamMembersCount = count($teamMembers) ?: 1;

		// Calculate project progress based on time entries vs available hours
		$projectProgress = 0;
		if ($project->getAvailableHours() > 0) {
			$projectProgress = min(100, ($totalHours / $project->getAvailableHours()) * 100);
		}

		// Determine warning level for budget using project's built-in method
		$warningLevel = $project->getBudgetWarningLevel($totalHours);

		$projectFiles = $this->projectFileService->listFiles($id, $user->getUID());
		$canManageFiles = $this->projectService->canUserEditProject($user->getUID(), $id);

		// Get common stats for the sidebar
		$stats = $this->getCommonStats($this->projectService, $this->customerService);

		$response = new TemplateResponse($this->appName, 'project-detail', [
			'project' => $project,
			'teamMembers' => $teamMembers,
			'timeEntries' => $recentTimeEntries,
			'customerName' => $customerName,
			'createdBy' => $createdBy,
			'totalHours' => $totalHours,
			'budgetConsumption' => $budgetConsumption,
			'timeEntriesCount' => $timeEntriesCount,
			'teamMembersCount' => $teamMembersCount,
			'projectProgress' => round($projectProgress, 1),
			'warningLevel' => $warningLevel,
			'budgetInfo' => $budgetInfo,
			'yearlyStats' => $yearlyStats,
			'stats' => $stats,
			'projectFiles' => $projectFiles,
			'canManageFiles' => $canManageFiles,
			'projectId' => $id,
			'urlGenerator' => $this->urlGenerator,
			'canEdit' => true, // Open for now - can be restricted later if needed
			'canChangeStatus' => true, // Open for now - can be restricted later if needed  
			'canAddTeamMember' => true, // Open for now - can be restricted later if needed
			'canDelete' => $this->projectService->canUserDeleteProject($user->getUID(), $id), // Keep delete restrictions
		]);

		return $this->configureCSP($response);
	}

	/**
	 * Get budget information for a project
	 *
	 * @param int $id Project ID
	 * @return JSONResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getBudgetInfo(int $id): JSONResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new JSONResponse(['error' => 'User not authenticated'], 401);
		}

		try {
			$project = $this->projectService->getProject($id);
			if (!$project) {
				return new JSONResponse(['error' => 'Project not found'], 404);
			}

			// No access restrictions for viewing budget info

			$budgetInfo = $this->budgetService->getProjectBudgetInfo($project, $user->getUID());

			return new JSONResponse([
				'success' => true,
				'budget' => [
					'total_budget' => $budgetInfo['total_budget'],
					'used_budget' => $budgetInfo['used_budget'],
					'remaining_budget' => $budgetInfo['remaining_budget'],
					'consumption_percentage' => $budgetInfo['consumption_percentage'],
					'warning_level' => $budgetInfo['warning_level'],
					'total_hours' => $budgetInfo['used_hours'],
					'project_name' => $project->getName(),
					'currency' => '€'
				]
			]);
		} catch (\Exception $e) {
			return new JSONResponse([
				'success' => false,
				'error' => $e->getMessage()
			], 500);
		}
	}

	/**
	 * Check budget impact for a time entry
	 *
	 * @param int $project_id
	 * @param float $additional_hours
	 * @param float $additional_rate
	 * @return JSONResponse
	 */
	#[NoAdminRequired]
	public function checkBudgetImpact(): JSONResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new JSONResponse(['error' => 'User not authenticated'], 401);
		}

		$projectId = (int) $this->request->getParam('project_id');
		$additionalHours = (float) $this->request->getParam('additional_hours');
		$additionalRate = (float) $this->request->getParam('additional_rate');

		if (!$projectId || $additionalHours <= 0 || $additionalRate <= 0) {
			return new JSONResponse(['error' => 'Invalid parameters'], 400);
		}

		try {
			$project = $this->projectService->getProject($projectId);
			if (!$project) {
				return new JSONResponse(['error' => 'Project not found'], 404);
			}

			$impact = $this->budgetService->checkTimeEntryBudgetImpact($project, $additionalHours, $additionalRate);

			return new JSONResponse([
				'success' => true,
				'impact' => $impact
			]);
		} catch (\Exception $e) {
			return new JSONResponse([
				'success' => false,
				'error' => $e->getMessage()
			], 500);
		}
	}

	/**
	 * Display project edit form
	 *
	 * @param int $id
	 * @return TemplateResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function edit(int $id): TemplateResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			$response = new TemplateResponse($this->appName, 'error', ['error' => 'User not authenticated'], 'guest');
			return $this->configureCSP($response, 'guest');
		}

		$project = $this->projectService->getProject($id);
		if (!$project) {
			$response = new TemplateResponse($this->appName, 'error', ['error' => 'Project not found'], 'guest');
			return $this->configureCSP($response, 'guest');
		}

		// No edit restrictions for now - open access

		// Get customers for the dropdown
		$customers = $this->customerService->getCustomersForSelect();

		// Get common stats for the sidebar
		$stats = $this->getCommonStats($this->projectService, $this->customerService);

		$response = new TemplateResponse($this->appName, 'project-form', [
			'project' => $project,
			'mode' => 'edit',
			'customers' => $customers,
			'stats' => $stats,
			'indexUrl' => $this->urlGenerator->linkToRoute('projectcheck.project.index'),
			'formAction' => $this->urlGenerator->linkToRoute('projectcheck.project.updatePost', ['id' => $id]),
			'urlGenerator' => $this->urlGenerator,
		]);

		return $this->configureCSP($response);
	}

	/**
	 * Update project
	 *
	 * @param int $id
	 * @return RedirectResponse
	 */
	#[NoAdminRequired]
	public function update(int $id): RedirectResponse|DataResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			if ($this->request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
				return new DataResponse(['error' => 'User not authenticated'], 401);
			}
			return new RedirectResponse($this->urlGenerator->linkToRoute('projectcheck.project.index'));
		}

		// No edit restrictions for now - open access

		// Handle method override for HTML forms
		$method = $this->request->getMethod();
		$postData = $this->request->getParam('_method');

		// If this is a POST request with _method=PUT, treat it as a PUT request
		if ($method === 'POST' && $postData === 'PUT') {
			// Continue with update logic
		} elseif ($method !== 'PUT') {
			// If it's not a PUT request and not a POST with _method=PUT, return error
			if ($this->request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
				return new DataResponse(['error' => 'Method not allowed'], 405);
			}
			$url = $this->urlGenerator->linkToRoute('projectcheck.project.index', ['message' => 'error', 'error_text' => 'Method not allowed']);
			return new RedirectResponse($url);
		}

		try {
			$data = $this->request->getParams();

			$project = $this->projectService->updateProject($id, $data);

			// Return appropriate response based on request type
			if ($this->request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
				return new DataResponse(['success' => true, 'message' => 'Project updated successfully', 'project' => $project->getId()]);
			}

			// Redirect to projects list with success message
			$url = $this->urlGenerator->linkToRoute('projectcheck.project.index', ['message' => 'success', 'project_name' => $project->getName()]);
			return new RedirectResponse($url);
		} catch (\Exception $e) {
			// Return appropriate response based on request type
			if ($this->request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
				return new DataResponse(['error' => $e->getMessage()], 400);
			}

			// Redirect to projects list with error message
			$url = $this->urlGenerator->linkToRoute('projectcheck.project.index', ['message' => 'error', 'error_text' => $e->getMessage()]);
			return new RedirectResponse($url);
		}
	}

	/**
	 * Update project via POST (for form submissions)
	 *
	 * @param int $id
	 * @return RedirectResponse|DataResponse
	 */
	#[NoAdminRequired]
	public function updatePost(int $id): RedirectResponse|DataResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			if ($this->request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
				return new DataResponse(['error' => 'User not authenticated'], 401);
			}
			return new RedirectResponse($this->urlGenerator->linkToRoute('projectcheck.project.index'));
		}

		try {
			$data = $this->request->getParams();
			$project = $this->projectService->updateProject($id, $data);

			// Return appropriate response based on request type
			if ($this->request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
				return new DataResponse(['success' => true, 'message' => 'Project updated successfully', 'project' => $project->getId()]);
			}

			// Redirect to projects list with success message
			$url = $this->urlGenerator->linkToRoute('projectcheck.project.index', ['message' => 'success', 'project_name' => $project->getName()]);
			return new RedirectResponse($url);
		} catch (\Exception $e) {
			// Return appropriate response based on request type
			if ($this->request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
				return new DataResponse(['error' => $e->getMessage()], 400);
			}

			// Redirect to projects list with error message
			$url = $this->urlGenerator->linkToRoute('projectcheck.project.index', ['message' => 'error', 'error_text' => $e->getMessage()]);
			return new RedirectResponse($url);
		}
	}

	/**
	 * Delete project
	 *
	 * @param int $id
	 * @return RedirectResponse|DataResponse
	 */
	#[NoAdminRequired]
	public function delete(int $id): RedirectResponse|DataResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			if ($this->request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
				return new DataResponse(['error' => 'User not authenticated'], 401);
			}
			return new RedirectResponse($this->urlGenerator->linkToRoute('projectcheck.project.index'));
		}

		// Check if user can delete this project
		if (!$this->projectService->canUserDeleteProject($user->getUID(), $id)) {
			if ($this->request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
				return new DataResponse(['error' => 'Access denied'], 403);
			}
			return new RedirectResponse($this->urlGenerator->linkToRoute('projectcheck.project.index', ['message' => 'error', 'error_text' => 'Access denied']));
		}

		// Handle method override for HTML forms
		$method = $this->request->getMethod();
		$postData = $this->request->getParam('_method');

		// If this is a POST request with _method=DELETE, treat it as a DELETE request
		if ($method === 'POST' && $postData === 'DELETE') {
			// Continue with delete logic
		} elseif ($method !== 'DELETE') {
			// If it's not a DELETE request and not a POST with _method=DELETE, redirect with error
			if ($this->request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
				return new DataResponse(['error' => 'Method not allowed'], 405);
			}
			$url = $this->urlGenerator->linkToRoute('projectcheck.project.index', ['message' => 'error', 'error_text' => 'Method not allowed']);
			return new RedirectResponse($url);
		}

		try {
			// Get project info before deletion for activity logging
			$project = $this->projectService->getProject($id);
			$impact = $this->deletionService->getProjectDeletionImpact($id);

			$this->projectService->deleteProject($id);

			// Log activity
			if ($project) {
				$this->activityService->logProjectDeleted($user->getUID(), $project, $impact);
			}

			// Return appropriate response based on request type
			if ($this->request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
				return new DataResponse(['success' => true, 'message' => 'Project deleted successfully']);
			}

			// Redirect to projects list with success message
			$url = $this->urlGenerator->linkToRoute('projectcheck.project.index', ['message' => 'success', 'deleted' => 'true']);
			return new RedirectResponse($url);
		} catch (\Exception $e) {
			// Return appropriate response based on request type
			if ($this->request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
				return new DataResponse(['error' => $e->getMessage()], 400);
			}

			// Redirect to projects list with error message
			$url = $this->urlGenerator->linkToRoute('projectcheck.project.index', ['message' => 'error', 'error_text' => $e->getMessage()]);
			return new RedirectResponse($url);
		}
	}

	/**
	 * Change project status
	 *
	 * @param int $id
	 * @return RedirectResponse|DataResponse
	 */
	#[NoAdminRequired]
	public function changeStatus(int $id): RedirectResponse|DataResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			if ($this->request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
				return new DataResponse(['error' => 'User not authenticated'], 401);
			}
			return new RedirectResponse($this->urlGenerator->linkToRoute('projectcheck.project.index'));
		}

		try {
			$status = $this->request->getParam('status');
			$project = $this->projectService->updateProject($id, ['status' => $status]);

			// Return appropriate response based on request type
			if ($this->request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
				return new DataResponse(['success' => true, 'message' => 'Project status updated successfully']);
			}

			// Redirect to projects list with success message
			$url = $this->urlGenerator->linkToRoute('projectcheck.project.index', ['message' => 'success', 'status_updated' => 'true']);
			return new RedirectResponse($url);
		} catch (\Exception $e) {
			// Return appropriate response based on request type
			if ($this->request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
				return new DataResponse(['error' => $e->getMessage()], 400);
			}

			// Redirect to projects list with error message
			$url = $this->urlGenerator->linkToRoute('projectcheck.project.index', ['message' => 'error', 'error_text' => $e->getMessage()]);
			return new RedirectResponse($url);
		}
	}

	/**
	 * Get project team members
	 *
	 * @param int $id
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	public function getTeamMembers(int $id): DataResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new DataResponse(['error' => 'User not authenticated'], 401);
		}

		try {
			$teamMembers = $this->projectService->getProjectTeam($id);

			return new DataResponse([
				'success' => true,
				'teamMembers' => $teamMembers,
			]);
		} catch (\Exception $e) {
			// Removed logger call
			return new DataResponse(['error' => $e->getMessage()], 400);
		}
	}

	/**
	 * Add team member to project
	 *
	 * @param int $id
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	public function addTeamMember(int $id): DataResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new DataResponse(['error' => 'User not authenticated'], 401);
		}

		try {
			$userId = $this->request->getParam('user_id');
			$role = $this->request->getParam('role');
			$hourlyRate = $this->request->getParam('hourly_rate');

			$member = $this->projectService->addTeamMember($id, $userId, $role, $hourlyRate);

			return new DataResponse([
				'success' => true,
				'member' => $member,
				'message' => 'Team member added successfully',
			]);
		} catch (\Exception $e) {
			// Removed logger call
			return new DataResponse(['error' => $e->getMessage()], 400);
		}
	}

	/**
	 * Update team member
	 *
	 * @param int $id
	 * @param string $userId
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	public function updateTeamMember(int $id, string $userId): DataResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new DataResponse(['error' => 'User not authenticated'], 401);
		}

		try {
			$role = $this->request->getParam('role');
			$hourlyRate = $this->request->getParam('hourly_rate');

			// Remove existing member and add with new data
			$this->projectService->removeTeamMember($id, $userId);
			$member = $this->projectService->addTeamMember($id, $userId, $role, $hourlyRate);

			return new DataResponse([
				'success' => true,
				'member' => $member,
				'message' => 'Team member updated successfully',
			]);
		} catch (\Exception $e) {
			// Removed logger call
			return new DataResponse(['error' => $e->getMessage()], 400);
		}
	}

	/**
	 * Remove team member from project
	 *
	 * @param int $id
	 * @param string $userId
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	public function removeTeamMember(int $id, string $userId): DataResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new DataResponse(['error' => 'User not authenticated'], 401);
		}

		try {
			$this->projectService->removeTeamMember($id, $userId);

			return new DataResponse([
				'success' => true,
				'message' => 'Team member removed successfully',
			]);
		} catch (\Exception $e) {
			// Removed logger call
			return new DataResponse(['error' => $e->getMessage()], 400);
		}
	}

	/**
	 * Search projects
	 *
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	public function search(): DataResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new DataResponse(['error' => 'User not authenticated'], 401);
		}

		try {
			$query = $this->request->getParam('q', '');
			$filters = ['search' => $query];
			$projects = $this->projectService->getProjects($filters);

			return new DataResponse([
				'success' => true,
				'projects' => $projects,
			]);
		} catch (\Exception $e) {
			// Removed logger call
			return new DataResponse(['error' => $e->getMessage()], 400);
		}
	}

	/**
	 * Filter projects
	 *
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	public function filter(): DataResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new DataResponse(['error' => 'User not authenticated'], 401);
		}

		try {
			$filters = $this->request->getParams();
			$projects = $this->projectService->getProjects($filters);

			return new DataResponse([
				'success' => true,
				'projects' => $projects,
			]);
		} catch (\Exception $e) {
			// Removed logger call
			return new DataResponse(['error' => $e->getMessage()], 400);
		}
	}

	/**
	 * API endpoint for project list
	 *
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function apiIndex(): DataResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new DataResponse(['error' => 'User not authenticated'], 401);
		}

		try {
			$filters = $this->request->getParams();
			$projects = $this->projectService->getProjects($filters);

			return new DataResponse([
				'success' => true,
				'projects' => $projects,
			]);
		} catch (\Exception $e) {
			// Removed logger call
			return new DataResponse(['error' => $e->getMessage()], 400);
		}
	}

	/**
	 * API endpoint for creating project
	 *
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	public function apiStore(): DataResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new DataResponse(['error' => 'User not authenticated'], 401);
		}

		try {
			$data = $this->request->getParams();
			$project = $this->projectService->createProject($data);

			return new DataResponse([
				'success' => true,
				'project' => $project,
				'message' => 'Project created successfully'
			]);
		} catch (\Exception $e) {
			return new DataResponse(['error' => $e->getMessage()], 400);
		}
	}

	/**
	 * API endpoint for getting project
	 *
	 * @param int $id
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function apiShow(int $id): DataResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new DataResponse(['error' => 'User not authenticated'], 401);
		}

		try {
			$project = $this->projectService->getProject($id);
			if (!$project) {
				return new DataResponse(['error' => 'Project not found'], 404);
			}

			$teamMembers = $this->projectService->getProjectTeam($id);

			return new DataResponse([
				'success' => true,
				'project' => $project,
				'teamMembers' => $teamMembers,
			]);
		} catch (\Exception $e) {
			// Removed logger call
			return new DataResponse(['error' => $e->getMessage()], 400);
		}
	}

	/**
	 * API endpoint for updating project
	 *
	 * @param int $id
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	public function apiUpdate(int $id): DataResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new DataResponse(['error' => 'User not authenticated'], 401);
		}

		try {
			$data = $this->request->getParams();
			$project = $this->projectService->updateProject($id, $data);

			return new DataResponse([
				'success' => true,
				'project' => $project,
				'message' => 'Project updated successfully'
			]);
		} catch (\Exception $e) {
			return new DataResponse(['error' => $e->getMessage()], 400);
		}
	}

	/**
	 * API endpoint for deleting project
	 *
	 * @param int $id
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	public function apiDelete(int $id): DataResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new DataResponse(['error' => 'User not authenticated'], 401);
		}

		try {
			$this->projectService->deleteProject($id);
			return new DataResponse(['success' => true, 'message' => 'Project deleted successfully']);
		} catch (\Exception $e) {
			return new DataResponse(['error' => $e->getMessage()], 400);
		}
	}

	/**
	 * Get deletion impact for a project
	 *
	 * @param int $id
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getDeletionImpact(int $id): DataResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new DataResponse(['error' => 'User not authenticated'], 401);
		}

		try {
			$impact = $this->deletionService->getProjectDeletionImpact($id);
			return new DataResponse(['success' => true, 'impact' => $impact]);
		} catch (\Exception $e) {
			return new DataResponse(['success' => false, 'error' => $e->getMessage()], 400);
		}
	}

	/**
	 * API endpoint for getting projects by customer
	 *
	 * @param int $customerId
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function apiByCustomer(int $customerId): DataResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new DataResponse(['error' => 'User not authenticated'], 401);
		}

		try {
			$projects = $this->projectService->getProjectsByCustomer($customerId);

			// Convert projects to array format expected by JavaScript
			$projectData = [];
			foreach ($projects as $project) {
				// Get budget information for this project
				$budgetInfo = $this->budgetService->getProjectBudgetInfo($project, $user->getUID());

				$projectData[] = [
					'id' => $project->getId(),
					'name' => $project->getName(),
					'status' => $project->getStatus(),
					'total_budget' => $project->getTotalBudget(),
					'used_budget' => $budgetInfo['used_budget'],
					'remaining_budget' => $budgetInfo['remaining_budget'],
					'consumption_percentage' => $budgetInfo['consumption_percentage'],
					'used_hours' => $budgetInfo['used_hours'],
					'warning_level' => $budgetInfo['warning_level'],
					'start_date' => $project->getStartDate() ? $project->getStartDate()->format('d.m.Y') : null,
					'end_date' => $project->getEndDate() ? $project->getEndDate()->format('d.m.Y') : null,
					'priority' => $project->getPriority(),
					'hourly_rate' => $project->getHourlyRate(),
					'available_hours' => $project->getAvailableHours()
				];
			}

			return new DataResponse([
				'success' => true,
				'projects' => $projectData,
			]);
		} catch (\Exception $e) {
			return new DataResponse(['error' => $e->getMessage()], 400);
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
