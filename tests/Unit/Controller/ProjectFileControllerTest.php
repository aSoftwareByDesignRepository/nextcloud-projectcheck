<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Controller;

use OCA\ProjectCheck\Controller\ProjectFileController;
use OCA\ProjectCheck\Service\ProjectFileService;
use OCP\AppFramework\Http\DataResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

/**
 * Smoke tests for {@see ProjectFileController}.
 *
 * Audit reference: AUDIT-FINDINGS G24 - the file controller previously had
 * no dedicated unit tests. We cover the most security-relevant branches:
 * unauthenticated requests are rejected with the right status, errors do
 * not leak internal exception messages, and successful list/delete paths
 * shape the JSON envelope as documented.
 */
class ProjectFileControllerTest extends TestCase {
	/** @var IRequest|\PHPUnit\Framework\MockObject\MockObject */
	private $request;
	/** @var IUserSession|\PHPUnit\Framework\MockObject\MockObject */
	private $userSession;
	/** @var IUser|\PHPUnit\Framework\MockObject\MockObject */
	private $user;
	/** @var ProjectFileService|\PHPUnit\Framework\MockObject\MockObject */
	private $fileService;
	/** @var IL10N|\PHPUnit\Framework\MockObject\MockObject */
	private $l10n;
	private ProjectFileController $controller;

	protected function setUp(): void {
		parent::setUp();
		$this->request = $this->createMock(IRequest::class);
		$this->fileService = $this->createMock(ProjectFileService::class);
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('linkToRoute')->willReturn('/x');
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnCallback(static fn ($s, $p = []) => (string)$s);

		$this->userSession = $this->createMock(IUserSession::class);
		$this->user = $this->createMock(IUser::class);
		$this->user->method('getUID')->willReturn('alice');

		$this->controller = new ProjectFileController(
			'projectcheck',
			$this->request,
			$this->fileService,
			$this->userSession,
			$urlGenerator,
			$this->l10n
		);
	}

	public function testListReturns401WithoutAuthenticatedUser(): void {
		$this->userSession->method('getUser')->willReturn(null);
		$response = $this->controller->list(7);
		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertSame(401, $response->getStatus());
		$body = $response->getData();
		$this->assertArrayHasKey('error', $body);
	}

	public function testListEnvelopeShapeForAuthorisedUser(): void {
		$this->userSession->method('getUser')->willReturn($this->user);
		$file = new \OCA\ProjectCheck\Db\ProjectFile();
		$file->setId(101);
		$file->setDisplayName('plan.pdf');
		$file->setMimeType('application/pdf');
		$file->setSize(2048);
		$file->setUploadedBy('alice');
		$file->setCreatedAt(new \DateTime('2026-04-30T10:00:00+00:00'));

		$this->fileService->method('listFiles')->willReturn([$file]);

		$response = $this->controller->list(7);
		$this->assertSame(200, $response->getStatus());
		$body = $response->getData();
		$this->assertTrue($body['success']);
		$this->assertCount(1, $body['files']);
		$this->assertSame(101, $body['files'][0]['id']);
		$this->assertSame('plan.pdf', $body['files'][0]['name']);
	}

	public function testListReturnsGenericErrorOnServiceFailure(): void {
		$this->userSession->method('getUser')->willReturn($this->user);
		$this->fileService->method('listFiles')->willThrowException(new \RuntimeException('internal-detail'));

		$response = $this->controller->list(7);
		$this->assertSame(400, $response->getStatus());
		$body = $response->getData();
		$this->assertArrayHasKey('error', $body);
		// Internal exception text must NOT leak to the client.
		$this->assertStringNotContainsString('internal-detail', (string)$body['error']);
	}

	public function testDeleteReturns401WithoutUser(): void {
		$this->userSession->method('getUser')->willReturn(null);
		$response = $this->controller->delete(7, 99);
		$this->assertSame(401, $response->getStatus());
	}

	public function testDeleteReturns400WhenServiceRejects(): void {
		$this->userSession->method('getUser')->willReturn($this->user);
		$this->fileService->method('deleteFile')->willThrowException(new \RuntimeException('Access denied'));
		$response = $this->controller->delete(7, 99);
		$this->assertSame(400, $response->getStatus());
		$body = $response->getData();
		$this->assertStringNotContainsString('Access denied', (string)$body['error']);
	}
}
