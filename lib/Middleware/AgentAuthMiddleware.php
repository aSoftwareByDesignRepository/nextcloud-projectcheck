<?php
/** Synced from _shared/agent-auth — do not edit app-side. */

declare(strict_types=1);

namespace OCA\ProjectCheck\Middleware;

use OCA\ProjectCheck\Agent\AgentAudit;
use OCA\ProjectCheck\Agent\AgentAuditMapper;
use OCA\ProjectCheck\Agent\AgentContext;
use OCA\ProjectCheck\Agent\AgentException;
use OCA\ProjectCheck\Agent\AgentManifest;
use OCA\ProjectCheck\Agent\AgentSeatGate;
use OCA\ProjectCheck\Agent\AgentToken;
use OCA\ProjectCheck\Agent\AgentTokenService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Middleware;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * Agent credential pipeline for the X-ProjectCheck-Agent header (SPEC §9.3):
 *
 *   resolve token → arm AgentContext → per-request seat check →
 *   scope check vs manifest tool → confirm gate (risk ≥2) →
 *   fixed-window mutation budget → last_used stamp → audit write
 *
 * Design invariants (SPEC NN-xx):
 *  - Custom header only. A ckst_ value smuggled into Authorization
 *    Basic/Bearer is rejected outright (NN-09) — those schemes belong to
 *    core token login / oauth2, foreign values there are undefined.
 *  - Never a persistent session/cookie/authtoken side effect (NN-10):
 *    this class only arms the request-scoped AgentContext; requireUid()
 *    resolves agentContext->uid() before the session user, so a foreign
 *    session cookie cannot shadow the explicit credential.
 *  - Token admin endpoints (/agent/v1/tokens*, /agent/v1/policy) are
 *    session+CSRF surfaces: an agent credential presented there is denied
 *    (a leaked token must never mint more tokens).
 *  - Agent credential on ANY non-declared path → 403 (fail-closed).
 *
 * Middleware order: register AFTER the app's other middlewares — this
 * middleware's afterException then runs FIRST and maps AgentException to
 * the app JSON envelope before generic handlers see it.
 */
class AgentAuthMiddleware extends Middleware
{
	private const MANIFEST_PATH = '/agent/v1/manifest';
	private const ADMIN_PREFIXES = ['/agent/v1/tokens', '/agent/v1/policy', '/agent/v1/audit'];
	private const AGENT_PREFIX = '/agent/v1/';

	/** Params that are always legal on a tool call (not app payload). */
	private const SYSTEM_PARAMS = ['confirm', 'clientRequestId', 'client_request_id', 'dry_run'];

	private ?array $auditedTool = null;
	private bool $auditedDryRun = false;
	private bool $auditWritten = false;

	public function __construct(
		private readonly IRequest $request,
		private readonly AgentContext $agentContext,
		private readonly AgentTokenService $tokenService,
		private readonly AgentManifest $manifest,
		private readonly AgentSeatGate $seatGate,
		private readonly AgentAuditMapper $auditMapper,
		private readonly LoggerInterface $logger,
		private readonly string $appId,
	) {
	}

	public function beforeController($controller, $methodName): void
	{
		$path = $this->normalizeAppPath((string)$this->request->getPathInfo());
		$header = trim((string)$this->request->getHeader($this->manifest->agentHeader()));

		if ($header === '') {
			// NN-09: a ckst_ value must never flow into Basic/Bearer resolution.
			$auth = (string)$this->request->getHeader('Authorization');
			if ($auth !== '' && $this->authorizationContainsAgentToken($auth)) {
				throw new AgentException('agent_token_invalid', 'Agent tokens are only accepted in the ' . $this->manifest->agentHeader() . ' header.', 401);
			}
			return;
		}

		// Token-admin surfaces are session+CSRF only — a scoped credential
		// must never mint, revoke, or enumerate tokens (escalation guard).
		foreach (self::ADMIN_PREFIXES as $prefix) {
			if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
				throw new AgentException('agent_scope_denied', 'Agent tokens cannot manage agent tokens.', 403);
			}
		}

