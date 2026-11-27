<?php
/** Synced from _shared/agent-auth — do not edit app-side. */

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Agent;

use OCA\ProjectCheck\Agent\AgentAudit;
use OCA\ProjectCheck\Agent\AgentContext;
use OCA\ProjectCheck\Agent\AgentException;
use OCA\ProjectCheck\Agent\AgentManifest;
use OCA\ProjectCheck\Agent\AgentSeatGate;
use OCA\ProjectCheck\Agent\AgentToken;
use OCA\ProjectCheck\Agent\AgentTokenMapper;
use OCA\ProjectCheck\Agent\AgentAuditMapper;
use OCA\ProjectCheck\Agent\AgentTokenService;
use OCA\ProjectCheck\Agent\AgentWebhook;
use OCA\ProjectCheck\Agent\AgentWebhookDispatcher;
use OCA\ProjectCheck\Agent\AgentWebhookMapper;
use OCA\ProjectCheck\BackgroundJob\AgentWebhookDeliverJob;
use OCA\ProjectCheck\Middleware\AgentAuthMiddleware;
use OCP\AppFramework\Http\JSONResponse;
use OCP\BackgroundJob\IJobList;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * The §9.3 pipeline — every stage pinned: header absence, ckst-in-Basic
 * rejection (NN-09), admin-surface denial, undeclared-path denial, token
 * resolve, seat check, scope check, confirm gate, mutation budget, audit.
 */
class AgentAuthMiddlewareTest extends TestCase
{
	private IRequest&MockObject $request;
	private AgentContext $context;
	private AgentTokenService&MockObject $tokenService;
	private AgentManifest $manifest;
	private AgentSeatGate&MockObject $seatGate;
	private AgentAuditMapper&MockObject $auditMapper;
	private LoggerInterface&MockObject $logger;
	private AgentAuthMiddleware $middleware;
	private string $manifestFile;

	private const HEADER = 'X-ProjectCheck-Agent';
	private const TOOL_READ = [
		'name' => 'pc_list', 'kind' => 'read', 'riskClass' => 0,
		'method' => 'GET', 'path' => '/mobile/v1/things',
		'scope' => 'projectcheck:things:read', 'route' => 'mobile#things',
	];
	private const TOOL_WRITE = [
		'name' => 'pc_write', 'kind' => 'write', 'riskClass' => 1,
		'method' => 'POST', 'path' => '/mobile/v1/things',
		'scope' => 'projectcheck:things:write', 'route' => 'mobile#writeThing',
		'params' => ['title' => ['type' => 'string', 'required' => true]],
	];

	protected function setUp(): void
	{
		$this->request = $this->createMock(IRequest::class);
		$this->context = new AgentContext();
		$this->manifestFile = (string)tempnam(sys_get_temp_dir(), 'agent-mw-');
		$this->seatGate = $this->createMock(AgentSeatGate::class);
		$this->auditMapper = $this->createMock(AgentAuditMapper::class);
		$this->logger = $this->createMock(LoggerInterface::class);
	}

	protected function tearDown(): void
	{
		@unlink($this->manifestFile);
	}

	private function middlewareWithManifest(array $tools, ?AgentWebhookDispatcher $dispatcher = null): void
	{
		file_put_contents($this->manifestFile, json_encode([
			'schemaVersion' => 1,
			'appId' => 'projectcheck',
			'agentExposure' => 'declared',
			'tools' => $tools,
		]));
		$this->manifest = new AgentManifest('projectcheck', $this->manifestFile);
		$this->middleware = new AgentAuthMiddleware(
			$this->request,
			$this->context,
			$this->tokenServiceFor(),
			$this->manifest,
			$this->seatGate,
			$this->auditMapper,
			$this->logger,
			'projectcheck',
			$dispatcher,
		);
	}

	private function tokenServiceFor(): AgentTokenService
	{
		if (!isset($this->tokenService)) {
			$this->tokenService = $this->getMockBuilder(AgentTokenService::class)
				->disableOriginalConstructor()
				->getMock();
		}
		return $this->tokenService;
	}

