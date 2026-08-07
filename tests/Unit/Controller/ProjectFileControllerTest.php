<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Controller;

use OCA\ProjectCheck\Controller\ProjectFileController;
use OCA\ProjectCheck\Service\ProjectFileService;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\RedirectResponse;
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
 * not leak internal exception messages, successful list/delete paths
 * shape the JSON envelope as documented, and upload never honours a
 * client-supplied redirect (open-redirect / phishing).
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
	/** @var IURLGenerator|\PHPUnit\Framework\MockObject\MockObject */
	private $urlGenerator;
	/** @var IL10N|\PHPUnit\Framework\MockObject\MockObject */
	private $l10n;
	private ProjectFileController $controller;

	/** @var list<array{0: string, 1: array}> */
	private array $linkToRouteCalls = [];

	protected function setUp(): void {
		parent::setUp();
		$this->request = $this->createMock(IRequest::class);
		$this->fileService = $this->createMock(ProjectFileService::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->linkToRouteCalls = [];
		$this->urlGenerator->method('linkToRoute')->willReturnCallback(
			function (string $route, array $params = []) {
				$this->linkToRouteCalls[] = [$route, $params];
				$query = $params === [] ? '' : ('?' . http_build_query($params));
				return '/apps/projectcheck/projects/' . ($params['id'] ?? '0') . $query;
			}
		);
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
			$this->urlGenerator,
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

	public function testDeletePostSucceedsForAuthorisedUser(): void {
		$this->userSession->method('getUser')->willReturn($this->user);
		$this->fileService->expects($this->once())->method('deleteFile')->with(7, 99, 'alice');
		$response = $this->controller->deletePost(7, 99);
		$this->assertSame(200, $response->getStatus());
		$this->assertTrue($response->getData()['success']);
	}

	public function testUploadReturns401WithoutUser(): void {
		$this->userSession->method('getUser')->willReturn(null);
		$response = $this->controller->upload(7);
		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertSame(401, $response->getStatus());
		$this->fileService->expects($this->never())->method('addFilesFromUpload');
	}

	public function testUploadAjaxSuccessReturnsJsonEnvelope(): void {
		$this->userSession->method('getUser')->willReturn($this->user);
		$this->request->method('getHeader')->with('X-Requested-With')->willReturn('XMLHttpRequest');
		$this->request->method('getUploadedFile')->with('project_files')->willReturn([
			'name' => 'a.pdf',
			'tmp_name' => '/tmp/x',
			'error' => UPLOAD_ERR_OK,
			'size' => 10,
			'type' => 'application/pdf',
		]);
		$this->fileService->expects($this->once())->method('addFilesFromUpload')->with(7, $this->anything(), 'alice');

		$response = $this->controller->upload(7);
		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertSame(200, $response->getStatus());
		$this->assertTrue($response->getData()['success']);
		$this->assertSame([], $this->linkToRouteCalls, 'AJAX success must not redirect');
	}

	public function testUploadAjaxFailureDoesNotLeakInternalMessage(): void {
		$this->userSession->method('getUser')->willReturn($this->user);
		$this->request->method('getHeader')->with('X-Requested-With')->willReturn('XMLHttpRequest');
		$this->request->method('getUploadedFile')->willReturn([]);
		$this->fileService->method('addFilesFromUpload')->willThrowException(new \RuntimeException('disk-full-secret'));

		$response = $this->controller->upload(7);
		$this->assertSame(400, $response->getStatus());
		$body = $response->getData();
		$this->assertArrayHasKey('error', $body);
		$this->assertStringNotContainsString('disk-full-secret', (string)$body['error']);
	}

	public function testUploadNonAjaxSuccessIgnoresClientRedirectParam(): void {
		$this->userSession->method('getUser')->willReturn($this->user);
		$this->request->method('getHeader')->with('X-Requested-With')->willReturn('');
		$this->request->method('getUploadedFile')->willReturn([]);
		$this->request->expects($this->never())->method('getParam');
		$this->fileService->method('addFilesFromUpload')->willReturn([]);

		$response = $this->controller->upload(42);
		$this->assertInstanceOf(RedirectResponse::class, $response);
		$target = $response->getRedirectURL();
		$this->assertStringNotContainsString('evil.example', $target);
		$this->assertStringContainsString('/apps/projectcheck/projects/42', $target);
		$this->assertCount(1, $this->linkToRouteCalls);
		$this->assertSame('projectcheck.project.show', $this->linkToRouteCalls[0][0]);
		$this->assertSame(42, $this->linkToRouteCalls[0][1]['id']);
		$this->assertSame('success', $this->linkToRouteCalls[0][1]['message']);
	}

	public function testUploadNonAjaxFailureIgnoresClientRedirectParam(): void {
		$this->userSession->method('getUser')->willReturn($this->user);
		$this->request->method('getHeader')->with('X-Requested-With')->willReturn('');
		$this->request->method('getUploadedFile')->willReturn([]);
		$this->request->expects($this->never())->method('getParam');
		$this->fileService->method('addFilesFromUpload')->willThrowException(new \RuntimeException('boom'));

		$response = $this->controller->upload(42);
		$this->assertInstanceOf(RedirectResponse::class, $response);
		$target = $response->getRedirectURL();
		$this->assertStringNotContainsString('https://', $target);
		$this->assertSame('projectcheck.project.show', $this->linkToRouteCalls[0][0]);
		$this->assertSame('error', $this->linkToRouteCalls[0][1]['message']);
		$this->assertArrayHasKey('error_text', $this->linkToRouteCalls[0][1]);
	}

	public function testControllerSourceNeverReadsRedirectRequestParam(): void {
		$source = (string) file_get_contents(dirname(__DIR__, 3) . '/lib/Controller/ProjectFileController.php');
		$this->assertStringNotContainsString("getParam(\n\t\t\t\t'redirect'", $source);
		$this->assertStringNotContainsString("getParam('redirect'", $source);
		$this->assertStringNotContainsString('getParam("redirect"', $source);
	}
}
