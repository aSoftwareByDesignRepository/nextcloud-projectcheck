<?php

declare(strict_types=1);

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
use OCA\ProjectCheck\Service\IRequestTokenProvider;
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
use OCP\IL10N;
use OCA\ProjectCheck\Traits\StatsTrait;
use OCA\ProjectCheck\Db\ProjectMember;
use OCA\ProjectCheck\Db\UserAccountSnapshotMapper;
use OCP\IUserManager;

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

	/** @var IL10N */
	private $l;

	/** @var IRequestTokenProvider */
	private $requestTokenProvider;

	/** @var IUserManager */
	private $userManager;

	/** @var UserAccountSnapshotMapper */
	private $userAccountSnapshotMapper;

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
	 * @param IRequestTokenProvider $requestTokenProvider
	 * @param IL10N $l
	 * @param IUserManager $userManager
	 * @param UserAccountSnapshotMapper $userAccountSnapshotMapper
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
		CSPService $cspService,
		IRequestTokenProvider $requestTokenProvider,
		IL10N $l,
		IUserManager $userManager,
		UserAccountSnapshotMapper $userAccountSnapshotMapper
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
		$this->requestTokenProvider = $requestTokenProvider;
		$this->l = $l;
		$this->userManager = $userManager;
		$this->userAccountSnapshotMapper = $userAccountSnapshotMapper;
		$this->setCspService($cspService);
	}

	/**
	 * Roster rows for project-detail: active first; former with read-only display.
	 *
	 * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>}
	 */
	private function buildProjectTeamRosterForTemplate(int $projectId): array
	{
		$g = $this->projectService->getProjectTeamGrouped($projectId);
		return [
			$this->mapProjectMembersToRoster($projectId, $g['active'], false),
			$this->mapProjectMembersToRoster($projectId, $g['former'], true),
		];
	}

	/**
	 * @param list<ProjectMember> $members
	 * @return list<array<string, mixed>>
	 */
	private function mapProjectMembersToRoster(int $projectId, array $members, bool $isFormer): array
	{
		$roster = [];
		foreach ($members as $m) {
			$uid = $m->getUserId();
			$live = $this->userManager->get($uid);
			$display = $live && $live->getDisplayName() !== '' ? $live->getDisplayName() : null;
			if ($display === null) {
				$s = $this->userAccountSnapshotMapper->findByUserId($uid);
				$display = $s !== null ? $s->getDisplayName() : $uid;
			}
			$hours = $this->timeEntryService->getTotalHoursForProjectAndUser($projectId, $uid);
			$roster[] = [
				'id' => $m->getId(),
				'user_id' => $uid,
				'name' => $display,
				'role' => $m->getRole(),
				'hours' => round($hours, 2),
				'is_former' => $isFormer,
			];
		}
		return $roster;
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
			$response = new TemplateResponse($this->appName, 'error', ['error' => $this->l->t('User not authenticated')], 'guest');
			return $this->configureCSP($response, 'guest');
		}

		// Get user's default items per page setting
		$userId = $user->getUID();
		$defaultItemsPerPage = $this->config->getUserValue($userId, $this->appName, 'items_per_page', '20');

		$statusParam = $this->request->getParam('status', null);
		$page = max(1, (int)$this->request->getParam('page', 1));

		// Allowlisted sort columns (user-facing -> DB or computed)
		$allowedSortColumns = ['name', 'customer', 'type', 'status', 'remaining_budget', 'progress'];
		$requestedSort = $this->request->getParam('sort', 'remaining_budget');
		$sort = in_array($requestedSort, $allowedSortColumns, true) ? $requestedSort : 'remaining_budget';
		$requestedDirection = strtolower((string)$this->request->getParam('direction', 'asc'));
		$direction = ($requestedDirection === 'desc') ? 'desc' : 'asc';

		// Map user-facing sort to DB column for ProjectService
		$sortToDbColumn = [
			'name' => 'name',
			'customer' => 'customer_name',
			'type' => 'project_type',
			'status' => 'status',
		];
		$dbSortable = isset($sortToDbColumn[$sort]);
		$dbSort = $dbSortable ? $sortToDbColumn[$sort] : 'created_at';
		$dbDirection = ($direction === 'asc') ? 'ASC' : 'DESC';

		$filters = [
			'search' => $this->request->getParam('search', ''),
			'status' => $statusParam === null ? 'Active' : $statusParam,
			'priority' => $this->request->getParam('priority', ''),
			'project_type' => $this->request->getParam('project_type', ''),
			'customer_id' => $this->request->getParam('customer_id', ''),
			'limit' => $defaultItemsPerPage ? intval($defaultItemsPerPage) : 20,
			'offset' => ($page - 1) * ($defaultItemsPerPage ? intval($defaultItemsPerPage) : 20),
			'sort' => $dbSort,
			'direction' => $dbDirection,
		];

		$enrichedProjects = $this->projectService->getProjectsForListView($filters, $sort, $direction, $userId);

		$totalProjects = $this->projectService->countProjects($filters);
		$totalPages = (int)max(1, ceil($totalProjects / $filters['limit']));
		if ($page > $totalPages) {
			$page = $totalPages;
			$filters['offset'] = ($page - 1) * $filters['limit'];
			$enrichedProjects = $this->projectService->getProjectsForListView($filters, $sort, $direction, $userId);
		}

		// Get common stats for the sidebar
		$stats = $this->getCommonStats($this->projectService, $this->customerService, $this->timeEntryService, $userId);

		// Get customers for the filter dropdown
		$customers = $this->customerService->getCustomersForSelectForUser($userId);

		$response = new TemplateResponse($this->appName, 'projects', [
			'projects' => $enrichedProjects,
			'filters' => array_merge($filters, ['sort' => $sort, 'direction' => $direction]),
			'stats' => $stats,
			'customers' => $customers,
			'sort' => $sort,
			'direction' => $direction,
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
			$response = new TemplateResponse($this->appName, 'error', ['error' => $this->l->t('User not authenticated')], 'guest');
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
		$customers = $this->customerService->getCustomersForSelectForUser($userId);

		// Get pre-selected customer ID from URL parameter
		$selectedCustomerId = $this->request->getParam('customer_id', null);

		// Get common stats for the sidebar
		$stats = $this->getCommonStats($this->projectService, $this->customerService, null, $userId);

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
				return new DataResponse(['error' => $this->l->t('User not authenticated')], 401);
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
				return new DataResponse(['success' => true, 'message' => $this->l->t('Project created successfully'), 'project' => $project->getId()]);
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
			$response = new TemplateResponse($this->appName, 'error', ['error' => $this->l->t('User not authenticated')], 'guest');
			return $this->configureCSP($response, 'guest');
		}

		$project = $this->projectService->getProject($id);
		if (!$project) {
			$response = new TemplateResponse($this->appName, 'error', ['error' => $this->l->t('Project not found')], 'guest');
			return $this->configureCSP($response, 'guest');
		}
		if (!$this->projectService->canUserAccessProject($user->getUID(), $id)) {
			$response = new TemplateResponse($this->appName, 'error', ['error' => $this->l->t('Access denied')], 'guest');
			return $this->configureCSP($response, 'guest');
		}

		// Get additional project data (roster: active = current team; former = account removed, read-only)
		[ $teamMembersActive, $teamMembersFormer ] = $this->buildProjectTeamRosterForTemplate($id);
		$teamMembers = array_merge($teamMembersActive, $teamMembersFormer);

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
		$teamMembersCount = count($teamMembers) > 0 ? count($teamMembers) : 1;

		// Calculate project progress based on time entries vs available hours
		$projectProgress = 0;
		if ($project->getAvailableHours() > 0) {
			$projectProgress = min(100, ($totalHours / $project->getAvailableHours()) * 100);
		}

		// Determine warning level for budget using project's built-in method
		$warningLevel = $project->getBudgetWarningLevel($totalHours);

		$projectFiles = $this->projectFileService->listFiles($id, $user->getUID());
		$uid = $user->getUID();
		$canEdit = $this->projectService->canUserEditProject($uid, $id);
		$canChangeStatus = $this->projectService->canUserChangeProjectStatus($uid, $id);
		$canManageFiles = $canEdit;
		$canManageMembers = $this->projectService->canUserManageMembers($uid, $id) && $project->isEditableState();
		$canAddTimeEntry = $project->allowsTimeTracking() && $this->projectService->canUserAccessProject($uid, $id);

		$allowedStatusTargets = $this->projectService->getAllowedStatusTargets($project->getStatus());
		// Expose to JS as JSON
		$statusTargetsJson = json_encode($allowedStatusTargets) ?: '[]';

		// Get common stats for the sidebar
		$stats = $this->getCommonStats($this->projectService, $this->customerService, $this->timeEntryService, $uid);

		$requestToken = $this->requestTokenProvider->getEncryptedRequestToken();
		$response = new TemplateResponse($this->appName, 'project-detail', [
			'project' => $project,
			'teamMembers' => $teamMembers,
			'teamMembersActive' => $teamMembersActive,
			'teamMembersFormer' => $teamMembersFormer,
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
			'addTeamMemberUrl' => $this->urlGenerator->linkToRoute('projectcheck.project.addTeamMember', ['id' => $id]),
			'canEdit' => $canEdit,
			'canChangeStatus' => $canChangeStatus,
			'canAddTeamMember' => $canManageMembers,
			'canManageMembers' => $canManageMembers,
			'canAddTimeEntry' => $canAddTimeEntry,
			'allowedStatusTargets' => $allowedStatusTargets,
			'statusTargetsJson' => $statusTargetsJson,
			'canDelete' => $this->projectService->canUserDeleteProject($uid, $id),
			'requesttoken' => $requestToken,
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
			return new JSONResponse(['error' => $this->l->t('User not authenticated')], 401);
		}

		try {
			$project = $this->projectService->getProject($id);
			if (!$project) {
				return new JSONResponse(['error' => $this->l->t('Project not found')], 404);
			}

			if (!$this->projectService->canUserAccessProject($user->getUID(), $id)) {
				return new JSONResponse(['error' => $this->l->t('Access denied')], 403);
			}

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
			return new JSONResponse(['error' => $this->l->t('User not authenticated')], 401);
		}

		$projectId = (int) $this->request->getParam('project_id');
		$additionalHours = (float) $this->request->getParam('additional_hours');
		$additionalRate = (float) $this->request->getParam('additional_rate');

		if (!$projectId || $additionalHours <= 0 || $additionalRate <= 0) {
			return new JSONResponse(['error' => $this->l->t('Invalid parameters')], 400);
		}

		try {
			$project = $this->projectService->getProject($projectId);
			if (!$project) {
				return new JSONResponse(['error' => $this->l->t('Project not found')], 404);
			}
			if (!$this->projectService->canUserAccessProject($user->getUID(), $projectId)) {
				return new JSONResponse(['error' => $this->l->t('Access denied')], 403);
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
			$response = new TemplateResponse($this->appName, 'error', ['error' => $this->l->t('User not authenticated')], 'guest');
			return $this->configureCSP($response, 'guest');
		}

		$project = $this->projectService->getProject($id);
		if (!$project) {
			$response = new TemplateResponse($this->appName, 'error', ['error' => $this->l->t('Project not found')], 'guest');
			return $this->configureCSP($response, 'guest');
		}
		if (!$this->projectService->canUserEditProject($user->getUID(), $id)) {
			$response = new TemplateResponse($this->appName, 'error', ['error' => $this->l->t('Access denied')], 'guest');
			return $this->configureCSP($response, 'guest');
		}

		// Get customers for the dropdown
		$customers = $this->customerService->getCustomersForSelectForUser($user->getUID());

		// Get common stats for the sidebar
		$stats = $this->getCommonStats($this->projectService, $this->customerService, null, $user->getUID());

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
				return new DataResponse(['error' => $this->l->t('User not authenticated')], 401);
			}
			return new RedirectResponse($this->urlGenerator->linkToRoute('projectcheck.project.index'));
		}

		if (!$this->projectService->canUserEditProject($user->getUID(), $id)) {
			if ($this->request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
				return new DataResponse(['error' => $this->l->t('Access denied')], 403);
			}
			return new RedirectResponse($this->urlGenerator->linkToRoute('projectcheck.project.index', ['message' => 'error', 'error_text' => $this->l->t('Access denied')]));
		}

		// Handle method override for HTML forms
		$method = $this->request->getMethod();
		$postData = $this->request->getParam('_method');

		// If this is a POST request with _method=PUT, treat it as a PUT request
		if ($method === 'POST' && $postData === 'PUT') {
			// Continue with update logic
		} elseif ($method !== 'PUT') {
			// If it's not a PUT request and not a POST with _method=PUT, return error
			if ($this->request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
				return new DataResponse(['error' => $this->l->t('Method not allowed')], 405);
			}
			$url = $this->urlGenerator->linkToRoute('projectcheck.project.index', ['message' => 'error', 'error_text' => $this->l->t('Method not allowed')]);
			return new RedirectResponse($url);
		}

		try {
			$data = $this->request->getParams();

			$project = $this->projectService->updateProject($id, $data);

			// Return appropriate response based on request type
			if ($this->request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
				return new DataResponse(['success' => true, 'message' => $this->l->t('Project updated successfully'), 'project' => $project->getId()]);
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
				return new DataResponse(['error' => $this->l->t('User not authenticated')], 401);
			}
			return new RedirectResponse($this->urlGenerator->linkToRoute('projectcheck.project.index'));
		}
		if (!$this->projectService->canUserEditProject($user->getUID(), $id)) {
			if ($this->request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
				return new DataResponse(['error' => $this->l->t('Access denied')], 403);
			}
			return new RedirectResponse($this->urlGenerator->linkToRoute('projectcheck.project.index', ['message' => 'error', 'error_text' => $this->l->t('Access denied')]));
		}

		try {
			$data = $this->request->getParams();
			$project = $this->projectService->updateProject($id, $data);

			// Return appropriate response based on request type
			if ($this->request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
				return new DataResponse(['success' => true, 'message' => $this->l->t('Project updated successfully'), 'project' => $project->getId()]);
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
				return new DataResponse(['error' => $this->l->t('User not authenticated')], 401);
			}
			return new RedirectResponse($this->urlGenerator->linkToRoute('projectcheck.project.index'));
		}

		// Check if user can delete this project
		if (!$this->projectService->canUserDeleteProject($user->getUID(), $id)) {
			if ($this->request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
				return new DataResponse(['error' => $this->l->t('Access denied')], 403);
			}
			return new RedirectResponse($this->urlGenerator->linkToRoute('projectcheck.project.index', ['message' => 'error', 'error_text' => $this->l->t('Access denied')]));
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
				return new DataResponse(['error' => $this->l->t('Method not allowed')], 405);
			}
			$url = $this->urlGenerator->linkToRoute('projectcheck.project.index', ['message' => 'error', 'error_text' => $this->l->t('Method not allowed')]);
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
				return new DataResponse(['success' => true, 'message' => $this->l->t('Project deleted successfully')]);
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
				return new DataResponse(['error' => $this->l->t('User not authenticated')], 401);
			}
			return new RedirectResponse($this->urlGenerator->linkToRoute('projectcheck.project.index'));
		}
		if (!$this->projectService->canUserChangeProjectStatus($user->getUID(), $id)) {
			if ($this->request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
				return new DataResponse(['error' => $this->l->t('Access denied')], 403);
			}
			return new RedirectResponse($this->urlGenerator->linkToRoute('projectcheck.project.index', ['message' => 'error', 'error_text' => $this->l->t('Access denied')]));
		}

		try {
			$status = $this->request->getParam('status');
			if (!is_string($status) || $status === '') {
				throw new \Exception('Status is required');
			}
			$project = $this->projectService->changeProjectStatus($id, $status);

			// Return appropriate response based on request type
			if ($this->request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
				return new DataResponse(['success' => true, 'message' => $this->l->t('Project status updated successfully')]);
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
			return new DataResponse(['error' => $this->l->t('User not authenticated')], 401);
		}
		if (!$this->projectService->canUserAccessProject($user->getUID(), $id)) {
			return new DataResponse(['error' => $this->l->t('Access denied')], 403);
		}

		try {
			$g = $this->projectService->getProjectTeamGrouped($id);
			$rosterA = $this->mapProjectMembersToRoster($id, $g['active'], false);
			$rosterF = $this->mapProjectMembersToRoster($id, $g['former'], true);

			return new DataResponse([
				'success' => true,
				'teamMembers' => $rosterA,
				'teamMembersFormer' => $rosterF,
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
			return new DataResponse(['error' => $this->l->t('User not authenticated')], 401);
		}
		if (!$this->projectService->canUserManageMembers($user->getUID(), $id)) {
			return new DataResponse(['error' => $this->l->t('Access denied')], 403);
		}

		try {
			$userId = $this->request->getParam('user_id');
			$role = $this->request->getParam('role');
			$hourlyRate = $this->request->getParam('hourly_rate');

			$member = $this->projectService->addTeamMember($id, $userId, $role, $hourlyRate);

			return new DataResponse([
				'success' => true,
				'member' => $member,
				'message' => $this->l->t('Team member added successfully'),
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
			return new DataResponse(['error' => $this->l->t('User not authenticated')], 401);
		}
		if (!$this->projectService->canUserManageMembers($user->getUID(), $id)) {
			return new DataResponse(['error' => $this->l->t('Access denied')], 403);
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
				'message' => $this->l->t('Team member updated successfully'),
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
			return new DataResponse(['error' => $this->l->t('User not authenticated')], 401);
		}
		if (!$this->projectService->canUserManageMembers($user->getUID(), $id)) {
			return new DataResponse(['error' => $this->l->t('Access denied')], 403);
		}

		try {
			$this->projectService->removeTeamMember($id, $userId);

			return new DataResponse([
				'success' => true,
				'message' => $this->l->t('Team member removed successfully'),
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
			return new DataResponse(['error' => $this->l->t('User not authenticated')], 401);
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
			return new DataResponse(['error' => $this->l->t('User not authenticated')], 401);
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
			return new DataResponse(['error' => $this->l->t('User not authenticated')], 401);
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
			return new DataResponse(['error' => $this->l->t('User not authenticated')], 401);
		}

		try {
			$data = $this->request->getParams();
			$project = $this->projectService->createProject($data);

			return new DataResponse([
				'success' => true,
				'project' => $project,
				'message' => $this->l->t('Project created successfully')
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
			return new DataResponse(['error' => $this->l->t('User not authenticated')], 401);
		}
		if (!$this->projectService->canUserAccessProject($user->getUID(), $id)) {
			return new DataResponse(['error' => $this->l->t('Access denied')], 403);
		}

		try {
			$project = $this->projectService->getProject($id);
			if (!$project) {
				return new DataResponse(['error' => $this->l->t('Project not found')], 404);
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
			return new DataResponse(['error' => $this->l->t('User not authenticated')], 401);
		}
		$uid = $user->getUID();
		if (!$this->projectService->canUserAccessProject($uid, $id)) {
			return new DataResponse(['error' => $this->l->t('Access denied')], 403);
		}

		$raw = $this->request->getParams();
		$staged = $this->extractProjectApiUpdatePayload($raw);
		if ($staged === []) {
			return new DataResponse(['error' => $this->l->t('No update data')], 400);
		}

		$isStatusOnly = count($staged) === 1 && \array_key_exists('status', $staged);
		if ($isStatusOnly) {
			if (!$this->projectService->canUserChangeProjectStatus($uid, $id)) {
				return new DataResponse(['error' => $this->l->t('Access denied')], 403);
			}
			try {
				$this->projectService->changeProjectStatus($id, (string)$staged['status']);
				$project = $this->projectService->getProject($id);
				if (!$project) {
					return new DataResponse(['error' => $this->l->t('Project not found')], 404);
				}
				return new DataResponse([
					'success' => true,
					'project' => $project,
					'message' => $this->l->t('Project updated successfully'),
				]);
			} catch (\Exception $e) {
				return new DataResponse(['error' => $e->getMessage()], 400);
			}
		}

		if (!$this->projectService->canUserEditProject($uid, $id)) {
			return new DataResponse(['error' => $this->l->t('Access denied')], 403);
		}

		try {
			$project = $this->projectService->updateProject($id, $staged);

			return new DataResponse([
				'success' => true,
				'project' => $project,
				'message' => $this->l->t('Project updated successfully')
			]);
		} catch (\Exception $e) {
			return new DataResponse(['error' => $e->getMessage()], 400);
		}
	}

	/**
	 * @param array<string, mixed> $raw
	 * @return array<string, mixed>
	 */
	private function extractProjectApiUpdatePayload(array $raw): array
	{
		$internal = ['requesttoken', '_method', 'format', 'g'];
		$allowed = [
			'name', 'short_description', 'detailed_description', 'customer_id', 'hourly_rate', 'total_budget',
			'available_hours', 'category', 'priority', 'status', 'start_date', 'end_date', 'tags', 'project_type',
		];
		$staged = [];
		foreach ($raw as $k => $v) {
			if (!\is_string($k) || \in_array($k, $internal, true) || !\in_array($k, $allowed, true)) {
				continue;
			}
			if ($k === 'status') {
				if (\is_string($v) && $v !== '') {
					$staged[$k] = $v;
				}
				continue;
			}
			if ($v === null) {
				continue;
			}
			if (\is_string($v) && $v === '') {
				continue;
			}
			$staged[$k] = $v;
		}
		return $staged;
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
			return new DataResponse(['error' => $this->l->t('User not authenticated')], 401);
		}
		if (!$this->projectService->canUserDeleteProject($user->getUID(), $id)) {
			return new DataResponse(['error' => $this->l->t('Access denied')], 403);
		}

		try {
			$this->projectService->deleteProject($id);
			return new DataResponse(['success' => true, 'message' => $this->l->t('Project deleted successfully')]);
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
			return new DataResponse(['error' => $this->l->t('User not authenticated')], 401);
		}
		if (!$this->projectService->canUserDeleteProject($user->getUID(), $id)) {
			return new DataResponse(['error' => $this->l->t('Access denied')], 403);
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
			return new DataResponse(['error' => $this->l->t('User not authenticated')], 401);
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

}
