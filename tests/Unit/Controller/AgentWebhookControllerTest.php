<?php
/** Synced from _shared/agent-auth — do not edit app-side. */

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Controller;

use OCA\ProjectCheck\Agent\AgentAccessGate;
use OCA\ProjectCheck\Agent\AgentContext;
use OCA\ProjectCheck\Agent\AgentException;
use OCA\ProjectCheck\Agent\AgentManifest;
use OCA\ProjectCheck\Agent\AgentWebhook;
use OCA\ProjectCheck\Agent\AgentWebhookDispatcher;
use OCA\ProjectCheck\Agent\AgentWebhookMapper;
use OCA\ProjectCheck\Agent\WebhookUrlGuard;
use OCA\ProjectCheck\BackgroundJob\AgentWebhookDeliverJob;
use OCA\ProjectCheck\Controller\AgentWebhookController;
use OCP\BackgroundJob\IJobList;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * NH-02 subscription CRUD: uniform-404 ownership, once-only secret
 * disclosure, per-user cap, event catalog validation, and the dual
 * auth path (armed agent context OR app-door session user).
 */
class AgentWebhookControllerTest extends TestCase
{
	private IRequest&MockObject $request;
	private AgentWebhookMapper&MockObject $webhooks;
	private IJobList&MockObject $jobList;
	private WebhookUrlGuard $urlGuard;
	private AgentManifest $manifest;
	private AgentAccessGate&MockObject $accessGate;
	private AgentContext $context;
	private IUserSession&MockObject $userSession;
	private IConfig&MockObject $config;
	private AgentWebhookController $controller;
	private string $manifestFile;
	/** @var array<string, mixed> request params seen by the controller */
	private array $params = [];

