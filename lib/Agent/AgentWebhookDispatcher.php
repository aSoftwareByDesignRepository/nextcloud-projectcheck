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
	 * @param array<string, mixed> $context caller-sanitized event data
	 */
	public function emit(string $event, array $context): void
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
			return;
		}
		try {
			$subs = $this->webhooks->findAllActive();
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
			$this->jobList->add(AgentWebhookDeliverJob::class, [
				'webhookId' => $sub->getId(),
				'payload' => $payload,
				'attempt' => 0,
			]);
		}
	}
}
