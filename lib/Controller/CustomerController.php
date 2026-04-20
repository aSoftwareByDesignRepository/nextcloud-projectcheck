<?php

declare(strict_types=1);

/**
 * Customer controller for projectcheck app
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
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\IURLGenerator;
use OCA\ProjectCheck\Service\CustomerService;
use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Service\BudgetService;
use OCA\ProjectCheck\Service\TimeEntryService;
use OCA\ProjectCheck\Service\DeletionService;
use OCA\ProjectCheck\Service\ActivityService;
use OCA\ProjectCheck\Service\CSPService;
use OCP\IConfig;
use OCP\IL10N;
use Psr\Log\LoggerInterface;
use OCA\ProjectCheck\Traits\StatsTrait;

/**
 * Customer controller for customer management
 */
class CustomerController extends Controller
{
	use CSPTrait;
	use StatsTrait;

	/** @var IUserSession */
	private $userSession;

	/** @var CustomerService */
	private $customerService;

	/** @var ProjectService */
	private $projectService;

	/** @var BudgetService */
	private $budgetService;

	/** @var TimeEntryService */
	private $timeEntryService;

	/** @var DeletionService */
	private $deletionService;

	/** @var ActivityService */
	private $activityService;

	/** @var IURLGenerator */
	private $urlGenerator;

	/** @var IConfig */
	private $config;

	/** @var IL10N */
	private $l;

	/** @var LoggerInterface */
	private $logger;