	protected function setUp(): void
	{
		$this->request = $this->createMock(IRequest::class);
		$this->request->method('getParams')
			->willReturnCallback(fn (): array => $this->params);
		$this->webhooks = $this->getMockBuilder(AgentWebhookMapper::class)
			->disableOriginalConstructor()->getMock();
		$this->jobList = $this->createMock(IJobList::class);
		$this->accessGate = $this->createMock(AgentAccessGate::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->config = $this->createMock(IConfig::class);
		$this->context = new AgentContext();

		$this->manifestFile = (string)tempnam(sys_get_temp_dir(), 'agent-wh-');
		file_put_contents($this->manifestFile, json_encode([
			'schemaVersion' => 1,
			'appId' => 'projectcheck',
			'agentExposure' => 'declared',
			'events' => ['custom.thing.done'],
			'tools' => [],
		]));
		$this->manifest = new AgentManifest('projectcheck', $this->manifestFile);

		$this->urlGuard = new WebhookUrlGuard($this->config);
		$dispatcher = new AgentWebhookDispatcher(
			$this->webhooks, $this->jobList, $this->createMock(LoggerInterface::class),
		);

		$this->controller = new AgentWebhookController(
			$this->request,
			$this->webhooks,
			$dispatcher,
			$this->urlGuard,
			$this->manifest,
			$this->accessGate,
			$this->context,
			$this->userSession,
		);
	}

	protected function tearDown(): void
	{
		if (is_file($this->manifestFile)) {
			unlink($this->manifestFile);
		}
	}

	private function asUser(string $uid, bool $canUse = true): void
	{
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$this->userSession->method('getUser')->willReturn($user);
		$this->accessGate->method('canUseApp')->willReturn($canUse);
	}

	private function asAgent(string $uid): void
	{
		$this->context->arm($uid, 42, ['projectcheck:webhooks:write']);
	}

	private function body(array $params): void
	{
		$this->params = $params;
	}

	private function devUrlGuard(bool $on): void
	{
		$this->config->method('getAppValue')->willReturnCallback(
			static fn (string $app, string $key, string $default = '') =>
				$key === WebhookUrlGuard::DEV_FLAG ? ($on ? '1' : '0') : $default
		);
	}

	private function sub(int $id, string $uid, ?int $disabledAt = null, ?int $revokedAt = null): AgentWebhook
	{
		$w = new AgentWebhook();
		$w->setId($id);
		$w->setUserId($uid);
		$w->setUrl('https://receiver.test/hook');
		$w->setSecret('whsec_hidden');
		$w->setEvents('agent.write.completed');
		$w->setCreatedAt(1700000000);
		$w->setLastStatus(AgentWebhook::STATUS_NEVER);
		$w->setDisabledAt($disabledAt);
		$w->setRevokedAt($revokedAt);
		return $w;
	}

	public function testIndexRequiresAuthentication(): void
	{
		$this->userSession->method('getUser')->willReturn(null);
		try {
			$this->controller->index();
			$this->fail('expected AgentException');
		} catch (AgentException $e) {
			$this->assertSame(401, $e->getHttpStatus());
		}
	}

	public function testIndexForbiddenForNonAppUser(): void
	{
		$this->asUser('outsider', canUse: false);
		try {
			$this->controller->index();
			$this->fail('expected AgentException');
		} catch (AgentException $e) {
			$this->assertSame(403, $e->getHttpStatus());
		}
	}

	public function testIndexListsOwnWebhooksAndCatalog(): void
	{
		$this->asAgent('alice');
		$this->webhooks->method('findByUser')->with('alice')
			->willReturn([$this->sub(1, 'alice')]);
		$data = $this->controller->index()->getData();
		$this->assertTrue($data['ok']);
		$this->assertCount(1, $data['webhooks']);
		$this->assertArrayNotHasKey('secret', $data['webhooks'][0]);
		$this->assertContains('agent.write.completed', $data['events']);
		$this->assertContains('custom.thing.done', $data['events']);
	}

	public function testCreateRejectsNonStringUrl(): void
	{
		$this->asAgent('alice');
		$this->body(['url' => ['x'], 'events' => ['agent.write.completed']]);
		try {
			$this->controller->create();
			$this->fail('expected AgentException');
		} catch (AgentException $e) {
			$this->assertSame(422, $e->getHttpStatus());
			$this->assertSame('invalid_type', $e->getDetails()['fields']['url'] ?? null);
		}
	}

	public function testCreateRejectsUnsafeUrl(): void
	{
		$this->asAgent('alice');
		$this->devUrlGuard(false);
		$this->body(['url' => 'http://169.254.169.254/latest/meta-data', 'events' => ['*']]);
		try {
			$this->controller->create();
			$this->fail('expected AgentException');
		} catch (AgentException $e) {
			$this->assertSame(422, $e->getHttpStatus());
			$this->assertSame('https_required', $e->getDetails()['fields']['url'] ?? null);
		}
	}

	public function testCreateRejectsUnknownAndEmptyEvents(): void
	{
		$this->asAgent('alice');
		$this->devUrlGuard(true);
		$this->body(['url' => 'http://127.0.0.1/hook', 'events' => ['does.not.exist']]);
		try {
			$this->controller->create();
			$this->fail('expected AgentException');
		} catch (AgentException $e) {
			$this->assertSame('unknown_event', $e->getDetails()['fields']['events'] ?? null);
		}

		$this->body(['url' => 'http://127.0.0.1/hook', 'events' => 'notalist']);
		try {
			$this->controller->create();
			$this->fail('expected AgentException');
		} catch (AgentException $e) {
			$this->assertSame('invalid_type', $e->getDetails()['fields']['events'] ?? null);
		}

		$this->body(['url' => 'http://127.0.0.1/hook', 'events' => ['', '  ']]);
		try {
			$this->controller->create();
			$this->fail('expected AgentException');
		} catch (AgentException $e) {
			$this->assertSame('required', $e->getDetails()['fields']['events'] ?? null);
		}
	}

	public function testCreateEnforcesSubscriptionCap(): void
	{
		$this->asAgent('alice');
		$this->devUrlGuard(true);
		$this->body(['url' => 'http://127.0.0.1/hook', 'events' => ['*']]);
		$this->webhooks->method('countActiveByUser')
			->willReturn(AgentWebhookController::MAX_SUBSCRIPTIONS_PER_USER);
		$this->webhooks->expects($this->never())->method('insert');
		try {
			$this->controller->create();
			$this->fail('expected AgentException');
		} catch (AgentException $e) {
			$this->assertSame('limit_reached', $e->getDetails()['fields']['webhooks'] ?? null);
		}
	}

	public function testCreateReturnsSecretOnceAndNotInEntity(): void
	{
		$this->asAgent('alice');
		$this->devUrlGuard(true);
		$this->body(['url' => 'http://127.0.0.1/hook', 'events' => ['agent.write.completed', '*']]);
		$this->webhooks->method('countActiveByUser')->willReturn(0);
		$this->webhooks->expects($this->once())->method('insert')
			->willReturnCallback(function (AgentWebhook $w) {
				$this->assertStringStartsWith('whsec_', $w->getSecret());
				$this->assertSame('alice', $w->getUserId());
				$this->assertSame(['agent.write.completed', '*'], $w->eventList());
				$w->setId(5);
				return $w;
			});

		$resp = $this->controller->create();
		$data = $resp->getData();
		$this->assertSame(201, $resp->getStatus());
		$this->assertStringStartsWith('whsec_', (string)$data['secret']);
		$this->assertArrayNotHasKey('secret', $data['webhook']);
		$this->assertStringContainsString('Webhook-Signature', (string)$data['signatureHeader']);
	}

	public function testDeleteForeignSubscriptionIsUniform404(): void
	{
		$this->asAgent('mallory');
		$this->webhooks->method('findById')->with(9)->willReturn($this->sub(9, 'alice'));
		$this->webhooks->expects($this->never())->method('revoke');
		try {
			$this->controller->delete(9);
			$this->fail('expected AgentException');
		} catch (AgentException $e) {
			$this->assertSame(404, $e->getHttpStatus());
			$this->assertSame('not_found', $e->getErrorCode());
		}
	}

	public function testDeleteOwnSubscriptionRevokes(): void
	{
		$this->asAgent('alice');
		$this->webhooks->method('findById')->willReturn($this->sub(9, 'alice'));
		$this->webhooks->expects($this->once())->method('revoke')->with(9, $this->anything());
		$this->assertTrue($this->controller->delete(9)->getData()['ok']);
	}

	public function testTestFireRejectsDisabledSubscription(): void
	{
		$this->asAgent('alice');
		$this->webhooks->method('findById')
			->willReturn($this->sub(9, 'alice', disabledAt: 1700000000));
		$this->jobList->expects($this->never())->method('add');
		try {
			$this->controller->test(9);
			$this->fail('expected AgentException');
		} catch (AgentException $e) {
			$this->assertSame('inactive', $e->getDetails()['fields']['webhook'] ?? null);
		}
	}

	public function testTestFireQueuesDelivery(): void
	{
		$this->asAgent('alice');
		$sub = $this->sub(9, 'alice');
		$sub->setEvents('*'); // wildcard subscribes to webhook.test too
		$this->webhooks->method('findById')->willReturn($sub);
		$this->webhooks->method('findAllActive')->willReturn([$sub]);
		$this->jobList->expects($this->once())->method('add')
			->with(AgentWebhookDeliverJob::class, $this->callback(
				static fn (array $args): bool => $args['webhookId'] === 9
					&& str_contains((string)$args['payload'], 'webhook.test')
			));
		$data = $this->controller->test(9)->getData();
		$this->assertTrue($data['ok']);
		$this->assertTrue($data['queued']);
	}

	public function testAgentContextWinsOverForeignSessionCookie(): void
	{
		// Armed agent + a session cookie for a different user: the explicit
		// credential must resolve the uid (cookie-shadows-basic guard).
		$this->asUser('mallory');
		$this->asAgent('alice');
		$this->webhooks->method('findByUser')->with('alice')->willReturn([]);
		$data = $this->controller->index()->getData();
		$this->assertTrue($data['ok']);
		$this->assertSame([], $data['webhooks']);
	}
}
