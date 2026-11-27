<?php
/** Synced from _shared/agent-auth — do not edit app-side. */

declare(strict_types=1);

namespace OCA\ProjectCheck\Agent;

use OCA\ProjectCheck\AppInfo\Application;
use OCA\ProjectCheck\BackgroundJob\AgentWebhookDeliverJob;
use OCP\BackgroundJob\IJobList;
use Psr\Log\LoggerInterface;

/**
 * Event fan-out for NH-02 webhook subscriptions.
 *
 * emit() builds a signed-delivery envelope and enqueues one
 * AgentWebhookDeliverJob per matching subscription — the caller's request
 * path is never blocked by an outbound HTTP call (an unreachable receiver
 * must never slow or fail the agent action that fired the event).
 *
 * Payload hygiene: callers pass already-sanitized context; the dispatcher
 * caps the encoded payload (64 KiB) and drops oversize events with a log
 * rather than delivering a truncated lie.
 */
final class AgentWebhookDispatcher
{
	public const EVENT_WRITE_COMPLETED = 'agent.write.completed';
	public const EVENT_TEST = 'webhook.test';
	private const MAX_PAYLOAD_BYTES = 65536;

	public function __construct(
		private readonly AgentWebhookMapper $webhooks,
		private readonly IJobList $jobList,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * Fan out an event to the OWNING user's subscriptions only.
	 *
	 * Fan-out is per-owner — subscriptions see their owner's actions,
	 * never another user's. A '*' subscription owned by uid Y must
	 * receive zero deliveries for events emitted by uid X; the mapper
	 * query is owner-scoped so cross-owner delivery is impossible by
	 * construction (not just filtered in PHP).
	 *
	 * @param array<string, mixed> $context caller-sanitized event data
	 */
	public function emit(string $event, array $context, string $ownerUid): void
	{
		$payload = $this->envelope($event, $context);
		if ($payload === null) {
			return;
		}
		try {
			$subs = $this->webhooks->findActiveByUser($ownerUid);
		} catch (\Throwable $e) {
			// A missing table (mid-upgrade) must never break the emitting
			// request path — delivery is best-effort by design.
			$this->logger->warning('agent webhook fan-out skipped: subscriptions unreadable', [
				'exception' => $e->getMessage(),
			]);
			return;
		}
		foreach ($subs as $sub) {
			if (!$sub->subscribesTo($event)) {
				continue;
			}
			$this->enqueue($sub, $payload);
		}
	}

	/**
	 * Deliver an event to exactly one subscription (test-fire). The
	 * caller already resolved ownership — this never scans other rows,
	 * so a test lands on the addressed webhook only.
	 *
	 * @param array<string, mixed> $context caller-sanitized event data
	 */
	public function deliverTo(AgentWebhook $sub, string $event, array $context): void
	{
		$payload = $this->envelope($event, $context);
		if ($payload === null) {
			return;
		}
		$this->enqueue($sub, $payload);
	}

	/**
	 * @param array<string, mixed> $context
	 */
	private function envelope(string $event, array $context): ?string
	{
		$envelope = [
			'event' => $event,
			'app' => Application::APP_ID,
			'occurredAt' => time(),
			'data' => $context,
		];
		$payload = json_encode($envelope, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		if (!is_string($payload) || strlen($payload) > self::MAX_PAYLOAD_BYTES) {
			$this->logger->warning('agent webhook event dropped: payload unencodable or oversize', [
				'event' => $event,
			]);
			return null;
		}
		return $payload;
	}

	private function enqueue(AgentWebhook $sub, string $payload): void
	{
		$this->jobList->add(AgentWebhookDeliverJob::class, [
			'webhookId' => $sub->getId(),
			'payload' => $payload,
			'attempt' => 0,
		]);
	}
}