	private function agentToken(array $scopes = ['projectcheck:things:read']): AgentToken
	{
		$token = new AgentToken();
		$token->setId(5);
		$token->setUserId('agent-owner');
		$token->setScopes(implode(' ', $scopes));
		$token->setCreatedAt(time());
		$token->setExpiresAt(time() + 3600);
		return $token;
	}

	private function headers(array $map): void
	{
		$this->request->method('getHeader')->willReturnCallback(
			static fn (string $name): string => $map[$name] ?? ''
		);
	}

	public function testNoAgentHeaderPassesThrough(): void
	{
		$this->middlewareWithManifest([self::TOOL_READ]);
		$this->headers([self::HEADER => '', 'Authorization' => '']);
		$this->request->method('getPathInfo')->willReturn('/apps/projectcheck/mobile/v1/things');
		$this->middleware->beforeController(new \stdClass(), 'x');
		$this->assertFalse($this->context->isArmed());
	}

	public function testCkstInAuthorizationRejected(): void
	{
		$this->middlewareWithManifest([self::TOOL_READ]);
		$this->headers([self::HEADER => '', 'Authorization' => 'Basic ' . base64_encode('u:ckst_projectcheck_abc')]);
		$this->request->method('getPathInfo')->willReturn('/apps/projectcheck/mobile/v1/things');
		try {
			$this->middleware->beforeController(new \stdClass(), 'x');
			$this->fail('ckst_ in Basic must be rejected (NN-09)');
		} catch (AgentException $e) {
			$this->assertSame('agent_token_invalid', $e->getErrorCode());
			$this->assertSame(401, $e->getHttpStatus());
		}
	}

	public function testCkstInBearerRejected(): void
	{
		$this->middlewareWithManifest([self::TOOL_READ]);
		$this->headers([self::HEADER => '', 'Authorization' => 'Bearer ckst_projectcheck_abc']);
		$this->request->method('getPathInfo')->willReturn('/apps/projectcheck/mobile/v1/things');
		$this->expectException(AgentException::class);
		$this->middleware->beforeController(new \stdClass(), 'x');
	}

	public function testAgentHeaderOnTokenAdminDenied(): void
	{
		$this->middlewareWithManifest([self::TOOL_READ]);
		$this->headers([self::HEADER => 'ckst_projectcheck_xyz', 'Authorization' => '']);
		$this->request->method('getPathInfo')->willReturn('/apps/projectcheck/agent/v1/tokens');
		try {
			$this->middleware->beforeController(new \stdClass(), 'x');
			$this->fail('agent token must never manage tokens');
		} catch (AgentException $e) {
			$this->assertSame('agent_scope_denied', $e->getErrorCode());
			$this->assertSame(403, $e->getHttpStatus());
			$this->assertStringContainsString(
				'manage agent tokens',
				$e->getMessage(),
				'the admin-prefix guard — not the generic undeclared-path denial — must fire',
			);
		}
	}

	public function testAgentHeaderOnUndeclaredPathDenied(): void
	{
		$this->middlewareWithManifest([self::TOOL_READ]);
		$this->headers([self::HEADER => 'ckst_projectcheck_xyz', 'Authorization' => '']);
		$this->request->method('getPathInfo')->willReturn('/apps/projectcheck/settings');
		$this->request->method('getMethod')->willReturn('GET');
		try {
			$this->middleware->beforeController(new \stdClass(), 'x');
			$this->fail('agent token on a non-declared path must be denied');
		} catch (AgentException $e) {
			$this->assertSame('agent_scope_denied', $e->getErrorCode());
		}
	}

