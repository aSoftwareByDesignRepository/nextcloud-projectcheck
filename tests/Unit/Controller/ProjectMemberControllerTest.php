<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Controller;

use OCA\ProjectCheck\Controller\ProjectMemberController;
use OCA\ProjectCheck\Db\ProjectMember;
use OCA\ProjectCheck\Service\ActivityService;
use OCA\ProjectCheck\Service\CSPService;
use OCA\ProjectCheck\Service\DeletionService;
use OCA\ProjectCheck\Service\ProjectMemberService;
use OCA\ProjectCheck\Service\ProjectService;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

class ProjectMemberControllerTest extends TestCase
{
	/** @var IRequest|\PHPUnit\Framework\MockObject\MockObject */
	private $request;
	/** @var IUserSession|\PHPUnit\Framework\MockObject\MockObject */
	private $userSession;
	/** @var ProjectMemberService|\PHPUnit\Framework\MockObject\MockObject */
	private $projectMemberService;
	/** @var ProjectService|\PHPUnit\Framework\MockObject\MockObject */
	private $projectService;
	/** @var DeletionService|\PHPUnit\Framework\MockObject\MockObject */
	private $deletionService;
	/** @var ActivityService|\PHPUnit\Framework\MockObject\MockObject */
	private $activityService;
	/** @var ProjectMemberController */
	private $controller;
	/** @var IUser|\PHPUnit\Framework\MockObject\MockObject */
	private $user;

	protected function setUp(): void
	{
		parent::setUp();
		$this->request = $this->createMock(IRequest::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->projectMemberService = $this->createMock(ProjectMemberService::class);
		$this->projectService = $this->createMock(ProjectService::class);
		$this->deletionService = $this->createMock(DeletionService::class);
		$this->activityService = $this->createMock(ActivityService::class);
		$cspService = $this->createMock(CSPService::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn(string $t): string => $t);

		$this->controller = new ProjectMemberController(
			'projectcheck',
			$this->request,
			$this->userSession,
			$this->projectMemberService,
			$this->projectService,
			$this->deletionService,
			$this->activityService,
			$cspService,
			$l10n
		);

		$this->user = $this->createMock(IUser::class);
		$this->user->method('getUID')->willReturn('manager1');
		$this->userSession->method('getUser')->willReturn($this->user);
	}

	public function testGetDeletionImpactDeniedWithoutManagePermission(): void
	{
		$member = new ProjectMember();
		$member->setId(9);
		$member->setProjectId(3);
		$this->projectMemberService->method('getProjectMember')->with(9)->willReturn($member);
		$this->projectService->method('canUserManageMembers')->with('manager1', 3)->willReturn(false);
		$this->deletionService->expects($this->never())->method('getProjectMemberDeletionImpact');

		$response = $this->controller->getDeletionImpact(9);
		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertEquals(403, $response->getStatus());
	}

	public function testRemoveDeniedWithoutManagePermission(): void
	{
		$member = new ProjectMember();
		$member->setId(11);
		$member->setProjectId(4);
		$this->request->method('getMethod')->willReturn('DELETE');
		$this->request->method('getParam')->with('_method')->willReturn(null);
		$this->projectMemberService->method('getProjectMember')->with(11)->willReturn($member);
		$this->projectService->method('canUserManageMembers')->with('manager1', 4)->willReturn(false);
		$this->deletionService->expects($this->never())->method('deleteProjectMember');

		$response = $this->controller->remove(11);
		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertEquals(403, $response->getStatus());
	}
}

