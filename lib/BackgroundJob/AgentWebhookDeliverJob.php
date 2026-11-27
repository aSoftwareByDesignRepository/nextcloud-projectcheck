<?php
/** Synced from _shared/agent-auth — do not edit app-side. */

declare(strict_types=1);

namespace OCA\ProjectCheck\BackgroundJob;

use OCA\ProjectCheck\Agent\AgentWebhookMapper;
use OCA\ProjectCheck\Agent\WebhookUrlGuard;
use OCA\ProjectCheck\AppInfo\Application;
use OCA\ProjectCheck\Service\UpgradeBackupService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\QueuedJob;
use OCP\Http\Client\IClientService;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;
use Psr\Log\LoggerInterface;

/**
 * Delivers one signed webhook POST (NH-02).
 *
 * Signature (verifiable by receivers):
 *   X-<App>-Webhook-Signature: t=<unix>,v1=<hex hmac_sha256(secret, "t.body")>
 *   X-<App>-Webhook-Event: <event name>
 *
 * Failure semantics: non-2xx / timeout / transport error → fail_count+1
 * and a re-queued job (max MAX_ATTEMPTS). After MAX_FAILURES consecutive
 * failures the subscription auto-disables — a dead endpoint must not keep
 * a retry storm alive. The job NEVER throws: a failing receiver is an
 * operator concern, not a cron fatal.
 */
final class AgentWebhookDeliverJob extends QueuedJob
{
	private const MAX_ATTEMPTS = 5;
	private const MAX_FAILURES = 5;
	private const TIMEOUT_SECONDS = 10;

	public function __construct(
		ITimeFactory $time,
		private readonly AgentWebhookMapper $webhooks,
		private readonly IClientService $clientService,
		private readonly IJobList $jobList,
		private readonly LoggerInterface $logger,
		private readonly ILockingProvider $locking,
		private readonly WebhookUrlGuard $urlGuard,
	) {
		parent::__construct($time);
	}

	protected function run($argument): void
	{
		// Shared lock on the upgrade-backup key: a restore holds it
		// exclusively — webhook table reads/writes must not interleave
		// with a mid-restore truncate+reinsert. A contended delivery is
		// re-queued unchanged (NOT counted as an attempt or a failure —
		// the event must not be silently dropped during a restore).
		try {
			$this->locking->acquireLock(
				UpgradeBackupService::LOCK_KEY,
				ILockingProvider::LOCK_SHARED,
				'agent-webhook-deliver',
			);
		} catch (LockedException) {
			$this->logger->info('agent webhook delivery deferred: upgrade backup/restore in progress', [
				'webhookId' => (int)($argument['webhookId'] ?? 0),
			]);
			$this->jobList->add(self::class, $argument);
			return;
		}
		try {
			$this->deliver(is_array($argument) ? $argument : []);
		} finally {
			$this->locking->releaseLock(UpgradeBackupService::LOCK_KEY, ILockingProvider::LOCK_SHARED);
		}
	}

	private function deliver(array $argument): void
	{
		$id = (int)($argument['webhookId'] ?? 0);
		$payload = (string)($argument['payload'] ?? '');
		$attempt = (int)($argument['attempt'] ?? 0);
		if ($id === 0 || $payload === '') {
			return;
		}
		$webhook = $this->webhooks->findById($id);
		if ($webhook === null || !$webhook->isActive()) {
			return;
		}

		// SSRF TOCTOU: the URL was validated at registration, but DNS answers
		// change — re-resolve NOW and pin exactly the verified public IPs on
		// the connection, so a rebind between registration (or even between
		// this check and connect) cannot retarget the POST at an internal
		// host. A URL failing here is tampered or broken, not transient:
		// disable the webhook — blind retries can't heal DNS.
		$pinIps = [];
		try {
			$pinIps = $this->urlGuard->resolveForDelivery($webhook->getUrl());
		} catch (\Throwable $e) {
			$this->logger->warning('agent webhook disabled: URL failed delivery-time SSRF re-check', [
				'webhookId' => $id,
				'exception' => $e->getMessage(),
			]);
			try {
				$this->webhooks->recordDelivery($id, time(), false, true);
			} catch (\Throwable $inner) {
				$this->logger->warning('agent webhook bookkeeping failed', [
					'webhookId' => $id,
					'exception' => $inner->getMessage(),
				]);
			}
			return;
		}

		$envelope = json_decode($payload, true);
		$event = is_array($envelope) ? (string)($envelope['event'] ?? 'unknown') : 'unknown';
		$timestamp = time();
		$signature = hash_hmac('sha256', $timestamp . '.' . $payload, $webhook->getSecret());

		$ok = false;
		try {
			$stem = Application::APP_ID;
			if (str_ends_with($stem, 'check') && strlen($stem) > 5) {
				$headerStem = 'X-' . ucfirst(substr($stem, 0, -5)) . 'Check';
			} else {
				$headerStem = 'X-' . ucfirst($stem);
			}
			$requestOptions = [
				'timeout' => self::TIMEOUT_SECONDS,
				'connect_timeout' => self::TIMEOUT_SECONDS,
				'timeout' => self::TIMEOUT_SECONDS,
				'connect_timeout' => self::TIMEOUT_SECONDS,
				'headers' => [
					'Content-Type' => 'application/json',
					'User-Agent' => 'check-suite-webhook/1.0',
					$headerStem . '-Webhook-Event' => $event,
					$headerStem . '-Webhook-Signature' => 't=' . $timestamp . ',v1=' . $signature,
				],
				'body' => $payload,
				// Never follow redirects: a 30x could silently retarget the
				// subscription at a different host — treat as failure.
				'allow_redirects' => false,
			];
			// Pin the connection to exactly the IPs validated above —
			// CURLOPT_RESOLVE overrides DNS for this request, closing the
			// re-check→connect rebind window entirely.
			if ($pinIps !== [] && defined('CURLOPT_RESOLVE')) {
				$urlParts = parse_url($webhook->getUrl());
				$port = (int)($urlParts['port']
					?? (strtolower((string)($urlParts['scheme'] ?? 'https')) === 'https' ? 443 : 80));
				$requestOptions['curl'] = [
					CURLOPT_RESOLVE => [
						sprintf('%s:%d:%s', (string)$urlParts['host'], $port, implode(',', $pinIps)),
					],
				];
			}
			$response = $this->clientService->newClient()->post($webhook->getUrl(), $requestOptions);
			$status = $response->getStatusCode();
			$ok = $status >= 200 && $status < 300;
		} catch (\Throwable $e) {
			$this->logger->info('agent webhook delivery transport failure', [
				'webhookId' => $id,
				'event' => $event,
				'attempt' => $attempt,
				'exception' => $e->getMessage(),
			]);
		}

		$failCount = $ok ? 0 : $webhook->getFailCount() + 1;
		$disable = !$ok && ($failCount >= self::MAX_FAILURES || $attempt + 1 >= self::MAX_ATTEMPTS);
		try {
			$this->webhooks->recordDelivery($id, $timestamp, $ok, $disable);
		} catch (\Throwable $e) {
			$this->logger->warning('agent webhook bookkeeping failed', [
				'webhookId' => $id,
				'exception' => $e->getMessage(),
			]);
		}
		if (!$ok && !$disable) {
			$this->jobList->add(self::class, [
				'webhookId' => $id,
				'payload' => $payload,
				'attempt' => $attempt + 1,
			]);
		}
		if ($disable) {
			$this->logger->warning('agent webhook auto-disabled after repeated failures', [
				'webhookId' => $id,
				'event' => $event,
			]);
		}
	}
}
