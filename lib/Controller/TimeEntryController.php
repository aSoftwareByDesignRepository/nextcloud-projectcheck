<?php

declare(strict_types=1);

/**
 * TimeEntry controller for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\IURLGenerator;
use OCP\IConfig;
use OCA\ProjectCheck\Service\TimeEntryService;
use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Service\CustomerService;
use OCA\ProjectCheck\Service\BudgetService;
use OCA\ProjectCheck\Service\DeletionService;
use OCA\ProjectCheck\Service\ActivityService;
use OCA\ProjectCheck\Service\CSPService;
use OCA\ProjectCheck\Service\DateFormatService;
use OCA\ProjectCheck\Traits\StatsTrait;
use OCA\ProjectCheck\Controller\CSPTrait;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

/**
 * TimeEntry controller for time tracking
 */
class TimeEntryController extends Controller
{
	use CSPTrait;
	use StatsTrait;

	/** @var IUserSession */
	private $userSession;

	/** @var TimeEntryService */
	private $timeEntryService;

	/** @var ProjectService */
	private $projectService;

	/** @var CustomerService */
	private $customerService;

	/** @var IURLGenerator */
	private $urlGenerator;

	/** @var IConfig */
	private $config;

	/** @var DateFormatService */
	private $dateFormatService;

	/** @var DeletionService */
	private $deletionService;

	/** @var ActivityService */
	private $activityService;

	/** @var IL10N */
	private $l;

	/** @var LoggerInterface */
	private $logger;