		$tool = null;
		if ($path === self::MANIFEST_PATH) {
			// Manifest endpoint: any valid token (any scope) or session user.
			$isManifestEndpoint = true;
		} else {
			$isManifestEndpoint = false;
			$tool = $this->manifest->toolFor($this->request->getMethod(), $path);
			if ($tool === null) {
				// Agent credential on an undeclared path — whether the route
				// exists for other clients is not the agent's business.
				throw new AgentException('agent_scope_denied', 'No declared agent tool for this route.', 403);
			}
		}

		$now = time();
		$token = $this->tokenService->resolve($header, $now);
		$this->agentContext->arm($token->getUserId(), $token->getId(), $token->scopeList());

		// Per-request seat entitlement (BQ-3): mint-time checks are not
		// sufficient — a revoked seat must kill the token on the next call.
		try {
			$this->seatGate->assertEntitled($token->getUserId());
		} catch (AgentException $e) {
			$this->writeAudit($token, $tool, $path, AgentAudit::RESULT_DENIED);
			throw $e;
		}

		if ($isManifestEndpoint) {
			return;
		}

		$scope = (string)($tool['scope'] ?? '');
		if (!$this->agentContext->hasScope($scope)) {
			$this->writeAudit($token, $tool, $path, AgentAudit::RESULT_DENIED);
			throw new AgentException('agent_scope_denied', 'Token lacks scope ' . $scope . '.', 403);
		}
		$this->agentContext->setTool((string)($tool['name'] ?? 'unknown'));
		$this->auditedTool = $tool;

		$this->assertNoUnknownParams($tool, $path);

		// NH-05 dry-run (fail-closed): dry_run is only honored on tools
		// declaring "dryRun": true — everywhere else a truthy flag is a
		// hard 422, never a silent commit. A supported dry-run commits
		// nothing, so the confirm gate and its confirmation requirement
		// do not apply to it.
		$this->auditedDryRun = $this->truthyFlag('dry_run');
		if ($this->auditedDryRun && !$this->manifest->supportsDryRun($tool)) {
			$this->writeAudit($token, $tool, $path, AgentAudit::RESULT_DENIED);
			throw new AgentException('agent_dryrun_unsupported', 'This tool does not support dry_run.', 422);
		}

		if (!$this->auditedDryRun && $this->manifest->requiresConfirm($tool) && !$this->truthyFlag('confirm')) {
			$this->writeAudit($token, $tool, $path, AgentAudit::RESULT_DENIED);
			throw new AgentException('confirmation_required', 'This action requires confirm=true.', 422);
		}

		if ($this->manifest->isMutating($tool)) {
			$retryAfter = $this->tokenService->consumeMutationBudget($token, $now);
			if ($retryAfter !== null) {
				$this->writeAudit($token, $tool, $path, AgentAudit::RESULT_DENIED);
				$e = new AgentException('rate_limited', 'Mutation rate limit exceeded.', 429, ['retryAfter' => $retryAfter]);
				throw $e;
			}
		}

