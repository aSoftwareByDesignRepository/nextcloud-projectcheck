<?php
/** Synced from _shared/agent-auth — do not edit app-side. */

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\BackgroundJob;

use OCA\ProjectCheck\Agent\AgentWebhook;
use OCA\ProjectCheck\Agent\AgentWebhookMapper;
use OCA\ProjectCheck\BackgroundJob\AgentWebhookDeliverJob;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Signed delivery: HMAC signature headers, no redirect following, retry
 * with bounded attempts, auto-disable on persistent failure, and a hard
 * never-throw contract (a dead receiver is not a cron fatal).
 */
class AgentWebhookDeliverJobTest extends TestCase
{
	private AgentWebhookMapper&MockObject $webhooks;
	private IClientService&MockObject $clientService;
	private IClient&MockObject $client;
	private IJobList&MockObject $jobList;
	private LoggerInterface&MockObject $logger;
	private ILockingProvider&MockObject $locking;
	private AgentWebhookDeliverJob $job;

	protected function setUp(): void
	{
		$time = $this->createMock(ITimeFactory::class);
		$time->method('getTime')->willReturn(1700000000);
		$this->webhooks = $this->getMockBuilder(AgentWebhookMapper::class)
			->disableOriginalConstructor()->getMock();
		$this->clientService = $this->createMock(IClientService::class);
		$this->client = $this->createMock(IClient::class);
		$this->clientService->method('newClient')->willReturn($this->client);
		$this->jobList = $this->createMock(IJobList::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->locking = $this->createMock(ILockingProvider::class);
		$this->job = new AgentWebhookDeliverJob(
			$time, $this->webhooks, $this->clientService, $this->jobList, $this->logger,
			$this->locking,
		);
	}

	private function runJob(array $args): void
	{
		$run = new \ReflectionMethod(AgentWebhookDeliverJob::class, 'run');
		$run->invoke($this->job, $args);
	}

	private function sub(?int $disabledAt = null, int $failCount = 0): AgentWebhook
	{
		$w = new AgentWebhook();
		$w->setId(9);
		$w->setUserId('alice');
		$w->setUrl('https://receiver.test/hook');
		$w->setSecret('whsec_unitsecret');
		$w->setEvents('agent.write.completed');
		$w->setDisabledAt($disabledAt);
		$w->setFailCount($failCount);
		return $w;
	}

	private function args(int $attempt = 0): array
	{
		return [
			'webhookId' => 9,
			'payload' => json_encode(['event' => 'agent.write.completed', 'data' => ['tool' => 'x']]),
			'attempt' => $attempt,
		];
	}

	private function response(int $status): IResponse
	{
		$r = $this->createMock(IResponse::class);
		$r->method('getStatusCode')->willReturn($status);
		return $r;
	}

	public function testSkipsInvalidArguments(): void
	{
		$this->client->expects($this->never())->method('post');
		$this->runJob(['webhookId' => 0, 'payload' => '{}']);
		$this->runJob(['webhookId' => 9, 'payload' => '']);
		$this->runJob([]);
	}

	public function testSkipsUnknownOrInactiveWebhook(): void
	{
		$this->webhooks->method('findById')->willReturnOnConsecutiveCalls(
			null, $this->sub(disabledAt: 1700000000),
		);
		$this->client->expects($this->never())->method('post');
		$this->webhooks->expects($this->never())->method('recordDelivery');
		$this->runJob($this->args());
		$this->runJob($this->args());
	}

	public function testSuccessfulDeliveryRecordsOkAndSendsSignedPost(): void
	{
		$webhook = $this->sub();
		$this->webhooks->method('findById')->willReturn($webhook);
		$payload = $this->args()['payload'];

		$this->client->expects($this->once())->method('post')
			->willReturnCallback(function (string $url, array $options) use ($payload, $webhook) {
				$this->assertSame('https://receiver.test/hook', $url);
				// Redirects must never be followed — a 30x is a failure,
				// not a silent retarget.
				$this->assertFalse($options['allow_redirects']);
				$this->assertSame($payload, $options['body']);
				$sig = null;
				foreach ($options['headers'] as $name => $value) {
					if (str_ends_with(strtolower($name), 'webhook-signature')) {
						$sig = $value;
					}
				}
				$this->assertNotNull($sig, 'signature header missing');
				$this->assertMatchesRegularExpression('/^t=\d+,v1=[0-9a-f]{64}$/', $sig);
				[, $v1] = explode(',v1=', $sig);
				$t = (int)substr($sig, 2, strpos($sig, ',') - 2);
				$expected = hash_hmac('sha256', $t . '.' . $payload, 'whsec_unitsecret');
				$this->assertSame($expected, $v1, 'HMAC is not secret-bound to the payload');
				return $this->response(200);
			});

		$this->webhooks->expects($this->once())->method('recordDelivery')
			->with(9, $this->anything(), true, false);
		$this->jobList->expects($this->never())->method('add');
		$this->runJob($this->args());
	}

	public function testFailedDeliveryRequeuesWithIncrementedAttempt(): void
	{
		$this->webhooks->method('findById')->willReturn($this->sub());
		$this->client->method('post')->willReturn($this->response(500));
		$this->webhooks->expects($this->once())->method('recordDelivery')
			->with(9, $this->anything(), false, false);
		$this->jobList->expects($this->once())->method('add')
			->willReturnCallback(function (string $class, array $args) {
				$this->assertSame(AgentWebhookDeliverJob::class, $class);
				$this->assertSame(1, $args['attempt']);
				$this->assertSame(9, $args['webhookId']);
			});
		$this->runJob($this->args(attempt: 0));
	}

	public function testLastAttemptDisablesInsteadOfRequeueing(): void
	{
		$this->webhooks->method('findById')->willReturn($this->sub());
		$this->client->method('post')->willReturn($this->response(503));
		$this->webhooks->expects($this->once())->method('recordDelivery')
			->with(9, $this->anything(), false, true);
		$this->jobList->expects($this->never())->method('add');
		$this->runJob($this->args(attempt: 4));
	}

	public function testReachingMaxFailuresDisablesEarly(): void
	{
		$this->webhooks->method('findById')->willReturn($this->sub(failCount: 4));
		$this->client->method('post')->willReturn($this->response(500));
		$this->webhooks->expects($this->once())->method('recordDelivery')
			->with(9, $this->anything(), false, true);
		$this->jobList->expects($this->never())->method('add');
		$this->runJob($this->args(attempt: 0));
	}

	public function testTransportExceptionIsAFailureNotAFatal(): void
	{
		$this->webhooks->method('findById')->willReturn($this->sub());
		$this->client->method('post')->willThrowException(new \RuntimeException('dns down'));
		$this->webhooks->expects($this->once())->method('recordDelivery')
			->with(9, $this->anything(), false, false);
		$this->jobList->expects($this->once())->method('add');
		// Must not throw.
		$this->runJob($this->args());
	}

	public function testLockContentionRequeuesUnchangedDelivery(): void
	{
		// A mid-restore lock must not consume an attempt, record a
		// failure, or drop the event — the same argument is re-queued.
		$this->locking->method('acquireLock')
			->willThrowException(new LockedException('restore in progress'));
		$this->client->expects($this->never())->method('post');
		$this->webhooks->expects($this->never())->method('recordDelivery');
		$args = $this->args();
		$this->jobList->expects($this->once())->method('add')
			->with(AgentWebhookDeliverJob::class, $args);
		$this->runJob($args);
	}

	public function testRedirectResponseCountsAsFailure(): void
	{
		$this->webhooks->method('findById')->willReturn($this->sub());
		$this->client->method('post')->willReturn($this->response(302));
		$this->webhooks->expects($this->once())->method('recordDelivery')
			->with(9, $this->anything(), false, false);
		$this->jobList->expects($this->once())->method('add');
		$this->runJob($this->args());
	}
}
