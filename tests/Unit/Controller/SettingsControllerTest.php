<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Controller;

use OCA\ProjectCheck\Controller\SettingsController;
use OCA\ProjectCheck\Service\CSPService;
use OCA\ProjectCheck\Service\CustomerService;
use OCA\ProjectCheck\Service\ProjectService;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

class SettingsControllerTest extends TestCase {
	/** @var SettingsController */
	private $controller;
	/** @var IUserSession|\PHPUnit\Framework\MockObject\MockObject */
	private $userSession;
	/** @var ProjectService|\PHPUnit\Framework\MockObject\MockObject */
	private $projectService;
	/** @var IRequest|\PHPUnit\Framework\MockObject\MockObject */
	private $request;
	/** @var IUser|\PHPUnit\Framework\MockObject\MockObject */
	private $user;
	/** @var IConfig|\PHPUnit\Framework\MockObject\MockObject */
	private $config;
	/** @var CustomerService|\PHPUnit\Framework\MockObject\MockObject */
	private $customerService;

	protected function setUp(): void {
		parent::setUp();

		$this->request = $this->createMock(IRequest::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->config = $this->createMock(IConfig::class);
		$groupManager = $this->createMock(IGroupManager::class);
		$cspService = $this->createMock(CSPService::class);
		$cspService->method('applyPolicyWithNonce')->willReturnArgument(0);
		$this->projectService = $this->createMock(ProjectService::class);
		$this->customerService = $this->createMock(CustomerService::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $s): string => $s);

		$this->controller = new SettingsController(
			'projectcheck',
			$this->request,
			$this->userSession,
			$this->config,
			$groupManager,
			$cspService,
			$this->projectService,
			$this->customerService,
			$l10n
		);

		$this->user = $this->createMock(IUser::class);
		$this->user->method('getUID')->willReturn('member-user');
		$this->userSession->method('getUser')->willReturn($this->user);
		$this->request->method('getParams')->willReturn([]);
	}

	public function testIndexDeniedWhenUserCannotManageSettings(): void {
		$this->projectService->method('canManageSettings')->with('member-user')->willReturn(false);

		$response = $this->controller->index();

		$this->assertInstanceOf(TemplateResponse::class, $response);
		$this->assertSame('error', $response->getTemplateName());
	}

	public function testUpdateDeniedWhenUserCannotManageSettings(): void {
		$this->projectService->method('canManageSettings')->with('member-user')->willReturn(false);

		$response = $this->controller->update();

		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertSame(403, $response->getStatus());
	}

	public function testUpdateAllowedWhenUserCanManageSettings(): void {
		$this->projectService->method('canManageSettings')->with('member-user')->willReturn(true);
		$this->request->method('getParams')->willReturn(['itemsPerPage' => '25']);

		$response = $this->controller->update();

		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertSame(200, $response->getStatus());
	}
}