	/**
	 * TimeEntryController constructor
	 *
	 * @param string $appName
	 * @param IRequest $request
	 * @param IUserSession $userSession
	 * @param TimeEntryService $timeEntryService
	 * @param ProjectService $projectService
	 * @param CustomerService $customerService
	 * @param BudgetService $budgetService
	 * @param IURLGenerator $urlGenerator
	 * @param IConfig $config
	 * @param DateFormatService $dateFormatService
	 * @param DeletionService $deletionService
	 * @param ActivityService $activityService
	 * @param CSPService $cspService
	 * @param IL10N $l
	 * @param LoggerInterface $logger
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		IUserSession $userSession,
		TimeEntryService $timeEntryService,
		ProjectService $projectService,
		CustomerService $customerService,
		BudgetService $budgetService,
		IURLGenerator $urlGenerator,
		IConfig $config,
		DateFormatService $dateFormatService,
		DeletionService $deletionService,
		ActivityService $activityService,
		CSPService $cspService,
		IL10N $l,
		LoggerInterface $logger
	) {
		parent::__construct($appName, $request);
		$this->userSession = $userSession;
		$this->timeEntryService = $timeEntryService;
		$this->projectService = $projectService;
		$this->customerService = $customerService;
		$this->urlGenerator = $urlGenerator;
		$this->config = $config;
		$this->dateFormatService = $dateFormatService;
		$this->deletionService = $deletionService;
		$this->activityService = $activityService;
		$this->l = $l;
		$this->logger = $logger;
		$this->setCspService($cspService);
	}

	/**
	 * Show time entry list page
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

		// Get filters from request
		$projectId = $this->request->getParam('project_id', '');
		$dateFrom = $this->request->getParam('date_from', '');
		$dateTo = $this->request->getParam('date_to', '');
		$search = $this->request->getParam('search', '');
		$filterUserId = trim((string)$this->request->getParam('user_id', ''));
		$projectType = $this->request->getParam('project_type', '');
		$page = max(1, (int)$this->request->getParam('page', 1));

		// Determine pagination settings (fixed 20 per page)
		$perPage = 20;

		$filters = [];
		if ($projectId) $filters['project_id'] = $projectId;
		if ($dateFrom) {
			$filters['date_from'] = $dateFrom;
		}
		if ($dateTo) {
			$filters['date_to'] = $dateTo;
		}
		if ($search) $filters['search'] = $search;
		$canViewAllEntries = $this->projectService->canUserViewAllTimeEntries($userId);
		if ($filterUserId) {
			$filters['user_id'] = $canViewAllEntries ? $filterUserId : $userId;
		} elseif (!$canViewAllEntries) {
			$filters['user_id'] = $userId;
		}
		if ($projectType) $filters['project_type'] = $projectType;
		if ($accessibleProjectIds !== null) {
			$filters['project_ids'] = $accessibleProjectIds;
		}

		// Apply pagination filters
		$filters['limit'] = $perPage;
		$filters['offset'] = ($page - 1) * $perPage;

		// Count total entries for pagination
		$totalEntries = $this->timeEntryService->countTimeEntries($filters);
		$totalPages = (int)max(1, ceil($totalEntries / $perPage));

		// Clamp page if user requests beyond last page
		if ($page > $totalPages) {
			$page = $totalPages;
			$filters['offset'] = ($page - 1) * $perPage;
		}

		// Keep original filter values for the form (in ISO format for date inputs)
		$formFilters = [
			'project_id' => $projectId,
			'date_from' => $dateFrom,
			'date_to' => $dateTo,
			'search' => $search,
			'user_id' => $canViewAllEntries ? $filterUserId : $userId,
			'project_type' => $projectType
		];

		// Get time entries
		$timeEntries = $this->timeEntryService->getTimeEntriesWithProjectInfo($filters);

		// Get all projects for filter dropdown (incl. archived for viewing historical entries)
		$userProjects = $this->projectService->getProjectsForUserTimeEntry($user->getUID(), ['status' => ['Active', 'On Hold', 'Completed', 'Archived']]);
		$userProjects = $this->sortProjectsByName($userProjects);

		// Get all users who have time entries
		$users = $this->timeEntryService->getUsersWithTimeEntries($accessibleProjectIds);
		$users = $this->ensureSelectedUserVisibleInFilters($users, $filterUserId, $timeEntries);

		// Get common stats for the sidebar
		$stats = $this->getCommonStats($this->projectService, $this->customerService, $this->timeEntryService, $user->getUID());

		// Get project type statistics for the current filters
		$projectTypeStats = $this->timeEntryService->getYearlyStatsByProjectType();
		$detailedProjectTypeStats = $this->timeEntryService->getDetailedYearlyStatsByProjectType();
		$productivityAnalysis = $this->timeEntryService->getProductivityAnalysis();

		$response = new TemplateResponse($this->appName, 'time-entries', [
			'timeEntries' => $timeEntries,
			'projects' => $userProjects,
			'users' => $users,
			'filters' => $formFilters,
			'userId' => $userId,
			'canViewAllEntries' => $canViewAllEntries,
			'stats' => $stats,
			'projectTypeStats' => $projectTypeStats,
			'detailedProjectTypeStats' => $detailedProjectTypeStats,
			'productivityAnalysis' => $productivityAnalysis,
			'dateFormatService' => $this->dateFormatService,
			'pagination' => [
				'page' => $page,
				'perPage' => $perPage,
				'totalEntries' => $totalEntries,
				'totalPages' => $totalPages,
			],
			'createUrl' => $this->urlGenerator->linkToRoute('projectcheck.timeentry.create'),
			'indexUrl' => $this->urlGenerator->linkToRoute('projectcheck.timeentry.index'),
			'showUrl' => $this->urlGenerator->linkToRoute('projectcheck.timeentry.show', ['id' => 'ENTRY_ID']),
			'editUrl' => $this->urlGenerator->linkToRoute('projectcheck.timeentry.edit', ['id' => 'ENTRY_ID']),
			'projectShowUrl' => $this->urlGenerator->linkToRoute('projectcheck.project.show', ['id' => 'PROJECT_ID']),
			'exportUrl' => $this->urlGenerator->linkToRoute('projectcheck.timeentry.export')
		]);

		return $this->configureCSP($response);
	}

	/**
	 * Show time entry creation form
	 *
	 * @return TemplateResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function create()
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			$response = new TemplateResponse($this->appName, 'error', [
				'message' => $this->l->t('User not authenticated')
			], 'guest');
			return $this->configureCSP($response, 'guest');
		}

		$userId = $user->getUID();

		// Get all projects for time entry selection (exclude completed/cancelled)
		$userProjects = $this->projectService->getProjectsForUserTimeEntry($user->getUID(), ['status' => ['Active', 'On Hold']]);
		$userProjects = array_values(array_filter($userProjects, static function ($project) {
			$status = trim((string)$project->getStatus());
			return strcasecmp($status, 'Completed') !== 0
				&& strcasecmp($status, 'Cancelled') !== 0
				&& strcasecmp($status, 'Archived') !== 0;
		}));

		$userProjects = $this->sortProjectsByName($userProjects);

		// Get common stats for the sidebar
		$stats = $this->getCommonStats($this->projectService, $this->customerService, $this->timeEntryService, $user->getUID());

		$response = new TemplateResponse($this->appName, 'time-entry-form', [
			'timeEntry' => null,
			'projects' => $userProjects,
			'isEdit' => false,
			'stats' => $stats,
			'indexUrl' => $this->urlGenerator->linkToRoute('projectcheck.timeentry.index'),
			'storeUrl' => $this->urlGenerator->linkToRoute('projectcheck.timeentry.store'),
			'updateUrl' => $this->urlGenerator->linkToRoute('projectcheck.timeentry.update', ['id' => 'TIME_ENTRY_ID']),
		]);

		return $this->configureCSP($response);
	}

	/**
	 * Store new time entry
	 *
	 * Mutating endpoint — CSRF is enforced via Nextcloud's automatic
	 * `requesttoken` verification (the time-entry form submits it as a
	 * hidden input on POST, time-entry-form.js sends it as a header).
	 *
	 * @return JSONResponse
	 */
	#[NoAdminRequired]
	public function store()
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new JSONResponse(['error' => $this->l->t('User not authenticated')], 401);
		}

		$userId = $user->getUID();

		// Get data from JSON request body
		$jsonData = file_get_contents('php://input');
		$data = json_decode($jsonData, true);

		// Fallback to getParams if JSON parsing fails
		if ($data === null) {
			$data = $this->request->getParams();
		}

		try {
			// Validate data
			$validation = $this->timeEntryService->validateTimeEntryDataDetailed($data);
			if (!empty($validation['errors'])) {
				return new JSONResponse([
					'success' => false,
					'errors' => $validation['errors'],
					'errorCodes' => $validation['errorCodes'],
				], 400);
			}

			// Create time entry
			$timeEntry = $this->timeEntryService->createTimeEntry($data, $userId);

			return new JSONResponse([
				'success' => true,
				'timeEntry' => $timeEntry->getSummary(),
				'message' => $this->l->t('Time entry created successfully')
			]);
		} catch (\Throwable $e) {
			$this->logger->error('Time entry creation failed', ['exception' => $e]);
			if ($e->getMessage() === 'Access denied') {
				return new JSONResponse([
					'success' => false,
					'error' => $this->l->t('Access denied')
				], 403);
			}
			$status = $e instanceof \Exception ? 400 : 500;
			return new JSONResponse([
				'success' => false,
				'error' => $this->l->t('Could not create time entry. Please check your input.')
			], $status);
		}
	}

	/**
	 * Show time entry detail page
	 *
	 * @param int $id Time entry ID
	 * @return TemplateResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function show($id)
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			$response = new TemplateResponse($this->appName, 'error', [
				'message' => $this->l->t('User not authenticated')
			]);
			return $this->configureCSP($response);
		}

		$timeEntry = $this->timeEntryService->getTimeEntry($id);
		if (!$timeEntry) {
			$response = new TemplateResponse($this->appName, 'error', [
				'message' => $this->l->t('Time entry not found')
			], 'guest');
			return $this->configureCSP($response, 'guest');
		}

		$uid = $user->getUID();
		$isOwner = $timeEntry->getUserId() === $uid;
		if (!$isOwner && !$this->projectService->canUserViewAllTimeEntries($uid)) {
			$response = new TemplateResponse($this->appName, 'error', [
				'message' => $this->l->t('Access denied')
			], 'guest');
			return $this->configureCSP($response, 'guest');
		}

		$project = $this->projectService->getProject($timeEntry->getProjectId());
		$projectName = $project ? $project->getName() : (string) $this->l->t('Unknown project');
		$projectShowUrl = $this->urlGenerator->linkToRoute('projectcheck.project.show', ['id' => $timeEntry->getProjectId()]);

		// Get common stats for the sidebar
		$stats = $this->getCommonStats($this->projectService, $this->customerService, $this->timeEntryService, $user->getUID());

		$response = new TemplateResponse($this->appName, 'time-entry-detail', [
			'timeEntry' => $timeEntry,
			'project' => $project,
			'projectName' => $projectName,
			'projectShowUrl' => $projectShowUrl,
			'stats' => $stats,
			'urlGenerator' => $this->urlGenerator,
			'userId' => $user->getUID()
		]);

		return $this->configureCSP($response);
	}

	/**
	 * Show time entry edit form
	 *
	 * @param int $id Time entry ID
	 * @return TemplateResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function edit($id)
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			$response = new TemplateResponse($this->appName, 'error', [
				'message' => $this->l->t('User not authenticated')
			], 'guest');
			return $this->configureCSP($response, 'guest');
		}

		$timeEntry = $this->timeEntryService->getTimeEntry($id);
		if (!$timeEntry) {
			$response = new TemplateResponse($this->appName, 'error', [
				'message' => $this->l->t('Time entry not found')
			], 'guest');
			return $this->configureCSP($response, 'guest');
		}

		// Check if user has access to this time entry
		if ($timeEntry->getUserId() !== $user->getUID()) {
			$response = new TemplateResponse($this->appName, 'error', [
				'message' => $this->l->t('Access denied')
			], 'guest');
			return $this->configureCSP($response, 'guest');
		}

		$userId = $user->getUID();
		// Get all projects for time entry selection (incl. archived; moving to a closed project is blocked in the service)
		$userProjects = $this->projectService->getProjectsForUserTimeEntry($user->getUID(), ['status' => ['Active', 'On Hold', 'Completed', 'Archived']]);
		$userProjects = $this->sortProjectsByName($userProjects);

		// Get common stats for the sidebar
		$stats = $this->getCommonStats($this->projectService, $this->customerService, $this->timeEntryService, $user->getUID());

		$response = new TemplateResponse($this->appName, 'time-entry-form', [
			'timeEntry' => $timeEntry,
			'projects' => $userProjects,
			'isEdit' => true,
			'stats' => $stats,
			'indexUrl' => $this->urlGenerator->linkToRoute('projectcheck.timeentry.index'),
			'storeUrl' => $this->urlGenerator->linkToRoute('projectcheck.timeentry.store'),
			'updateUrl' => $this->urlGenerator->linkToRoute('projectcheck.timeentry.updatePost', ['id' => $timeEntry->getId()]),
		]);

		return $this->configureCSP($response);
	}

	/**
	 * Update time entry
	 *
	 * Mutating endpoint — CSRF is enforced via Nextcloud's automatic
	 * `requesttoken` verification (form hidden input + AJAX header).
	 *
	 * @param int $id Time entry ID
	 * @return JSONResponse
	 */
	#[NoAdminRequired]
	public function update($id)
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new JSONResponse(['error' => $this->l->t('User not authenticated')], 401);
		}

		// Accept JSON body (AJAX) or form-encoded params (HTML form)
		$jsonData = file_get_contents('php://input');
		$data = json_decode($jsonData, true);
		if ($data === null || !is_array($data)) {
			$data = $this->request->getParams();
		}

		try {
			// Validate data
			$validation = $this->timeEntryService->validateTimeEntryDataDetailed($data);
			if (!empty($validation['errors'])) {
				return new JSONResponse([
					'success' => false,
					'errors' => $validation['errors'],
					'errorCodes' => $validation['errorCodes'],
				], 400);
			}

			// Update time entry
			$timeEntry = $this->timeEntryService->updateTimeEntry($id, $data, $user->getUID());

			return new JSONResponse([
				'success' => true,
				'timeEntry' => $timeEntry->getSummary(),
				'message' => $this->l->t('Time entry was updated successfully!')
			]);
		} catch (\Throwable $e) {
			$this->logger->error('Time entry update failed', ['exception' => $e]);
			if ($e->getMessage() === 'Access denied') {
				return new JSONResponse([
					'success' => false,
					'error' => $this->l->t('Access denied')
				], 403);
			}
			$status = $e instanceof \Exception ? 400 : 500;
			return new JSONResponse([
				'success' => false,
				'error' => $this->l->t('Could not update time entry. Please check your input.')
			], $status);
		}
	}

	/**
	 * Update time entry via POST for forms that cannot send PUT
	 *
	 * Mutating endpoint — CSRF is enforced via Nextcloud's automatic
	 * `requesttoken` verification.
	 *
	 * @param int $id Time entry ID
	 * @return JSONResponse
	 */
	#[NoAdminRequired]
	public function updatePost($id)
	{
		// Delegate to update() to keep logic in one place
		return $this->update($id);
	}

	/**
	 * Get deletion impact for a time entry
	 *
	 * @param int $id
	 * @return JSONResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getDeletionImpact(int $id): JSONResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new JSONResponse(['error' => $this->l->t('User not authenticated')], 401);
		}
		$te = $this->timeEntryService->getTimeEntry($id);
		if (!$te || $te->getUserId() !== $user->getUID()) {
			return new JSONResponse(['success' => false, 'error' => $this->l->t('Access denied')], 403);
		}

		try {
			$impact = $this->timeEntryService->getTimeEntryDeletionImpact($id);
			return new JSONResponse(['success' => true, 'impact' => $impact]);
		} catch (\Throwable $e) {
			$this->logger->error('Time entry deletion impact failed', ['exception' => $e]);
			$status = $e instanceof \Exception ? 400 : 500;
			return new JSONResponse(['success' => false, 'error' => $this->l->t('Could not load deletion impact.')], $status);
		}
	}

	/**
	 * Delete time entry
	 *
	 * Mutating endpoint — CSRF is enforced via Nextcloud's automatic
	 * `requesttoken` verification (deletion modal sends header + query param).
	 *
	 * @param int $id Time entry ID
	 * @return JSONResponse
	 */
	#[NoAdminRequired]
	public function delete($id)
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new JSONResponse(['error' => $this->l->t('User not authenticated')], 401);
		}

		try {
			// Get time entry info before deletion for activity logging
			$timeEntry = $this->timeEntryService->getTimeEntry($id);

			$this->timeEntryService->deleteTimeEntry($id, $user->getUID());

			// Log activity
			if ($timeEntry) {
				$this->activityService->logTimeEntryDeleted($user->getUID(), $timeEntry);
			}

			return new JSONResponse([
				'success' => true,
				'message' => 'Time entry deleted successfully'
			]);
		} catch (\Throwable $e) {
			$this->logger->error('Time entry delete failed', ['exception' => $e]);
			$status = $e instanceof \Exception ? 400 : 500;
			return new JSONResponse([
				'success' => false,
				'error' => $this->l->t('Could not delete time entry.')
			], $status);
		}
	}

	/**
	 * Get time entries for a project
	 *
	 * @param int $projectId Project ID
	 * @return JSONResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getForProject($projectId)
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new JSONResponse(['error' => $this->l->t('User not authenticated')], 401);
		}
		$projectId = (int) $projectId;
		if (!$this->projectService->canUserAccessProject($user->getUID(), $projectId)) {
			return new JSONResponse(['success' => false, 'error' => $this->l->t('Access denied')], 403);
		}

		$timeEntries = $this->timeEntryService->getTimeEntriesByProject($projectId);

		$results = [];
		foreach ($timeEntries as $timeEntry) {
			$results[] = $timeEntry->getSummary();
		}

		return new JSONResponse([
			'success' => true,
			'timeEntries' => $results
		]);
	}

	/**
	 * Get time entry statistics
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
		$stats = $this->timeEntryService->getTimeEntryStats($userId);

		return new JSONResponse([
			'success' => true,
			'stats' => $stats
		]);
	}

	/**
	 * Search time entries
	 *
	 * @return JSONResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[UserRateLimit(limit: 60, period: 60)]
	public function search()
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new JSONResponse(['error' => $this->l->t('User not authenticated')], 401);
		}

		$query = $this->request->getParam('q', '');
		$timeEntries = $this->timeEntryService->searchTimeEntries($query, $user->getUID());

		$results = [];
		foreach ($timeEntries as $timeEntry) {
			$results[] = $timeEntry->getSummary();
		}

		return new JSONResponse([
			'success' => true,
			'timeEntries' => $results
		]);
	}

	/**
	 * Export time entries to CSV.
	 *
	 * Rate-limited because export materialises the user's accessible
	 * time-entry rows into a single response: it is the most expensive
	 * read endpoint and the most attractive for data scraping.
	 *
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[UserRateLimit(limit: 10, period: 60)]
	public function export()
	{
		try {
			$user = $this->userSession->getUser();
			if (!$user) {
				return new DataResponse(['error' => $this->l->t('User not authenticated')], 401);
			}

			$currentUserId = $user->getUID();
			$accessibleProjectIds = $this->projectService->getAccessibleProjectIdListForUser($currentUserId);

			// Get filters from request
			$projectId = $this->request->getParam('project_id', '');
			$filterUserId = $this->request->getParam('user_id', '');
			$projectType = $this->request->getParam('project_type', '');
			$dateFrom = $this->request->getParam('date_from', '');
			$dateTo = $this->request->getParam('date_to', '');
			$search = $this->request->getParam('search', '');

			$filters = [];
			if ($projectId) $filters['project_id'] = $projectId;
			$canViewAllEntries = $this->projectService->canUserViewAllTimeEntries($currentUserId);
			if ($filterUserId) {
				$filters['user_id'] = $canViewAllEntries ? $filterUserId : $currentUserId;
			} elseif (!$canViewAllEntries) {
				$filters['user_id'] = $currentUserId;
			}
			if ($projectType) $filters['project_type'] = $projectType;
			if ($dateFrom) $filters['date_from'] = $dateFrom;
			if ($dateTo) $filters['date_to'] = $dateTo;
			if ($search) $filters['search'] = $search;
			if ($accessibleProjectIds !== null) {
				$filters['project_ids'] = $accessibleProjectIds;
			}

			// Get time entries with project and user information
			$timeEntries = $this->timeEntryService->getTimeEntriesWithProjectInfo($filters);

			// Generate CSV content
			$csv = $this->generateTimeEntriesCSV($timeEntries, $currentUserId);

			// Generate filename with current date
			$filename = 'time_entries_' . date('Y-m-d_H-i-s') . '.csv';

			return new DataResponse([
				'csv_data' => $csv,
				'filename' => $filename
			]);
		} catch (\Throwable $e) {
			$this->logger->error('Time entry export failed', ['exception' => $e]);
			return new DataResponse(['error' => $this->l->t('Export failed. Please try again.')], 500);
		}
	}

	/**
	 * Sort projects alphabetically by name (case-insensitive).
	 * Null-safe for PHP 8.1+ compatibility (strcasecmp rejects null).
	 *
	 * @param array<\OCA\ProjectCheck\Db\Project> $projects
	 * @return array<\OCA\ProjectCheck\Db\Project>
	 */
	private function sortProjectsByName(array $projects): array
	{
		usort($projects, static function ($a, $b) {
			$nameA = $a->getName() ?? '';
			$nameB = $b->getName() ?? '';
			return strcasecmp($nameA, $nameB);
		});
		return $projects;
	}

	/**
	 * Ensure the currently selected user filter is always present in the dropdown.
	 *
	 * This avoids a confusing state where URL/user filter is active but invisible
	 * because the user list source does not include that id in the current dataset.
	 *
	 * @param array<int, array{user_id:mixed, displayname:mixed}> $users
	 * @param string $selectedUserId
	 * @param array<int, array<string, mixed>> $timeEntries
	 * @return array<int, array{user_id:string, displayname:string}>
	 */
	private function ensureSelectedUserVisibleInFilters(array $users, string $selectedUserId, array $timeEntries): array
	{
		$normalizedUsers = [];
		$selectedPresent = false;

		foreach ($users as $user) {
			$uid = trim((string)($user['user_id'] ?? ''));
			if ($uid === '') {
				continue;
			}

			$displayName = trim((string)($user['displayname'] ?? ''));
			if ($displayName === '') {
				$displayName = $uid;
			}

			if ($uid === $selectedUserId) {
				$selectedPresent = true;
			}

			$normalizedUsers[] = [
				'user_id' => $uid,
				'displayname' => $displayName,
			];
		}

		if ($selectedUserId === '' || $selectedPresent) {
			return $normalizedUsers;
		}

		$fallbackLabel = $selectedUserId;
		foreach ($timeEntries as $entry) {
			if (!isset($entry['timeEntry']) || !is_object($entry['timeEntry']) || !method_exists($entry['timeEntry'], 'getUserId')) {
				continue;
			}
			$entryUserId = trim((string)$entry['timeEntry']->getUserId());
			if ($entryUserId !== $selectedUserId) {
				continue;
			}

			$candidateLabel = trim((string)($entry['userDisplayName'] ?? ''));
			if ($candidateLabel !== '') {
				$fallbackLabel = $candidateLabel;
			}
			break;
		}

		$normalizedUsers[] = [
			'user_id' => $selectedUserId,
			'displayname' => $fallbackLabel,
		];

		usort($normalizedUsers, static function (array $a, array $b): int {
			return strcasecmp($a['displayname'], $b['displayname']);
		});

		return $normalizedUsers;
	}

	/**
	 * Generate CSV content from time entries
	 *
	 * @param array $timeEntries
	 * @param string $userId
	 * @return string
	 */
	private function generateTimeEntriesCSV(array $timeEntries, string $userId): string
	{
		try {
			// CSV headers
			$headers = [
				'Date',
				'Project',
				'Customer',
				'Project Type',
				'Description',
				'Hours',
				'Hourly Rate (€)',
				'Total Amount (€)',
				'User',
				'Created At'
			];

			$output = fopen('php://temp', 'r+');
			if (!$output) {
				throw new \Exception('Failed to create temporary file for CSV generation');
			}

			// Write BOM for UTF-8
			fputs($output, "\xEF\xBB\xBF");

			// Write headers manually to ensure proper formatting
			fputs($output, implode(';', array_map(function ($header) {
				return '"' . str_replace('"', '""', $header) . '"';
			}, $headers)) . "\n");

			// Write data rows
			foreach ($timeEntries as $entry) {
				$timeEntry = $entry['timeEntry'];
				if (!$timeEntry || !is_object($timeEntry)) {
					$this->logger->warning('Skipping invalid time entry in CSV export', ['entry' => $entry]);
					continue;
				}

				$totalAmount = \OCA\ProjectCheck\Util\Money::asFloat(
					\OCA\ProjectCheck\Util\Money::mul(
						$timeEntry->getHours(),
						$timeEntry->getHourlyRate(),
						\OCA\ProjectCheck\Util\Money::MONEY_SCALE
					),
					\OCA\ProjectCheck\Util\Money::MONEY_SCALE
				);

				// Get project type display name
				$projectTypeDisplayName = $entry['project_type_display_name'] ?? $entry['project_type'] ?? 'Client Project';

				$row = [
					$timeEntry->getDate() ? $timeEntry->getDate()->format('Y-m-d') : '',
					$entry['projectName'] ?? 'Unknown Project',
					$entry['customerName'] ?? '',
					$projectTypeDisplayName,
					$timeEntry->getDescription() ?? '',
					number_format($timeEntry->getHours(), 2, ',', ''),
					number_format($timeEntry->getHourlyRate(), 2, ',', ''),
					number_format($totalAmount, 2, ',', ''),
					$entry['userDisplayName'] ?? $timeEntry->getUserId() ?? '',
					$timeEntry->getCreatedAt() ? $timeEntry->getCreatedAt()->format('Y-m-d H:i') : ''
				];


				// Write row manually to ensure proper formatting
				fputs($output, implode(';', array_map(function ($field) {
					return '"' . str_replace('"', '""', $field) . '"';
				}, $row)) . "\n");
			}

			rewind($output);
			$csv = stream_get_contents($output);
			fclose($output);

			return $csv;
		} catch (\Throwable $e) {
			$this->logger->error('CSV generation failed', ['exception' => $e]);
			throw $e;
		}
	}
}
