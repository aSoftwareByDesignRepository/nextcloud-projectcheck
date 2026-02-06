<?php

/**
 * Settings controller for projectcheck app
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
use OCP\IConfig;
use OCP\IGroupManager;
use OCA\ProjectCheck\Service\CSPService;
use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Service\CustomerService;
use OCA\ProjectCheck\Traits\StatsTrait;

/**
 * Settings controller for app configuration
 */
class SettingsController extends Controller
{
	use CSPTrait;
	use StatsTrait;

	/** @var IUserSession */
	private $userSession;

	/** @var IConfig */
	private $config;

	/** @var IGroupManager */
	private $groupManager;

	/** @var ProjectService */
	private $projectService;

	/** @var CustomerService */
	private $customerService;

	/**
	 * SettingsController constructor
	 *
	 * @param string $appName
	 * @param IRequest $request
	 * @param IUserSession $userSession
	 * @param IConfig $config
	 * @param IGroupManager $groupManager
	 * @param CSPService $cspService
	 * @param ProjectService $projectService
	 * @param CustomerService $customerService
	 */
	public function __construct(
		$appName,
		IRequest $request,
		IUserSession $userSession,
		IConfig $config,
		IGroupManager $groupManager,
		CSPService $cspService,
		ProjectService $projectService,
		CustomerService $customerService
	) {
		parent::__construct($appName, $request);
		$this->userSession = $userSession;
		$this->config = $config;
		$this->groupManager = $groupManager;
		$this->setCspService($cspService);
		$this->projectService = $projectService;
		$this->customerService = $customerService;
	}

	/**
	 * Show settings page
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
				'message' => 'User not authenticated'
			]);
			return $this->configureCSP($response, 'guest');
		}

		// Check if user is admin
		if (!$this->groupManager->isInGroup($user->getUID(), 'admin')) {
			$response = new TemplateResponse($this->appName, 'error', [
				'message' => 'Access denied - Admin privileges required'
			]);
			return $this->configureCSP($response, 'guest');
		}

		$userId = $user->getUID();

		// Get user settings
		$settings = $this->getUserSettings($userId);

		// Get stats for sidebar
		$stats = $this->getCommonStats($this->projectService, $this->customerService);

		$response = new TemplateResponse($this->appName, 'settings', [
			'settings' => $settings,
			'stats' => $stats,
			'userId' => $userId,
			'requesttoken' => \OC::$server->getCSRFTokenManager()->getToken()->getEncryptedValue(),
			'cspNonce' => \OC::$server->getContentSecurityPolicyNonceManager()->getNonce()
		]);

		// Apply standard main policy
		return $this->configureCSP($response, 'main');
	}

	/**
	 * Update settings
	 *
	 * @return JSONResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function update()
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new JSONResponse(['error' => 'User not authenticated'], 401);
		}

		// Check if user is admin
		if (!$this->groupManager->isInGroup($user->getUID(), 'admin')) {
			return new JSONResponse(['error' => 'Access denied - Admin privileges required'], 403);
		}

		$userId = $user->getUID();
		$data = $this->request->getParams();

		try {
			// Update user settings
			$updatedSettings = $this->updateUserSettings($userId, $data);

			return new JSONResponse([
				'success' => true,
				'settings' => $updatedSettings,
				'message' => 'Settings updated successfully'
			]);
		} catch (\Exception $e) {
			return new JSONResponse([
				'success' => false,
				'error' => $e->getMessage()
			], 400);
		}
	}

	/**
	 * Get user settings
	 *
	 * @param string $userId
	 * @return array
	 */
	private function getUserSettings($userId)
	{
		return [
			'defaultHourlyRate' => $this->config->getUserValue($userId, $this->appName, 'default_hourly_rate', '50.00'),
			'budgetWarningThreshold' => $this->config->getUserValue($userId, $this->appName, 'budget_warning_threshold', '80'),
			'budgetCriticalThreshold' => $this->config->getUserValue($userId, $this->appName, 'budget_critical_threshold', '90'),
			'emailNotifications' => $this->config->getUserValue($userId, $this->appName, 'email_notifications', 'true'),
			'budgetAlerts' => $this->config->getUserValue($userId, $this->appName, 'budget_alerts', 'true'),
			'projectUpdates' => $this->config->getUserValue($userId, $this->appName, 'project_updates', 'true'),
			'defaultProjectStatus' => $this->config->getUserValue($userId, $this->appName, 'default_project_status', 'Active'),
			'defaultProjectPriority' => $this->config->getUserValue($userId, $this->appName, 'default_project_priority', 'Medium'),
			'itemsPerPage' => $this->config->getUserValue($userId, $this->appName, 'items_per_page', '20'),
			'showCompletedProjects' => $this->config->getUserValue($userId, $this->appName, 'show_completed_projects', 'true'),
			'autoCalculateHours' => $this->config->getUserValue($userId, $this->appName, 'auto_calculate_hours', 'true'),
			// Date format is enforced to d.m.Y system-wide
			'dateFormat' => 'd.m.Y',
			'timeFormat' => $this->config->getUserValue($userId, $this->appName, 'time_format', 'H:i'),
			'currency' => $this->config->getUserValue($userId, $this->appName, 'currency', 'EUR'),
			'language' => $this->config->getUserValue($userId, $this->appName, 'language', 'en')
		];
	}

