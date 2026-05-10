<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Controller;

use OCA\ProjectCheck\Controller\TimeEntryController;
use OCA\ProjectCheck\Db\Project;
use OCA\ProjectCheck\Db\TimeEntry;
use OCA\ProjectCheck\Service\ActivityService;
use OCA\ProjectCheck\Service\BudgetService;
use OCA\ProjectCheck\Service\CSPService;
use OCA\ProjectCheck\Service\CustomerService;
use OCA\ProjectCheck\Service\DateFormatService;
use OCA\ProjectCheck\Service\DeletionService;
use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Service\TimeEntryService;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class TimeEntryControllerTest extends TestCase {
	private TimeEntryController $controller;
	private IRequest $request;
	private ProjectService $projectService;
	private CustomerService $customerService;
	private TimeEntryService $timeEntryService;
	private IUserSession $userSession;
	private IUser $user;
	private IConfig $config;
	private string $configuredCurrency = 'EUR';

	protected function setUp(): void {
		parent::setUp();

		$this->request = $this->createMock(IRequest::class);

		$this->projectService = $this->createMock(ProjectService::class);
		$this->customerService = $this->createMock(CustomerService::class);
		$this->timeEntryService = $this->createMock(TimeEntryService::class);
		$budgetService = $this->createMock(BudgetService::class);
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('linkToRoute')->willReturnCallback(
			static fn (string $route, array $params = []): string => '/index.php/' . $route . (empty($params) ? '' : '?' . http_build_query($params))
		);
		$this->config = $this->createMock(IConfig::class);
		$this->config->method('getAppValue')->willReturnCallback(function (
			string $app,
			string $key,
			string $default = ''
		): string {
			if ($app === 'projectcheck' && $key === 'currency') {
				return $this->configuredCurrency;
			}
			return $default;
		});
		$dateFormatService = $this->createMock(DateFormatService::class);
		$deletionService = $this->createMock(DeletionService::class);
		$activityService = $this->createMock(ActivityService::class);
		$cspService = $this->createMock(CSPService::class);
		$cspService->method('applyPolicyWithNonce')->willReturnArgument(0);

		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(
			static fn ($s, $p = []) => is_array($p) && !empty($p) ? vsprintf((string)$s, $p) : (string)$s
		);

		$logger = $this->createMock(LoggerInterface::class);

		$this->userSession = $this->createMock(IUserSession::class);
		$this->user = $this->createMock(IUser::class);
		$this->user->method('getUID')->willReturn('member-user');
		$this->userSession->method('getUser')->willReturn($this->user);

		$this->projectService->method('getProjectsByIdList')->willReturn([]);
		$this->projectService->method('canUserViewAllTimeEntries')->willReturn(false);
		$this->customerService->method('getVisibleCustomerCountForUser')->willReturn(0);
		$this->timeEntryService->method('countTimeEntries')->willReturn(0);
		$this->timeEntryService->method('getUsersWithTimeEntries')->willReturn([]);
		$this->timeEntryService->method('getYearlyStatsByProjectType')->willReturn([]);
		$this->timeEntryService->method('getDetailedYearlyStatsByProjectType')->willReturn([]);
		$this->timeEntryService->method('getProductivityAnalysis')->willReturn([]);

		$this->controller = new TimeEntryController(
			'projectcheck',
			$this->request,
			$this->userSession,
			$this->timeEntryService,
			$this->projectService,
			$this->customerService,
			$budgetService,
			$urlGenerator,
			$this->config,
			$dateFormatService,
			$deletionService,
			$activityService,
			$cspService,
			$l10n,
			$logger
		);
	}

	public function testCreateShowsAccessibleProjectsForNonAdminUser(): void {
		$projectA = new Project();
		$projectA->setId(10);
		$projectA->setName('Zeus');
		$projectA->setStatus('Active');
		$projectA->setCreatedBy('owner');
		$projectA->setCreatedAt(new \DateTime('2026-01-01 00:00:00'));
		$projectA->setUpdatedAt(new \DateTime('2026-01-01 00:00:00'));

		$projectB = new Project();
		$projectB->setId(11);
		$projectB->setName('Apollo');
		$projectB->setStatus('On Hold');
		$projectB->setCreatedBy('owner');
		$projectB->setCreatedAt(new \DateTime('2026-01-01 00:00:00'));
		$projectB->setUpdatedAt(new \DateTime('2026-01-01 00:00:00'));

		$this->projectService->expects($this->once())
			->method('getProjectsForUserTimeEntry')
			->with('member-user', ['status' => ['Active', 'On Hold']])
			->willReturn([$projectA, $projectB]);

		$response = $this->controller->create();

		$this->assertInstanceOf(TemplateResponse::class, $response);
		$this->assertSame('time-entry-form', $response->getTemplateName());
		$params = $response->getParams();
		$this->assertArrayHasKey('projects', $params);
		$this->assertCount(2, $params['projects']);
		// Controller sorts by name, so Apollo should come before Zeus.
		$this->assertSame('Apollo', $params['projects'][0]->getName());
		$this->assertSame('Zeus', $params['projects'][1]->getName());
	}

	public function testIndexKeepsSelectedUserFilterVisibleWhenMissingInUserOptions(): void {
		$selectedUserId = 'gna';
		$timeEntry = new TimeEntry();
		$timeEntry->setId(123);
		$timeEntry->setProjectId(2);
		$timeEntry->setUserId($selectedUserId);
		$timeEntry->setDate(new \DateTime('2026-04-01'));
		$timeEntry->setHours(2.5);
		$timeEntry->setDescription('Filtered entry');
		$timeEntry->setHourlyRate(100.0);
		$timeEntry->setCreatedAt(new \DateTime('2026-04-01 10:00:00'));
		$timeEntry->setUpdatedAt(new \DateTime('2026-04-01 10:00:00'));

		$this->request->method('getParam')->willReturnCallback(
			static function (string $name, $default = null) use ($selectedUserId) {
				return match ($name) {
					'project_id' => '2',
					'user_id' => $selectedUserId,
					'page' => 1,
					'date_from', 'date_to', 'search', 'project_type' => '',
					default => $default,
				};
			}
		);

		$this->timeEntryService->method('countTimeEntries')->willReturn(1);
		$this->projectService->method('getProjectsForUserTimeEntry')->willReturn([]);
		$this->timeEntryService->method('getUsersWithTimeEntries')->willReturn([
			['user_id' => 'alice', 'displayname' => 'Alice'],
		]);
		$this->timeEntryService->method('getTimeEntriesWithProjectInfo')->willReturn([
			[
				'timeEntry' => $timeEntry,
				'userDisplayName' => 'Gina Adams',
			],
		]);

		$response = $this->controller->index();

		$this->assertInstanceOf(TemplateResponse::class, $response);
		$params = $response->getParams();
		$this->assertSame('member-user', $params['filters']['user_id']);
		$selectedUserOption = array_values(array_filter(
			$params['users'],
			static fn (array $user): bool => ($user['user_id'] ?? '') === $selectedUserId
		));
		$this->assertNotEmpty($selectedUserOption);
		$this->assertNotSame('', trim((string)($selectedUserOption[0]['displayname'] ?? '')));
	}

	public function testIndexScopesQueriesToAccessibleProjectsForNonGlobalUser(): void {
		$this->request->method('getParam')->willReturnCallback(
			static function (string $name, $default = null) {
				return match ($name) {
					'project_id', 'date_from', 'date_to', 'search', 'user_id', 'project_type' => '',
					'page' => 1,
					default => $default,
				};
			}
		);

		$accessibleProjectIds = [2, 4];
		$this->projectService->method('getAccessibleProjectIdListForUser')->with('member-user')->willReturn($accessibleProjectIds);
		$this->projectService->method('canUserViewAllTimeEntries')->with('member-user')->willReturn(false);
		$this->projectService->method('getProjectsForUserTimeEntry')->willReturn([]);

		$this->timeEntryService->expects($this->once())
			->method('countTimeEntries')
			->with($this->callback(static function (array $filters) use ($accessibleProjectIds): bool {
				return ($filters['project_ids'] ?? null) === $accessibleProjectIds
					&& ($filters['user_id'] ?? null) === 'member-user';
			}))
			->willReturn(0);

		$this->timeEntryService->expects($this->once())
			->method('getTimeEntriesWithProjectInfo')
			->with($this->callback(static function (array $filters) use ($accessibleProjectIds): bool {
				return ($filters['project_ids'] ?? null) === $accessibleProjectIds
					&& ($filters['user_id'] ?? null) === 'member-user';
			}))
			->willReturn([]);

		$this->timeEntryService->expects($this->once())
			->method('getUsersWithTimeEntries')
			->with($accessibleProjectIds)
			->willReturn([]);

		$response = $this->controller->index();
		$this->assertInstanceOf(TemplateResponse::class, $response);
	}

	public function testExportScopesQueriesToAccessibleProjectsForNonGlobalUser(): void {
		$this->request->method('getParam')->willReturnCallback(
			static function (string $name, $default = null) {
				return match ($name) {
					'project_id', 'date_from', 'date_to', 'search', 'user_id', 'project_type' => '',
					default => $default,
				};
			}
		);

		$accessibleProjectIds = [5, 8];
		$this->projectService->method('getAccessibleProjectIdListForUser')->with('member-user')->willReturn($accessibleProjectIds);
		$this->projectService->method('canUserViewAllTimeEntries')->with('member-user')->willReturn(false);

		$this->timeEntryService->expects($this->once())
			->method('getTimeEntriesWithProjectInfo')
			->with($this->callback(static function (array $filters) use ($accessibleProjectIds): bool {
				return ($filters['project_ids'] ?? null) === $accessibleProjectIds
					&& ($filters['user_id'] ?? null) === 'member-user';
			}))
			->willReturn([]);

		$response = $this->controller->export();

		$this->assertInstanceOf(DataResponse::class, $response);
		$data = $response->getData();
		$this->assertIsArray($data);
		$this->assertArrayHasKey('csv_data', $data);
	}

	public function testExportUsesConfiguredCurrencyInCsvHeader(): void {
		$this->request->method('getParam')->willReturnCallback(
			static function (string $name, $default = null) {
				return match ($name) {
					'project_id', 'date_from', 'date_to', 'search', 'user_id', 'project_type' => '',
					default => $default,
				};
			}
		);
		$this->projectService->method('getAccessibleProjectIdListForUser')->with('member-user')->willReturn([]);
		$this->projectService->method('canUserViewAllTimeEntries')->with('member-user')->willReturn(true);
		$this->configuredCurrency = 'usd';
		$this->timeEntryService->method('getTimeEntriesWithProjectInfo')->willReturn([]);

		$response = $this->controller->export();
		$this->assertInstanceOf(DataResponse::class, $response);
		$data = $response->getData();
		$this->assertIsArray($data);
		$this->assertStringContainsString('"Hourly Rate (USD)";"Total Amount (USD)"', (string)($data['csv_data'] ?? ''));
	}

	public function testExportNeutralizesCsvFormulaInjectionInTextFields(): void {
		$this->request->method('getParam')->willReturnCallback(
			static function (string $name, $default = null) {
				return match ($name) {
					'project_id', 'date_from', 'date_to', 'search', 'user_id', 'project_type' => '',
					default => $default,
				};
			}
		);
		$this->projectService->method('getAccessibleProjectIdListForUser')->with('member-user')->willReturn([]);
		$this->projectService->method('canUserViewAllTimeEntries')->with('member-user')->willReturn(true);

		$entry = new TimeEntry();
		$entry->setProjectId(1);
		$entry->setUserId('member-user');
		$entry->setDate(new \DateTime('2026-05-01'));
		$entry->setHours(1.5);
		$entry->setHourlyRate(100.0);
		$entry->setDescription('=HYPERLINK("http://evil.local","click")');
		$entry->setCreatedAt(new \DateTime('2026-05-01 12:00:00'));
		$entry->setUpdatedAt(new \DateTime('2026-05-01 12:00:00'));

		$this->timeEntryService->method('getTimeEntriesWithProjectInfo')->willReturn([
			[
				'timeEntry' => $entry,
				'projectName' => '=cmd',
				'customerName' => '+sum(A1:A2)',
				'project_type_display_name' => '@calc',
				'userDisplayName' => '-user',
			],
		]);

		$response = $this->controller->export();
		$this->assertInstanceOf(DataResponse::class, $response);
		$data = $response->getData();
		$this->assertIsArray($data);
		$csv = (string)($data['csv_data'] ?? '');
		$this->assertStringContainsString("\"'=cmd\"", $csv);
		$this->assertStringContainsString("\"'+sum(A1:A2)\"", $csv);
		$this->assertStringContainsString("\"'@calc\"", $csv);
		$this->assertStringContainsString("\"'=HYPERLINK(\"\"http://evil.local\"\",\"\"click\"\")\"", $csv);
		$this->assertStringContainsString("\"'-user\"", $csv);
	}
}

