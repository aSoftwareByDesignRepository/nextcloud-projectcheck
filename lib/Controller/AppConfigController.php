<?php

declare(strict_types=1);

/**
 * App-wide (organization) configuration: access policy, defaults, and in-app org settings page.
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Controller;

use JsonException;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use Psr\Log\LoggerInterface;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Log\Audit\CriticalActionPerformedEvent;
use OCA\ProjectCheck\Service\AccessControlService;
use OCA\ProjectCheck\Service\CSPService;
use OCA\ProjectCheck\Service\SavePolicyUiStrings;
use OCA\ProjectCheck\Service\OrgPolicySaveAudit;
use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Service\CustomerService;
use OCA\ProjectCheck\Service\TimeEntryService;
use OCA\ProjectCheck\Traits\StatsTrait;

class AppConfigController extends Controller
{
	use CSPTrait;
	use StatsTrait;

	public function __construct(
		$appName,
		IRequest $request,
		private IUserSession $userSession,
		private AccessControlService $accessControl,
		private IConfig $config,
		private IURLGenerator $urlGenerator,
		private IFactory $l10nFactory,
		private LoggerInterface $logger,
		private IEventDispatcher $eventDispatcher,
		private ProjectService $projectService,
		private CustomerService $customerService,
		private TimeEntryService $timeEntryService,
		private IUserManager $userManager,
		private IGroupManager $groupManager,
		CSPService $cspService
	) {
		parent::__construct($appName, $request);
		$this->setCspService($cspService);
	}

	/** @see self::searchUsers for rationale (delegated org admins need consistent discovery) */
	private const ORG_SEARCH_MAX = 20;

	/** @see self::searchUsers */
	private const ORG_SEARCH_Q_MAX_LEN = 120;

	/**
	 * Autocomplete: Nextcloud user accounts. Restricted to users who may edit org policy (avoids unauthenticated search).
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function searchUsers(): JSONResponse
	{
		$auth = $this->orgSearchAuthResponse();
		if ($auth !== null) {
			return $auth;
		}
		$q = $this->sanitizeOrgSearchQuery();
		if ($q === null) {
			return new JSONResponse([ 'ok' => true, 'items' => [] ], 200);
		}
		$byId = $this->userManager->search($q, self::ORG_SEARCH_MAX, 0);
		$byName = $this->userManager->searchDisplayName($q, self::ORG_SEARCH_MAX, 0);
		/** @var array<string, IUser> $merged */
		$merged = [];
		foreach (array_merge($byId, $byName) as $user) {
			if (!($user instanceof IUser)) {
				continue;
			}
			$uid = $user->getUID();
			if (!isset($merged[$uid])) {
				$merged[$uid] = $user;
			}
		}
		$out = [];
		$n = 0;
		foreach ($merged as $user) {
			if ($n >= self::ORG_SEARCH_MAX) {
				break;
			}
			$out[] = [
				'id' => $user->getUID(),
				'displayName' => $user->getDisplayName(),
			];
			$n++;
		}
		return new JSONResponse([ 'ok' => true, 'items' => $out ], 200);
	}

	/**
	 * Autocomplete: Nextcloud groups (GID + display name).
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function searchGroups(): JSONResponse
	{
		$auth = $this->orgSearchAuthResponse();
		if ($auth !== null) {
			return $auth;
		}
		$q = $this->sanitizeOrgSearchQuery();
		if ($q === null) {
			return new JSONResponse([ 'ok' => true, 'items' => [] ], 200);
		}
		$groups = $this->groupManager->search($q, self::ORG_SEARCH_MAX, 0);
		$out = [];
		foreach ($groups as $group) {
			$out[] = [
				'id' => $group->getGID(),
				'displayName' => $group->getDisplayName(),
			];
		}
		return new JSONResponse([ 'ok' => true, 'items' => $out ], 200);
	}

	private function orgSearchAuthResponse(): ?JSONResponse
	{
		$sess = $this->userSession->getUser();
		if ($sess === null) {
			return new JSONResponse([ 'ok' => false, 'error' => 'unauthorized' ], 401);
		}
		if (!$this->accessControl->canManageOrganization($sess->getUID())) {
			return new JSONResponse([ 'ok' => false, 'error' => 'forbidden' ], 403);
		}
		return null;
	}

	/**
	 * @return string|null null => empty/invalid query (return empty list)
	 */
	private function sanitizeOrgSearchQuery(): ?string
	{
		$q = trim($this->request->getParam('q', ''));
		if ($q === '') {
			return null;
		}
		if (mb_strlen($q) < 2) {
			return null;
		}
		if (mb_strlen($q) > self::ORG_SEARCH_Q_MAX_LEN) {
			$q = (string) mb_substr($q, 0, self::ORG_SEARCH_Q_MAX_LEN);
		}
		return $q;
	}

	/**
	 * In-app org administration (app admins; Nextcloud system admins can use this or the server form).
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function orgIndex(): TemplateResponse
	{
		$user = $this->userSession->getUser();
		$l = $this->l10nFactory->get('projectcheck');
		if ($user === null) {
			return new TemplateResponse('projectcheck', 'error', [
				'message' => $l->t('User not authenticated'),
				'urlGenerator' => $this->urlGenerator,
			], 'guest');
		}
		if (!$this->accessControl->canManageOrganization($user->getUID())) {
			return new TemplateResponse('projectcheck', 'error', [
				'message' => $l->t('You do not have permission to change organization settings for ProjectCheck.'),
				'urlGenerator' => $this->urlGenerator,
			]);
		}
		$resp = $this->buildFormParameters($l);
		$response = new TemplateResponse('projectcheck', 'org-app-settings', $resp);
		return $this->configureCSP($response, 'main');
	}

	/**
	 * Save organization settings (CSRF required).
	 * Accepts form POST (application/x-www-form-urlencoded) or JSON with the same field names.
	 */
	#[NoAdminRequired]
	public function savePolicy(): JSONResponse
	{
		$user = $this->userSession->getUser();
		$l = $this->l10nFactory->get('projectcheck');
		$apiMsg = SavePolicyUiStrings::apiMessages($l);
		if ($user === null) {
			return new JSONResponse([
				'error' => 'unauthorized',
				'message' => $apiMsg['unauthorized'],
			], 401);
		}
		if (!$this->accessControl->canManageOrganization($user->getUID())) {
			$this->logger->warning('projectcheck org save denied', [ 'userId' => $user->getUID() ]);
			return new JSONResponse([
				'error' => 'forbidden',
				'message' => $apiMsg['forbidden'],
			], 403);
		}

		try {
			$payload = $this->getPayload();
		} catch (JsonException) {
			return new JSONResponse([
				'error' => 'invalid_json',
				'message' => $apiMsg['invalidJson'],
			], 400);
		}
		$parseLines = function ($raw): array {
			if (is_string($raw)) {
				$raw = str_replace([ "\r\n", "\r" ], "\n", $raw);
				$lines = array_map('trim', explode("\n", $raw));
				return array_values(array_filter($lines, static fn (string $line): bool => $line !== ''));
			}
			if (is_array($raw)) {
				$out = [];
				foreach ($raw as $item) {
					if (is_string($item) && trim($item) !== '') {
						$out[] = trim($item);
					}
				}
				return $out;
			}
			return [];
		};

		$restrictionEnabled = false;
		if (isset($payload['access_restriction_enabled'])) {
			$v = is_string($payload['access_restriction_enabled']) ? strtolower($payload['access_restriction_enabled']) : $payload['access_restriction_enabled'];
			$restrictionEnabled = in_array($v, [ true, 1, '1', 'yes', 'on' ], true);
		}
		$allowedUsers = $parseLines($payload['access_allowed_user_ids'] ?? $payload['allowedUserLines'] ?? '');
		$allowedGroups = $parseLines($payload['access_allowed_group_ids'] ?? $payload['allowedGroupLines'] ?? '');
		$appAdmins = $parseLines($payload['app_admin_user_ids'] ?? $payload['appAdminLines'] ?? '');

		$policyBefore = $this->accessControl->getPolicyState();
		$defaultsBefore = $this->readAppDefaultSnapshot();
		$uid = $user->getUID();

		try {
			$this->accessControl->applyFullAccessPolicy(
				$restrictionEnabled,
				$allowedUsers,
				$allowedGroups,
				$appAdmins
			);
			$this->saveAppDefaults($payload);
		} catch (\InvalidArgumentException $e) {
			return new JSONResponse([
				'error' => 'validation',
				'message' => $this->mapPolicyValidationToMessage($e, $l),
			], 400);
		} catch (\Throwable $e) {
			$this->logger->error($e->getMessage(), [ 'exception' => $e ]);
			return new JSONResponse([
				'error' => 'server',
				'message' => $apiMsg['server'],
			], 500);
		}

		$policyAfter = $this->accessControl->getPolicyState();
		$defaultsAfter = $this->readAppDefaultSnapshot();
		$auditContext = OrgPolicySaveAudit::build($policyBefore, $policyAfter, $defaultsBefore, $defaultsAfter, $uid);
		$this->logger->info('projectcheck org policy saved', $auditContext);
		$flags = $auditContext['flags'] ?? [];
		$anyPolicyChange = ($flags['restriction_toggled'] ?? false)
			|| ($flags['allowed_users_set_changed'] ?? false)
			|| ($flags['allowed_groups_set_changed'] ?? false)
			|| ($flags['app_admins_set_changed'] ?? false)
			|| ($flags['app_defaults_changed'] ?? false);
		$this->eventDispatcher->dispatchTyped(
			new CriticalActionPerformedEvent(
				'ProjectCheck: organization access policy was updated by %s (restriction: %s; any change: %s)',
				[ $uid, $policyAfter['restrictionEnabled'] ? 'on' : 'off', $anyPolicyChange ? 'yes' : 'no' ]
			)
		);

		return new JSONResponse([
			'success' => true,
			'message' => $l->t('Settings saved'),
			'state' => $this->accessControl->getPolicyState(),
		]);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function buildFormParameters(IL10N $l): array
	{
		$defaultHourlyRate = $this->config->getAppValue('projectcheck', 'default_hourly_rate', '50.00');
		$budgetWarningThreshold = $this->config->getAppValue('projectcheck', 'budget_warning_threshold', '80');
		$maxProjectsPerUser = $this->config->getAppValue('projectcheck', 'max_projects_per_user', '100');
		$enableTimeTracking = $this->config->getAppValue('projectcheck', 'enable_time_tracking', 'yes');
		$enableCustomerManagement = $this->config->getAppValue('projectcheck', 'enable_customer_management', 'yes');
		$enableBudgetTracking = $this->config->getAppValue('projectcheck', 'enable_budget_tracking', 'yes');
		$st = $this->accessControl->getPolicyState();
		$viewUid = $this->userSession->getUser()?->getUID();

		$stats = $this->getCommonStats($this->projectService, $this->customerService, $this->timeEntryService, $viewUid);
		$mapStats = [
			'total_projects' => $stats['totalProjects'] ?? 0,
			'total_customers' => $stats['totalCustomers'] ?? 0,
			'total_time_entries' => 0,
		];

		return [
			'l' => $l,
			'formUiStrings' => SavePolicyUiStrings::forForm($l),
			'urlGenerator' => $this->urlGenerator,
			'stats' => $mapStats,
			'policy' => $st,
			'allowedUserLines' => implode("\n", $st['allowedUserIds'] ?? []),
			'allowedGroupLines' => implode("\n", $st['allowedGroupIds'] ?? []),
			'appAdminLines' => implode("\n", $st['appAdminUserIds'] ?? []),
			'default_hourly_rate' => $defaultHourlyRate,
			'budget_warning_threshold' => $budgetWarningThreshold,
			'max_projects_per_user' => $maxProjectsPerUser,
			'enable_time_tracking' => $enableTimeTracking,
			'enable_customer_management' => $enableCustomerManagement,
			'enable_budget_tracking' => $enableBudgetTracking,
			'saveUrl' => $this->urlGenerator->linkToRoute('projectcheck.app_config.savePolicy'),
			'dashboardUrl' => $this->urlGenerator->linkToRoute('projectcheck.dashboard.index'),
			'projectsUrl' => $this->urlGenerator->linkToRoute('projectcheck.project.index'),
			'customersUrl' => $this->urlGenerator->linkToRoute('projectcheck.customer.index'),
			'timeEntriesUrl' => $this->urlGenerator->linkToRoute('projectcheck.timeentry.index'),
			'employeesUrl' => $this->urlGenerator->linkToRoute('projectcheck.employee.index'),
			'settingsUrl' => $this->urlGenerator->linkToRoute('projectcheck.settings.index'),
			'orgAppSettingsUrl' => $this->urlGenerator->linkToRoute('projectcheck.app_config.orgIndex'),
			'orgSearchUsersUrl' => $this->urlGenerator->linkToRoute('projectcheck.app_config.searchUsers'),
			'orgSearchGroupsUrl' => $this->urlGenerator->linkToRoute('projectcheck.app_config.searchGroups'),
		];
	}

	/**
	 * Current org default settings for audit diff (string map only).
	 *
	 * @return array<string, string>
	 */
	private function readAppDefaultSnapshot(): array
	{
		return [
			'default_hourly_rate' => $this->config->getAppValue('projectcheck', 'default_hourly_rate', '50.00'),
			'budget_warning_threshold' => $this->config->getAppValue('projectcheck', 'budget_warning_threshold', '80'),
			'max_projects_per_user' => $this->config->getAppValue('projectcheck', 'max_projects_per_user', '100'),
			'enable_time_tracking' => $this->config->getAppValue('projectcheck', 'enable_time_tracking', 'yes'),
			'enable_customer_management' => $this->config->getAppValue('projectcheck', 'enable_customer_management', 'yes'),
			'enable_budget_tracking' => $this->config->getAppValue('projectcheck', 'enable_budget_tracking', 'yes'),
		];
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private function saveAppDefaults(array $payload): void
	{
		$v = $payload;
		if (isset($v['default_hourly_rate']) && (string) $v['default_hourly_rate'] !== '') {
			$x = (float) $v['default_hourly_rate'];
			if ($x >= 0) {
				$this->config->setAppValue('projectcheck', 'default_hourly_rate', number_format($x, 2, '.', ''));
			}
		}
		if (isset($v['budget_warning_threshold'])) {
			$t = (int) $v['budget_warning_threshold'];
			if ($t >= 0 && $t <= 100) {
				$this->config->setAppValue('projectcheck', 'budget_warning_threshold', (string) $t);
			}
		}
		if (isset($v['max_projects_per_user'])) {
			$m = (int) $v['max_projects_per_user'];
			if ($m >= 1) {
				$this->config->setAppValue('projectcheck', 'max_projects_per_user', (string) $m);
			}
		}
		foreach (['enable_time_tracking', 'enable_customer_management', 'enable_budget_tracking'] as $k) {
			if (!isset($v[$k])) {
				continue;
			}
			$val = is_string($v[$k]) && strtolower($v[$k]) === 'yes' ? 'yes' : (strtolower((string) $v[$k]) === 'no' ? 'no' : null);
			if ($val === null) {
				continue;
			}
			$this->config->setAppValue('projectcheck', $k, $val);
		}
	}

	/**
	 * Read POST/PUT data without using Request::getContent() (protected in Nextcloud core).
	 *
	 * For JSON, the body is read from php://input so the result matches the request body and is
	 * not combined with the query string (relevant for security and parity with the browser client
	 * which always sends application/json with a full body).
	 *
	 * @return array<string, mixed>
	 * @throws JsonException when the body looks like JSON but is not valid
	 */
	private function getPayload(): array
	{
		$contentType = $this->request->getHeader('Content-Type');
		if (preg_match(IRequest::JSON_CONTENT_TYPE_REGEX, $contentType) === 1) {
			$raw = (string) file_get_contents('php://input');
			if ($raw === '') {
				// Stream may have been read earlier; the framework may have merged JSON into parameters.
				$merged = $this->request->getParams();
				return is_array($merged) ? $merged : [];
			}
			$data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
			return is_array($data) ? $data : [];
		}
		$params = $this->request->getParams();
		if (is_array($params) && $params !== []) {
			return $params;
		}
		$raw = (string) file_get_contents('php://input');
		if ($raw === '') {
			return is_array($params) ? $params : [];
		}
		$trim = ltrim($raw);
		if (!str_starts_with($trim, '{') && !str_starts_with($trim, '[')) {
			return is_array($params) ? $params : [];
		}
		$data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
		return is_array($data) ? $data : [];
	}

	/**
	 * User-facing text for policy validation; never expose raw English service strings to the client.
	 */
	private function mapPolicyValidationToMessage(\InvalidArgumentException $e, IL10N $l): string
	{
		$raw = $e->getMessage();
		if (str_contains($raw, 'at least one user or one group is required')) {
			return $l->t('When access restriction is on, at least one allowed user or one allowed group is required.');
		}
		if (preg_match('/^User does not exist: (.+)$/', $raw, $m)) {
			return $l->t('The user “%s” was not found in Nextcloud. Use the exact account login name.', [ $m[1] ]);
		}
		if (preg_match('/^Group does not exist: (.+)$/', $raw, $m)) {
			return $l->t('The group “%s” was not found in Nextcloud. Use the exact group identifier from the user management app.', [ $m[1] ]);
		}
		return $l->t('The data could not be saved. Check your entries and try again.');
	}
}
