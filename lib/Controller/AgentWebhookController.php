<?php
/** Synced from _shared/agent-auth — do not edit app-side. */

declare(strict_types=1);

namespace OCA\ProjectCheck\Controller;

use OCA\ProjectCheck\Agent\AgentAccessGate;
use OCA\ProjectCheck\Agent\AgentContext;
use OCA\ProjectCheck\Agent\AgentException;
use OCA\ProjectCheck\Agent\AgentManifest;
use OCA\ProjectCheck\Agent\AgentWebhook;
use OCA\ProjectCheck\Agent\AgentWebhookDispatcher;
use OCA\ProjectCheck\Agent\AgentWebhookMapper;
use OCA\ProjectCheck\Agent\WebhookUrlGuard;
use OCA\ProjectCheck\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * NH-02 outbound webhook subscriptions — declared agent tools AND a
 * session surface (settings UI/API clients use the same endpoints).
 *
 * Auth (both paths allowed, never anonymous):
 *   - agent credential: resolved + scope-checked by AgentAuthMiddleware
 *     against the manifest (projectcheck:webhooks:read / :write)
 *   - session user: must pass the app's access door (canUseApp)
 *
 * Mutating endpoints carry NO NoCSRFRequired: session callers must send
 * requesttoken; agent calls pass the framework CSRF gate via the
 * OCS-APIRequest client assertion (same mechanism as companion APIs).
 *
 * Ownership is uniform-404: a subscription that exists but is not yours
 * is indistinguishable from a missing one. Subscriptions are per-owner —
 * no cross-user listing, admins manage their own.
 */
class AgentWebhookController extends Controller
{
	public const MAX_SUBSCRIPTIONS_PER_USER = 10;

