<?php

declare(strict_types=1);

/**
 * Employee controller for projectcheck app
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
use OCP\AppFramework\Http\RedirectResponse;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCA\ProjectCheck\Service\TimeEntryService;
use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Service\EmployeeHourlyRateService;
use OCA\ProjectCheck\Util\CostRateMode;
use OCA\ProjectCheck\Service\CustomerService;
use OCA\ProjectCheck\Service\CSPService;
use OCA\ProjectCheck\Service\AccessControlService;
use OCA\ProjectCheck\Db\UserAccountSnapshotMapper;
use OCA\ProjectCheck\Service\IRequestTokenProvider;
use OCP\IConfig;
use OCP\IL10N;
use OCA\ProjectCheck\Exception\RateResolutionException;
use OCA\ProjectCheck\Util\RateResolutionMessage;
use OCA\ProjectCheck\Traits\StatsTrait;

/**
 * Employee controller for employee management and statistics
 */
class EmployeeController extends Controller
{
    use CSPTrait;
    use ErrorPageTrait;
    use StatsTrait;

    /** @var IUserSession */
    private $userSession;

    /** @var IUserManager */
    private $userManager;

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

	/** @var IL10N */
	private $l;

	/** @var UserAccountSnapshotMapper */
	private $userAccountSnapshotMapper;

	/** @var AccessControlService */
	private $accessControlService;

	/** @var EmployeeHourlyRateService */
	private $employeeHourlyRateService;

	/** @var IRequestTokenProvider */
	private $requestTokenProvider;

    /**
     * EmployeeController constructor
     *
     * @param string $appName
     * @param IRequest $request
     * @param IUserSession $userSession
     * @param IUserManager $userManager
     * @param TimeEntryService $timeEntryService
     * @param ProjectService $projectService
     * @param CustomerService $customerService
	 * @param IURLGenerator $urlGenerator
	 * @param IConfig $config
     * @param CSPService $cspService
	 * @param IL10N $l
	 * @param UserAccountSnapshotMapper $userAccountSnapshotMapper
	 * @param AccessControlService $accessControlService
	 * @param EmployeeHourlyRateService $employeeHourlyRateService
	 * @param IRequestTokenProvider $requestTokenProvider
     */
    public function __construct(
        $appName,
        IRequest $request,
        IUserSession $userSession,
        IUserManager $userManager,
        TimeEntryService $timeEntryService,
        ProjectService $projectService,
        EmployeeHourlyRateService $employeeHourlyRateService,
        CustomerService $customerService,
		IURLGenerator $urlGenerator,
		IConfig $config,
        CSPService $cspService,
        IL10N $l,
		UserAccountSnapshotMapper $userAccountSnapshotMapper,
		AccessControlService $accessControlService,
		IRequestTokenProvider $requestTokenProvider
    ) {
        parent::__construct($appName, $request);
        $this->userSession = $userSession;
        $this->userManager = $userManager;
        $this->timeEntryService = $timeEntryService;
        $this->projectService = $projectService;
        $this->customerService = $customerService;
        $this->urlGenerator = $urlGenerator;
		$this->config = $config;
		$this->l = $l;
		$this->userAccountSnapshotMapper = $userAccountSnapshotMapper;
		$this->accessControlService = $accessControlService;
		$this->employeeHourlyRateService = $employeeHourlyRateService;
		$this->requestTokenProvider = $requestTokenProvider;
        $this->setCspService($cspService);
    }

