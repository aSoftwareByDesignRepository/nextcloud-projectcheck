<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Controller;

use OCA\ProjectCheck\Controller\CustomerController;
use OCA\ProjectCheck\Service\ActivityService;
use OCA\ProjectCheck\Service\BudgetService;
use OCA\ProjectCheck\Service\CSPService;
use OCA\ProjectCheck\Service\CustomerService;
use OCA\ProjectCheck\Service\DeletionService;
use OCA\ProjectCheck\Service\IRequestTokenProvider;
use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Service\TimeEntryService;
use OCA\ProjectCheck\Db\Customer;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CustomerControllerTest extends TestCase {
	/** @var CustomerController */
	private $controller;
	/** @var IRequest|\PHPUnit\Framework\MockObject\MockObject */
	private $request;
	/** @var IUserSession|\PHPUnit\Framework\MockObject\MockObject */
	private $userSession;
	/** @var CustomerService|\PHPUnit\Framework\MockObject\MockObject */
	private $customerService;
	/** @var ProjectService|\PHPUnit\Framework\MockObject\MockObject */
	private $projectService;
	/** @var IUser|\PHPUnit\Framework\MockObject\MockObject */
	private $user;

	protected function setUp(): void {
		parent::setUp();

		$this->request = $this->createMock(IRequest::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->customerService = $this->createMock(CustomerService::class);
		$this->projectService = $this->createMock(ProjectService::class);
		$budgetService = $this->createMock(BudgetService::class);
		$timeEntryService = $this->createMock(TimeEntryService::class);
		$deletionService = $this->createMock(DeletionService::class);
		$activityService = $this->createMock(ActivityService::class);
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('linkToRoute')->willReturnCallback(
			static fn (string $route, array $params = []): string => '/index.php/' . $route . (empty($params) ? '' : '?' . http_build_query($params))
		);
		$config = $this->createMock(IConfig::class);
		$cspService = $this->createMock(CSPService::class);
		$cspService->method('applyPolicyWithNonce')->willReturnArgument(0);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn ($s, $p = []) => is_array($p) && $p !== [] ? vsprintf((string)$s, $p) : (string)$s);
		$logger = $this->createMock(LoggerInterface::class);
		$requestTokenProvider = $this->createMock(IRequestTokenProvider::class);
		$requestTokenProvider->method('getEncryptedRequestToken')->willReturn('mock-token');

		$this->controller = new CustomerController(
			'projectcheck',
			$this->request,
			$this->userSession,
			$this->customerService,
			$this->projectService,
			$budgetService,
			$timeEntryService,
			$deletionService,
			$activityService,
			$urlGenerator,
			$config,
			$cspService,
			$l10n,
			$logger,
			$requestTokenProvider
		);

		$this->user = $this->createMock(IUser::class);
		$this->user->method('getUID')->willReturn('member-user');
		$this->userSession->method('getUser')->willReturn($this->user);

	}

	public function testCreateDeniedWhenUserCannotCreateCustomers(): void {
		$this->projectService->method('canUserCreateCustomer')->with('member-user')->willReturn(false);

		$response = $this->controller->create();

		$this->assertInstanceOf(TemplateResponse::class, $response);
		$this->assertSame('error', $response->getTemplateName());
	}

	public function testStoreDeniedWhenUserCannotCreateCustomers(): void {
		$this->projectService->method('canUserCreateCustomer')->with('member-user')->willReturn(false);

		$response = $this->controller->store();

		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertSame(403, $response->getStatus());
	}

	public function testUpdateDeniedWhenUserCannotEditCustomer(): void {
		$this->request->method('getMethod')->willReturn('PUT');
		$this->request->method('getParam')->willReturnCallback(static fn (string $name, $default = null) => $name === '_method' ? null : $default);
		$this->customerService->method('canUserEditCustomer')->with('member-user', 9)->willReturn(false);

		$response = $this->controller->update(9);

		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertSame(403, $response->getStatus());
	}

	public function testGetStatsDeniedForInaccessibleCustomer(): void {
		$this->request->method('getParam')->willReturnCallback(
			static fn (string $name, $default = null) => $name === 'customer_id' ? 7 : $default
		);
		$this->customerService->method('canUserViewCustomer')->with('member-user', 7)->willReturn(false);

		$response = $this->controller->getStats();

		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertSame(403, $response->getStatus());
	}

	public function testCreateAllowedWhenUserCanCreateCustomers(): void {
		$this->projectService->method('canUserCreateCustomer')->with('member-user')->willReturn(true);
		$this->projectService->method('getAccessibleProjectIdListForUser')->with('member-user')->willReturn([]);
		$this->customerService->method('getVisibleCustomerCountForUser')->with('member-user')->willReturn(0);

		$response = $this->controller->create();

		$this->assertInstanceOf(TemplateResponse::class, $response);
		$this->assertSame('customer-form', $response->getTemplateName());
	}

	public function testStoreAllowedWhenUserCanCreateCustomers(): void {
		$this->projectService->method('canUserCreateCustomer')->with('member-user')->willReturn(true);
		$this->request->method('getParams')->willReturn(['name' => 'Acme']);
		$this->customerService->method('validateCustomerData')->with(['name' => 'Acme'])->willReturn([]);
		$customer = $this->createMock(Customer::class);
		$customer->method('getSummary')->willReturn(['id' => 10, 'name' => 'Acme']);
		$this->customerService->method('createCustomer')->with(['name' => 'Acme'], 'member-user')->willReturn($customer);

		$response = $this->controller->store();

		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertSame(200, $response->getStatus());
	}

	public function testGetStatsAllowedForAccessibleCustomer(): void {
		$this->request->method('getParam')->willReturnCallback(
			static fn (string $name, $default = null) => $name === 'customer_id' ? 7 : $default
		);
		$this->customerService->method('canUserViewCustomer')->with('member-user', 7)->willReturn(true);
		$this->projectService->method('getUserScopedProjectIdsForCustomer')->with('member-user', 7)->willReturn([2, 3]);
		$this->customerService->method('getCustomerSpecificStats')->with(7, [2, 3])->willReturn(['total_projects' => 2]);

		$response = $this->controller->getStats();

		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertSame(200, $response->getStatus());
	}
}