		// One row write per request — "last used" stays honest within seconds.
		$this->tokenService->touch($token->getId(), (string)($tool['name'] ?? 'unknown'), $now);
	}

	public function afterController($controller, $methodName, Response $response)
	{
		if ($this->auditedTool !== null && $this->agentContext->isArmed() && $this->manifest->isMutating($this->auditedTool)) {
			$status = $response->getStatus();
			// 'dryrun' is a distinct audit result: an auditor filtering
			// result='ok' sees actual commits only, never previews.
			// 'dryrun' is a distinct audit result but only on success — a
			// failed dry-run is still an error, and an auditor filtering
			// result='ok' sees actual commits only, never previews.
			$this->writeAuditByContext(
				$this->auditedDryRun && $status < 400 ? AgentAudit::RESULT_DRYRUN
					: ($status < 400 ? AgentAudit::RESULT_OK : AgentAudit::RESULT_ERROR)
			);
		}
		return $response;
	}

	public function afterException($controller, $methodName, \Exception $exception)
	{
		// Audit mutating agent calls that failed after arming — denied for
		// our own auth-family errors, error for anything unexpected.
		if ($this->agentContext->isArmed() && $this->auditedTool !== null && $this->manifest->isMutating($this->auditedTool)) {
			$result = $exception instanceof AgentException ? AgentAudit::RESULT_DENIED : AgentAudit::RESULT_ERROR;
			$this->writeAuditByContext($result);
		}

		if ($exception instanceof AgentException) {
			$resp = $this->envelope($exception);
			$retryAfter = $exception->getDetails()['retryAfter'] ?? null;
			if ($retryAfter !== null) {
				$resp->addHeader('Retry-After', (string)$retryAfter);
			}
			return $resp;
		}

		$this->logger->debug('projectcheck agent middleware passing through non-agent exception');
		throw $exception;
	}

	/**
	 * NN-09 catch: Bearer carries the token in plaintext (stripos on the raw
	 * header); Basic base64-encodes "user:password" — the ckst_ prefix is
	 * invisible unless the payload is decoded first. Both must die here, or
	 * the credential would fall through to core tryTokenLogin/session auth.
	 */
	private function authorizationContainsAgentToken(string $auth): bool
	{
		if (stripos($auth, AgentTokenService::TOKEN_PREFIX) !== false
			|| stripos($auth, 'ckst_') !== false) {
			return true;
		}
		if (stripos($auth, 'Basic ') === 0) {
			$decoded = base64_decode(substr($auth, 6), true);
			if (is_string($decoded) && stripos($decoded, 'ckst_') !== false) {
				return true;
			}
		}
		return false;
	}

	// ── internals ────────────────────────────────────────────────────

	/**
	 * Strict confirm: JSON true, or form-encoded 'true'/'1'/'on'. The
	 * adapter always sends a strict JSON bool — accepting the form-encoded
	 * equivalents keeps server-side enforcement honest for form clients
	 * while 'false'/'0'/presence-only NEVER count (InputCoercion footgun).
	 */
	private function truthyFlag(string $name): bool
	{
		$value = $this->param($name);
		return in_array($value, [true, 1, '1', 'true', 'on'], true);
	}

	/**
	 * Hallucination guard (US-006): a param the manifest does not declare
	 * is a contract violation — the agent learns the real accepted set
	 * instead of a silent ignore or a downstream 500.
	 */
	private function assertNoUnknownParams(array $tool, string $path): void
	{
		$declared = array_keys((array)($tool['params'] ?? []));
		$allowed = array_merge($declared, self::SYSTEM_PARAMS, $this->pathParamNames((string)($tool['path'] ?? '')));

		$unknown = [];
		foreach (array_keys($this->request->getParams()) as $name) {
			if (!in_array($name, $allowed, true) && !in_array($name, ['id', '_route'], true)) {
				$unknown[] = $name;
			}
		}
		foreach (array_keys($this->jsonBody()) as $name) {
			if (!in_array($name, $allowed, true)) {
				$unknown[] = $name;
			}
		}
		$unknown = array_values(array_unique($unknown));
		if ($unknown === []) {
			return;
		}

		throw new AgentException('unknown_param', 'Unknown parameter(s): ' . implode(', ', $unknown), 400, [
			'acceptedParams' => array_values(array_unique($allowed)),
			'unknownParams' => $unknown,
		]);
	}

	/**
	 * @return list<string>
	 */
	private function pathParamNames(string $template): array
	{
		preg_match_all('#\{([^}/]+)\}#', $template, $m);
		return $m[1] ?? [];
	}

	private function param(string $name): mixed
	{
		$json = $this->jsonBody();
		if (array_key_exists($name, $json)) {
			return $json[$name];
		}
		return $this->request->getParam($name);
	}

	/**
	 * php://input is re-readable (PHP ≥5.6) — safe to peek for middleware
	 * checks; controllers still read it themselves afterwards.
	 *
	 * @return array<string, mixed>
	 */
	private function jsonBody(): array
	{
		$raw = file_get_contents('php://input');
		if (!is_string($raw) || $raw === '') {
			return [];
		}
		try {
			$decoded = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
		} catch (\JsonException) {
			return [];
		}
		return is_array($decoded) ? $decoded : [];
	}

	private function writeAudit(AgentToken $token, ?array $tool, string $path, string $result): void
	{
		if ($this->auditWritten || $tool === null || !$this->manifest->isMutating($tool)) {
			return;
		}
		$this->auditWritten = true;
		try {
			$this->auditMapper->record(
				$token->getId(),
				$token->getUserId(),
				(string)($tool['name'] ?? 'unknown'),
				(string)($tool['route'] ?? $path),
				$this->clientRequestId(),
				$result,
				time(),
			);
		} catch (\Throwable $e) {
			// An audit-INSERT failure must never mask the auth decision or a
			// committed response: a dropped/locked audit table turning a 403
			// into a 500 — or a committed mutation into a client-visible
			// failure that triggers a retry — is worse than a missing row.
			$this->logger->error('agent audit write failed', [
				'exception' => $e->getMessage(),
			]);
		}
	}

	private function writeAuditByContext(string $result): void
	{
		$tokenId = $this->agentContext->tokenId();
		$uid = $this->agentContext->uid();
		if ($this->auditWritten || $tokenId === null || $uid === null || $this->auditedTool === null) {
			return;
		}
		$this->auditWritten = true;
		try {
			$this->auditMapper->record(
				$tokenId,
				$uid,
				(string)($this->auditedTool['name'] ?? 'unknown'),
				(string)($this->auditedTool['route'] ?? ''),
				$this->clientRequestId(),
				$result,
				time(),
			);
		} catch (\Throwable $e) {
			$this->logger->error('agent audit write failed', [
				'exception' => $e->getMessage(),
			]);
		}
	}

	private function clientRequestId(): ?string
	{
		$value = $this->param('clientRequestId') ?? $this->param('client_request_id');
		if (!is_string($value) || trim($value) === '') {
			return null;
		}
		return mb_substr(trim($value), 0, 190);
	}

	private function envelope(AgentException $e): JSONResponse
	{
		$body = [
			'error' => [
				'code' => $e->getErrorCode(),
				'message' => $e->getMessage(),
			],
		];
		if ($e->getDetails() !== []) {
			$body['error']['details'] = $e->getDetails();
		}
		return new JSONResponse($body, $e->getHttpStatus());
	}

	/**
	 * Strip /index.php and /apps/<appid> mounts; collapse .. and %2e so a
	 * crafted path cannot dodge the declared-surface check.
	 */
	private function normalizeAppPath(string $pathInfo): string
	{
		$path = '/' . ltrim($pathInfo, '/');
		$path = str_replace(['%2e', '%2E'], '.', $path);
		if (str_starts_with($path, '/index.php/')) {
			$path = substr($path, strlen('/index.php'));
		}
		foreach (['/apps/projectcheck', '/index.php/apps/projectcheck'] as $prefix) {
			if ($path === $prefix) {
				return '/';
			}
			if (str_starts_with($path, $prefix . '/')) {
				$rest = substr($path, strlen($prefix));
				$path = $rest === '' ? '/' : $rest;
				break;
			}
		}
		// collapse dot segments
		$segments = [];
		foreach (explode('/', $path) as $seg) {
			if ($seg === '' || $seg === '.') {
				continue;
			}
			if ($seg === '..') {
				array_pop($segments);
				continue;
			}
			$segments[] = $seg;
		}
		$normalized = '/' . implode('/', $segments);
		return $normalized === '/' ? '/' : rtrim($normalized, '/');
	}
}
