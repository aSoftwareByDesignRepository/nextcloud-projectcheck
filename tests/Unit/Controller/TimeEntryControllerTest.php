<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Controller;

use OCA\ProjectCheck\Controller\TimeEntryController;
use OCA\ProjectCheck\Db\Project;
use OCA\ProjectCheck\Db\TimeEntry;
use OCA\ProjectCheck\Exception\PermissionDeniedException;
use OCA\ProjectCheck\Exception\TimeEntryNotFoundException;
use OCA\ProjectCheck\Service\ActivityService;
use OCA\ProjectCheck\Service\BudgetService;
use OCA\ProjectCheck\Service\CSPService;
use OCA\ProjectCheck\Service\CustomerService;
use OCA\ProjectCheck\Service\DateFormatService;
use OCA\ProjectCheck\Service\DeletionService;
use OCA\ProjectCheck\Service\ListExportService;
use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Service\TimeEntryBillingService;
use OCA\ProjectCheck\Service\TimeEntryService;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\DataDownloadResponse;
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
		$this->timeEntryService->method('getUsersWithTimeEntries')->willReturn([]);
		$this->timeEntryService->method('getYearlyStatsByProjectType')->willReturn([]);
		$this->timeEntryService->method('getDetailedYearlyStatsByProjectType')->willReturn([]);
		$this->timeEntryService->method('getProductivityAnalysis')->willReturn([]);
		$this->timeEntryService->method('getTotalHoursForUser')->willReturn(0.0);
		$this->timeEntryService->method('getTotalCostForUser')->willReturn(0.0);

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
			$logger,
			new ListExportService($this->config, 'projectcheck'),
			$this->createMock(TimeEntryBillingService::class)
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
		$this->timeEntryService->method('sumTimeEntriesHours')->willReturn(2.5);
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
		$summary = $params['selectionSummary'] ?? null;
		$this->assertIsArray($summary);
		$this->assertSame(2.5, $summary['hoursTotal']);
		$this->assertSame(1, $summary['entryCount']);
		$this->assertSame(2.5, $summary['pageHoursTotal']);
		$this->assertSame(1, $summary['pageEntryCount']);
	}

	/**
	 * A non-global user's list is restricted to their *own* entries
	 * (user_id = self). Ownership is sufficient visibility, so the
	 * project-access scope must NOT additionally be applied — it would
	 * only hide the user's own historical entries on projects they have
	 * since left, making them unreachable for editing/deleting.
	 */
	public function testIndexDoesNotHideOwnEntriesBehindProjectScopeForNonGlobalUser(): void {
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
		$this->timeEntryService->method('getProjectIdsWithEntriesForUser')->willReturn([]);

		$ownEntriesOnly = static function (array $filters): bool {
			return !array_key_exists('project_ids', $filters)
				&& ($filters['user_id'] ?? null) === 'member-user';
		};

		$this->timeEntryService->expects($this->once())
			->method('countTimeEntries')
			->with($this->callback($ownEntriesOnly))
			->willReturn(0);

		$this->timeEntryService->expects($this->once())
			->method('sumTimeEntriesHours')
			->with($this->callback(static function (array $filters) use ($ownEntriesOnly): bool {
				return $ownEntriesOnly($filters)
					&& !array_key_exists('limit', $filters)
					&& !array_key_exists('offset', $filters);
			}))
			->willReturn(0.0);

		$this->timeEntryService->expects($this->once())
			->method('getTimeEntriesWithProjectInfo')
			->with($this->callback($ownEntriesOnly))
			->willReturn([]);

		// The *user filter dropdown* stays scoped to accessible projects —
		// it lists other people and must not leak inaccessible projects.
		$this->timeEntryService->expects($this->once())
			->method('getUsersWithTimeEntries')
			->with($accessibleProjectIds)
			->willReturn([]);

		$response = $this->controller->index();
		$this->assertInstanceOf(TemplateResponse::class, $response);
		$this->assertSame($accessibleProjectIds, $response->getParams()['accessibleProjectIds']);
	}

	/**
	 * The filter dropdown must include projects the user owns entries on but
	 * can no longer access (membership ended) — those entries are listed, so
	 * they must be filterable.
	 */
	public function testIndexFilterDropdownIncludesProjectsFromOwnEntries(): void {
		$this->request->method('getParam')->willReturnCallback(
			static function (string $name, $default = null) {
				return match ($name) {
					'project_id', 'date_from', 'date_to', 'search', 'user_id', 'project_type' => '',
					'page' => 1,
					default => $default,
				};
			}
		);

		$accessible = new Project();
		$accessible->setId(2);
		$accessible->setName('Current project');

		$left = new Project();
		$left->setId(9);
		$left->setName('Left project');

		$this->projectService->method('getAccessibleProjectIdListForUser')->with('member-user')->willReturn([2]);
		$this->projectService->method('canUserViewAllTimeEntries')->with('member-user')->willReturn(false);
		$this->projectService->method('getProjectsForUserTimeEntry')->willReturn([$accessible]);
		$this->projectService->method('getProject')->with(9)->willReturn($left);
		$this->timeEntryService->method('getProjectIdsWithEntriesForUser')->with('member-user')->willReturn([2, 9]);

		$this->timeEntryService->method('countTimeEntries')->willReturn(0);
		$this->timeEntryService->method('sumTimeEntriesHours')->willReturn(0.0);
		$this->timeEntryService->method('getTimeEntriesWithProjectInfo')->willReturn([]);
		$this->timeEntryService->method('getUsersWithTimeEntries')->willReturn([]);

		$response = $this->controller->index();
		$this->assertInstanceOf(TemplateResponse::class, $response);

		$dropdownIds = array_map(
			static fn ($p) => (int) $p->getId(),
			$response->getParams()['projects']
		);
		$this->assertContains(2, $dropdownIds);
		$this->assertContains(9, $dropdownIds, 'Project of own historical entries must be filterable');
	}

	public function testExportDoesNotHideOwnEntriesBehindProjectScopeForNonGlobalUser(): void {
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
			->with($this->callback(static function (array $filters): bool {
				return !array_key_exists('project_ids', $filters)
					&& ($filters['user_id'] ?? null) === 'member-user';
			}))
			->willReturn([]);

		$response = $this->controller->export();

		$this->assertInstanceOf(DataDownloadResponse::class, $response);
		$this->assertSame(200, $response->getStatus());
		$this->assertStringStartsWith("\xEF\xBB\xBF", (string)$response->render());
		$this->assertSame('0', $response->getHeaders()['X-ProjectCheck-Export-Row-Count'] ?? null);
	}

	/**
	 * Global viewers can filter by another user; project scope must then apply
	 * (here: null scope = all projects, so no project_ids filter either, but the
	 * foreign user filter must be honored as requested).
	 */
	public function testExportKeepsProjectScopeWhenFilteringForeignUser(): void {
		$this->request->method('getParam')->willReturnCallback(
			static function (string $name, $default = null) {
				return match ($name) {
					'user_id' => 'someone-else',
					'project_id', 'date_from', 'date_to', 'search', 'project_type' => '',
					default => $default,
				};
			}
		);

		$this->projectService->method('getAccessibleProjectIdListForUser')->with('member-user')->willReturn([5]);
		$this->projectService->method('canUserViewAllTimeEntries')->with('member-user')->willReturn(false);

		// Non-global user asking for someone else's entries is forced back to
		// their own — and then the project scope is again unnecessary.
		$this->timeEntryService->expects($this->once())
			->method('getTimeEntriesWithProjectInfo')
			->with($this->callback(static function (array $filters): bool {
				return ($filters['user_id'] ?? null) === 'member-user'
					&& !array_key_exists('project_ids', $filters);
			}))
			->willReturn([]);

		$response = $this->controller->export();
		$this->assertInstanceOf(DataDownloadResponse::class, $response);
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
		$this->assertInstanceOf(DataDownloadResponse::class, $response);
		$this->assertStringContainsString('"Hourly Rate (USD)";"Total Amount (USD)"', (string)$response->render());
	}

	/**
	 * Regression guard for the UI delete 500: route handlers receiving {id} must
	 * declare a native int parameter so the AppFramework dispatcher casts the
	 * (string) route segment before the call. An untyped parameter put a string
	 * into the strictly-typed mapper and produced a TypeError on every delete.
	 */
	public function testRouteIdParametersAreNativelyTypedInt(): void {
		foreach (['show', 'edit', 'update', 'updatePost', 'delete', 'deletePost', 'getDeletionImpact'] as $method) {
			$reflection = new \ReflectionMethod(TimeEntryController::class, $method);
			$param = $reflection->getParameters()[0];
			$type = $param->getType();
			$this->assertInstanceOf(\ReflectionNamedType::class, $type, "$method(\$id) must have a native type");
			$this->assertSame('int', $type->getName(), "$method(\$id) must be natively typed int");
		}
		$projectParam = (new \ReflectionMethod(TimeEntryController::class, 'getForProject'))->getParameters()[0];
		$projectType = $projectParam->getType();
		$this->assertInstanceOf(\ReflectionNamedType::class, $projectType);
		$this->assertSame('int', $projectType->getName());
	}

	public function testDeletePostReturnsSuccessWithLocalizedMessage(): void {
		$entry = new TimeEntry();
		$entry->setId(60);
		$entry->setProjectId(4);
		$entry->setUserId('member-user');
		$entry->setDate(new \DateTime('2026-06-10'));
		$entry->setHours(2.0);
		$entry->setHourlyRate(123.0);
		$entry->setCreatedAt(new \DateTime('2026-06-10 09:00:00'));
		$entry->setUpdatedAt(new \DateTime('2026-06-10 09:00:00'));

		$this->timeEntryService->method('getTimeEntry')->with(60)->willReturn($entry);
		$this->timeEntryService->expects($this->once())
			->method('deleteTimeEntry')
			->with(60, 'member-user');

		$response = $this->controller->deletePost(60);

		$this->assertSame(200, $response->getStatus());
		$data = $response->getData();
		$this->assertTrue($data['success']);
		$this->assertSame('Time entry was deleted successfully!', $data['message']);
	}

	public function testDeleteMapsPermissionDeniedTo403(): void {
		$this->timeEntryService->method('getTimeEntry')->willReturn(null);
		$this->timeEntryService->method('deleteTimeEntry')
			->willThrowException(new PermissionDeniedException('delete', 'time entry', 'Access denied'));

		$response = $this->controller->deletePost(61);

		$this->assertSame(403, $response->getStatus());
		$this->assertFalse($response->getData()['success']);
	}

	public function testDeleteMapsNotFoundTo404(): void {
		$this->timeEntryService->method('getTimeEntry')->willReturn(null);
		$this->timeEntryService->method('deleteTimeEntry')
			->willThrowException(new TimeEntryNotFoundException(62, 'Time entry not found'));

		$response = $this->controller->deletePost(62);

		$this->assertSame(404, $response->getStatus());
		$this->assertFalse($response->getData()['success']);
	}

	public function testEditAlwaysIncludesTheEntrysOwnProjectInTheDropdown(): void {
		$entry = new TimeEntry();
		$entry->setId(77);
		$entry->setProjectId(12);
		$entry->setUserId('member-user');
		$entry->setDate(new \DateTime('2026-02-06'));
		$entry->setHours(2.0);
		$entry->setHourlyRate(121.14);
		$entry->setCreatedAt(new \DateTime('2026-02-06 09:00:00'));
		$entry->setUpdatedAt(new \DateTime('2026-02-06 09:00:00'));

		$archivedProject = new Project();
		$archivedProject->setId(12);
		$archivedProject->setName('DWE');
		$archivedProject->setStatus('Archived');

		$otherProject = new Project();
		$otherProject->setId(4);
		$otherProject->setName('Lorem ipsum');
		$otherProject->setStatus('Active');

		$this->timeEntryService->method('getTimeEntry')->with(77)->willReturn($entry);
		// Picker no longer returns the archived project (e.g. membership ended).
		$this->projectService->method('getProjectsForUserTimeEntry')->willReturn([$otherProject]);
		$this->projectService->method('getProject')->with(12)->willReturn($archivedProject);

		$response = $this->controller->edit(77);

		$this->assertInstanceOf(TemplateResponse::class, $response);
		$params = $response->getParams();
		$projectIds = array_map(static fn ($p) => (int) $p->getId(), $params['projects']);
		$this->assertContains(12, $projectIds, 'The entry\'s own project must stay selectable');
		$this->assertContains(4, $projectIds);
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
		$this->assertInstanceOf(DataDownloadResponse::class, $response);
		$csv = (string)$response->render();
		$this->assertStringContainsString("\"'=cmd\"", $csv);
		$this->assertStringContainsString("\"'+sum(A1:A2)\"", $csv);
		$this->assertStringContainsString("\"'@calc\"", $csv);
		$this->assertStringContainsString("\"'=HYPERLINK(\"\"http://evil.local\"\",\"\"click\"\")\"", $csv);
		$this->assertStringContainsString("\"'-user\"", $csv);
	}

	public function testShowOmitsProjectLinkWhenViewerCannotAccessProject(): void {
		$entry = new TimeEntry();
		$entry->setId(88);
		$entry->setProjectId(12);
		$entry->setUserId('member-user');
		$entry->setDate(new \DateTime('2026-03-01'));
		$entry->setHours(1.0);
		$entry->setHourlyRate(100.0);
		$entry->setCreatedAt(new \DateTime('2026-03-01 10:00:00'));
		$entry->setUpdatedAt(new \DateTime('2026-03-01 10:00:00'));

		$project = new Project();
		$project->setId(12);
		$project->setName('Former team project');

		$this->timeEntryService->method('getTimeEntry')->with(88)->willReturn($entry);
		$this->projectService->method('getProject')->with(12)->willReturn($project);
		$this->projectService->method('canUserAccessProject')->with('member-user', 12)->willReturn(false);

		$response = $this->controller->show(88);

		$this->assertInstanceOf(TemplateResponse::class, $response);
		$this->assertFalse($response->getParams()['projectLinkable']);
	}

	/**
	 * D5b / E20c: getForProject must not dump teammate entries to ordinary Members.
	 */
	public function testGetForProjectFiltersByCanUserViewTimeEntry(): void {
		$own = new TimeEntry();
		$own->setId(1);
		$own->setProjectId(42);
		$own->setUserId('member-user');
		$own->setDate(new \DateTime('2026-07-01'));
		$own->setHours(2.0);
		$own->setDescription('mine');
		$own->setHourlyRate(100.0);
		$own->setCreatedAt(new \DateTime('2026-07-01 10:00:00'));
		$own->setUpdatedAt(new \DateTime('2026-07-01 10:00:00'));

		$teammate = new TimeEntry();
		$teammate->setId(2);
		$teammate->setProjectId(42);
		$teammate->setUserId('other-user');
		$teammate->setDate(new \DateTime('2026-07-01'));
		$teammate->setHours(8.0);
		$teammate->setDescription('secret teammate hours');
		$teammate->setHourlyRate(150.0);
		$teammate->setCreatedAt(new \DateTime('2026-07-01 10:00:00'));
		$teammate->setUpdatedAt(new \DateTime('2026-07-01 10:00:00'));

		$this->projectService->method('canUserAccessProject')->with('member-user', 42)->willReturn(true);
		$this->timeEntryService->method('getTimeEntriesByProject')->with(42)->willReturn([$own, $teammate]);
		$this->projectService->method('canUserViewTimeEntry')->willReturnCallback(
			static function (string $uid, TimeEntry $entry) use ($own): bool {
				return $uid === 'member-user' && (int) $entry->getId() === (int) $own->getId();
			}
		);

		$response = $this->controller->getForProject(42);

		$this->assertSame(200, $response->getStatus());
		$data = $response->getData();
		$this->assertTrue($data['success']);
		$this->assertCount(1, $data['timeEntries']);
		$this->assertSame(1, (int) $data['timeEntries'][0]['id']);
	}
}

