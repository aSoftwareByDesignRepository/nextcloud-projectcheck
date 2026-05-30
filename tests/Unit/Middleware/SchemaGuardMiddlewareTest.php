<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Middleware;

use OCA\ProjectCheck\Controller\DashboardController;
use OCA\ProjectCheck\Exception\SchemaRepairFailedException;
use OCA\ProjectCheck\Middleware\SchemaGuardMiddleware;
use OCA\ProjectCheck\Service\CSPService;
use OCA\ProjectCheck\Service\SchemaGuardService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SchemaGuardMiddlewareTest extends TestCase
{
	private function makeMiddleware(
		?SchemaGuardService $guard = null,
		?IRequest $request = null,
	): SchemaGuardMiddleware {
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$factory = $this->createMock(IFactory::class);
		$factory->method('get')->willReturn($l10n);

		$csp = $this->createMock(CSPService::class);
		$csp->method('applyPolicyWithNonce')->willReturnArgument(0);

		return new SchemaGuardMiddleware(
			$guard ?? $this->createMock(SchemaGuardService::class),
			$request ?? $this->createMock(IRequest::class),
			$factory,
			$this->createMock(IURLGenerator::class),
			$csp,
			$this->createMock(LoggerInterface::class),
		);
	}

	public function testBeforeControllerRunsGuardForProjectCheckControllers(): void
	{
		$guard = $this->createMock(SchemaGuardService::class);
		$guard->expects(self::once())->method('ensureReady');

		$middleware = $this->makeMiddleware($guard);
		$middleware->beforeController($this->createMock(DashboardController::class), 'index');
	}

	public function testBeforeControllerIgnoresForeignControllers(): void
	{
		$guard = $this->createMock(SchemaGuardService::class);
		$guard->expects(self::never())->method('ensureReady');

		$middleware = $this->makeMiddleware($guard);
		$middleware->beforeController(new \stdClass(), 'index');
	}

	public function testAfterExceptionReturnsJsonForApiPathsWithoutInternalDetails(): void
	{
		$request = $this->createMock(IRequest::class);
		$request->method('getPathInfo')->willReturn('/apps/projectcheck/api/dashboard/stats');
		$request->method('getMethod')->willReturn('GET');
		$request->method('getHeader')->willReturn('');

		$middleware = $this->makeMiddleware(null, $request);

		$response = $middleware->afterException(
			$this->createMock(DashboardController::class),
			'index',
			new SchemaRepairFailedException('internal repair detail must not leak')
		);

		self::assertInstanceOf(JSONResponse::class, $response);
		self::assertSame(Http::STATUS_SERVICE_UNAVAILABLE, $response->getStatus());
		$data = $response->getData();
		self::assertSame('schema_repair_failed', $data['error']);
		self::assertStringNotContainsString('internal repair detail', (string) $data['message']);
	}

	public function testAfterExceptionReturnsHtmlErrorPageForBrowserGet(): void
	{
		$request = $this->createMock(IRequest::class);
		$request->method('getPathInfo')->willReturn('/apps/projectcheck/dashboard');
		$request->method('getMethod')->willReturn('GET');
		$request->method('getHeader')->willReturn('');

		$middleware = $this->makeMiddleware(null, $request);

		$response = $middleware->afterException(
			$this->createMock(DashboardController::class),
			'index',
			new SchemaRepairFailedException('internal')
		);

		self::assertInstanceOf(TemplateResponse::class, $response);
		self::assertSame(Http::STATUS_SERVICE_UNAVAILABLE, $response->getStatus());
	}
}
