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
use OCA\ProjectCheck\Service\HourlyRateService;
use OCA\ProjectCheck\Service\CustomerService;
use OCA\ProjectCheck\Service\TimeEntryService;
use OCA\ProjectCheck\Exception\RateResolutionException;
use OCA\ProjectCheck\Util\CostRateMode;
use OCA\ProjectCheck\Util\ProjectCapacity;
use OCA\ProjectCheck\Service\BudgetService;
use OCA\ProjectCheck\Service\DeletionService;
use OCA\ProjectCheck\Service\ActivityService;
use OCA\ProjectCheck\Service\ProjectFileService;
use OCA\ProjectCheck\Service\ProjectMemberHourlyRateService;
use OCA\ProjectCheck\Service\CSPService;
use OCA\ProjectCheck\Service\IRequestTokenProvider;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
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

	/** @var HourlyRateService */
	private $hourlyRateService;

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

	/** @var ProjectMemberHourlyRateService */
	private $projectMemberHourlyRateService;

	/**
	 * ProjectController constructor
	 *
	 * @param string $appName
	 * @param IRequest $request
	 * @param ProjectService $projectService
	 * @param HourlyRateService $hourlyRateService
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
	 * @param ProjectMemberHourlyRateService $projectMemberHourlyRateService
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		ProjectService $projectService,
		HourlyRateService $hourlyRateService,
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
		UserAccountSnapshotMapper $userAccountSnapshotMapper,
		ProjectMemberHourlyRateService $projectMemberHourlyRateService
	) {
		parent::__construct($appName, $request);
		$this->projectService = $projectService;
		$this->hourlyRateService = $hourlyRateService;
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
		$this->projectMemberHourlyRateService = $projectMemberHourlyRateService;
		$this->setCspService($cspService);
	}

	/**
	 * Roster rows for project-detail: active first; former with read-only display.
	 *
	 * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>}
	 */
	private function buildProjectTeamRosterForTemplate(int $projectId, \OCA\ProjectCheck\Db\Project $project): array
	{
		$g = $this->projectService->getProjectTeamGrouped($projectId);
		$mode = $project->getCostRateMode();
		return [
			$this->mapProjectMembersToRoster($projectId, $g['active'], false, $mode),
			$this->mapProjectMembersToRoster($projectId, $g['former'], true, $mode),
		];
	}

	/**
	 * @param list<ProjectMember> $members
	 * @return list<array<string, mixed>>
	 */
	private function mapProjectMembersToRoster(int $projectId, array $members, bool $isFormer, string $costRateMode): array
	{
		$roster = [];
		$today = gmdate('Y-m-d');
		foreach ($members as $m) {
			$uid = $m->getUserId();
			$live = $this->userManager->get($uid);
			$display = $live && $live->getDisplayName() !== '' ? $live->getDisplayName() : null;
			if ($display === null) {
				$s = $this->userAccountSnapshotMapper->findByUserId($uid);
				$display = $s !== null ? $s->getDisplayName() : $uid;
			}
			$hours = $this->timeEntryService->getTotalHoursForProjectAndUser($projectId, $uid);
			$row = [
				'id' => $m->getId(),
				'user_id' => $uid,
				'name' => $display,
				'role' => $m->getRole(),
				'hours' => round($hours, 2),
				'is_former' => $isFormer,
				'profile_url' => $live ? $this->urlGenerator->linkToRoute('projectcheck.employee.show', ['userId' => $uid]) : null,
			];
			if (!$isFormer && $costRateMode === CostRateMode::PROJECT_MEMBER) {
				try {
					$row['current_rate'] = $this->projectMemberHourlyRateService->resolveRateForProjectMember($projectId, $uid, $today);
				} catch (\Throwable $e) {
					$row['current_rate'] = null;
				}
				$history = [];
				foreach ($this->projectMemberHourlyRateService->listRatesForMember($projectId, $uid) as $rateRow) {
					$ef = $rateRow->getEffectiveFrom();
					$history[] = [
						'rate' => $rateRow->getHourlyRate(),
						'effective_from' => $ef ? $ef->format('Y-m-d') : '',
					];
				}
				usort($history, static fn (array $a, array $b): int => strcmp($b['effective_from'], $a['effective_from']));
				$row['rate_history'] = array_slice($history, 0, 5);
				$row['update_rate_url'] = $this->urlGenerator->linkToRoute('projectcheck.project.updateTeamMember', [
					'id' => $projectId,
					'userId' => $uid,
				]);
			}
			$roster[] = $row;
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
		$appItemsPerPage = $this->config->getAppValue($this->appName, 'items_per_page', '20');
		$defaultItemsPerPage = $this->config->getUserValue($userId, $this->appName, 'items_per_page', $appItemsPerPage);

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

		$totalProjects = $this->projectService->countProjectsForUser($filters, $userId);
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
			'canCreateProject' => $this->projectService->canUserCreateProject($userId),
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
		if (!$this->projectService->canUserCreateProject($userId)) {
			$response = new TemplateResponse($this->appName, 'error', ['error' => $this->l->t('Access denied')], 'main');
			return $this->configureCSP($response, 'main');
		}

		// Get user's default settings for pre-filling the form
		$defaultSettings = [
			'hourly_rate' => $this->config->getUserValue(
				$userId,
				$this->appName,
				'default_hourly_rate',
				$this->config->getAppValue($this->appName, 'default_hourly_rate', '50.00')
			),
			'status' => $this->config->getUserValue(
				$userId,
				$this->appName,
				'default_project_status',
				$this->config->getAppValue($this->appName, 'default_project_status', 'Active')
			),
			'priority' => $this->config->getUserValue(
				$userId,
				$this->appName,
				'default_project_priority',
				$this->config->getAppValue($this->appName, 'default_project_priority', 'Medium')
			)
		];

		// Get customers for the dropdown
		$customers = $this->customerService->getCustomersForSelectForUser($userId);

		// Get pre-selected customer ID from URL parameter
		$selectedCustomerId = $this->request->getParam('customer_id', null);

		// Get common stats for the sidebar
		$stats = $this->getCommonStats($this->projectService, $this->customerService, null, $userId);

		$currency = strtoupper(trim((string) $this->config->getAppValue($this->appName, 'currency', 'EUR')));
		if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
			$currency = 'EUR';
		}

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
			'orgCurrency' => $currency,
			'costRateModeLocked' => false,
			'employeesIndexUrl' => $this->urlGenerator->linkToRoute('projectcheck.employee.index'),
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
				return new DataResponse($this->errorPayload($this->l->t('User not authenticated')), 401);
			}
			return new RedirectResponse($this->urlGenerator->linkToRoute('projectcheck.project.index'));
		}

		$userId = $user->getUID();
		if (!$this->projectService->canUserCreateProject($userId)) {
			if ($this->request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
				return new DataResponse($this->errorPayload($this->l->t('Access denied')), 403);
			}
			return new RedirectResponse($this->urlGenerator->linkToRoute('projectcheck.project.index', ['message' => 'error', 'error_text' => $this->l->t('Access denied')]));
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

			$url = $this->urlGenerator->linkToRoute('projectcheck.project.show', [
				'id' => $project->getId(),
				'message' => 'created',
			]);
			return new RedirectResponse($url);
		} catch (\Exception $e) {
			$safeError = $this->toSafeProjectErrorMessage($e, $this->l->t('Could not create project. Please check your input.'));
			// Return appropriate response based on request type
			if ($this->request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
				return new DataResponse($this->errorPayload($safeError), 400);
			}

			// Redirect to projects list with error message
			$url = $this->urlGenerator->linkToRoute('projectcheck.project.index', ['message' => 'error', 'error_text' => $safeError]);
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
		[ $teamMembersActive, $teamMembersFormer ] = $this->buildProjectTeamRosterForTemplate($id, $project);
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

		// Calculate project progress from authoritative budget info
		$projectProgress = ProjectCapacity::progressPercent($budgetInfo, (float) $totalHours);

		$warningLevel = (string) ($budgetInfo['warning_level'] ?? 'none');

		$projectFiles = $this->projectFileService->listFiles($id, $user->getUID());
		$uid = $user->getUID();
		$canEdit = $this->projectService->canUserEditProject($uid, $id);
		$canChangeStatus = $this->projectService->canUserChangeProjectStatus($uid, $id);
		$canManageFiles = $canEdit;
		$canManageMembers = $this->projectService->canUserManageMembers($uid, $id) && $project->isEditableState();
		$canAddTimeEntry = $project->allowsTimeTracking() && $this->projectService->canUserAccessProject($uid, $id);
		$canViewMemberTimeEntries = $this->projectService->canUserAccessProject($uid, $id);
		$canViewMemberProfiles = $this->projectService->canUserAccessProject($uid, $id);

		$allowedStatusTargets = $this->projectService->getAllowedStatusTargets($project->getStatus());
		// Expose to JS as JSON
		$statusTargetsJson = json_encode($allowedStatusTargets) ?: '[]';

		// Get common stats for the sidebar
		$stats = $this->getCommonStats($this->projectService, $this->customerService, $this->timeEntryService, $uid);

		$requestToken = $this->requestTokenProvider->getEncryptedRequestToken();
		$pricingModeLabel = $this->pricingModeLabel($project->getCostRateMode());
		$showCreatedBanner = (string) $this->request->getParam('message', '') === 'created';
		$response = new TemplateResponse($this->appName, 'project-detail', [
			'project' => $project,
			'pricingModeLabel' => $pricingModeLabel,
			'costRateMode' => $project->getCostRateMode(),
			'hideAddAllTeam' => $project->getCostRateMode() === CostRateMode::PROJECT_MEMBER,
			'showCreatedBanner' => $showCreatedBanner,
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
			'canViewMemberTimeEntries' => $canViewMemberTimeEntries,
			'canViewMemberProfiles' => $canViewMemberProfiles,
			'allowedStatusTargets' => $allowedStatusTargets,
			'statusTargetsJson' => $statusTargetsJson,
			'canDelete' => $this->projectService->canUserDeleteProject($uid, $id),
			'requesttoken' => $requestToken,
		]);

		return $this->configureCSP($response);
	}

	/**
	 * Resolve hourly rate for time-entry preview (server authority).
	 * Read-only GET; session + project access checks apply (see getBudgetInfo).
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[UserRateLimit(limit: 60, period: 60)]
	public function resolveHourlyRate(int $id): JSONResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new JSONResponse(['error' => $this->l->t('User not authenticated')], 401);
		}
		if (!$this->projectService->canUserAccessProject($user->getUID(), $id)) {
			return new JSONResponse(['error' => $this->l->t('Access denied')], 403);
		}

		$dateRaw = (string) $this->request->getParam('date', '');
		$targetUserId = trim((string) $this->request->getParam('user_id', $user->getUID()));
		if ($targetUserId === '') {
			$targetUserId = $user->getUID();
		}
		if ($targetUserId !== $user->getUID()) {
			return new JSONResponse(['error' => $this->l->t('Access denied')], 403);
		}

		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateRaw) && !preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $dateRaw)) {
			return new JSONResponse(['error' => $this->l->t('Invalid date format')], 400);
		}
		if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $dateRaw, $m)) {
			$dateRaw = $m[3] . '-' . $m[2] . '-' . $m[1];
		}

		try {
			$preview = $this->hourlyRateService->resolvePreview($id, $targetUserId, new \DateTime($dateRaw));
			return new JSONResponse(['success' => true, 'data' => $preview]);
		} catch (RateResolutionException $e) {
			return new JSONResponse([
				'success' => false,
				'error' => $e->getMessage(),
				'code' => $e->getCodeKey(),
			], 400);
		} catch (\Throwable $e) {
			return new JSONResponse(['success' => false, 'error' => $this->l->t('Could not resolve hourly rate.')], 500);
		}
	}

	/**
	 * Get budget information for a project
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
			$currency = strtoupper(trim((string)$this->config->getAppValue($this->appName, 'currency', 'EUR')));
			if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
				$currency = 'EUR';
			}

			return new JSONResponse([
				'success' => true,
				'budget' => [
					'total_budget' => $budgetInfo['total_budget'],
					'used_budget' => $budgetInfo['used_budget'],
					'remaining_budget' => $budgetInfo['remaining_budget'],
					'consumption_percentage' => $budgetInfo['consumption_percentage'],
					'warning_level' => $budgetInfo['warning_level'],
					'warning_threshold' => $budgetInfo['warning_threshold'],
					'critical_threshold' => $budgetInfo['critical_threshold'],
					'used_hours' => $budgetInfo['used_hours'],
					'remaining_hours' => $budgetInfo['remaining_hours'],
					'hours_estimated' => $budgetInfo['hours_estimated'],
					'capacity_basis' => $budgetInfo['capacity_basis'],
					'capacity_rate' => $budgetInfo['capacity_rate'],
					'total_hours' => $budgetInfo['used_hours'],
					'project_name' => $project->getName(),
					'currency' => $currency,
				]
			]);
		} catch (\Exception $e) {
			return new JSONResponse([
				'success' => false,
				'error' => $this->l->t('Could not load budget information.')
			], 500);
		}
	}

	/**
	 * Check budget impact for a time entry
	 *
	 * @param int $project_id
	 * @param float $additional_hours
	 * @param string $entry_date Optional YYYY-MM-DD (defaults to today)
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
		$entryDateRaw = trim((string) $this->request->getParam('entry_date', ''));

		if (!$projectId || $additionalHours <= 0) {
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

			$entryDate = $this->parseEntryDateParam($entryDateRaw);
			if ($entryDate === null) {
				return new JSONResponse(['error' => $this->l->t('Invalid parameters')], 400);
			}

			try {
				$resolvedRate = $this->hourlyRateService->resolveForTimeEntry(
					$projectId,
					$user->getUID(),
					$entryDate
				);
			} catch (RateResolutionException $e) {
				return new JSONResponse([
					'success' => false,
					'error' => $e->getMessage(),
				], 400);
			}

			if ($resolvedRate <= 0) {
				return new JSONResponse(['error' => $this->l->t('Invalid parameters')], 400);
			}

			$impact = $this->budgetService->checkTimeEntryBudgetImpact(
				$project,
				$additionalHours,
				$resolvedRate,
				$user->getUID()
			);
			$impact['resolved_hourly_rate'] = $resolvedRate;

			return new JSONResponse([
				'success' => true,
				'impact' => $impact,
			]);
		} catch (\Exception $e) {
			return new JSONResponse([
				'success' => false,
				'error' => $this->l->t('Could not calculate budget impact.'),
			], 500);
		}
	}

	/**
	 * Parse optional YYYY-MM-DD for budget impact preview (defaults to today).
	 */
	private function parseEntryDateParam(string $raw): ?\DateTimeImmutable
	{
		if ($raw === '') {
			return new \DateTimeImmutable('today');
		}
		$dt = \DateTimeImmutable::createFromFormat('Y-m-d', $raw);
		if ($dt === false || $dt->format('Y-m-d') !== $raw) {
			return null;
		}

		return $dt;
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

		$currency = strtoupper(trim((string) $this->config->getAppValue($this->appName, 'currency', 'EUR')));
		if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
			$currency = 'EUR';
		}

		$response = new TemplateResponse($this->appName, 'project-form', [
			'project' => $project,
			'mode' => 'edit',
			'customers' => $customers,
			'stats' => $stats,
			'indexUrl' => $this->urlGenerator->linkToRoute('projectcheck.project.index'),
			'formAction' => $this->urlGenerator->linkToRoute('projectcheck.project.updatePost', ['id' => $id]),
			'urlGenerator' => $this->urlGenerator,
			'orgCurrency' => $currency,
			'costRateModeLocked' => $this->projectService->projectHasLoggedTime($id),
			'employeesIndexUrl' => $this->urlGenerator->linkToRoute('projectcheck.employee.index'),
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
				return new DataResponse($this->errorPayload($this->l->t('User not authenticated')), 401);
			}
			return new RedirectResponse($this->urlGenerator->linkToRoute('projectcheck.project.index'));
		}

		if (!$this->projectService->canUserEditProject($user->getUID(), $id)) {
			if ($this->request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
				return new DataResponse($this->errorPayload($this->l->t('Access denied')), 403);
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
				return new DataResponse($this->errorPayload($this->l->t('Method not allowed')), 405);
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
			$safeError = $this->toSafeProjectErrorMessage($e, $this->l->t('Could not update project. Please check your input.'));
			// Return appropriate response based on request type
			if ($this->request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
				return new DataResponse($this->errorPayload($safeError), 400);
			}

			// Redirect to projects list with error message
			$url = $this->urlGenerator->linkToRoute('projectcheck.project.index', ['message' => 'error', 'error_text' => $safeError]);
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
				return new DataResponse(['error' => $this->l->t('Could not update project. Please check your input.')], 400);
			}

			// Redirect to projects list with error message
			$url = $this->urlGenerator->linkToRoute('projectcheck.project.index', ['message' => 'error', 'error_text' => $this->l->t('Could not update project. Please check your input.')]);
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
				return new DataResponse($this->errorPayload($this->l->t('User not authenticated')), 401);
			}
			return new RedirectResponse($this->urlGenerator->linkToRoute('projectcheck.project.index'));
		}

		// Check if user can delete this project
		if (!$this->projectService->canUserDeleteProject($user->getUID(), $id)) {
			if ($this->request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
				return new DataResponse($this->errorPayload($this->l->t('Access denied')), 403);
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
				return new DataResponse($this->errorPayload($this->l->t('Method not allowed')), 405);
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
				return new DataResponse($this->errorPayload($this->l->t('Could not delete project.')), 400);
			}

			// Redirect to projects list with error message
			$url = $this->urlGenerator->linkToRoute('projectcheck.project.index', ['message' => 'error', 'error_text' => $this->l->t('Could not delete project.')]);
			return new RedirectResponse($url);
		}
	}

	/**
	 * Change project status (PUT; body must be JSON or url-encoded — not multipart)
	 *
	 * @param int $id
	 * @return RedirectResponse|DataResponse
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 60, period: 60)]
	public function changeStatus(int $id): RedirectResponse|DataResponse
	{
		return $this->mutateProjectStatus($id);
	}

	/**
	 * Change project status via POST (browser FormData / standard PHP $_POST parsing)
	 *
	 * @param int $id
	 * @return RedirectResponse|DataResponse
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 60, period: 60)]
	public function changeStatusPost(int $id): RedirectResponse|DataResponse
	{
		return $this->mutateProjectStatus($id);
	}

	/**
	 * Shared handler for PUT and POST project status changes.
	 *
	 * Nextcloud's IRequest does not merge multipart/form-data into parameters for PUT;
	 * the UI therefore uses POST for form submissions. PUT remains for API-style callers
	 * sending application/x-www-form-urlencoded or application/json bodies.
	 *
	 * @param int $id
	 * @return RedirectResponse|DataResponse
	 */
	private function mutateProjectStatus(int $id): RedirectResponse|DataResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			if ($this->request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
				return new DataResponse($this->errorPayload($this->l->t('User not authenticated')), 401);
			}
			return new RedirectResponse($this->urlGenerator->linkToRoute('projectcheck.project.index'));
		}
		if (!$this->projectService->canUserChangeProjectStatus($user->getUID(), $id)) {
			if ($this->request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
				return new DataResponse($this->errorPayload($this->l->t('Access denied')), 403);
			}
			return new RedirectResponse($this->urlGenerator->linkToRoute('projectcheck.project.index', ['message' => 'error', 'error_text' => $this->l->t('Access denied')]));
		}

		$status = $this->request->getParam('status');
		if (!is_string($status) || trim($status) === '') {
			if ($this->request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
				return new DataResponse($this->errorPayload($this->l->t('Please select a status.')), 400);
			}
			$url = $this->urlGenerator->linkToRoute('projectcheck.project.index', ['message' => 'error', 'error_text' => $this->l->t('Please select a status.')]);
			return new RedirectResponse($url);
		}

		try {
			$projectBefore = $this->projectService->getProject($id);
			$previousStatus = $projectBefore !== null ? (string) $projectBefore->getStatus() : '';

			$reasonRaw = $this->request->getParam('reason');
			$reason = is_string($reasonRaw) ? trim(strip_tags($reasonRaw)) : '';
			if ($reason !== '') {
				if (function_exists('mb_strlen') && mb_strlen($reason, 'UTF-8') > 2000) {
					$reason = mb_substr($reason, 0, 2000, 'UTF-8');
				} elseif (strlen($reason) > 2000) {
					$reason = substr($reason, 0, 2000);
				}
			}

			$project = $this->projectService->changeProjectStatus($id, $status);

			if ($projectBefore !== null) {
				try {
					$this->activityService->logProjectStatusChanged(
						$user->getUID(),
						$project,
						$previousStatus,
						(string) $project->getStatus(),
						$reason !== '' ? $reason : null
					);
				} catch (\Throwable) {
					// Status is already persisted; activity feed is best-effort audit only.
				}
			}

			if ($this->request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
				return new DataResponse([
					'success' => true,
					'message' => $this->l->t('Project status updated successfully'),
				]);
			}

			$url = $this->urlGenerator->linkToRoute('projectcheck.project.index', ['message' => 'success', 'status_updated' => 'true']);
			return new RedirectResponse($url);
		} catch (\Exception $e) {
			$userMessage = $this->localizeProjectStatusChangeError($e);
			if ($this->request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
				return new DataResponse($this->errorPayload($userMessage), 400);
			}

			$url = $this->urlGenerator->linkToRoute('projectcheck.project.index', ['message' => 'error', 'error_text' => $userMessage]);
			return new RedirectResponse($url);
		}
	}

	/**
	 * Map service-layer English exceptions to localized, user-safe messages (no raw internals).
	 */
	private function localizeProjectStatusChangeError(\Exception $e): string
	{
		$raw = trim($e->getMessage());
		if ($raw === 'Project not found') {
			return $this->l->t('Project not found');
		}
		if ($raw === 'Invalid status value') {
			return $this->l->t('The selected status is not valid.');
		}
		if ($raw === 'User not authenticated') {
			return $this->l->t('User not authenticated');
		}
		if (preg_match('/^Invalid status transition from /', $raw) === 1) {
			return $this->l->t('This status change is not allowed for the current project state.');
		}

		return $this->l->t('Could not change project status.');
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
			return new DataResponse(['error' => $this->l->t('Could not load team members.')], 400);
		}
	}

	/**
	 * Search assignable users for a project (modal autocomplete).
	 *
	 * Rate-limited because this endpoint can be used to enumerate users
	 * across the directory; the cap supports live typing while blocking
	 * scripted enumeration.
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[UserRateLimit(limit: 60, period: 60)]
	public function searchAssignableUsers(int $id): JSONResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new JSONResponse(['error' => $this->l->t('User not authenticated')], 401);
		}
		$project = $this->projectService->getProject($id);
		if ($project === null) {
			return new JSONResponse(['error' => $this->l->t('Project not found')], 404);
		}
		if (!$this->projectService->canUserManageMembers($user->getUID(), $id)) {
			return new JSONResponse(['error' => $this->l->t('Access denied')], 403);
		}
		if (!$project->isEditableState()) {
			return new JSONResponse(['error' => $this->l->t('Cannot change the team for a completed, cancelled, or archived project')], 403);
		}

		$q = trim((string)$this->request->getParam('q', ''));
		if (mb_strlen($q) < 2) {
			return new JSONResponse(['success' => true, 'items' => []]);
		}
		$q = mb_substr($q, 0, 120);

		$activeTeam = $this->projectService->getProjectTeamGrouped($id)['active'] ?? [];
		$activeUids = [];
		foreach ($activeTeam as $member) {
			if ($member instanceof ProjectMember) {
				$activeUids[(string)$member->getUserId()] = true;
			}
		}

		try {
			$byId = $this->userManager->search($q, 20, 0);
			$byName = $this->userManager->searchDisplayName($q, 20, 0);
		} catch (\Throwable $e) {
			return new JSONResponse(['error' => $this->l->t('User search failed. Check your connection and try again.')], 500);
		}
		$merged = [];
		foreach (array_merge($byId, $byName) as $candidate) {
			if (!($candidate instanceof \OCP\IUser)) {
				continue;
			}
			if (method_exists($candidate, 'isEnabled') && !$candidate->isEnabled()) {
				continue;
			}
			$uid = $candidate->getUID();
			if ($uid === '' || isset($activeUids[$uid]) || isset($merged[$uid])) {
				continue;
			}
			$display = trim((string)$candidate->getDisplayName());
			$label = $display !== '' && $display !== $uid ? $display . ' (' . $uid . ')' : $uid;
			$merged[$uid] = [
				'uid' => $uid,
				'displayName' => $display !== '' ? $display : $uid,
				'label' => $label,
			];
		}

		return new JSONResponse(['success' => true, 'items' => array_values($merged)]);
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
		$project = $this->projectService->getProject($id);
		if ($project === null) {
			return new DataResponse(['error' => $this->l->t('Project not found')], 404);
		}
		if (!$this->projectService->canUserManageMembers($user->getUID(), $id)) {
			return new DataResponse(['error' => $this->l->t('Access denied')], 403);
		}
		if (!$project->isEditableState()) {
			return new DataResponse(['error' => $this->l->t('Cannot change the team for a completed, cancelled, or archived project')], 403);
		}

		try {
			$userId = trim((string)$this->request->getParam('user_id', ''));
			$role = \OCA\ProjectCheck\Service\ProjectService::DEFAULT_MEMBER_ROLE;
			$hourlyRateRaw = $this->request->getParam('hourly_rate');
			$hourlyRate = null;
			if ($hourlyRateRaw !== null && $hourlyRateRaw !== '') {
				if (!is_numeric($hourlyRateRaw)) {
					return new DataResponse(['error' => $this->l->t('Hourly rate must be a non-negative number')], 400);
				}
				$hourlyRate = (float)$hourlyRateRaw;
				if ($hourlyRate < 0) {
					return new DataResponse(['error' => $this->l->t('Hourly rate must be a non-negative number')], 400);
				}
			}

			if ($userId === '') {
				return new DataResponse(['error' => $this->l->t('Invalid parameters')], 400);
			}

			if ($project->getCostRateMode() === CostRateMode::PROJECT_MEMBER) {
				if ($hourlyRate === null || $hourlyRate <= 0) {
					return new DataResponse([
						'error' => $this->l->t('Enter an hourly rate for this person before adding them to the project.'),
					], 400);
				}
			}

			$member = $this->projectService->addTeamMember($id, $userId, $role, $hourlyRate);

			return new DataResponse([
				'success' => true,
				'member' => $member,
				'message' => $this->l->t('Team member added successfully'),
			]);
		} catch (\Exception $e) {
			// Removed logger call
			return new DataResponse(['error' => $this->l->t('Could not add team member.')], 400);
		}
	}

	/**
	 * Add all assignable (enabled, non-member) users to a project.
	 */
	#[NoAdminRequired]
	public function addAllTeamMembers(int $id): DataResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new DataResponse(['error' => $this->l->t('User not authenticated')], 401);
		}
		$project = $this->projectService->getProject($id);
		if ($project === null) {
			return new DataResponse(['error' => $this->l->t('Project not found')], 404);
		}
		if (!$this->projectService->canUserManageMembers($user->getUID(), $id)) {
			return new DataResponse(['error' => $this->l->t('Access denied')], 403);
		}
		if (!$project->isEditableState()) {
			return new DataResponse(['error' => $this->l->t('Cannot change the team for a completed, cancelled, or archived project')], 403);
		}
		if ($project->getCostRateMode() === CostRateMode::PROJECT_MEMBER) {
			return new DataResponse([
				'error' => $this->l->t('Add each person individually with their rate when using per-person project pricing.'),
			], 400);
		}

		$activeTeam = $this->projectService->getProjectTeamGrouped($id)['active'] ?? [];
		$activeUids = [];
		foreach ($activeTeam as $member) {
			if ($member instanceof ProjectMember) {
				$activeUids[(string)$member->getUserId()] = true;
			}
		}

		$batchSize = 500;
		$offset = 0;
		$addedCount = 0;
		$skippedCount = 0;
		$seenUids = [];

		try {
			do {
				$candidates = $this->userManager->search('', $batchSize, $offset);
				if (!is_array($candidates) || $candidates === []) {
					break;
				}

				foreach ($candidates as $candidate) {
					if (!($candidate instanceof \OCP\IUser)) {
						continue;
					}

					$uid = trim((string)$candidate->getUID());
					if ($uid === '' || isset($seenUids[$uid])) {
						continue;
					}
					$seenUids[$uid] = true;

					if (isset($activeUids[$uid])) {
						$skippedCount++;
						continue;
					}
					if (method_exists($candidate, 'isEnabled') && !$candidate->isEnabled()) {
						$skippedCount++;
						continue;
					}

					try {
						$this->projectService->addTeamMember($id, $uid, \OCA\ProjectCheck\Service\ProjectService::DEFAULT_MEMBER_ROLE, null);
						$activeUids[$uid] = true;
						$addedCount++;
					} catch (\Exception $e) {
						$skippedCount++;
					}
				}

				$offset += $batchSize;
			} while (count($candidates) === $batchSize);
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $this->l->t('Could not add all users to the project')], 400);
		}

		return new DataResponse([
			'success' => true,
			'added_count' => $addedCount,
			'skipped_count' => $skippedCount,
			'message' => $this->l->t('Added %d users to the project', [$addedCount]),
		]);
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
		$project = $this->projectService->getProject($id);
		if ($project === null) {
			return new DataResponse(['error' => $this->l->t('Project not found')], 404);
		}
		if (!$this->projectService->canUserManageMembers($user->getUID(), $id)) {
			return new DataResponse(['error' => $this->l->t('Access denied')], 403);
		}
		if (!$project->isEditableState()) {
			return new DataResponse(['error' => $this->l->t('Cannot change the team for a completed, cancelled, or archived project')], 403);
		}

		try {
			$targetUserId = trim($userId);
			$role = \OCA\ProjectCheck\Service\ProjectService::DEFAULT_MEMBER_ROLE;
			$hourlyRateRaw = $this->request->getParam('hourly_rate');
			$hourlyRate = null;
			if ($hourlyRateRaw !== null && $hourlyRateRaw !== '') {
				if (!is_numeric($hourlyRateRaw)) {
					return new DataResponse(['error' => $this->l->t('Hourly rate must be a non-negative number')], 400);
				}
				$hourlyRate = (float)$hourlyRateRaw;
				if ($hourlyRate < 0) {
					return new DataResponse(['error' => $this->l->t('Hourly rate must be a non-negative number')], 400);
				}
			}
			if ($targetUserId === '') {
				return new DataResponse(['error' => $this->l->t('Invalid parameters')], 400);
			}

			if ($project->getCostRateMode() === CostRateMode::PROJECT_MEMBER) {
				$effectiveFrom = trim((string) $this->request->getParam('effective_from', ''));
				if ($effectiveFrom === '') {
					$effectiveFrom = gmdate('Y-m-d');
				}
				if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $effectiveFrom, $m)) {
					$effectiveFrom = $m[3] . '-' . $m[2] . '-' . $m[1];
				}
				if ($hourlyRate === null || $hourlyRate <= 0) {
					return new DataResponse(['error' => $this->l->t('Hourly rate must be a positive number')], 400);
				}
				$this->projectService->appendTeamMemberRate($id, $targetUserId, $hourlyRate, $effectiveFrom, $user->getUID());
				$member = $this->projectService->getActiveProjectMember($id, $targetUserId);
				return new DataResponse([
					'success' => true,
					'member' => $member,
					'message' => $this->l->t('Rate saved. Past time entries keep their previous rate.'),
				]);
			}

			$this->projectService->removeTeamMember($id, $targetUserId);
			$member = $this->projectService->addTeamMember($id, $targetUserId, $role, $hourlyRate);

			return new DataResponse([
				'success' => true,
				'member' => $member,
				'message' => $this->l->t('Team member updated successfully'),
			]);
		} catch (\Exception $e) {
			// Removed logger call
			return new DataResponse(['error' => $this->l->t('Could not update team member.')], 400);
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
		$project = $this->projectService->getProject($id);
		if ($project === null) {
			return new DataResponse(['error' => $this->l->t('Project not found')], 404);
		}
		if (!$this->projectService->canUserManageMembers($user->getUID(), $id)) {
			return new DataResponse(['error' => $this->l->t('Access denied')], 403);
		}
		if (!$project->isEditableState()) {
			return new DataResponse(['error' => $this->l->t('Cannot change the team for a completed, cancelled, or archived project')], 403);
		}

		try {
			$targetUserId = trim($userId);
			if ($targetUserId === '') {
				return new DataResponse(['error' => $this->l->t('Invalid parameters')], 400);
			}
			$this->projectService->removeTeamMember($id, $targetUserId);

			return new DataResponse([
				'success' => true,
				'message' => $this->l->t('Team member removed successfully'),
			]);
		} catch (\Exception $e) {
			// Removed logger call
			return new DataResponse(['error' => $this->l->t('Could not remove team member.')], 400);
		}
	}

	/**
	 * Search projects
	 *
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 60, period: 60)]
	public function search(): DataResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new DataResponse(['error' => $this->l->t('User not authenticated')], 401);
		}

		try {
			$query = $this->request->getParam('q', '');
			$filters = ['search' => $query];
			$projects = $this->projectService->getUserScopedProjects($user->getUID(), $filters);

			return new DataResponse([
				'success' => true,
				'projects' => $projects,
			]);
		} catch (\Exception $e) {
			// Removed logger call
			return new DataResponse(['error' => $this->l->t('Could not search projects.')], 400);
		}
	}

	/**
	 * Filter projects
	 *
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 60, period: 60)]
	public function filter(): DataResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new DataResponse(['error' => $this->l->t('User not authenticated')], 401);
		}

		try {
			$filters = $this->request->getParams();
			$projects = $this->projectService->getUserScopedProjects($user->getUID(), $filters);

			return new DataResponse([
				'success' => true,
				'projects' => $projects,
			]);
		} catch (\Exception $e) {
			// Removed logger call
			return new DataResponse(['error' => $this->l->t('Could not filter projects.')], 400);
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
			return new DataResponse($this->errorPayload($this->l->t('User not authenticated')), 401);
		}

		try {
			$filters = $this->request->getParams();
			$projects = $this->projectService->getUserScopedProjects($user->getUID(), $filters);

			return new DataResponse([
				'success' => true,
				'projects' => $projects,
			]);
		} catch (\Exception $e) {
			// Removed logger call
			return new DataResponse($this->errorPayload($this->l->t('Could not load projects.')), 400);
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
			return new DataResponse($this->errorPayload($this->l->t('User not authenticated')), 401);
		}

		if (!$this->projectService->canUserCreateProject($user->getUID())) {
			return new DataResponse($this->errorPayload($this->l->t('Access denied')), 403);
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
			return new DataResponse($this->errorPayload($this->l->t('Could not create project. Please check your input.')), 400);
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
			return new DataResponse($this->errorPayload($this->l->t('User not authenticated')), 401);
		}
		if (!$this->projectService->canUserAccessProject($user->getUID(), $id)) {
			return new DataResponse($this->errorPayload($this->l->t('Access denied')), 403);
		}

		try {
			$project = $this->projectService->getProject($id);
			if (!$project) {
				return new DataResponse($this->errorPayload($this->l->t('Project not found')), 404);
			}

			$teamMembers = $this->projectService->getProjectTeam($id);

			return new DataResponse([
				'success' => true,
				'project' => $project,
				'teamMembers' => $teamMembers,
			]);
		} catch (\Exception $e) {
			// Removed logger call
			return new DataResponse($this->errorPayload($this->l->t('Could not load project details.')), 400);
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
			return new DataResponse($this->errorPayload($this->l->t('User not authenticated')), 401);
		}
		$uid = $user->getUID();
		if (!$this->projectService->canUserAccessProject($uid, $id)) {
			return new DataResponse($this->errorPayload($this->l->t('Access denied')), 403);
		}

		$raw = $this->request->getParams();
		$staged = $this->extractProjectApiUpdatePayload($raw);
		if ($staged === []) {
			return new DataResponse($this->errorPayload($this->l->t('No update data')), 400);
		}

		$isStatusOnly = count($staged) === 1 && \array_key_exists('status', $staged);
		if ($isStatusOnly) {
			if (!$this->projectService->canUserChangeProjectStatus($uid, $id)) {
				return new DataResponse($this->errorPayload($this->l->t('Access denied')), 403);
			}
			try {
				$this->projectService->changeProjectStatus($id, (string)$staged['status']);
				$project = $this->projectService->getProject($id);
				if (!$project) {
					return new DataResponse($this->errorPayload($this->l->t('Project not found')), 404);
				}
				return new DataResponse([
					'success' => true,
					'project' => $project,
					'message' => $this->l->t('Project updated successfully'),
				]);
			} catch (\Exception $e) {
				return new DataResponse($this->errorPayload($this->l->t('Could not update project status.')), 400);
			}
		}

		if (!$this->projectService->canUserEditProject($uid, $id)) {
			return new DataResponse($this->errorPayload($this->l->t('Access denied')), 403);
		}

		try {
			$project = $this->projectService->updateProject($id, $staged);

			return new DataResponse([
				'success' => true,
				'project' => $project,
				'message' => $this->l->t('Project updated successfully')
			]);
		} catch (\Exception $e) {
			return new DataResponse($this->errorPayload($this->l->t('Could not update project. Please check your input.')), 400);
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
			'cost_rate_mode',
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
			return new DataResponse($this->errorPayload($this->l->t('User not authenticated')), 401);
		}
		if (!$this->projectService->canUserDeleteProject($user->getUID(), $id)) {
			return new DataResponse($this->errorPayload($this->l->t('Access denied')), 403);
		}

		try {
			$this->projectService->deleteProject($id);
			return new DataResponse(['success' => true, 'message' => $this->l->t('Project deleted successfully')]);
		} catch (\Exception $e) {
			return new DataResponse($this->errorPayload($this->l->t('Could not delete project.')), 400);
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
			return new DataResponse($this->errorPayload($this->l->t('User not authenticated')), 401);
		}
		if (!$this->projectService->canUserDeleteProject($user->getUID(), $id)) {
			return new DataResponse($this->errorPayload($this->l->t('Access denied')), 403);
		}

		try {
			$impact = $this->deletionService->getProjectDeletionImpact($id);
			return new DataResponse(['success' => true, 'impact' => $impact]);
		} catch (\Exception $e) {
			return new DataResponse(['success' => false, 'error' => $this->l->t('Could not load deletion impact.')], 400);
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
			return new DataResponse($this->errorPayload($this->l->t('User not authenticated')), 401);
		}

		try {
			// Scope projects by customer and per-user visibility.
			$projects = $this->projectService->getUserScopedProjects($user->getUID(), [
				'customer_id' => $customerId,
			]);

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
					'remaining_hours' => $budgetInfo['remaining_hours'],
					'hours_estimated' => $budgetInfo['hours_estimated'],
					'capacity_basis' => $budgetInfo['capacity_basis'],
					'capacity_rate' => $budgetInfo['capacity_rate'],
					'available_hours' => $budgetInfo['available_hours'],
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
			return new DataResponse($this->errorPayload($this->l->t('Could not load customer projects.')), 400);
		}
	}

	private function toSafeProjectErrorMessage(\Exception $e, string $fallback): string
	{
		$message = trim($e->getMessage());
		if ($message === '') {
			return $fallback;
		}

		$allowlistPatterns = [
			'/^Access denied$/',
			'/^Project not found$/',
			'/^Customer not found$/',
			'/^.+ is required$/',
			'/^Invalid (parameters|status value|priority value|project type value)$/',
			'/^Field .+ is required$/',
			'/^.+ must be .+$/',
			'/^Cannot .+$/',
		];
		foreach ($allowlistPatterns as $pattern) {
			if (preg_match($pattern, $message) === 1) {
				return $message;
			}
		}

		return $fallback;
	}

	private function pricingModeLabel(string $mode): string
	{
		return match (CostRateMode::normalize($mode)) {
			CostRateMode::EMPLOYEE => $this->l->t('Rate per employee (master data)'),
			CostRateMode::PROJECT_MEMBER => $this->l->t('Rate per person on this project'),
			default => $this->l->t('One rate for the whole project'),
		};
	}

	/**
	 * @return array{success: false, error: string, message: string}
	 */
	private function errorPayload(string $message): array
	{
		return [
			'success' => false,
			'error' => $message,
			'message' => $message,
		];
	}

}
