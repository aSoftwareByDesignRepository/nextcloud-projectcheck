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
use OCP\IRequest;
use OCP\IUserSession;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCA\ProjectCheck\Service\TimeEntryService;
use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Service\CustomerService;
use OCA\ProjectCheck\Service\CSPService;
use OCA\ProjectCheck\Service\AccessControlService;
use OCA\ProjectCheck\Db\UserAccountSnapshotMapper;
use OCA\ProjectCheck\Service\IRequestTokenProvider;
use OCP\IConfig;
use OCP\IL10N;
use OCA\ProjectCheck\Traits\StatsTrait;

/**
 * Employee controller for employee management and statistics
 */
class EmployeeController extends Controller
{
    use CSPTrait;
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
	 * @param IRequestTokenProvider $requestTokenProvider
     */
    public function __construct(
        $appName,
        IRequest $request,
        IUserSession $userSession,
        IUserManager $userManager,
        TimeEntryService $timeEntryService,
        ProjectService $projectService,
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
            $response = new TemplateResponse($this->appName, 'error', [
                'message' => $this->l->t('User not authenticated')
            ], 'guest');
            return $this->configureCSP($response, 'guest');
        }

        $userId = $user->getUID();

        // Get employee yearly statistics
        $employeeYearlyStats = $this->timeEntryService->getEmployeeYearlyStats();

        // Filters and pagination
        $search = $this->request->getParam('search', '');
        $page = max(1, (int)$this->request->getParam('page', 1));
        $defaultItemsPerPage = (int)$this->config->getUserValue($userId, $this->appName, 'items_per_page', '20');
        $perPage = $defaultItemsPerPage > 0 ? $defaultItemsPerPage : 20;

        // Get employee comparison statistics
        $employeeComparisonStatsAll = $this->timeEntryService->getEmployeeComparisonStats();

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

        // Get employee project type statistics
        $employeeProjectTypeStats = $this->timeEntryService->getYearlyStatsByProjectTypeForEmployee($userId);
        $detailedEmployeeProjectTypeStats = $this->timeEntryService->getDetailedYearlyStatsByProjectTypeForEmployees();

        // Get all users who have time entries
        $usersWithTimeEntries = $this->timeEntryService->getUsersWithTimeEntries();

        // Get common stats for the sidebar
        $stats = $this->getCommonStats($this->projectService, $this->customerService, $this->timeEntryService, $userId);

        $response = new TemplateResponse($this->appName, 'employees', [
            'employeeYearlyStats' => $employeeYearlyStats,
            'employeeComparisonStats' => $employeeComparisonStats,
            'employeeProjectTypeStats' => $employeeProjectTypeStats,
            'detailedEmployeeProjectTypeStats' => $detailedEmployeeProjectTypeStats,
            'usersWithTimeEntries' => $usersWithTimeEntries,
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
            $response = new TemplateResponse($this->appName, 'error', [
                'message' => $this->l->t('User not authenticated')
            ], 'guest');
            return $this->configureCSP($response, 'guest');
        }

        $employeeUser = $this->userManager->get($userId);
        $snapshot = $this->userAccountSnapshotMapper->findByUserId($userId);
        if ($employeeUser === null && $snapshot === null) {
			$hasHistory = $this->timeEntryService->getTimeEntriesByUser($userId) !== [];
			if (!$hasHistory) {
				$response = new TemplateResponse($this->appName, 'error', [
					'message' => $this->l->t('Employee not found')
				], 'guest');
				return $this->configureCSP($response, 'guest');
			}
		}

        // Get yearly statistics for this employee
        $yearlyStats = $this->timeEntryService->getYearlyStatsForEmployee($userId);

        // Get project type statistics for this employee
        $employeeProjectTypeStats = $this->timeEntryService->getYearlyStatsByProjectTypeForEmployee($userId);
        $employeeProductivityAnalysis = $this->timeEntryService->getProductivityAnalysisForEmployee($userId);

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
            'yearlyStats' => $yearlyStats,
            'employeeProjectTypeStats' => $employeeProjectTypeStats,
            'employeeProductivityAnalysis' => $employeeProductivityAnalysis,
            'stats' => $stats,
            'urlGenerator' => $this->urlGenerator,
			'requesttoken' => $requestToken,
        ]);

        return $this->configureCSP($response);
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
		if (is_string($userId) && $userId !== '' && $userId !== $viewerId) {
			if (!$this->accessControlService->isSystemAdministrator($viewerId) && !$this->accessControlService->canManageAppConfiguration($viewerId)) {
				return new JSONResponse(['error' => $this->l->t('Access denied')], 403);
			}
		}

        if ($userId) {
            // Get employee-specific statistics
            $yearlyStats = $this->timeEntryService->getYearlyStatsForEmployee($userId);
        } else {
            // Get all employee statistics
            $yearlyStats = $this->timeEntryService->getEmployeeYearlyStats();
        }

        return new JSONResponse([
            'success' => true,
            'yearlyStats' => $yearlyStats
        ]);
    }
}
