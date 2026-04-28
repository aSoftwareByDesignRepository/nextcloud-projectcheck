<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Controller;

use OCA\ProjectCheck\Controller\TimeEntryController;
use OCA\ProjectCheck\Db\Project;
use OCA\ProjectCheck\Service\ActivityService;
use OCA\ProjectCheck\Service\BudgetService;
use OCA\ProjectCheck\Service\CSPService;
use OCA\ProjectCheck\Service\CustomerService;
use OCA\ProjectCheck\Service\DateFormatService;
use OCA\ProjectCheck\Service\DeletionService;
use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Service\TimeEntryService;
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
	private ProjectService $projectService;
	private CustomerService $customerService;
	private TimeEntryService $timeEntryService;
	private IUserSession $userSession;
	private IUser $user;

	protected function setUp(): void {
		parent::setUp();

		$request = $this->createMock(IRequest::class);
		$request->method('getParam')->willReturn('');

		$this->projectService = $this->createMock(ProjectService::class);
		$this->customerService = $this->createMock(CustomerService::class);
		$this->timeEntryService = $this->createMock(TimeEntryService::class);
		$budgetService = $this->createMock(BudgetService::class);
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('linkToRoute')->willReturnCallback(
			static fn (string $route, array $params = []): string => '/index.php/' . $route . (empty($params) ? '' : '?' . http_build_query($params))
		);
		$config = $this->createMock(IConfig::class);
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

		$this->projectService->method('getAccessibleProjectIdListForUser')->willReturn([]);
		$this->projectService->method('getProjectsByIdList')->willReturn([]);
		$this->customerService->method('getVisibleCustomerCountForUser')->willReturn(0);

		$this->controller = new TimeEntryController(
			'projectcheck',
			$request,
			$this->userSession,
			$this->timeEntryService,
			$this->projectService,
			$this->customerService,
			$budgetService,
			$urlGenerator,
			$config,
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
}