	/**
	 * Deletion impact preview
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getDeletionImpact(int $id): JSONResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new JSONResponse(['error' => $this->l->t('User not authenticated')], 401);
		}

		try {
			$impact = $this->deletionService->getCustomerDeletionImpact((int)$id);
			return new JSONResponse(['success' => true, 'impact' => $impact]);
		} catch (\Exception $e) {
			return new JSONResponse(['success' => false, 'error' => $e->getMessage()], 400);
		}
	}

	/**
	 * CustomerController constructor
	 *
	 * @param string $appName
	 * @param IRequest $request
	 * @param IUserSession $userSession
	 * @param CustomerService $customerService
	 * @param ProjectService $projectService
	 * @param BudgetService $budgetService
	 * @param TimeEntryService $timeEntryService
	 * @param DeletionService $deletionService
	 * @param ActivityService $activityService
	 * @param IURLGenerator $urlGenerator
	 * @param IConfig $config
	 * @param CSPService $cspService
	 * @param IL10N $l
	 * @param LoggerInterface $logger
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		IUserSession $userSession,
		CustomerService $customerService,
		ProjectService $projectService,
		BudgetService $budgetService,
		TimeEntryService $timeEntryService,
		DeletionService $deletionService,
		ActivityService $activityService,
		IURLGenerator $urlGenerator,
		IConfig $config,
		CSPService $cspService,
		IL10N $l,
		LoggerInterface $logger
	) {
		parent::__construct($appName, $request);
		$this->userSession = $userSession;
		$this->customerService = $customerService;
		$this->projectService = $projectService;
		$this->budgetService = $budgetService;
		$this->timeEntryService = $timeEntryService;
		$this->deletionService = $deletionService;
		$this->activityService = $activityService;
		$this->urlGenerator = $urlGenerator;
		$this->config = $config;
		$this->l = $l;
		$this->logger = $logger;
		$this->setCspService($cspService);
	}

	/**
	 * Show customer list page
	 *
	 * @return TemplateResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): TemplateResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			$response = new TemplateResponse($this->appName, 'error', [
				'message' => 'User not authenticated',
				'urlGenerator' => $this->urlGenerator
			], 'guest');
			return $this->configureCSP($response, 'guest');
		}

		$userId = $user->getUID();

		// Pagination settings
		$page = max(1, (int)$this->request->getParam('page', 1));
		$defaultItemsPerPage = (int)$this->config->getUserValue($userId, $this->appName, 'items_per_page', '20');
		$perPage = $defaultItemsPerPage > 0 ? $defaultItemsPerPage : 20;

		// Get customers with optional search
		$search = $this->request->getParam('search', '');
		$filters = [
			'search' => $search,
			'limit' => $perPage,
			'offset' => ($page - 1) * $perPage,
		];
		$customers = $this->customerService->getCustomers($filters);

		$totalCustomers = $this->customerService->countCustomers($filters);
		$totalPages = (int)max(1, ceil($totalCustomers / $perPage));
		if ($page > $totalPages) {
			$page = $totalPages;
			$filters['offset'] = ($page - 1) * $perPage;
			$customers = $this->customerService->getCustomers($filters);
		}

		// Add deletion permission info to each customer
		foreach ($customers as $customer) {
			$customer->setCanDelete($this->customerService->canDeleteCustomer($customer->getId()));
		}

		// Get comprehensive statistics for all customers
		$comprehensiveStats = $this->getComprehensiveCustomerStats($userId);

		// Get common stats for the sidebar
		$stats = $this->getCommonStats($this->projectService, $this->customerService, $this->timeEntryService);

		$deleteUrl = $this->urlGenerator->linkToRoute('projectcheck.customer.deletePost', ['id' => 'CUSTOMER_ID']);

		$response = new TemplateResponse($this->appName, 'customers', [
			'customers' => $customers,
			'search' => $search,
			'filters' => $filters,
			'pagination' => [
				'page' => $page,
				'perPage' => $perPage,
				'totalEntries' => $totalCustomers,
				'totalPages' => $totalPages,
			],
			'userId' => $userId,
			'stats' => array_merge($stats, $comprehensiveStats),
			'urlGenerator' => $this->urlGenerator,
			'showUrl' => $this->urlGenerator->linkToRoute('projectcheck.customer.show', ['id' => 'CUSTOMER_ID']),
			'editUrl' => $this->urlGenerator->linkToRoute('projectcheck.customer.edit', ['id' => 'CUSTOMER_ID']),
			'deleteUrl' => $deleteUrl
		]);

		return $this->configureCSP($response);
	}

	/**
	 * Show customer creation form
	 *
	 * @return TemplateResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function create(): TemplateResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			$response = new TemplateResponse($this->appName, 'error', [
				'message' => 'User not authenticated',
				'urlGenerator' => $this->urlGenerator
			], 'guest');
			return $this->configureCSP($response, 'guest');
		}

		// Get common stats for the sidebar
		$stats = $this->getCommonStats($this->projectService, $this->customerService);

		$response = new TemplateResponse($this->appName, 'customer-form', [
			'customer' => null,
			'isEdit' => false,
			'stats' => $stats,
			'urlGenerator' => $this->urlGenerator
		]);

		return $this->configureCSP($response);
	}

	/**
	 * Store new customer
	 *
	 * @return JSONResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function store(): JSONResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new JSONResponse(['error' => $this->l->t('User not authenticated')], 401);
		}

		$userId = $user->getUID();
		$data = $this->request->getParams();

		try {
			// Validate data
			$errors = $this->customerService->validateCustomerData($data);
			if (!empty($errors)) {
				return new JSONResponse([
					'success' => false,
					'errors' => $errors
				], 400);
			}

			// Create customer
			$customer = $this->customerService->createCustomer($data, $userId);

			return new JSONResponse([
				'success' => true,
				'customer' => $customer->getSummary(),
				'message' => 'Customer created successfully'
			]);
		} catch (\Exception $e) {
			return new JSONResponse([
				'success' => false,
				'error' => $e->getMessage()
			], 400);
		}
	}

	/**
	 * Show customer detail page
	 *
	 * @param int $id Customer ID
	 * @return TemplateResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function show($id)
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			$response = new TemplateResponse($this->appName, 'error', [
				'message' => 'User not authenticated',
				'urlGenerator' => $this->urlGenerator
			], 'guest');
			return $this->configureCSP($response, 'guest');
		}

		$customer = $this->customerService->getCustomer($id);
		if (!$customer) {
			$response = new TemplateResponse($this->appName, 'error', [
				'message' => 'Customer not found',
				'urlGenerator' => $this->urlGenerator
			], 'guest');
			return $this->configureCSP($response, 'guest');
		}

		// Get projects for this customer with budget information
		$projects = $this->projectService->getProjectsByCustomer($id);
		$projectsWithBudgetInfo = $this->enrichProjectsWithBudgetInfo($projects, $user->getUID());

		// Get yearly statistics for the customer
		$yearlyStats = $this->timeEntryService->getYearlyStatsForCustomer($id);

		// Get project type statistics for the customer
		$projectTypeStats = $this->timeEntryService->getYearlyStatsByProjectType();
		$detailedProjectTypeStats = $this->timeEntryService->getDetailedYearlyStatsByProjectType();
		$productivityAnalysis = $this->timeEntryService->getProductivityAnalysis();

		// Get common stats for the sidebar
		$stats = $this->getCommonStats($this->projectService, $this->customerService);

		$response = new TemplateResponse($this->appName, 'customer-detail', [
			'customer' => $customer,
			'projects' => $projectsWithBudgetInfo,
			'yearlyStats' => $yearlyStats,
			'projectTypeStats' => $projectTypeStats,
			'detailedProjectTypeStats' => $detailedProjectTypeStats,
			'productivityAnalysis' => $productivityAnalysis,
			'stats' => $stats,
			'urlGenerator' => $this->urlGenerator
		]);

		return $this->configureCSP($response);
	}

	/**
	 * Show customer edit form
	 *
	 * @param int $id Customer ID
	 * @return TemplateResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function edit(int $id): TemplateResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			$response = new TemplateResponse($this->appName, 'error', [
				'message' => 'User not authenticated',
				'urlGenerator' => $this->urlGenerator
			], 'guest');
			return $this->configureCSP($response, 'guest');
		}

		$customer = $this->customerService->getCustomer($id);
		if (!$customer) {
			$response = new TemplateResponse($this->appName, 'error', [
				'message' => 'Customer not found',
				'urlGenerator' => $this->urlGenerator
			], 'guest');
			return $this->configureCSP($response, 'guest');
		}

		// Get common stats for the sidebar
		$stats = $this->getCommonStats($this->projectService, $this->customerService);

		$response = new TemplateResponse($this->appName, 'customer-form', [
			'customer' => $customer,
			'isEdit' => true,
			'stats' => $stats,
			'urlGenerator' => $this->urlGenerator
		]);

		return $this->configureCSP($response);
	}

	/**
	 * Update customer
	 *
	 * @param int $id Customer ID
	 * @return JSONResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function update($id)
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new JSONResponse(['error' => $this->l->t('User not authenticated')], 401);
		}

		// Handle method override for HTML forms
		$method = $this->request->getMethod();
		$postData = $this->request->getParam('_method');

		// If this is a POST request with _method=PUT, treat it as a PUT request
		if ($method === 'POST' && $postData === 'PUT') {
			// Continue with update logic
		} elseif ($method !== 'PUT') {
			// If it's not a PUT request and not a POST with _method=PUT, return 405
			return new JSONResponse(['error' => $this->l->t('Method not allowed')], 405);
		}

		$data = $this->request->getParams();

		try {
			// Validate data
			$errors = $this->customerService->validateCustomerData($data);
			if (!empty($errors)) {
				return new JSONResponse([
					'success' => false,
					'errors' => $errors
				], 400);
			}

			// Update customer
			$customer = $this->customerService->updateCustomer($id, $data);

			return new JSONResponse([
				'success' => true,
				'customer' => $customer->getSummary(),
				'message' => 'Customer updated successfully'
			]);
		} catch (\Exception $e) {
			return new JSONResponse([
				'success' => false,
				'error' => $e->getMessage()
			], 400);
		}
	}

	/**
	 * Delete customer
	 *
	 * @param int $id Customer ID
	 * @return JSONResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function delete(int $id): JSONResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new JSONResponse(['error' => $this->l->t('User not authenticated')], 401);
		}

		// Handle method override for HTML forms
		$method = $this->request->getMethod();
		$postData = $this->request->getParam('_method');

		// If this is a POST request with _method=DELETE, treat it as a DELETE request
		if ($method === 'POST' && $postData === 'DELETE') {
			// Continue with delete logic
		} elseif ($method !== 'DELETE') {
			// If it's not a DELETE request and not a POST with _method=DELETE, return 405
			return new JSONResponse(['error' => $this->l->t('Method not allowed')], 405);
		}

		try {
			// Get customer info before deletion for activity logging
			$customer = $this->customerService->getCustomer($id);
			$impact = $this->deletionService->getCustomerDeletionImpact($id);

			// Strategy handling: 'restrict' (default) | 'cascade' | 'reassign'
			$strategy = $this->request->getParam('strategy', 'restrict');
			$reassignCustomerId = $this->request->getParam('reassign_customer_id');
			$options = ['strategy' => $strategy];
			if ($strategy === 'reassign' && $reassignCustomerId !== null) {
				$options['reassignCustomerId'] = (int) $reassignCustomerId;
			}

			$this->deletionService->deleteCustomerWithStrategy($id, $options);

			// Log activity
			if ($customer) {
				$this->activityService->logCustomerDeleted($user->getUID(), $customer, $impact);
			}

			return new JSONResponse([
				'success' => true,
				'message' => $this->l->t('Customer deleted successfully')
			]);
		} catch (\Exception $e) {
			$this->logger->error('Customer deletion failed', ['exception' => $e]);

			return new JSONResponse([
				'success' => false,
				'error' => $e->getMessage()
			], 400);
		}
	}

	/**
	 * Update customer via POST (for HTML forms)
	 *
	 * @param int $id Customer ID
	 * @return JSONResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function updatePost(int $id): JSONResponse
	{
		// Delegate to the update method
		return $this->update($id);
	}

	/**
	 * Delete customer via POST (for HTML forms)
	 *
	 * @param int $id Customer ID
	 * @return JSONResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function deletePost(int $id): JSONResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new JSONResponse(['error' => $this->l->t('User not authenticated')], 401);
		}

		try {
			$strategy = $this->request->getParam('strategy', 'restrict');
			$reassignCustomerId = $this->request->getParam('reassign_customer_id');
			$options = ['strategy' => $strategy];
			if ($strategy === 'reassign' && $reassignCustomerId !== null) {
				$options['reassignCustomerId'] = (int) $reassignCustomerId;
			}

			$this->deletionService->deleteCustomerWithStrategy($id, $options);

			return new JSONResponse([
				'success' => true,
				'message' => $this->l->t('Customer deleted successfully')
			]);
		} catch (\Exception $e) {
			$this->logger->error('Customer deletion failed', ['exception' => $e, 'customerId' => $id]);

			return new JSONResponse([
				'success' => false,
				'error' => $e->getMessage()
			], 400);
		}
	}

	/**
	 * Search customers via API
	 *
	 * @return JSONResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function search(): JSONResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new JSONResponse(['error' => $this->l->t('User not authenticated')], 401);
		}

		$query = $this->request->getParam('q', '');
		$customers = $this->customerService->searchCustomers($query);

		$results = [];
		foreach ($customers as $customer) {
			$results[] = $customer->getSummary();
		}

		return new JSONResponse([
			'success' => true,
			'customers' => $results
		]);
	}

	/**
	 * Get customers for select dropdown
	 *
	 * @return JSONResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getForSelect(): JSONResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new JSONResponse(['error' => $this->l->t('User not authenticated')], 401);
		}

		$customers = $this->customerService->getCustomersForSelect();

		return new JSONResponse([
			'success' => true,
			'customers' => $customers
		]);
	}

	/**
	 * Get customer statistics
	 *
	 * @return JSONResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getStats(): JSONResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new JSONResponse(['error' => $this->l->t('User not authenticated')], 401);
		}

		$customerId = $this->request->getParam('customer_id', null);

		if ($customerId) {
			// Get customer-specific statistics
			$stats = $this->customerService->getCustomerSpecificStats((int) $customerId);
		} else {
			// Get general customer statistics
			$stats = $this->customerService->getCustomerStats();
		}

		return new JSONResponse([
			'success' => true,
			'stats' => $stats
		]);
	}

	/**
	 * Get customer analytics
	 *
	 * @return JSONResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getAnalytics(): JSONResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new JSONResponse(['error' => $this->l->t('User not authenticated')], 401);
		}

		try {
			$analytics = $this->getCustomerAnalytics($user->getUID());

			return new JSONResponse([
				'success' => true,
				'analytics' => $analytics
			]);
		} catch (\Exception $e) {
			$this->logger->error('Customer analytics failed', ['exception' => $e]);
			return new JSONResponse([
				'success' => false,
				'error' => $e->getMessage()
			], 500);
		}
	}

	/**
	 * Get customer analytics data
	 *
	 * @param string $userId
	 * @return array
	 */
	private function getCustomerAnalytics(string $userId): array
	{
		// Get all customers with their projects and time entries
		$allCustomers = $this->customerService->getAllCustomers();
		$allProjects = $this->projectService->getAllProjects();

		// Calculate basic statistics
		$totalProjects = count($allProjects);
		$activeProjects = 0;
		$completedProjects = 0;
		$totalHours = 0;
		$totalRevenue = 0;

		foreach ($allProjects as $project) {
			if ($project->getStatus() === 'Active') {
				$activeProjects++;
			} elseif ($project->getStatus() === 'Completed') {
				$completedProjects++;
			}

			// Get hours and revenue for this project
			$projectHours = $this->timeEntryService->getTotalHoursForProject($project->getId());
			$projectRevenue = $this->timeEntryService->getTotalCostForProject($project->getId());
			$totalHours += $projectHours;
			$totalRevenue += $projectRevenue;
		}

		// Get top customer by revenue
		$topCustomersByRevenue = $this->getTopCustomersByRevenue($allCustomers, $allProjects);
		$topCustomer = $topCustomersByRevenue[0] ?? null;

		// Get recent activity
		$recentActivity = $this->getRecentActivity();

		return [
			'total_projects' => $totalProjects,
			'active_projects' => $activeProjects,
			'completed_projects' => $completedProjects,
			'total_hours' => $totalHours,
			'total_revenue' => $totalRevenue,
			'topCustomersByRevenue' => $topCustomersByRevenue,
			'recentActivity' => $recentActivity
		];
	}

