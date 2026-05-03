<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Middleware;

use OCA\ProjectCheck\Exception\AppAccessDeniedException;
use OCA\ProjectCheck\Middleware\AppAccessMiddleware;
use OCA\ProjectCheck\Service\AccessControlService;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AppAccessMiddlewareTest extends TestCase {
	/** @var IRequest|\PHPUnit\Framework\MockObject\MockObject */
	private $request;
	/** @var IUserSession|\PHPUnit\Framework\MockObject\MockObject */
	private $userSession;
	/** @var AccessControlService|\PHPUnit\Framework\MockObject\MockObject */
	private $accessControl;
	/** @var IURLGenerator|\PHPUnit\Framework\MockObject\MockObject */
	private $urlGenerator;
	/** @var IFactory|\PHPUnit\Framework\MockObject\MockObject */
	private $l10nFactory;
	/** @var LoggerInterface|\PHPUnit\Framework\MockObject\MockObject */
	private $logger;
	/** @var AppAccessMiddleware */
	private $middleware;

	protected function setUp(): void {
		parent::setUp();
		$this->request = $this->createMock(IRequest::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->accessControl = $this->createMock(AccessControlService::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->l10nFactory = $this->createMock(IFactory::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->middleware = new AppAccessMiddleware(
			$this->userSession,
			$this->accessControl,
			$this->request,
			$this->urlGenerator,
			$this->l10nFactory,
			$this->logger
		);
	}

	public function testBeforeControllerThrowsWhenUserCannotUseApp(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('member-user');
		$this->userSession->method('getUser')->willReturn($user);
		$this->accessControl->method('canUseApp')->with('member-user')->willReturn(false);
		$this->request->method('getPathInfo')->willReturn('/apps/projectcheck/projects');

		$this->expectException(AppAccessDeniedException::class);
		// Any controller from OCA\ProjectCheck\Controller\* triggers the middleware.
		// PageController is the smallest, with no service dependencies.
		$controller = new \OCA\ProjectCheck\Controller\PageController(
			'projectcheck',
			$this->createMock(\OCP\IRequest::class),
			$this->createMock(\OCP\IURLGenerator::class)
		);
		$this->middleware->beforeController($controller, 'index');
	}

	public function testAfterExceptionReturnsJsonForApiRequests(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('member-user');
		$this->userSession->method('getUser')->willReturn($user);
		$this->request->method('getPathInfo')->willReturn('/apps/projectcheck/api/projects');
		$this->request->method('getHeader')->willReturn('');
		$this->request->method('getMethod')->willReturn('GET');

		$response = $this->middleware->afterException(
			new \stdClass(),
			'index',
			new AppAccessDeniedException('app_access_denied')
		);

		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertSame(403, $response->getStatus());
	}

	public function testAfterExceptionReturnsTemplateForPageGetRequests(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('member-user');
		$this->userSession->method('getUser')->willReturn($user);
		$this->request->method('getPathInfo')->willReturn('/apps/projectcheck/projects');
		$this->request->method('getHeader')->willReturn('');
		$this->request->method('getMethod')->willReturn('GET');
		$this->urlGenerator->method('linkToDefaultPageUrl')->willReturn('/index.php/apps/files');
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $s): string => $s);
		$this->l10nFactory->method('get')->willReturn($l10n);

		$response = $this->middleware->afterException(
			new \stdClass(),
			'index',
			new AppAccessDeniedException('app_access_denied')
		);

		$this->assertInstanceOf(TemplateResponse::class, $response);
		$this->assertSame(403, $response->getStatus());
	}

	public function testAfterExceptionReturnsJsonWhenAcceptHeaderContainsJson(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('member-user');
		$this->userSession->method('getUser')->willReturn($user);
		$this->request->method('getPathInfo')->willReturn('/apps/projectcheck/projects');
		$this->request->method('getMethod')->willReturn('GET');
		$this->request->method('getHeader')->willReturnMap([
			['Accept', 'text/html,application/json;q=0.9'],
			['Content-Type', 'text/plain'],
			['X-Requested-With', ''],
		]);

		$response = $this->middleware->afterException(
			new \stdClass(),
			'index',
			new AppAccessDeniedException('app_access_denied')
		);

		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertSame(403, $response->getStatus());
	}
}