	public function testValidTokenArmsContextAndScopes(): void
	{
		$this->middlewareWithManifest([self::TOOL_READ]);
		$this->headers([self::HEADER => 'ckst_projectcheck_abc', 'Authorization' => '']);
		$this->request->method('getPathInfo')->willReturn('/apps/projectcheck/mobile/v1/things');
		$this->request->method('getMethod')->willReturn('GET');
		$this->request->method('getParams')->willReturn([]);
		$this->tokenService->method('resolve')->willReturn($this->agentToken());
		$this->seatGate->expects($this->once())->method('assertEntitled')->with('agent-owner');
		$this->tokenService->expects($this->once())->method('touch');
		$this->middleware->beforeController(new \stdClass(), 'x');
		$this->assertTrue($this->context->isArmed());
		$this->assertSame('agent-owner', $this->context->uid());
		$this->assertSame(5, $this->context->tokenId());
	}

	public function testMissingScopeDenied(): void
	{
		$this->middlewareWithManifest([self::TOOL_WRITE]);
		$this->headers([self::HEADER => 'ckst_projectcheck_abc', 'Authorization' => '']);
		$this->request->method('getPathInfo')->willReturn('/apps/projectcheck/mobile/v1/things');
		$this->request->method('getMethod')->willReturn('POST');
		$this->request->method('getParams')->willReturn(['title' => 'x']);
		$this->tokenService->method('resolve')->willReturn($this->agentToken(['projectcheck:things:read']));
		$this->auditMapper->expects($this->once())->method('record')
			->with(5, 'agent-owner', 'pc_write', 'mobile#writeThing', null, 'denied', $this->anything());
		try {
			$this->middleware->beforeController(new \stdClass(), 'x');
			$this->fail('read-scoped token must not reach write tool');
		} catch (AgentException $e) {
			$this->assertSame('agent_scope_denied', $e->getErrorCode());
		}
	}

	public function testConfirmRequiredWithoutConfirmFlag(): void
	{
		$risky = self::TOOL_WRITE + ['riskClass' => 2, 'confirm' => 'required'];
		$this->middlewareWithManifest([$risky]);
		$this->headers([self::HEADER => 'ckst_projectcheck_abc', 'Authorization' => '']);
		$this->request->method('getPathInfo')->willReturn('/apps/projectcheck/mobile/v1/things');
		$this->request->method('getMethod')->willReturn('POST');
		$this->request->method('getParams')->willReturn(['title' => 'x']);
		$this->request->method('getParam')->willReturnCallback(static fn (string $n) => $n === 'title' ? 'x' : null);
		$this->tokenService->method('resolve')->willReturn($this->agentToken(['projectcheck:things:write']));
		try {
			$this->middleware->beforeController(new \stdClass(), 'x');
			$this->fail('risk≥2 tool without confirm must be denied');
		} catch (AgentException $e) {
			$this->assertSame('confirmation_required', $e->getErrorCode());
			$this->assertSame(422, $e->getHttpStatus());
		}
	}

	public function testConfirmStringFalseNeverCounts(): void
	{
		$risky = self::TOOL_WRITE + ['riskClass' => 2, 'confirm' => 'required'];
		$this->middlewareWithManifest([$risky]);
		$this->headers([self::HEADER => 'ckst_projectcheck_abc', 'Authorization' => '']);
		$this->request->method('getPathInfo')->willReturn('/apps/projectcheck/mobile/v1/things');
		$this->request->method('getMethod')->willReturn('POST');
		$this->request->method('getParams')->willReturn(['title' => 'x', 'confirm' => 'false']);
		$this->request->method('getParam')->willReturnCallback(
			static fn (string $n) => ['title' => 'x', 'confirm' => 'false'][$n] ?? null
		);
		$this->tokenService->method('resolve')->willReturn($this->agentToken(['projectcheck:things:write']));
		try {
			$this->middleware->beforeController(new \stdClass(), 'x');
			$this->fail('"false" string must never count as confirmation');
		} catch (AgentException $e) {
			$this->assertSame('confirmation_required', $e->getErrorCode());
		}
	}

