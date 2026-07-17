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
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\IURLGenerator;
use OCP\IConfig;
use OCA\ProjectCheck\Db\TimeEntry;
use OCA\ProjectCheck\Exception\BillingLockedException;
use OCA\ProjectCheck\Exception\PermissionDeniedException;
use OCA\ProjectCheck\Exception\SettlementConflictException;
use OCA\ProjectCheck\Exception\TimeEntryNotFoundException;
use OCA\ProjectCheck\Exception\ValidationException;
use OCA\ProjectCheck\Service\TimeEntryService;
use OCA\ProjectCheck\Service\TimeEntryBillingService;
use OCA\ProjectCheck\Util\BillingStatus;
use OCA\ProjectCheck\Util\Money;
use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Service\CustomerService;
use OCA\ProjectCheck\Service\BudgetService;
use OCA\ProjectCheck\Service\DeletionService;
use OCA\ProjectCheck\Service\ActivityService;
use OCA\ProjectCheck\Service\CSPService;
use OCA\ProjectCheck\Service\DateFormatService;
use OCA\ProjectCheck\Service\ListExportService;
use OCA\ProjectCheck\Util\CostRateMode;
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
	use ErrorPageTrait;
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

	/** @var ListExportService */
	private $listExportService;

	/** @var TimeEntryBillingService */
	private $billingService;

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
		LoggerInterface $logger,
		ListExportService $listExportService,
		TimeEntryBillingService $billingService
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
		$this->listExportService = $listExportService;
		$this->billingService = $billingService;
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
			$response = new TemplateResponse($this->appName, 'error', $this->errorPageGuest(
				$this->l->t('User not authenticated')
			), 'guest');
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
		$billingStatus = strtolower(trim((string)$this->request->getParam('billing_status', '')));
		if (!in_array($billingStatus, array_merge(BillingStatus::ALL, ['outstanding']), true)) {
			$billingStatus = '';
		}
		$page = max(1, (int)$this->request->getParam('page', 1));

		// Determine pagination settings (fixed 20 per page)
		$perPage = 20;

		// Settlement scope (spec D5b/§8.1): a Manager/creator sees the whole
		// team's entries on the projects they can settle. null = every project.
		$settleableProjectIds = $this->projectService->getSettleableProjectIdListForUser($userId);
		$canSettleAnything = $settleableProjectIds === null || $settleableProjectIds !== [];

		$filters = [];
		if ($projectId) $filters['project_id'] = $projectId;
		if ($dateFrom) {
			$filters['date_from'] = $dateFrom;
		}
		if ($dateTo) {
			$filters['date_to'] = $dateTo;
		}
		if ($search) $filters['search'] = $search;
		if ($billingStatus !== '') $filters['billing_status'] = $billingStatus;
		$canViewAllEntries = $this->projectService->canUserViewAllTimeEntries($userId);
		$hasScopedTeamView = !$canViewAllEntries && $settleableProjectIds !== null && $settleableProjectIds !== [];
		if ($filterUserId) {
			if ($canViewAllEntries) {
				$filters['user_id'] = $filterUserId;
			} elseif ($hasScopedTeamView && $filterUserId !== $userId) {
				// A scoped settler may inspect a teammate — but only on the
				// projects they manage (D5b), never org-wide.
				$filters['user_id'] = $filterUserId;
				$filters['project_ids'] = $settleableProjectIds;
			} else {
				$filters['user_id'] = $userId;
			}
		} elseif (!$canViewAllEntries) {
			if ($hasScopedTeamView) {
				// Own entries everywhere OR any entry on settleable projects.
				$filters['visible_to'] = [
					'user_id' => $userId,
					'project_ids' => $settleableProjectIds,
				];
			} else {
				$filters['user_id'] = $userId;
			}
		}
		if ($projectType) $filters['project_type'] = $projectType;
		// Project-access scoping protects *other people's* entries. When the result
		// set is already restricted to the requesting user's own entries, ownership
		// is sufficient visibility: applying the scope here would only hide the
		// user's own historical entries on projects they have since left — making
		// them unreachable for editing/deleting even though both are permitted.
		if (
			$accessibleProjectIds !== null
			&& ($filters['user_id'] ?? '') !== $userId
			&& !isset($filters['visible_to'])
			&& !isset($filters['project_ids'])
		) {
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
			'user_id' => ($canViewAllEntries || $hasScopedTeamView) ? $filterUserId : $userId,
			'project_type' => $projectType,
			'billing_status' => $billingStatus,
		];

		// Get time entries
		$timeEntries = $this->timeEntryService->getTimeEntriesWithProjectInfo($filters);

		$sumFilters = $filters;
		unset($sumFilters['limit'], $sumFilters['offset']);
		$selectionHoursTotal = $this->timeEntryService->sumTimeEntriesHours($sumFilters);
		$pageHoursTotal = $this->sumHoursOnCurrentPage($timeEntries);

		// Settlement summary strip: per-status sums over the *visible* result
		// set (ignores the billing_status filter itself so all four buckets
		// stay comparable while the user flips through them).
		$bucketFilters = $sumFilters;
		unset($bucketFilters['billing_status']);
		$billingBuckets = $this->billingService->getBillingBuckets($bucketFilters);

		// Get all projects for filter dropdown (incl. archived for viewing historical entries)
		$userProjects = $this->projectService->getProjectsForUserTimeEntry($user->getUID(), ['status' => ['Active', 'On Hold', 'Completed', 'Archived']]);

		// The dropdown must also offer projects the user can no longer access but
		// still owns entries on (e.g. their team membership ended) — those entries
		// are listed, so they must be filterable too.
		$listedProjectIds = [];
		foreach ($userProjects as $project) {
			$listedProjectIds[(int) $project->getId()] = true;
		}
		foreach ($this->timeEntryService->getProjectIdsWithEntriesForUser($userId) as $ownEntryProjectId) {
			if (isset($listedProjectIds[$ownEntryProjectId])) {
				continue;
			}
			$ownEntryProject = $this->projectService->getProject($ownEntryProjectId);
			if ($ownEntryProject !== null) {
				$userProjects[] = $ownEntryProject;
				$listedProjectIds[$ownEntryProjectId] = true;
			}
		}

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
			// null = all projects accessible; otherwise list of accessible ids.
			// Rows on inaccessible projects render the project name as text
			// instead of a link that would lead to an "Access denied" page.
			'accessibleProjectIds' => $accessibleProjectIds,
			'canViewAllEntries' => $canViewAllEntries,
			// Settlement (spec §12.2): buckets for the summary strip, scope for
			// the bulk bar + per-row checkboxes. null = may settle everywhere.
			'billingBuckets' => $billingBuckets,
			'canSettleAnything' => $canSettleAnything,
			'settleableProjectIds' => $settleableProjectIds,
			'billingBulkUrl' => $this->urlGenerator->linkToRoute('projectcheck.settlement.bulk'),
			'billingPreviewUrl' => $this->urlGenerator->linkToRoute('projectcheck.settlement.preview'),
			'billingEntryUrl' => $this->urlGenerator->linkToRoute('projectcheck.settlement.changeEntryStatus', ['id' => 'ENTRY_ID']),
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
			'deleteUrl' => $this->urlGenerator->linkToRoute('projectcheck.timeentry.deletePost', ['id' => 'ENTRY_ID']),
			'projectShowUrl' => $this->urlGenerator->linkToRoute('projectcheck.project.show', ['id' => 'PROJECT_ID']),
			'exportUrl' => $this->urlGenerator->linkToRoute('projectcheck.timeentry.export'),
			'selectionSummary' => [
				'hoursTotal' => $selectionHoursTotal,
				'entryCount' => $totalEntries,
				'pageHoursTotal' => $pageHoursTotal,
				'pageEntryCount' => count($timeEntries),
				'page' => $page,
				'totalPages' => $totalPages,
			],
		]);

		return $this->configureCSP($response);
	}

	/**
	 * @param array<int, array{timeEntry?: TimeEntry|null}> $timeEntries
	 */
	private function sumHoursOnCurrentPage(array $timeEntries): float
	{
		$total = Money::normalize(0, Money::HOUR_SCALE);
		foreach ($timeEntries as $entry) {
			$timeEntry = $entry['timeEntry'] ?? null;
			if (!$timeEntry instanceof TimeEntry) {
				continue;
			}
			$total = Money::add($total, Money::normalize($timeEntry->getHours() ?? 0, Money::HOUR_SCALE));
		}
		return Money::asFloat($total);
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
			$response = new TemplateResponse($this->appName, 'error', $this->errorPageGuest(
				$this->l->t('User not authenticated')
			), 'guest');
			return $this->configureCSP($response, 'guest');
		}

		$userId = $user->getUID();

		// Get all projects for time entry selection (exclude completed/cancelled).
		// `getProjectsForUserTimeEntry` already honours the membership / admin override
		// rules — non-member admins only see projects with a fixed project rate or
		// employee master rate.
		$userProjects = $this->projectService->getProjectsForUserTimeEntry($user->getUID(), ['status' => ['Active', 'On Hold']]);
		$userProjects = array_values(array_filter($userProjects, static function ($project) {
			$status = trim((string)$project->getStatus());
			return strcasecmp($status, 'Completed') !== 0
				&& strcasecmp($status, 'Cancelled') !== 0
				&& strcasecmp($status, 'Archived') !== 0;
		}));

		$userProjects = $this->sortProjectsByName($userProjects);

		$projectMembershipFlags = $this->buildProjectMembershipFlags($userId, $userProjects);

		$stats = $this->getCommonStats($this->projectService, $this->customerService, $this->timeEntryService, $user->getUID());

		$response = new TemplateResponse($this->appName, 'time-entry-form', [
			'timeEntry' => null,
			'projects' => $userProjects,
			'projectMembershipFlags' => $projectMembershipFlags,
			'isEdit' => false,
			'stats' => $stats,
			'indexUrl' => $this->urlGenerator->linkToRoute('projectcheck.timeentry.index'),
			'storeUrl' => $this->urlGenerator->linkToRoute('projectcheck.timeentry.store'),
			'updateUrl' => $this->urlGenerator->linkToRoute('projectcheck.timeentry.update', ['id' => 'TIME_ENTRY_ID']),
		]);

		return $this->configureCSP($response);
	}

	/**
	 * Build a per-project map flagging team membership and admin override usage,
	 * so the form can render a clear "logging as administrator" notice for the
	 * currently selected project without an extra round-trip.
	 *
	 * @param list<\OCA\ProjectCheck\Db\Project> $projects
	 * @return array<int, array{is_team_member: bool, admin_override: bool}>
	 */
	private function buildProjectMembershipFlags(string $userId, array $projects): array
	{
		$flags = [];
		foreach ($projects as $project) {
			$pid = (int) $project->getId();
			if ($pid <= 0) {
				continue;
			}
			$isMember = $this->projectService->isActiveTeamMember($pid, $userId);
			$flags[$pid] = [
				'is_team_member' => $isMember,
				// Admin override is *visible* only when the user is not on the team
				// but is still allowed to log time on this project.
				'admin_override' => !$isMember
					&& $this->projectService->isUsingAdminTimeEntryOverride($userId, $pid),
			];
		}
		return $flags;
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
		} catch (PermissionDeniedException $e) {
			return new JSONResponse([
				'success' => false,
				'error' => $this->l->t('Access denied')
			], 403);
		} catch (ValidationException $e) {
			// Business-rule rejection with an intentional, localized message.
			$this->logger->info('Time entry creation rejected', ['exception' => $e]);
			$message = trim($e->getMessage());
			return new JSONResponse([
				'success' => false,
				'error' => $message !== '' ? $message : $this->l->t('Could not create time entry. Please check your input.')
			], 400);
		} catch (\Throwable $e) {
			$this->logger->error('Time entry creation failed', ['exception' => $e]);
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
	public function show(int $id)
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			$response = new TemplateResponse($this->appName, 'error', $this->errorPage(
				$this->l->t('User not authenticated')
			));
			return $this->configureCSP($response);
		}

		$timeEntry = $this->timeEntryService->getTimeEntry($id);
		if (!$timeEntry) {
			$response = new TemplateResponse($this->appName, 'error', $this->errorPageGuest(
				$this->l->t('Time entry not found')
			), 'guest');
			return $this->configureCSP($response, 'guest');
		}

		$uid = $user->getUID();
		$isOwner = $timeEntry->isOwnedBy($uid);
		// Owner, global viewer, or scoped settler (Manager/creator — spec D5b).
		if (!$isOwner && !$this->projectService->canUserViewTimeEntry($uid, $timeEntry)) {
			$response = new TemplateResponse($this->appName, 'error', $this->errorPageGuest(
				$this->l->t('Access denied')
			), 'guest');
			return $this->configureCSP($response, 'guest');
		}

		$project = $this->projectService->getProject($timeEntry->getProjectId());
		$projectName = $project ? $project->getName() : (string) $this->l->t('Unknown project');
		$projectShowUrl = $this->urlGenerator->linkToRoute('projectcheck.project.show', ['id' => $timeEntry->getProjectId()]);
		$pricingRateSourceLabel = $project ? $this->pricingModeLabel($project->getCostRateMode()) : '';

		// Get common stats for the sidebar
		$stats = $this->getCommonStats($this->projectService, $this->customerService, $this->timeEntryService, $user->getUID());

		$response = new TemplateResponse($this->appName, 'time-entry-detail', [
			'timeEntry' => $timeEntry,
			'project' => $project,
			'projectName' => $projectName,
			'projectShowUrl' => $projectShowUrl,
			'projectLinkable' => $this->projectService->canUserAccessProject($uid, (int) $timeEntry->getProjectId()),
			'pricingRateSourceLabel' => $pricingRateSourceLabel,
			'stats' => $stats,
			'urlGenerator' => $this->urlGenerator,
			'userId' => $user->getUID(),
			// Settlement: settlers get the transition buttons; owners get the
			// lock explanation when the entry is invoiced/paid (spec §12).
			'canSettleEntry' => $this->projectService->canUserSettleProject($uid, (int) $timeEntry->getProjectId()),
			'billingEntryUrl' => $this->urlGenerator->linkToRoute('projectcheck.settlement.changeEntryStatus', ['id' => (string) $timeEntry->getId()]),
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
	public function edit(int $id)
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			$response = new TemplateResponse($this->appName, 'error', $this->errorPageGuest(
				$this->l->t('User not authenticated')
			), 'guest');
			return $this->configureCSP($response, 'guest');
		}

		$timeEntry = $this->timeEntryService->getTimeEntry($id);
		if (!$timeEntry) {
			$response = new TemplateResponse($this->appName, 'error', $this->errorPageGuest(
				$this->l->t('Time entry not found')
			), 'guest');
			return $this->configureCSP($response, 'guest');
		}

		// Check if user has access to this time entry
		if (!$timeEntry->isOwnedBy($user->getUID())) {
			$response = new TemplateResponse($this->appName, 'error', $this->errorPageGuest(
				$this->l->t('Access denied')
			), 'guest');
			return $this->configureCSP($response, 'guest');
		}

		$userId = $user->getUID();
		// Get all projects for time entry selection (incl. archived; moving to a closed project is blocked in the service)
		$userProjects = $this->projectService->getProjectsForUserTimeEntry($user->getUID(), ['status' => ['Active', 'On Hold', 'Completed', 'Archived']]);

		// The entry's own project must always be present in the dropdown, even when
		// the owner can no longer see it through the normal picker rules (e.g. their
		// team membership ended). Otherwise the required select renders without a
		// selected option and the form cannot be submitted at all.
		$currentProjectId = (int) $timeEntry->getProjectId();
		$currentProjectListed = false;
		foreach ($userProjects as $project) {
			if ((int) $project->getId() === $currentProjectId) {
				$currentProjectListed = true;
				break;
			}
		}
		if (!$currentProjectListed) {
			$currentProject = $this->projectService->getProject($currentProjectId);
			if ($currentProject !== null) {
				$userProjects[] = $currentProject;
			}
		}

		$userProjects = $this->sortProjectsByName($userProjects);

		$projectMembershipFlags = $this->buildProjectMembershipFlags($userId, $userProjects);

		$stats = $this->getCommonStats($this->projectService, $this->customerService, $this->timeEntryService, $user->getUID());

		$response = new TemplateResponse($this->appName, 'time-entry-form', [
			'timeEntry' => $timeEntry,
			'projects' => $userProjects,
			'projectMembershipFlags' => $projectMembershipFlags,
			'isEdit' => true,
			'stats' => $stats,
			'userId' => $userId,
			'indexUrl' => $this->urlGenerator->linkToRoute('projectcheck.timeentry.index'),
			'storeUrl' => $this->urlGenerator->linkToRoute('projectcheck.timeentry.store'),
			'updateUrl' => $this->urlGenerator->linkToRoute('projectcheck.timeentry.updatePost', ['id' => $timeEntry->getId()]),
			'deleteUrl' => $this->urlGenerator->linkToRoute('projectcheck.timeentry.deletePost', ['id' => $timeEntry->getId()]),
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
	public function update(int $id)
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
		} catch (TimeEntryNotFoundException $e) {
			return new JSONResponse([
				'success' => false,
				'error' => $this->l->t('Time entry not found')
			], 404);
		} catch (PermissionDeniedException $e) {
			return new JSONResponse([
				'success' => false,
				'error' => $this->l->t('Access denied')
			], 403);
		} catch (BillingLockedException $e) {
			return new JSONResponse([
				'success' => false,
				'error' => $e->getMessage(),
				'code' => 'billing_locked',
				'billingStatus' => $e->getBillingStatus(),
			], 409);
		} catch (SettlementConflictException $e) {
			return new JSONResponse([
				'success' => false,
				'error' => $e->getMessage(),
				'code' => $e->getConflictCode(),
			], 409);
		} catch (ValidationException $e) {
			// Business-rule rejection with an intentional, localized message.
			$this->logger->info('Time entry update rejected', ['exception' => $e]);
			$message = trim($e->getMessage());
			return new JSONResponse([
				'success' => false,
				'error' => $message !== '' ? $message : $this->l->t('Could not update time entry. Please check your input.')
			], 400);
		} catch (\Throwable $e) {
			$this->logger->error('Time entry update failed', ['exception' => $e]);
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
	public function updatePost(int $id)
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
		if (!$te || !$te->isOwnedBy($user->getUID())) {
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
	public function delete(int $id)
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
				'message' => $this->l->t('Time entry was deleted successfully!')
			]);
		} catch (TimeEntryNotFoundException $e) {
			return new JSONResponse([
				'success' => false,
				'error' => $this->l->t('Time entry not found')
			], 404);
		} catch (PermissionDeniedException $e) {
			return new JSONResponse([
				'success' => false,
				'error' => $this->l->t('Access denied')
			], 403);
		} catch (BillingLockedException $e) {
			return new JSONResponse([
				'success' => false,
				'error' => $e->getMessage(),
				'code' => 'billing_locked',
				'billingStatus' => $e->getBillingStatus(),
			], 409);
		} catch (SettlementConflictException $e) {
			return new JSONResponse([
				'success' => false,
				'error' => $e->getMessage(),
				'code' => $e->getConflictCode(),
			], 409);
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
	 * Delete time entry via POST (deletion modal — CSRF-safe).
	 *
	 * The id parameter is natively typed so the AppFramework dispatcher casts the
	 * route segment to int before the call (a string id previously caused a
	 * TypeError → HTTP 500 on every UI delete).
	 *
	 * @param int $id Time entry ID
	 * @return JSONResponse
	 */
	#[NoAdminRequired]
	public function deletePost(int $id): JSONResponse
	{
		$response = $this->delete($id);
		if ($response instanceof JSONResponse) {
			return $response;
		}

		return new JSONResponse([
			'success' => false,
			'error' => $this->l->t('Could not delete time entry.'),
		], 400);
	}

	/**
	 * Get time entries for a project
	 *
	 * @param int $projectId Project ID
	 * @return JSONResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getForProject(int $projectId)
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new JSONResponse(['error' => $this->l->t('User not authenticated')], 401);
		}
		if (!$this->projectService->canUserAccessProject($user->getUID(), $projectId)) {
			return new JSONResponse(['success' => false, 'error' => $this->l->t('Access denied')], 403);
		}

		$uid = $user->getUID();
		$timeEntries = $this->timeEntryService->getTimeEntriesByProject($projectId);

		// D5b / E20c: Members only see their own entries; Managers / global
		// viewers see the full project set. Never dump teammate hours to Members.
		$results = [];
		foreach ($timeEntries as $timeEntry) {
			if (!$this->projectService->canUserViewTimeEntry($uid, $timeEntry)) {
				continue;
			}
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
	 * Export time entries as CSV or JSON.
	 *
	 * Rate-limited because export materialises the user's accessible
	 * time-entry rows into a single response: it is the most expensive
	 * read endpoint and the most attractive for data scraping.
	 * Format is chosen via `?format=csv|json` (default csv).
	 * Success returns a direct file download ({@see DataDownloadResponse});
	 * auth/validation failures return JSON {@see DataResponse}.
	 *
	 * @return Response
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[UserRateLimit(limit: 10, period: 60)]
	public function export(): Response
	{
		try {
			$user = $this->userSession->getUser();
			if (!$user) {
				return new DataResponse(['error' => $this->l->t('User not authenticated')], 401);
			}

			$currentUserId = $user->getUID();
			$accessibleProjectIds = $this->projectService->getAccessibleProjectIdListForUser($currentUserId);
			$format = $this->listExportService->normalizeFormat(
				(string)$this->request->getParam('format', ListExportService::FORMAT_CSV)
			);

			// Get filters from request
			$projectId = $this->request->getParam('project_id', '');
			$filterUserId = $this->request->getParam('user_id', '');
			$projectType = $this->request->getParam('project_type', '');
			$dateFrom = $this->request->getParam('date_from', '');
			$dateTo = $this->request->getParam('date_to', '');
			$search = $this->request->getParam('search', '');

			$billingStatus = strtolower(trim((string)$this->request->getParam('billing_status', '')));
			if (!in_array($billingStatus, array_merge(BillingStatus::ALL, ['outstanding']), true)) {
				$billingStatus = '';
			}

			$filters = [];
			if ($projectId) $filters['project_id'] = $projectId;
			$canViewAllEntries = $this->projectService->canUserViewAllTimeEntries($currentUserId);
			// Mirror index(): scoped settlers export what they can see (D5b).
			$settleableProjectIds = $this->projectService->getSettleableProjectIdListForUser($currentUserId);
			$hasScopedTeamView = !$canViewAllEntries && $settleableProjectIds !== null && $settleableProjectIds !== [];
			if ($filterUserId) {
				if ($canViewAllEntries) {
					$filters['user_id'] = $filterUserId;
				} elseif ($hasScopedTeamView && $filterUserId !== $currentUserId) {
					$filters['user_id'] = $filterUserId;
					$filters['project_ids'] = $settleableProjectIds;
				} else {
					$filters['user_id'] = $currentUserId;
				}
			} elseif (!$canViewAllEntries) {
				if ($hasScopedTeamView) {
					$filters['visible_to'] = [
						'user_id' => $currentUserId,
						'project_ids' => $settleableProjectIds,
					];
				} else {
					$filters['user_id'] = $currentUserId;
				}
			}
			if ($projectType) $filters['project_type'] = $projectType;
			if ($dateFrom) $filters['date_from'] = $dateFrom;
			if ($dateTo) $filters['date_to'] = $dateTo;
			if ($search) $filters['search'] = $search;
			if ($billingStatus !== '') $filters['billing_status'] = $billingStatus;
			// Same rule as index(): only scope by project access when the rows may
			// belong to someone else. A user's own entries are always exportable.
			if (
				$accessibleProjectIds !== null
				&& ($filters['user_id'] ?? '') !== $currentUserId
				&& !isset($filters['visible_to'])
				&& !isset($filters['project_ids'])
			) {
				$filters['project_ids'] = $accessibleProjectIds;
			}

			$timeEntries = $this->timeEntryService->getTimeEntriesWithProjectInfo($filters);
			if ($this->listExportService->exceedsMaxRows(count($timeEntries))) {
				return new DataResponse([
					'error' => $this->l->t(
						'Too many rows to export (limit %s). Narrow your filters and try again.',
						[number_format(ListExportService::MAX_EXPORT_ROWS)]
					),
				], 422);
			}

			$packed = $this->listExportService->exportTimeEntries($timeEntries, $format);
			return $this->listExportService->toDownloadResponse($packed);
		} catch (\Throwable $e) {
			$this->logger->error('Time entry export failed', ['exception' => $e]);
			return new DataResponse(['error' => $this->l->t('Export failed. Please try again.')], 500);
		}
	}

	/**
	 * Sort projects alphabetically by name using a case-insensitive
	 * *natural* order, so that embedded numbers compare numerically
	 * ("Support 2" before "Support 10") instead of lexicographically.
	 *
	 * Names are trimmed before comparison so that stray leading/trailing
	 * whitespace in stored project names doesn't push a project to the top
	 * (or bottom) of the dropdown. Null-safe for PHP 8.1+ compatibility
	 * (strnatcasecmp rejects null).
	 *
	 * @param array<\OCA\ProjectCheck\Db\Project> $projects
	 * @return array<\OCA\ProjectCheck\Db\Project>
	 */
	private function sortProjectsByName(array $projects): array
	{
		usort($projects, static function ($a, $b) {
			$nameA = trim($a->getName() ?? '');
			$nameB = trim($b->getName() ?? '');
			return strnatcasecmp($nameA, $nameB);
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

	private function pricingModeLabel(string $mode): string
	{
		return match (CostRateMode::normalize($mode)) {
			CostRateMode::EMPLOYEE => $this->l->t('Rate per employee (master data)'),
			CostRateMode::PROJECT_MEMBER => $this->l->t('Rate per person on this project'),
			default => $this->l->t('One rate for the whole project'),
		};
	}
}