	/**
	 * Get top customers by revenue
	 *
	 * @param array $customers
	 * @param array $projects
	 * @return array
	 */
	private function getTopCustomersByRevenue(array $customers, array $projects): array
	{
		$customerRevenue = [];

		foreach ($customers as $customer) {
			$revenue = 0;
			$customerProjects = array_filter($projects, function ($project) use ($customer) {
				return $project->getCustomerId() === $customer->getId();
			});

			foreach ($customerProjects as $project) {
				$revenue += $this->timeEntryService->getTotalCostForProject($project->getId());
			}

			if ($revenue > 0) {
				$customerRevenue[] = [
					'id' => $customer->getId(),
					'name' => $customer->getName(),
					'revenue' => $revenue
				];
			}
		}

		// Sort by revenue descending
		usort($customerRevenue, function ($a, $b) {
			return $b['revenue'] <=> $a['revenue'];
		});

		// Calculate percentages
		$totalRevenue = array_sum(array_column($customerRevenue, 'revenue'));
		foreach ($customerRevenue as &$customer) {
			$customer['percentage'] = $totalRevenue > 0 ? ($customer['revenue'] / $totalRevenue) * 100 : 0;
		}

		return array_slice($customerRevenue, 0, 5);
	}

	/**
	 * Get top customers by hours
	 *
	 * @param array $customers
	 * @param array $projects
	 * @return array
	 */
	private function getTopCustomersByHours(array $customers, array $projects): array
	{
		$customerHours = [];

		foreach ($customers as $customer) {
			$hours = 0;
			$customerProjects = array_filter($projects, function ($project) use ($customer) {
				return $project->getCustomerId() === $customer->getId();
			});

			foreach ($customerProjects as $project) {
				$hours += $this->timeEntryService->getTotalHoursForProject($project->getId());
			}

			if ($hours > 0) {
				$customerHours[] = [
					'id' => $customer->getId(),
					'name' => $customer->getName(),
					'hours' => $hours
				];
			}
		}

		// Sort by hours descending
		usort($customerHours, function ($a, $b) {
			return $b['hours'] <=> $a['hours'];
		});

		// Calculate percentages
		$totalHours = array_sum(array_column($customerHours, 'hours'));
		foreach ($customerHours as &$customer) {
			$customer['percentage'] = $totalHours > 0 ? ($customer['hours'] / $totalHours) * 100 : 0;
		}

		return array_slice($customerHours, 0, 5);
	}