	public function testDryRunOnUndeclaredToolIsHardDenied(): void
	{
		// dry_run on a tool that does not declare "dryRun": true must fail
		// closed — an agent must never believe it previewed while a commit ran.
		$this->middlewareWithManifest([self::TOOL_WRITE]);
		$this->headers([self::HEADER => 'ckst_projectcheck_abc', 'Authorization' => '']);
		$this->request->method('getPathInfo')->willReturn('/apps/projectcheck/mobile/v1/things');
		$this->request->method('getMethod')->willReturn('POST');
		$this->request->method('getParams')->willReturn(['title' => 'x', 'dry_run' => true]);
		$this->request->method('getParam')->willReturnCallback(
			static fn (string $n) => ['title' => 'x', 'dry_run' => true][$n] ?? null
		);
		$this->tokenService->method('resolve')->willReturn($this->agentToken(['projectcheck:things:write']));
		try {
			$this->middleware->beforeController(new \stdClass(), 'x');
			$this->fail('dry_run on an unsupported tool must be denied');
		} catch (AgentException $e) {
			$this->assertSame('agent_dryrun_unsupported', $e->getErrorCode());
			$this->assertSame(422, $e->getHttpStatus());
		}
	}

	public function testDryRunSkipsConfirmGateOnSupportedTool(): void
	{
		// A supported dry-run commits nothing → confirm is not required.
		$tool = self::TOOL_WRITE + ['riskClass' => 3, 'confirm' => 'required', 'dryRun' => true];
		$this->middlewareWithManifest([$tool]);
		$this->headers([self::HEADER => 'ckst_projectcheck_abc', 'Authorization' => '']);
		$this->request->method('getPathInfo')->willReturn('/apps/projectcheck/mobile/v1/things');
		$this->request->method('getMethod')->willReturn('POST');
		$this->request->method('getParams')->willReturn(['title' => 'x', 'dry_run' => true]);
		$this->request->method('getParam')->willReturnCallback(
			static fn (string $n) => ['title' => 'x', 'dry_run' => true][$n] ?? null
		);
		$this->tokenService->method('resolve')->willReturn($this->agentToken(['projectcheck:things:write']));
		$this->tokenService->method('consumeMutationBudget')->willReturn(null);
		$this->middleware->beforeController(new \stdClass(), 'x'); // no exception
		$this->assertTrue(
			$this->context->isArmed(),
			'dry-run on a supported tool reaches dispatch without confirm',
		);
	}

	public function testDryRunAuditedAsDryrunNotOk(): void
	{
		// Audit integrity: a dry-run must not look like a commit.
		$tool = self::TOOL_WRITE + ['riskClass' => 3, 'confirm' => 'required', 'dryRun' => true];
		$this->middlewareWithManifest([$tool]);
		$this->headers([self::HEADER => 'ckst_projectcheck_abc', 'Authorization' => '']);
		$this->request->method('getPathInfo')->willReturn('/apps/projectcheck/mobile/v1/things');
		$this->request->method('getMethod')->willReturn('POST');
		$this->request->method('getParams')->willReturn(['title' => 'x', 'dry_run' => true]);
		$this->request->method('getParam')->willReturnCallback(
			static fn (string $n) => ['title' => 'x', 'dry_run' => true][$n] ?? null
		);
		$this->tokenService->method('resolve')->willReturn($this->agentToken(['projectcheck:things:write']));
		$this->auditMapper->expects($this->once())->method('record')
			->with(5, 'agent-owner', 'pc_write', 'mobile#writeThing', null, AgentAudit::RESULT_DRYRUN, $this->anything());
		$this->middleware->beforeController(new \stdClass(), 'x');
		$this->middleware->afterController(new \stdClass(), 'x', new JSONResponse([], 200));
	}

