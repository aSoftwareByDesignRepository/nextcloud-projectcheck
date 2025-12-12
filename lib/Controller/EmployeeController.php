<?php

/**
 * Employee controller for projectcheck app
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
use OCP\IUserManager;
use OCA\ProjectCheck\Service\TimeEntryService;
use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Service\CustomerService;
use OCA\ProjectCheck\Service\CSPService;
use OCP\IConfig;
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
        CSPService $cspService
    ) {
        parent::__construct($appName, $request);
        $this->userSession = $userSession;
        $this->userManager = $userManager;
        $this->timeEntryService = $timeEntryService;
        $this->projectService = $projectService;
        $this->customerService = $customerService;
        $this->urlGenerator = $urlGenerator;
		$this->config = $config;
        $this->setCspService($cspService);
    }

    /**
     * Show employee overview page
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
        $stats = $this->getCommonStats($this->projectService, $this->customerService);

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
     * @NoAdminRequired
     * @NoCSRFRequired
     * @param string $userId User ID
     * @return TemplateResponse
     */
    public function show($userId)
    {
        $user = $this->userSession->getUser();
        if (!$user) {
            $response = new TemplateResponse($this->appName, 'error', [
                'message' => 'User not authenticated'
            ], 'guest');
            return $this->configureCSP($response, 'guest');
        }

        // Get the employee user
        $employeeUser = $this->userManager->get($userId);
        if (!$employeeUser) {
            $response = new TemplateResponse($this->appName, 'error', [
                'message' => 'Employee not found'
            ], 'guest');
            return $this->configureCSP($response, 'guest');
        }

        // Get yearly statistics for this employee
        $yearlyStats = $this->timeEntryService->getYearlyStatsForEmployee($userId);

        // Get project type statistics for this employee
        $employeeProjectTypeStats = $this->timeEntryService->getYearlyStatsByProjectTypeForEmployee($userId);
        $employeeProductivityAnalysis = $this->timeEntryService->getProductivityAnalysisForEmployee($userId);

        // Get common stats for the sidebar
        $stats = $this->getCommonStats($this->projectService, $this->customerService);

        $response = new TemplateResponse($this->appName, 'employee-detail', [
            'employee' => $employeeUser,
            'yearlyStats' => $yearlyStats,
            'employeeProjectTypeStats' => $employeeProjectTypeStats,
            'employeeProductivityAnalysis' => $employeeProductivityAnalysis,
            'stats' => $stats,
            'urlGenerator' => $this->urlGenerator
        ]);

        return $this->configureCSP($response);
    }

    /**
     * Get employee statistics via API
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

        $userId = $this->request->getParam('user_id', null);

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