    /**
     * Show employee overview page
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
		$isGlobalViewer = $this->accessControlService->isSystemAdministrator($userId)
			|| $this->accessControlService->canManageOrganization($userId);
		$accessibleProjectIds = $this->projectService->getAccessibleProjectIdListForUser($userId);
		$visibleUserIds = $isGlobalViewer ? null : [$userId];

        // Get employee yearly statistics
        $employeeYearlyStats = $this->timeEntryService->getEmployeeYearlyStats($accessibleProjectIds, $visibleUserIds);

        // Filters and pagination
        // getParam can return an array (e.g. ?search[]=x); coerce to a trimmed
        // string so downstream mb_strtolower()/filtering never receives non-strings.
        $searchRaw = $this->request->getParam('search', '');
        $search = is_string($searchRaw) ? trim($searchRaw) : '';
        $page = max(1, (int)$this->request->getParam('page', 1));
        $appItemsPerPage = $this->config->getAppValue($this->appName, 'items_per_page', '20');
        $defaultItemsPerPage = (int)$this->config->getUserValue($userId, $this->appName, 'items_per_page', $appItemsPerPage);
        $perPage = $defaultItemsPerPage > 0 ? $defaultItemsPerPage : 20;

        // Get employee comparison statistics
        $employeeComparisonStatsAll = $this->timeEntryService->getEmployeeComparisonStats($accessibleProjectIds, $visibleUserIds);

        // Apply search filter (server-side)
        if ($search) {
            $needle = mb_strtolower($search);
            $employeeComparisonStatsAll = array_values(array_filter($employeeComparisonStatsAll, static function ($item) use ($needle) {
                $name = mb_strtolower($item['user_display_name'] ?? '');
                $id = mb_strtolower($item['user_id'] ?? '');
                return str_contains($name, $needle) || str_contains($id, $needle);
            }));
        }

        $totalEmployees = count($employeeComparisonStatsAll);
        $totalPages = (int)max(1, ceil($totalEmployees / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;
        $employeeComparisonStats = array_slice($employeeComparisonStatsAll, $offset, $perPage);

        // Team totals must reflect the full (search-filtered) result set, not just
        // the current page — otherwise the overview cards under-report once the
        // list spans more than one page. (Audit: pagination/aggregate consistency.)
        $teamTotalHours = 0.0;
        $teamTotalCost = 0.0;
        foreach ($employeeComparisonStatsAll as $row) {
            $teamTotalHours += (float)($row['total_hours'] ?? 0);
            $teamTotalCost += (float)($row['total_cost'] ?? 0);
        }
        $teamTotals = [
            'employees' => $totalEmployees,
            'hours' => $teamTotalHours,
            'cost' => $teamTotalCost,
        ];

        // Get employee project type statistics
        $employeeProjectTypeStats = $this->timeEntryService->getYearlyStatsByProjectTypeForEmployee($userId, $accessibleProjectIds);
        $detailedEmployeeProjectTypeStats = $this->timeEntryService->getDetailedYearlyStatsByProjectTypeForEmployees($accessibleProjectIds, $visibleUserIds);

        // Get all users who have time entries
        $usersWithTimeEntries = $this->timeEntryService->getUsersWithTimeEntries($accessibleProjectIds);

        // Get common stats for the sidebar
        $stats = $this->getCommonStats($this->projectService, $this->customerService, $this->timeEntryService, $userId);

        $response = new TemplateResponse($this->appName, 'employees', [
            'employeeYearlyStats' => $employeeYearlyStats,
            'employeeComparisonStats' => $employeeComparisonStats,
            'employeeProjectTypeStats' => $employeeProjectTypeStats,
            'detailedEmployeeProjectTypeStats' => $detailedEmployeeProjectTypeStats,
            'usersWithTimeEntries' => $usersWithTimeEntries,
            'isGlobalViewer' => $isGlobalViewer,
            'teamTotals' => $teamTotals,
            'filters' => [
                'search' => $search,
            ],
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'totalEntries' => $totalEmployees,
                'totalPages' => $totalPages,
            ],
            'stats' => $stats,
            'urlGenerator' => $this->urlGenerator,
            'userManager' => $this->userManager
        ]);

        return $this->configureCSP($response);
    }

    /**
     * Show employee detail page
     *
     * @param string $userId User ID
     * @return TemplateResponse
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function show($userId)
    {
        $user = $this->userSession->getUser();
        if (!$user) {
            $response = new TemplateResponse($this->appName, 'error', $this->errorPageGuest(
                $this->l->t('User not authenticated')
            ), 'guest');
            return $this->configureCSP($response, 'guest');
        }

        $employeeUser = $this->userManager->get($userId);
        $snapshot = $this->userAccountSnapshotMapper->findByUserId($userId);
		$viewerId = $user->getUID();
		$isGlobalViewer = $this->accessControlService->isSystemAdministrator($viewerId)
			|| $this->accessControlService->canManageOrganization($viewerId);
		if (!$isGlobalViewer && $viewerId !== $userId) {
			return new RedirectResponse($this->urlGenerator->linkToRoute('projectcheck.employee.show', ['userId' => $viewerId]));
		}
        if ($employeeUser === null && $snapshot === null) {
			$hasHistory = $this->timeEntryService->getTimeEntriesByUser($userId) !== [];
			if (!$hasHistory) {
				$response = new TemplateResponse($this->appName, 'error', $this->errorPageGuest(
					$this->l->t('Employee not found')
				), 'guest');
				return $this->configureCSP($response, 'guest');
			}
		}

        // Get yearly statistics for this employee
        $yearlyStats = $this->timeEntryService->getYearlyStatsForEmployee($userId);

        // Get project type statistics for this employee
        $accessibleProjectIds = $this->projectService->getAccessibleProjectIdListForUser($viewerId);
        $employeeProjectTypeStats = $this->timeEntryService->getYearlyStatsByProjectTypeForEmployee($userId, $accessibleProjectIds);
        $employeeProductivityAnalysis = $this->timeEntryService->getProductivityAnalysisForEmployee($userId, $accessibleProjectIds);
		$employeeAssignedProjects = $this->projectService->getUserProjects((string)$userId);
		$assignedProjectIds = [];
		foreach ($employeeAssignedProjects as $assignedProject) {
			$assignedProjectIds[(int)$assignedProject->getId()] = true;
		}
		$manageableProjects = [];
		foreach ($this->projectService->getProjects(['limit' => 500]) as $project) {
			$pid = (int)$project->getId();
			if (isset($assignedProjectIds[$pid])) {
				continue;
			}
			if ($this->projectService->canUserManageMembers($viewerId, $pid) && $project->isEditableState()) {
				$manageableProjects[] = $project;
			}
		}

        // Get common stats for the sidebar
        $stats = $this->getCommonStats($this->projectService, $this->customerService, $this->timeEntryService, $user->getUID());

		$formerDisplay = null;
		if ($employeeUser === null) {
			$formerDisplay = $snapshot !== null ? $snapshot->getDisplayName() : $userId;
		}
		$requestToken = $this->requestTokenProvider->getEncryptedRequestToken();
        $response = new TemplateResponse($this->appName, 'employee-detail', [
            'employee' => $employeeUser,
			'employeeId' => $userId,
			'formerAccountDisplayName' => $formerDisplay,
			'isFormerAccount' => $employeeUser === null,
			'isGlobalViewer' => $isGlobalViewer,
            'yearlyStats' => $yearlyStats,
            'employeeProjectTypeStats' => $employeeProjectTypeStats,
            'employeeProductivityAnalysis' => $employeeProductivityAnalysis,
			'employeeAssignedProjects' => $employeeAssignedProjects,
			'manageableProjects' => $manageableProjects,
			'canManageAssignments' => $employeeUser !== null,
            'stats' => $stats,
            'urlGenerator' => $this->urlGenerator,
			'requesttoken' => $requestToken,
			'assignProjectUrl' => $this->urlGenerator->linkToRoute('projectcheck.employee.assignProject', ['userId' => (string)$userId]),
			'employeeHourlyRates' => $this->employeeHourlyRateService->listRatesForUser((string) $userId),
			'canManageEmployeeRates' => $this->accessControlService->canManageAppConfiguration($viewerId),
			'addEmployeeRateUrl' => $this->urlGenerator->linkToRoute('projectcheck.employee.addHourlyRate', ['userId' => (string) $userId]),
        ]);

        return $this->configureCSP($response);
    }

	#[NoAdminRequired]
	public function assignProject(string $userId): JSONResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new JSONResponse(['error' => $this->l->t('User not authenticated')], 401);
		}
		if ($this->userManager->get($userId) === null) {
			return new JSONResponse(['error' => $this->l->t('Employee not found')], 404);
		}

		$projectId = (int)$this->request->getParam('project_id', 0);
		$role = \OCA\ProjectCheck\Service\ProjectService::DEFAULT_MEMBER_ROLE;
		$hourlyRateRaw = $this->request->getParam('hourly_rate', null);
		$hourlyRate = null;
		if ($hourlyRateRaw !== null && $hourlyRateRaw !== '') {
			if (!is_numeric($hourlyRateRaw)) {
				return new JSONResponse(['error' => $this->l->t('Hourly rate must be a non-negative number')], 400);
			}
			$hourlyRate = (float)$hourlyRateRaw;
			if ($hourlyRate < 0) {
				return new JSONResponse(['error' => $this->l->t('Hourly rate must be a non-negative number')], 400);
			}
		}
		if ($projectId <= 0) {
			return new JSONResponse(['error' => $this->l->t('Invalid parameters')], 400);
		}

		$project = $this->projectService->getProject($projectId);
		if ($project === null) {
			return new JSONResponse(['error' => $this->l->t('Project not found')], 404);
		}
		if (!$project->isEditableState()) {
			return new JSONResponse(['error' => $this->l->t('Cannot change the team for a completed, cancelled, or archived project')], 403);
		}
		if (!$this->projectService->canUserManageMembers($user->getUID(), $projectId)) {
			return new JSONResponse(['error' => $this->l->t('Access denied')], 403);
		}

		if ($project->getCostRateMode() === CostRateMode::PROJECT_MEMBER) {
			return new JSONResponse([
				'success' => false,
				'error' => $this->l->t('This project uses per-person rates. Open the project team page to add this person with their rate.'),
				'code' => 'project_member_mode',
				'project_url' => $this->urlGenerator->linkToRoute('projectcheck.project.show', ['id' => $projectId]) . '#team-section',
			], 400);
		}

		try {
			$member = $this->projectService->addTeamMember($projectId, $userId, $role, $hourlyRate);
			return new JSONResponse([
				'success' => true,
				'member' => $member,
				'message' => $this->l->t('Team member added successfully'),
			]);
		} catch (RateResolutionException $e) {
			return new JSONResponse([
				'error' => RateResolutionMessage::forException($e, $this->l),
				'code' => $e->getCodeKey(),
			], 400);
		} catch (\Exception $e) {
			return new JSONResponse(['error' => $this->l->t('Could not assign employee to project.')], 400);
		}
	}

	#[NoAdminRequired]
	public function addHourlyRate(string $userId): JSONResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new JSONResponse(['error' => $this->l->t('User not authenticated')], 401);
		}
		try {
			$row = $this->employeeHourlyRateService->addRateRow($userId, [
				'hourly_rate' => $this->request->getParam('hourly_rate'),
				'effective_from' => $this->request->getParam('effective_from'),
			], $user->getUID());
			return new JSONResponse([
				'success' => true,
				'rate' => [
					'id' => $row->getId(),
					'hourly_rate' => $row->getHourlyRate(),
					'effective_from' => $row->getEffectiveFrom()->format('Y-m-d'),
				],
				'message' => $this->l->t('Rate saved. Past time entries keep their previous rate.'),
			]);
		} catch (RateResolutionException $e) {
			return new JSONResponse([
				'error' => RateResolutionMessage::forException($e, $this->l),
				'code' => $e->getCodeKey(),
			], 400);
		} catch (\Exception $e) {
			return new JSONResponse(['error' => $this->l->t('Could not save rate. Please check your input.')], 400);
		}
	}

	#[NoAdminRequired]
	public function unassignProject(string $userId, int $projectId): JSONResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new JSONResponse(['error' => $this->l->t('User not authenticated')], 401);
		}

		return $this->finishUnassignProject($user->getUID(), $userId, $projectId);
	}

	/**
	 * Remove employee from project via POST (deletion modal — reliable CSRF).
	 */
	#[NoAdminRequired]
	public function unassignProjectPost(string $userId, int $projectId): JSONResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new JSONResponse(['error' => $this->l->t('User not authenticated')], 401);
		}

		return $this->finishUnassignProject($user->getUID(), $userId, $projectId);
	}

	private function finishUnassignProject(string $actorUid, string $memberUserId, int $projectId): JSONResponse
	{
		$project = $this->projectService->getProject($projectId);
		if ($project === null) {
			return new JSONResponse(['error' => $this->l->t('Project not found')], 404);
		}
		if (!$project->isEditableState()) {
			return new JSONResponse(['error' => $this->l->t('Cannot change the team for a completed, cancelled, or archived project')], 403);
		}
		if (!$this->projectService->canUserManageMembers($actorUid, $projectId)) {
			return new JSONResponse(['error' => $this->l->t('Access denied')], 403);
		}

		try {
			$this->projectService->removeTeamMember($projectId, $memberUserId);
			return new JSONResponse([
				'success' => true,
				'message' => $this->l->t('Team member removed successfully'),
			]);
		} catch (\Exception $e) {
			return new JSONResponse(['error' => $this->l->t('Could not remove employee from project.')], 400);
		}
	}

    /**
     * Get employee statistics via API
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

        $userId = $this->request->getParam('user_id', null);
		$viewerId = $user->getUID();
		$isGlobalViewer = $this->accessControlService->isSystemAdministrator($viewerId)
			|| $this->accessControlService->canManageOrganization($viewerId);
		if (is_string($userId) && $userId !== '' && $userId !== $viewerId) {
			if (!$isGlobalViewer) {
				return new JSONResponse(['error' => $this->l->t('Access denied')], 403);
			}
		}

        if ($userId) {
            // Get employee-specific statistics
            $yearlyStats = $this->timeEntryService->getYearlyStatsForEmployee($userId);
        } else {
            // Non-admins can only request their own statistics.
            $yearlyStats = $isGlobalViewer
				? $this->timeEntryService->getEmployeeYearlyStats()
				: $this->timeEntryService->getYearlyStatsForEmployee($viewerId);
        }

        return new JSONResponse([
            'success' => true,
            'yearlyStats' => $yearlyStats
        ]);
    }
}
