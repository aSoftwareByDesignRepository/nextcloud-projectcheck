<?php
/** Synced from _shared/agent-auth — do not edit app-side. */

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Agent;

use OCA\ProjectCheck\Agent\AgentWebhook;
use OCA\ProjectCheck\Agent\AgentWebhookDispatcher;
use OCA\ProjectCheck\Agent\AgentWebhookMapper;
use OCA\ProjectCheck\BackgroundJob\AgentWebhookDeliverJob;
use OCP\BackgroundJob\IJobList;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Event fan-out: emit() must enqueue one deliver job per matching
 * subscription OWNED BY the acting user, never throw into the caller's
 * request path, and never deliver to a subscription that does not
 * subscribe to the event — or to another owner's subscription.
 */
class AgentWebhookDispatcherTest extends TestCase
{
	private AgentWebhookMapper&MockObject $webhooks;
	private IJobList&MockObject $jobList;
	private LoggerInterface&MockObject $logger;
	private AgentWebhookDispatcher $dispatcher;

	protected function setUp(): void
	{
		$this->webhooks = $this->getMockBuilder(AgentWebhookMapper::class)
			->disableOriginalConstructor()->getMock();
		$this->jobList = $this->createMock(IJobList::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->dispatcher = new AgentWebhookDispatcher(
			$this->webhooks, $this->jobList, $this->logger,
		);
	}

	private function sub(int $id, string $events, string $uid = 'alice'): AgentWebhook
	{
		$w = new AgentWebhook();
		$w->setId($id);
		$w->setUserId($uid);
		$w->setUrl('https://example.test/hook');
		$w->setSecret('whsec_x');
		$w->setEvents($events);
		return $w;
	}

	public function testEmitEnqueuesOnlyMatchingSubscriptionsOfTheOwner(): void
	{
		$this->webhooks->expects($this->once())->method('findActiveByUser')
			->with('alice')
			->willReturn([
				$this->sub(1, 'agent.write.completed'),
				$this->sub(2, 'some.other.event'),
				$this->sub(3, AgentWebhook::ALL_EVENTS),
			]);
		$seen = [];
		$this->jobList->expects($this->exactly(2))
			->method('add')
			->willReturnCallback(function (string $class, array $args) use (&$seen) {
				$this->assertSame(AgentWebhookDeliverJob::class, $class);
				$this->assertSame(0, $args['attempt']);
				$envelope = json_decode((string)$args['payload'], true);
				$this->assertSame('agent.write.completed', $envelope['event']);
				$this->assertSame('projectcheck', $envelope['app']);
				$seen[] = $args['webhookId'];
			});
		$this->dispatcher->emit(AgentWebhookDispatcher::EVENT_WRITE_COMPLETED, ['tool' => 'x'], 'alice');
		$this->assertEqualsCanonicalizing([1, 3], $seen);
	}

	public function testEmitNeverDeliversToAnotherOwnersWildcard(): void
	{
		// Fan-out is per-owner: user B's '*' subscription must receive ZERO
		// deliveries for user A's event. The mapper query is owner-scoped —
		// cross-owner rows are never even selected.
		$this->webhooks->expects($this->once())->method('findActiveByUser')
			->with('alice')
			->willReturn([$this->sub(7, AgentWebhook::ALL_EVENTS, 'alice')]);
		$seen = [];
		$this->jobList->expects($this->once())->method('add')
			->willReturnCallback(function (string $class, array $args) use (&$seen) {
				$seen[] = $args['webhookId'];
			});
		$this->dispatcher->emit(AgentWebhookDispatcher::EVENT_WRITE_COMPLETED, [
			'tool' => 'x', 'userId' => 'alice',
		], 'alice');
		$this->assertSame([7], $seen);
	}

	public function testEmitSwallowsMapperFailure(): void
	{
		$this->webhooks->method('findActiveByUser')
			->willThrowException(new \RuntimeException('table missing'));
		$this->jobList->expects($this->never())->method('add');
		$this->logger->expects($this->once())->method('warning');
		// Must not throw — a broken delivery path can never break the
		// agent request that fired the event.
		$this->dispatcher->emit('agent.write.completed', [], 'alice');
		$this->addToAssertionCount(2);
	}

	public function testEmitDropsOversizePayload(): void
	{
		$this->webhooks->expects($this->never())->method('findActiveByUser');
		$this->jobList->expects($this->never())->method('add');
		$this->logger->expects($this->once())->method('warning');
		$this->dispatcher->emit('agent.write.completed', [
			'blob' => str_repeat('x', 70000),
		], 'alice');
	}

	public function testEmitWithNoSubscribersDoesNothing(): void
	{
		$this->webhooks->method('findActiveByUser')->with('alice')->willReturn([
			$this->sub(1, 'unrelated.event'),
		]);
		$this->jobList->expects($this->never())->method('add');
		$this->dispatcher->emit('agent.write.completed', [], 'alice');
	}

	public function testDeliverToTargetsOnlyTheAddressedSubscription(): void
	{
		// Test-fire semantics: exactly one delivery on the addressed
		// webhook — no owner scan, no event-list fan-out to sibling rows.
		$sub = $this->sub(9, 'agent.write.completed', 'alice');
		$this->webhooks->expects($this->never())->method('findActiveByUser');
		$this->jobList->expects($this->once())->method('add')
			->with(AgentWebhookDeliverJob::class, $this->callback(
				static function (array $args): bool {
					$envelope = json_decode((string)$args['payload'], true);
					return $args['webhookId'] === 9
						&& $envelope['event'] === 'webhook.test';
				}
			));
		$this->dispatcher->deliverTo($sub, AgentWebhookDispatcher::EVENT_TEST, [
			'webhookId' => 9,
		]);
	}
}