	/**
	 * Get best hourly rates
	 *
	 * @param array $projects
	 * @return array
	 */
	private function getBestHourlyRates(array $projects): array
	{
		$projectRates = [];

		foreach ($projects as $project) {
			$hourlyRate = $project->getHourlyRate();
			if ($hourlyRate > 0) {
				$projectRates[] = [
					'id' => $project->getId(),
					'name' => $project->getName(),
					'customer_name' => $project->getCustomerName(),
					'hourly_rate' => $hourlyRate
				];
			}
		}

		// Sort by hourly rate descending
		usort($projectRates, function ($a, $b) {
			return $b['hourly_rate'] <=> $a['hourly_rate'];
		});

		return array_slice($projectRates, 0, 5);
	}

	/**
	 * Get most profitable projects
	 *
	 * @param array $projects
	 * @return array
	 */
	private function getMostProfitableProjects(array $projects): array
	{
		$projectProfitability = [];

		foreach ($projects as $project) {
			$hours = $this->timeEntryService->getTotalHoursForProject($project->getId());
			$revenue = $this->timeEntryService->getTotalCostForProject($project->getId());

			if ($hours > 0) {
				$revenuePerHour = $revenue / $hours;
				$projectProfitability[] = [
					'id' => $project->getId(),
					'name' => $project->getName(),
					'customer_name' => $project->getCustomerName(),
					'revenue_per_hour' => $revenuePerHour,
					'total_hours' => $hours,
					'total_revenue' => $revenue
				];
			}
		}

		// Sort by revenue per hour descending
		usort($projectProfitability, function ($a, $b) {
			return $b['revenue_per_hour'] <=> $a['revenue_per_hour'];
		});

		return array_slice($projectProfitability, 0, 5);
	}