	public function testUnknownParamRejected(): void
	{
		$this->middlewareWithManifest([self::TOOL_WRITE]);
		$this->headers([self::HEADER => 'ckst_projectcheck_abc', 'Authorization' => '']);
		$this->request->method('getPathInfo')->willReturn('/apps/projectcheck/mobile/v1/things');
		$this->request->method('getMethod')->willReturn('POST');
		$this->request->method('getParams')->willReturn(['title' => 'x', 'bogus_field' => 'y']);
		$this->tokenService->method('resolve')->willReturn($this->agentToken(['projectcheck:things:write']));
		try {
			$this->middleware->beforeController(new \stdClass(), 'x');
			$this->fail('undeclared param must be rejected with accepted list');
		} catch (AgentException $e) {
			$this->assertSame('unknown_param', $e->getErrorCode());
			$this->assertSame(400, $e->getHttpStatus());
			$this->assertContains('title', $e->getDetails()['acceptedParams']);
			$this->assertContains('bogus_field', $e->getDetails()['unknownParams']);
		}
	}

	public function testRateLimitBreached(): void
	{
		$this->middlewareWithManifest([self::TOOL_WRITE]);
		$this->headers([self::HEADER => 'ckst_projectcheck_abc', 'Authorization' => '']);
		$this->request->method('getPathInfo')->willReturn('/apps/projectcheck/mobile/v1/things');
		$this->request->method('getMethod')->willReturn('POST');
		$this->request->method('getParams')->willReturn(['title' => 'x']);
		$this->request->method('getParam')->willReturnCallback(static fn (string $n) => $n === 'title' ? 'x' : null);
		$this->tokenService->method('resolve')->willReturn($this->agentToken(['projectcheck:things:write']));
		$this->tokenService->method('consumeMutationBudget')->willReturn(42);
		try {
			$this->middleware->beforeController(new \stdClass(), 'x');
			$this->fail('rate breach must deny');
		} catch (AgentException $e) {
			$this->assertSame('rate_limited', $e->getErrorCode());
			$this->assertSame(429, $e->getHttpStatus());
			$this->assertSame(42, $e->getDetails()['retryAfter']);
		}
	}

	public function testAfterExceptionMapsEnvelopeAndRetryAfter(): void
	{
		$this->middlewareWithManifest([self::TOOL_READ]);
		$e = new AgentException('rate_limited', 'slow down', 429, ['retryAfter' => 7]);
		$resp = $this->middleware->afterException(new \stdClass(), 'x', $e);
		$this->assertInstanceOf(JSONResponse::class, $resp);
		$this->assertSame(429, $resp->getStatus());
		$data = $resp->getData();
		$this->assertSame('rate_limited', $data['error']['code']);
		$this->assertSame('7', $resp->getHeaders()['Retry-After'] ?? null);
	}

	public function testManifestEndpointAllowsAnyValidToken(): void
	{
		$this->middlewareWithManifest([self::TOOL_READ]);
		$this->headers([self::HEADER => 'ckst_projectcheck_abc', 'Authorization' => '']);
		$this->request->method('getPathInfo')->willReturn('/apps/projectcheck/agent/v1/manifest');
		$this->request->method('getMethod')->willReturn('GET');
		$this->tokenService->method('resolve')->willReturn($this->agentToken());
		$this->middleware->beforeController(new \stdClass(), 'x');
		$this->assertTrue($this->context->isArmed());
	}

	public function testMutatingCallAuditsOkOnSuccess(): void
	{
		$this->middlewareWithManifest([self::TOOL_WRITE]);
		$this->headers([self::HEADER => 'ckst_projectcheck_abc', 'Authorization' => '']);
		$this->request->method('getPathInfo')->willReturn('/apps/projectcheck/mobile/v1/things');
		$this->request->method('getMethod')->willReturn('POST');
		$this->request->method('getParams')->willReturn(['title' => 'x']);
		$this->request->method('getParam')->willReturnCallback(static fn (string $n) => $n === 'title' ? 'x' : null);
		$this->tokenService->method('resolve')->willReturn($this->agentToken(['projectcheck:things:write']));
		$this->tokenService->method('consumeMutationBudget')->willReturn(null);
		$this->auditMapper->expects($this->once())->method('record')
			->with(5, 'agent-owner', 'pc_write', 'mobile#writeThing', null, 'ok', $this->anything());
		$this->middleware->beforeController(new \stdClass(), 'x');
		$this->middleware->afterController(new \stdClass(), 'x', new JSONResponse([], 200));
	}