	public function __construct(
		IRequest $request,
		private readonly AgentWebhookMapper $webhooks,
		private readonly AgentWebhookDispatcher $dispatcher,
		private readonly WebhookUrlGuard $urlGuard,
		private readonly AgentManifest $manifest,
		private readonly AgentAccessGate $accessGate,
		private readonly AgentContext $agentContext,
		private readonly IUserSession $userSession,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[PublicPage]
	#[NoAdminRequired]
	public function index(): JSONResponse
	{
		$uid = $this->requireUid();
		return new JSONResponse([
			'ok' => true,
			'webhooks' => array_map([$this, 'serialize'], $this->webhooks->findByUser($uid)),
			'events' => $this->eventCatalog(),
		]);
	}

	#[PublicPage]
	#[NoAdminRequired]
	public function create(): JSONResponse
	{
		$uid = $this->requireUid();
		$body = $this->jsonBody();

		$url = $body['url'] ?? '';
		if (!is_string($url)) {
			throw new AgentException('validation', 'url must be a string.', 422, [
				'fields' => ['url' => 'invalid_type'],
			]);
		}
		$this->urlGuard->assertAllowed($url);

		$events = $body['events'] ?? null;
		if (!is_array($events)) {
			throw new AgentException('validation', 'events must be a list.', 422, [
				'fields' => ['events' => 'invalid_type'],
			]);
		}
		$events = $this->validateEvents($events);

		if ($this->webhooks->countActiveByUser($uid) >= self::MAX_SUBSCRIPTIONS_PER_USER) {
			throw new AgentException('validation', 'Webhook subscription limit reached.', 422, [
				'fields' => ['webhooks' => 'limit_reached'],
			]);
		}

		// Signing secret — shown exactly once (like tokens); receivers
		// verify X-<App>-Webhook-Signature with it.
		$secret = 'whsec_' . rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');

		$entity = new AgentWebhook();
		$entity->setUserId($uid);
		$entity->setUrl(trim($url));
		$entity->setSecret($secret);
		$entity->setEvents(implode(' ', $events));
		$entity->setCreatedAt(time());
		$entity->setLastStatus(AgentWebhook::STATUS_NEVER);
		$entity->setFailCount(0);
		$entity = $this->webhooks->insert($entity);

		return new JSONResponse([
			'ok' => true,
			'webhook' => $this->serialize($entity),
			'secret' => $secret,
			'signatureHeader' => $this->signatureHeaderName(),
		], 201);
	}

	#[PublicPage]
	#[NoAdminRequired]
	public function delete(int $id): JSONResponse
	{
		$uid = $this->requireUid();
		$webhook = $this->requireOwned($id, $uid);
		if ($webhook->getRevokedAt() !== null) {
			// Revocation is terminal — a second delete gets the same
			// uniform 404 as a foreign or missing row (no resurrection,
			// no state oracle).
			throw new AgentException('not_found', 'Webhook not found.', 404);
		}
		$this->webhooks->revoke($webhook->getId(), time());
		return new JSONResponse(['ok' => true]);
	}

	#[PublicPage]
	#[NoAdminRequired]
	public function test(int $id): JSONResponse
	{
		$uid = $this->requireUid();
		$webhook = $this->requireOwned($id, $uid);
		if (!$webhook->isActive()) {
			throw new AgentException('validation', 'Subscription is disabled or revoked.', 422, [
				'fields' => ['webhook' => 'inactive'],
			]);
		}
		// Test-fire lands on the addressed subscription only — a deliverTo,
		// never a fan-out, so no other row (same owner's '*' subs included)
		// sees the probe.
		$this->dispatcher->deliverTo($webhook, AgentWebhookDispatcher::EVENT_TEST, [
			'message' => 'Test delivery from ' . Application::APP_ID,
			'webhookId' => $webhook->getId(),
		]);
		return new JSONResponse(['ok' => true, 'queued' => true]);
	}

	// ── internals ────────────────────────────────────────────────────

	private function requireUid(): string
	{
		if ($this->agentContext->isArmed()) {
			// Scope was already enforced by the middleware.
			return (string)$this->agentContext->uid();
		}
		$user = $this->userSession->getUser();
		if ($user === null) {
			throw new AgentException('agent_token_invalid', 'Authentication required.', 401);
		}
		if (!$this->accessGate->canUseApp($user->getUID())) {
			throw new AgentException('agent_scope_denied', 'No app access.', 403);
		}
		return $user->getUID();
	}

	private function requireOwned(int $id, string $uid): AgentWebhook
	{
		$webhook = $this->webhooks->findById($id);
		if ($webhook === null || $webhook->getUserId() !== $uid) {
			throw new AgentException('not_found', 'Webhook not found.', 404);
		}
		return $webhook;
	}

	/**
	 * Event names come from the manifest catalog — never free-text (a
	 * subscription to an event that does not exist is a silent dead end).
	 *
	 * @param list<mixed> $requested
	 * @return list<string>
	 */
	private function validateEvents(array $requested): array
	{
		$catalog = $this->eventCatalog();
		$events = [];
		foreach ($requested as $event) {
			if (!is_string($event)) {
				throw new AgentException('validation', 'Each event must be a string.', 422, [
					'fields' => ['events' => 'invalid_type'],
				]);
			}
			$event = trim($event);
			if ($event === '') {
				continue;
			}
			if ($event !== AgentWebhook::ALL_EVENTS && !in_array($event, $catalog, true)) {
				throw new AgentException('validation', 'Unknown event: ' . $event, 422, [
					'fields' => ['events' => 'unknown_event'],
				]);
			}
			$events[$event] = true;
		}
		$result = array_keys($events);
		if ($result === []) {
			throw new AgentException('validation', 'At least one event is required.', 422, [
				'fields' => ['events' => 'required'],
			]);
		}
		return $result;
	}

	/**
	 * @return list<string>
	 */
	private function eventCatalog(): array
	{
		$manifest = $this->manifest->servedEnvelope('')['manifest'] ?? [];
		$events = is_array($manifest) ? ($manifest['events'] ?? []) : [];
		$base = [AgentWebhookDispatcher::EVENT_WRITE_COMPLETED, AgentWebhookDispatcher::EVENT_TEST];
		return array_values(array_unique(array_merge(
			$base,
			is_array($events) ? array_filter($events, 'is_string') : [],
		)));
	}

	private function serialize(AgentWebhook $w): array
	{
		return [
			'id' => $w->getId(),
			'userId' => $w->getUserId(),
			'url' => $w->getUrl(),
			'events' => $w->eventList(),
			'createdAt' => $w->getCreatedAt(),
			'lastDeliveryAt' => $w->getLastDeliveryAt(),
			'lastStatus' => $w->getLastStatus(),
			'failCount' => $w->getFailCount(),
			'state' => $w->getRevokedAt() !== null ? 'revoked'
				: ($w->getDisabledAt() !== null ? 'disabled' : 'active'),
		];
	}

	private function signatureHeaderName(): string
	{
		return str_replace('-Agent', '-Webhook-Signature', $this->manifest->agentHeader());
	}

	/**
	 * @return array<string, mixed>
	 */
	private function jsonBody(): array
	{
		$raw = file_get_contents('php://input');
		if (is_string($raw) && $raw !== '') {
			try {
				$decoded = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
				if (is_array($decoded)) {
					return $decoded;
				}
			} catch (\JsonException) {
				// fall through to request params
			}
		}
		$params = $this->request->getParams();
		unset($params['id'], $params['_route']);
		return $params;
	}
}