	/**
	 * Get budget utilization
	 *
	 * @param array $projects
	 * @return array
	 */
	private function getBudgetUtilization(array $projects): array
	{
		$totalBudget = 0;
		$usedBudget = 0;

		foreach ($projects as $project) {
			$projectBudget = $project->getTotalBudget() ?? 0;
			$totalBudget += $projectBudget;
			$usedBudget += $this->timeEntryService->getTotalCostForProject($project->getId());
		}

		$remainingBudget = max(0, $totalBudget - $usedBudget);
		$utilizationPercentage = $totalBudget > 0 ? ($usedBudget / $totalBudget) * 100 : 0;

		return [
			'total_budget' => $totalBudget,
			'used_budget' => $usedBudget,
			'remaining_budget' => $remainingBudget,
			'utilization_percentage' => $utilizationPercentage
		];
	}

	/**
	 * Get recent activity
	 *
	 * @return array
	 */
	private function getRecentActivity(): array
	{
		$recentEntries = $this->timeEntryService->getAllTimeEntries();

		// Sort by date descending and take the 5 most recent
		usort($recentEntries, function ($a, $b) {
			return $b->getDate() <=> $a->getDate();
		});

		$activities = [];
		foreach (array_slice($recentEntries, 0, 5) as $entry) {
			$project = $this->projectService->getProject($entry->getProjectId());
			$activities[] = [
				'id' => $entry->getId(),
				'description' => $entry->getDescription(),
				'project_name' => $project ? $project->getName() : $this->l->t('Unknown Project'),
				'customer_name' => $project ? $project->getCustomerName() : $this->l->t('Unknown Customer'),
				'hours' => $entry->getHours(),
				'date' => $entry->getDate() ? $entry->getDate()->format('d.m.Y') : $this->l->t('Unknown Date')
			];
		}

		return $activities;
	}