	/**
	 * An audit-INSERT failure (dropped/locked table) must never mask the
	 * auth decision or a committed response — the denial still surfaces.
	 */
	public function testAuditWriteFailureDoesNotMaskDenial(): void
	{
		$this->middlewareWithManifest([self::TOOL_WRITE]);
		$this->headers([self::HEADER => 'ckst_projectcheck_abc', 'Authorization' => '']);
		$this->request->method('getPathInfo')->willReturn('/apps/projectcheck/mobile/v1/things');
		$this->request->method('getMethod')->willReturn('POST');
		$this->request->method('getParams')->willReturn(['title' => 'x']);
		$this->tokenService->method('resolve')->willReturn($this->agentToken(['projectcheck:things:read']));
		$this->auditMapper->method('record')->willThrowException(new \RuntimeException('no such table'));
		$this->logger->expects($this->once())->method('error');
		try {
			$this->middleware->beforeController(new \stdClass(), 'x');
			$this->fail('denial must survive an audit write failure');
		} catch (AgentException $e) {
			$this->assertSame('agent_scope_denied', $e->getErrorCode());
		}
	}

	/**
	 * Same for the post-dispatch path: a committed mutation must report
	 * its real status even when the audit row cannot be written.
	 */
	public function testAuditWriteFailureDoesNotCorruptSuccess(): void
	{
		$this->middlewareWithManifest([self::TOOL_WRITE]);
		$this->headers([self::HEADER => 'ckst_projectcheck_abc', 'Authorization' => '']);
		$this->request->method('getPathInfo')->willReturn('/apps/projectcheck/mobile/v1/things');
		$this->request->method('getMethod')->willReturn('POST');
		$this->request->method('getParams')->willReturn(['title' => 'x']);
		$this->request->method('getParam')->willReturnCallback(static fn (string $n) => $n === 'title' ? 'x' : null);
		$this->tokenService->method('resolve')->willReturn($this->agentToken(['projectcheck:things:write']));
		$this->tokenService->method('consumeMutationBudget')->willReturn(null);
		$this->auditMapper->method('record')->willThrowException(new \RuntimeException('no such table'));
		$this->logger->expects($this->once())->method('error');
		$this->middleware->beforeController(new \stdClass(), 'x');
		$resp = $this->middleware->afterController(new \stdClass(), 'x', new JSONResponse([], 200));
		$this->assertSame(200, $resp->getStatus());
	}

	/**
	 * NH-02: a committed agent mutation fans out to webhook subscribers.
	 * Dry-runs, failures, and reads must stay silent.
	 */
	private function armedWriteRequest(bool $dryRun = false): void
	{
		$this->headers([self::HEADER => 'ckst_projectcheck_abc', 'Authorization' => '']);
		$this->request->method('getPathInfo')->willReturn('/apps/projectcheck/mobile/v1/things');
		$this->request->method('getMethod')->willReturn('POST');
		$params = ['title' => 'x'] + ($dryRun ? ['dry_run' => true] : []);
		$this->request->method('getParams')->willReturn($params);
		$this->request->method('getParam')->willReturnCallback(
			static fn (string $n) => $params[$n] ?? null
		);
		$this->tokenService->method('resolve')->willReturn($this->agentToken(['projectcheck:things:write']));
		$this->tokenService->method('consumeMutationBudget')->willReturn(null);
	}

	private function webhookSub(): AgentWebhook
	{
		$w = new AgentWebhook();
		$w->setId(7);
		$w->setUserId('agent-owner');
		$w->setUrl('https://receiver.test/hook');
		$w->setSecret('whsec_x');
		$w->setEvents('*');
		return $w;
	}