	/**
	 * Update user settings
	 *
	 * @param string $userId
	 * @param array $data
	 * @return array
	 */
	private function updateUserSettings($userId, $data)
	{
		$settings = [];

		// Validate and update each setting
		if (isset($data['defaultHourlyRate'])) {
			$rate = floatval($data['defaultHourlyRate']);
			if ($rate > 0) {
				$this->config->setUserValue($userId, $this->appName, 'default_hourly_rate', number_format($rate, 2));
				$settings['defaultHourlyRate'] = number_format($rate, 2);
			}
		}

		if (isset($data['budgetWarningThreshold'])) {
			$threshold = intval($data['budgetWarningThreshold']);
			if ($threshold >= 0 && $threshold <= 100) {
				$this->config->setUserValue($userId, $this->appName, 'budget_warning_threshold', $threshold);
				$settings['budgetWarningThreshold'] = $threshold;
			}
		}

		if (isset($data['budgetCriticalThreshold'])) {
			$threshold = intval($data['budgetCriticalThreshold']);
			if ($threshold >= 0 && $threshold <= 100) {
				$this->config->setUserValue($userId, $this->appName, 'budget_critical_threshold', $threshold);
				$settings['budgetCriticalThreshold'] = $threshold;
			}
		}

		if (isset($data['emailNotifications'])) {
			$enabled = $data['emailNotifications'] === 'true' ? 'true' : 'false';
			$this->config->setUserValue($userId, $this->appName, 'email_notifications', $enabled);
			$settings['emailNotifications'] = $enabled;
		}

		if (isset($data['defaultProjectStatus'])) {
			$validStatuses = ['Active', 'On Hold', 'Completed', 'Cancelled'];
			if (in_array($data['defaultProjectStatus'], $validStatuses)) {
				$this->config->setUserValue($userId, $this->appName, 'default_project_status', $data['defaultProjectStatus']);
				$settings['defaultProjectStatus'] = $data['defaultProjectStatus'];
			}
		}

		if (isset($data['defaultProjectPriority'])) {
			$validPriorities = ['Low', 'Medium', 'High', 'Critical'];
			if (in_array($data['defaultProjectPriority'], $validPriorities)) {
				$this->config->setUserValue($userId, $this->appName, 'default_project_priority', $data['defaultProjectPriority']);
				$settings['defaultProjectPriority'] = $data['defaultProjectPriority'];
			}
		}

		if (isset($data['itemsPerPage'])) {
			$items = intval($data['itemsPerPage']);
			if ($items > 0 && $items <= 100) {
				$this->config->setUserValue($userId, $this->appName, 'items_per_page', $items);
				$settings['itemsPerPage'] = $items;
			}
		}

		if (isset($data['showCompletedProjects'])) {
			$show = $data['showCompletedProjects'] === 'true' ? 'true' : 'false';
			$this->config->setUserValue($userId, $this->appName, 'show_completed_projects', $show);
			$settings['showCompletedProjects'] = $show;
		}

		if (isset($data['autoCalculateHours'])) {
			$auto = $data['autoCalculateHours'] === 'true' ? 'true' : 'false';
			$this->config->setUserValue($userId, $this->appName, 'auto_calculate_hours', $auto);
			$settings['autoCalculateHours'] = $auto;
		}

		// Date format is fixed to d.m.Y; ignore any incoming value

		if (isset($data['timeFormat'])) {
			$this->config->setUserValue($userId, $this->appName, 'time_format', $data['timeFormat']);
			$settings['timeFormat'] = $data['timeFormat'];
		}

		if (isset($data['currency'])) {
			$this->config->setUserValue($userId, $this->appName, 'currency', $data['currency']);
			$settings['currency'] = $data['currency'];
		}

		if (isset($data['language'])) {
			$this->config->setUserValue($userId, $this->appName, 'language', $data['language']);
			$settings['language'] = $data['language'];
		}

		if (isset($data['budgetAlerts'])) {
			$enabled = $data['budgetAlerts'] === 'true' ? 'true' : 'false';
			$this->config->setUserValue($userId, $this->appName, 'budget_alerts', $enabled);
			$settings['budgetAlerts'] = $enabled;
		}

		if (isset($data['projectUpdates'])) {
			$enabled = $data['projectUpdates'] === 'true' ? 'true' : 'false';
			$this->config->setUserValue($userId, $this->appName, 'project_updates', $enabled);
			$settings['projectUpdates'] = $enabled;
		}

		return $settings;
	}
}