	/**
	 * Get comprehensive statistics for all customers
	 *
	 * @param string $userId
	 * @return array
	 */
	private function getComprehensiveCustomerStats(string $userId): array
	{
		try {
			// Get all customers
			$allCustomers = $this->customerService->getAllCustomers();

			// Get all projects
			$allProjects = $this->projectService->getAllProjects();

			// Initialize counters
			$totalCustomers = count($allCustomers);
			$totalProjects = count($allProjects);
			$activeProjects = 0;
			$completedProjects = 0;
			$totalBudget = 0;
			$totalHours = 0;
			$totalRevenue = 0;
			$totalTimeEntries = 0;

			foreach ($allProjects as $project) {
				if ($project->getStatus() === 'Active') {
					$activeProjects++;
				} elseif ($project->getStatus() === 'Completed') {
					$completedProjects++;
				}

				// Get project budget
				$projectBudget = $project->getTotalBudget() ?? 0;
				$totalBudget += $projectBudget;

				// Get total hours for this project
				$projectHours = $this->timeEntryService->getTotalHoursForProject($project->getId());
				$totalHours += $projectHours;

				// Calculate revenue (used budget)
				$projectCost = $this->timeEntryService->getTotalCostForProject($project->getId());
				$totalRevenue += $projectCost;

				// Count time entries for this project
				$projectTimeEntries = $this->timeEntryService->getTimeEntriesByProject($project->getId());
				$totalTimeEntries += count($projectTimeEntries);
			}

			// Calculate derived statistics
			$budgetEarned = $totalRevenue; // Revenue generated from time entries
			$budgetRemaining = max(0, $totalBudget - $totalRevenue);
			$averageHoursPerProject = $totalProjects > 0 ? $totalHours / $totalProjects : 0;
			$averageRevenuePerProject = $totalProjects > 0 ? $totalRevenue / $totalProjects : 0;
			$budgetUtilizationPercentage = $totalBudget > 0 ? ($totalRevenue / $totalBudget) * 100 : 0;
			$projectCompletionRate = $totalProjects > 0 ? ($completedProjects / $totalProjects) * 100 : 0;

			return [
				'totalCustomers' => $totalCustomers,
				'total_projects' => $totalProjects,
				'active_projects' => $activeProjects,
				'completed_projects' => $completedProjects,
				'total_hours' => $totalHours,
				'total_revenue' => $totalRevenue,
				'total_budget' => $totalBudget,
				'budget_earned' => $budgetEarned,
				'budget_remaining' => $budgetRemaining,
				'average_hours_per_project' => $averageHoursPerProject,
				'average_revenue_per_project' => $averageRevenuePerProject,
				'total_time_entries' => $totalTimeEntries,
				'budget_utilization_percentage' => $budgetUtilizationPercentage,
				'project_completion_rate' => $projectCompletionRate
			];
		} catch (\Exception $e) {
			// Return default values if calculation fails
			return [
				'totalCustomers' => 0,
				'total_projects' => 0,
				'active_projects' => 0,
				'completed_projects' => 0,
				'total_hours' => 0,
				'total_revenue' => 0,
				'total_budget' => 0,
				'budget_earned' => 0,
				'budget_remaining' => 0,
				'average_hours_per_project' => 0,
				'average_revenue_per_project' => 0,
				'total_time_entries' => 0,
				'budget_utilization_percentage' => 0,
				'project_completion_rate' => 0
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