	public function testCommittedWriteEmitsWebhookEvent(): void
	{
		$webhooks = $this->getMockBuilder(AgentWebhookMapper::class)
			->disableOriginalConstructor()->getMock();
		$webhooks->method('findActiveByUser')->with('agent-owner')
			->willReturn([$this->webhookSub()]);
		$jobList = $this->createMock(IJobList::class);
		$dispatcher = new AgentWebhookDispatcher($webhooks, $jobList, $this->logger);
		$this->middlewareWithManifest([self::TOOL_WRITE], $dispatcher);
		$this->armedWriteRequest();
		$this->auditMapper->method('record');

		$jobList->expects($this->once())->method('add')
			->willReturnCallback(function (string $class, array $args) {
				$this->assertSame(AgentWebhookDeliverJob::class, $class);
				$this->assertSame(7, $args['webhookId']);
				$envelope = json_decode((string)$args['payload'], true);
				$this->assertSame('agent.write.completed', $envelope['event']);
				$this->assertSame('pc_write', $envelope['data']['tool']);
				$this->assertSame('agent-owner', $envelope['data']['userId']);
			});

		$this->middleware->beforeController(new \stdClass(), 'x');
		$this->middleware->afterController(new \stdClass(), 'x', new JSONResponse([], 200));
	}

	public function testDryRunWriteDoesNotEmitWebhookEvent(): void
	{
		$webhooks = $this->getMockBuilder(AgentWebhookMapper::class)
			->disableOriginalConstructor()->getMock();
		$jobList = $this->createMock(IJobList::class);
		$dispatcher = new AgentWebhookDispatcher($webhooks, $jobList, $this->logger);
		$tool = self::TOOL_WRITE + ['riskClass' => 3, 'confirm' => 'required', 'dryRun' => true];
		$this->middlewareWithManifest([$tool], $dispatcher);
		$this->armedWriteRequest(dryRun: true);
		$this->auditMapper->method('record');

		$jobList->expects($this->never())->method('add');
		$webhooks->expects($this->never())->method('findActiveByUser');

		$this->middleware->beforeController(new \stdClass(), 'x');
		$this->middleware->afterController(new \stdClass(), 'x', new JSONResponse([], 200));
	}

	public function testFailedWriteDoesNotEmitWebhookEvent(): void
	{
		$webhooks = $this->getMockBuilder(AgentWebhookMapper::class)
			->disableOriginalConstructor()->getMock();
		$jobList = $this->createMock(IJobList::class);
		$dispatcher = new AgentWebhookDispatcher($webhooks, $jobList, $this->logger);
		$this->middlewareWithManifest([self::TOOL_WRITE], $dispatcher);
		$this->armedWriteRequest();
		$this->auditMapper->method('record');

		$jobList->expects($this->never())->method('add');

		$this->middleware->beforeController(new \stdClass(), 'x');
		$this->middleware->afterController(new \stdClass(), 'x', new JSONResponse([], 500));
	}

	public function testWebhookEmitFailureNeverBreaksResponse(): void
	{
		$webhooks = $this->getMockBuilder(AgentWebhookMapper::class)
			->disableOriginalConstructor()->getMock();
		$webhooks->method('findActiveByUser')
			->willThrowException(new \RuntimeException('webhooks table missing'));
		$jobList = $this->createMock(IJobList::class);
		$dispatcher = new AgentWebhookDispatcher($webhooks, $jobList, $this->logger);
		$this->middlewareWithManifest([self::TOOL_WRITE], $dispatcher);
		$this->armedWriteRequest();
		$this->auditMapper->method('record');

		$this->middleware->beforeController(new \stdClass(), 'x');
		$resp = $this->middleware->afterController(new \stdClass(), 'x', new JSONResponse([], 200));
		$this->assertSame(200, $resp->getStatus());
	}
}
