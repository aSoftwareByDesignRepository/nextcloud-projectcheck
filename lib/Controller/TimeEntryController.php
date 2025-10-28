<?php

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
use OCP\IRequest;
use OCP\IUserSession;
use OCP\IURLGenerator;
use OCP\IConfig;
use OCA\ProjectCheck\Service\TimeEntryService;
use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Service\CustomerService;
use OCA\ProjectCheck\Service\BudgetService;
use OCA\ProjectCheck\Service\DateFormatService;
use OCA\ProjectCheck\Service\DeletionService;
use OCA\ProjectCheck\Service\ActivityService;
use OCA\ProjectCheck\Service\CSPService;
use OCA\ProjectCheck\Traits\StatsTrait;
use OCA\ProjectCheck\Controller\CSPTrait;

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

	/**
	 * TimeEntryController constructor
	 *
	 * @param string $appName
	 * @param IRequest $request
	 * @param IUserSession $userSession
	 * @param TimeEntryService $timeEntryService
	 * @param ProjectService $projectService
	 * @param CustomerService $customerService
	 * @param IURLGenerator $urlGenerator
	 * @param IConfig $config
	 * @param DateFormatService $dateFormatService
	 * @param DeletionService $deletionService
	 * @param ActivityService $activityService
	 * @param CSPService $cspService
	 */
	public function __construct(
		$appName,
		IRequest $request,
		IUserSession $userSession,
		TimeEntryService $timeEntryService,
		ProjectService $projectService,
		CustomerService $customerService,
		IURLGenerator $urlGenerator,
		IConfig $config,
		DateFormatService $dateFormatService,
		DeletionService $deletionService,
		ActivityService $activityService,
		CSPService $cspService
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
		$this->setCspService($cspService);
	}

	/**
	 * Show time entry list page
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

		// Get filters from request
		$projectId = $this->request->getParam('project_id', '');
		$dateFrom = $this->request->getParam('date_from', '');
		$dateTo = $this->request->getParam('date_to', '');
		$search = $this->request->getParam('search', '');
		$filterUserId = $this->request->getParam('user_id', '');
		$projectType = $this->request->getParam('project_type', '');

		$filters = [];
		if ($projectId) $filters['project_id'] = $projectId;
		if ($dateFrom) {
			// Convert user format to ISO format for database queries
			$parsedDate = $this->dateFormatService->parseDate($dateFrom, $userId);
			$filters['date_from'] = $parsedDate ? $parsedDate->format('Y-m-d') : $dateFrom;
		}
		if ($dateTo) {
			// Convert user format to ISO format for database queries
			$parsedDate = $this->dateFormatService->parseDate($dateTo, $userId);
			$filters['date_to'] = $parsedDate ? $parsedDate->format('Y-m-d') : $dateTo;
		}
		if ($search) $filters['search'] = $search;
		if ($filterUserId) $filters['user_id'] = $filterUserId;
		if ($projectType) $filters['project_type'] = $projectType;

		// Keep original filter values for the form (in ISO format for date inputs)
		$formFilters = [
			'project_id' => $projectId,
			'date_from' => $dateFrom,
			'date_to' => $dateTo,
			'search' => $search,
			'user_id' => $filterUserId,
			'project_type' => $projectType
		];

		// Get time entries
		$timeEntries = $this->timeEntryService->getTimeEntriesWithProjectInfo($filters);

		// Get all projects for filter dropdown (excluding cancelled projects)
		$userProjects = $this->projectService->getProjects(['status' => ['Active', 'On Hold', 'Completed']]);

		// Get all users who have time entries
		$users = $this->timeEntryService->getUsersWithTimeEntries();

		// Get common stats for the sidebar
		$stats = $this->getCommonStats($this->projectService, $this->customerService);

		// Get project type statistics for the current filters
		$projectTypeStats = $this->timeEntryService->getYearlyStatsByProjectType();
		$detailedProjectTypeStats = $this->timeEntryService->getDetailedYearlyStatsByProjectType();
		$productivityAnalysis = $this->timeEntryService->getProductivityAnalysis();

		// Get user's date format setting
		$dateFormat = $this->dateFormatService->getUserDateFormat($userId);
		$availableDateFormats = $this->dateFormatService->getAvailableDateFormats();

		$response = new TemplateResponse($this->appName, 'time-entries', [
			'timeEntries' => $timeEntries,
			'projects' => $userProjects,
			'users' => $users,
			'filters' => $formFilters,
			'userId' => $userId,
			'dateFormat' => $dateFormat,
			'availableDateFormats' => $availableDateFormats,
			'dateFormatService' => $this->dateFormatService,
			'stats' => $stats,
			'projectTypeStats' => $projectTypeStats,
			'detailedProjectTypeStats' => $detailedProjectTypeStats,
			'productivityAnalysis' => $productivityAnalysis,
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
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @return TemplateResponse
	 */
	public function create()
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			$response = new TemplateResponse($this->appName, 'error', [
				'message' => 'User not authenticated'
			], 'guest');
			return $this->configureCSP($response, 'guest');
		}

		$userId = $user->getUID();

		// Get all projects for time entry selection (excluding cancelled projects)
		$userProjects = $this->projectService->getProjects(['status' => ['Active', 'On Hold', 'Completed']]);

		// Get common stats for the sidebar
		$stats = $this->getCommonStats($this->projectService, $this->customerService);

		$response = new TemplateResponse($this->appName, 'time-entry-form', [
			'timeEntry' => null,
			'projects' => $userProjects,
			'isEdit' => false,
			'stats' => $stats,
			'indexUrl' => $this->urlGenerator->linkToRoute('projectcheck.timeentry.index'),
			'storeUrl' => $this->urlGenerator->linkToRoute('projectcheck.timeentry.store'),
			'updateUrl' => $this->urlGenerator->linkToRoute('projectcheck.timeentry.update', ['id' => 'TIME_ENTRY_ID'])
		]);

		return $this->configureCSP($response);
	}

	/**
	 * Store new time entry
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @return JSONResponse
	 */
	public function store()
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new JSONResponse(['error' => 'User not authenticated'], 401);
		}

		$userId = $user->getUID();

		// Get data from JSON request body
		$jsonData = file_get_contents('php://input');
		$data = json_decode($jsonData, true);

		// Fallback to getParams if JSON parsing fails
		if ($data === null) {
			$data = $this->request->getParams();
		}

		// Debug: Log the received data
		error_log('TimeEntry store - Received data: ' . print_r($data, true));



		try {
			// Debug: Log validation step
			error_log('TimeEntry store - Starting validation');

			// Validate data
			$errors = $this->timeEntryService->validateTimeEntryData($data);
			if (!empty($errors)) {
				error_log('TimeEntry store - Validation errors: ' . print_r($errors, true));
				return new JSONResponse([
					'success' => false,
					'errors' => $errors
				], 400);
			}

			error_log('TimeEntry store - Validation passed, creating time entry');

			// Create time entry
			$timeEntry = $this->timeEntryService->createTimeEntry($data, $userId);

			error_log('TimeEntry store - Time entry created successfully');

			return new JSONResponse([
				'success' => true,
				'timeEntry' => $timeEntry->getSummary(),
				'message' => 'Time entry created successfully'
			]);
		} catch (\Exception $e) {
			error_log('TimeEntry store - Exception: ' . $e->getMessage());
			error_log('TimeEntry store - Exception trace: ' . $e->getTraceAsString());
			return new JSONResponse([
				'success' => false,
				'error' => $e->getMessage()
			], 400);
		}
	}

	/**
	 * Show time entry detail page
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @param int $id Time entry ID
	 * @return TemplateResponse
	 */
	public function show($id)
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			$response = new TemplateResponse($this->appName, 'error', [
				'message' => 'User not authenticated'
			]);
			return $this->configureCSP($response);
		}

		$timeEntry = $this->timeEntryService->getTimeEntry($id);
		if (!$timeEntry) {
			$response = new TemplateResponse($this->appName, 'error', [
				'message' => 'Time entry not found'
			], 'guest');
			return $this->configureCSP($response, 'guest');
		}

		// Get project info for display - no access restrictions for viewing
		$project = $this->projectService->getProject($timeEntry->getProjectId());

		$projectName = $project ? $project->getName() : 'Unknown Project';

		// Get common stats for the sidebar
		$stats = $this->getCommonStats($this->projectService, $this->customerService);

		$response = new TemplateResponse($this->appName, 'time-entry-detail', [
			'timeEntry' => $timeEntry,
			'projectName' => $projectName,
			'stats' => $stats,
			'urlGenerator' => $this->urlGenerator,
			'userId' => $user->getUID()
		]);

		return $this->configureCSP($response);
	}

	/**
	 * Show time entry edit form
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @param int $id Time entry ID
	 * @return TemplateResponse
	 */
	public function edit($id)
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			$response = new TemplateResponse($this->appName, 'error', [
				'message' => 'User not authenticated'
			], 'guest');
			return $this->configureCSP($response, 'guest');
		}

		$timeEntry = $this->timeEntryService->getTimeEntry($id);
		if (!$timeEntry) {
			$response = new TemplateResponse($this->appName, 'error', [
				'message' => 'Time entry not found'
			], 'guest');
			return $this->configureCSP($response, 'guest');
		}

		// Check if user has access to this time entry
		if ($timeEntry->getUserId() !== $user->getUID()) {
			$response = new TemplateResponse($this->appName, 'error', [
				'message' => 'Access denied'
			], 'guest');
			return $this->configureCSP($response, 'guest');
		}

		$userId = $user->getUID();
		// Get all projects for time entry selection (excluding cancelled projects)
		$userProjects = $this->projectService->getProjects(['status' => ['Active', 'On Hold', 'Completed']]);

		// Get common stats for the sidebar
		$stats = $this->getCommonStats($this->projectService, $this->customerService);

		$response = new TemplateResponse($this->appName, 'time-entry-form', [
			'timeEntry' => $timeEntry,
			'projects' => $userProjects,
			'isEdit' => true,
			'stats' => $stats,
			'indexUrl' => $this->urlGenerator->linkToRoute('projectcheck.timeentry.index'),
			'storeUrl' => $this->urlGenerator->linkToRoute('projectcheck.timeentry.store'),
			'updateUrl' => $this->urlGenerator->linkToRoute('projectcheck.timeentry.updatePost', ['id' => $timeEntry->getId()])
		]);

		return $this->configureCSP($response);
	}

	/**
	 * Update time entry
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @param int $id Time entry ID
	 * @return JSONResponse
	 */
	public function update($id)
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new JSONResponse(['error' => 'User not authenticated'], 401);
		}

		// Accept JSON body (AJAX) or form-encoded params (HTML form)
		$jsonData = file_get_contents('php://input');
		$data = json_decode($jsonData, true);
		if ($data === null || !is_array($data)) {
			$data = $this->request->getParams();
		}

		try {
			// Validate data
			$errors = $this->timeEntryService->validateTimeEntryData($data);
			if (!empty($errors)) {
				return new JSONResponse([
					'success' => false,
					'errors' => $errors
				], 400);
			}

			// Update time entry
			$timeEntry = $this->timeEntryService->updateTimeEntry($id, $data, $user->getUID());

			return new JSONResponse([
				'success' => true,
				'timeEntry' => $timeEntry->getSummary(),
				'message' => 'Time entry updated successfully'
			]);
		} catch (\Exception $e) {
			return new JSONResponse([
				'success' => false,
				'error' => $e->getMessage()
			], 400);
		}
	}

	/**
	 * Update time entry via POST for forms that cannot send PUT
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @param int $id Time entry ID
	 * @return JSONResponse
	 */
	public function updatePost($id)
	{
		// Delegate to update() to keep logic in one place
		return $this->update($id);
	}

	/**
	 * Get deletion impact for a time entry
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @param int $id
	 * @return JSONResponse
	 */
	public function getDeletionImpact(int $id): JSONResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new JSONResponse(['error' => 'User not authenticated'], 401);
		}

		try {
			$impact = $this->timeEntryService->getTimeEntryDeletionImpact($id);
			return new JSONResponse(['success' => true, 'impact' => $impact]);
		} catch (\Exception $e) {
			return new JSONResponse(['success' => false, 'error' => $e->getMessage()], 400);
		}
	}

	/**
	 * Delete time entry
	 *
	 * @param int $id Time entry ID
	 * @return JSONResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function delete($id)
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new JSONResponse(['error' => 'User not authenticated'], 401);
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
		} catch (\Exception $e) {
			return new JSONResponse([
				'success' => false,
				'error' => $e->getMessage()
			], 400);
		}
	}

	/**
	 * Get time entries for a project
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @param int $projectId Project ID
	 * @return JSONResponse
	 */
	public function getForProject($projectId)
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new JSONResponse(['error' => 'User not authenticated'], 401);
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
		$stats = $this->timeEntryService->getTimeEntryStats($userId);

		return new JSONResponse([
			'success' => true,
			'stats' => $stats
		]);
	}

	/**
	 * Search time entries
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @return JSONResponse
	 */
	public function search()
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new JSONResponse(['error' => 'User not authenticated'], 401);
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
	 * Export time entries to CSV
	 *
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function export()
	{
		try {
			$user = $this->userSession->getUser();
			if (!$user) {
				return new DataResponse(['error' => 'User not authenticated'], 401);
			}

			$currentUserId = $user->getUID();

			// Get filters from request
			$projectId = $this->request->getParam('project_id', '');
			$filterUserId = $this->request->getParam('user_id', '');
			$projectType = $this->request->getParam('project_type', '');
			$dateFrom = $this->request->getParam('date_from', '');
			$dateTo = $this->request->getParam('date_to', '');
			$search = $this->request->getParam('search', '');

			$filters = [];
			if ($projectId) $filters['project_id'] = $projectId;
			if ($filterUserId) $filters['user_id'] = $filterUserId;
			if ($projectType) $filters['project_type'] = $projectType;
			if ($dateFrom) $filters['date_from'] = $dateFrom;
			if ($dateTo) $filters['date_to'] = $dateTo;
			if ($search) $filters['search'] = $search;

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
		} catch (\Exception $e) {
			error_log('TimeEntryController::export() - Exception: ' . $e->getMessage());
			return new DataResponse(['error' => 'Export failed: ' . $e->getMessage()], 500);
		}
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
					error_log('Skipping invalid time entry: ' . json_encode($entry));
					continue;
				}

				$totalAmount = $timeEntry->getHours() * $timeEntry->getHourlyRate();

				// Get project type display name
				$projectTypeDisplayName = $entry['project_type_display_name'] ?? $entry['project_type'] ?? 'Client Project';

				$row = [
					$this->dateFormatService->formatDate($timeEntry->getDate(), $userId),
					$entry['projectName'] ?? 'Unknown Project',
					$entry['customerName'] ?? '',
					$projectTypeDisplayName,
					$timeEntry->getDescription() ?? '',
					number_format($timeEntry->getHours(), 2, ',', ''),
					number_format($timeEntry->getHourlyRate(), 2, ',', ''),
					number_format($totalAmount, 2, ',', ''),
					$entry['userDisplayName'] ?? $timeEntry->getUserId() ?? '',
					$this->dateFormatService->formatDateTime($timeEntry->getCreatedAt(), $userId)
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
		} catch (\Exception $e) {
			error_log('generateTimeEntriesCSV() - Exception: ' . $e->getMessage());
			throw $e;
		}
	}
}
