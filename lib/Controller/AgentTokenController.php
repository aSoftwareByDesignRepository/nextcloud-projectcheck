<?php
/** Synced from _shared/agent-auth — do not edit app-side. */

declare(strict_types=1);

namespace OCA\ProjectCheck\Controller;

use OCA\ProjectCheck\Agent\AgentAccessGate;
use OCA\ProjectCheck\Agent\AgentAuditMapper;
use OCA\ProjectCheck\Agent\AgentContext;
use OCA\ProjectCheck\Agent\AgentException;
use OCA\ProjectCheck\Agent\AgentManifest;
use OCA\ProjectCheck\Agent\AgentScopeChecker;
use OCA\ProjectCheck\Agent\AgentToken;
use OCA\ProjectCheck\Agent\AgentTokenMapper;
use OCA\ProjectCheck\Agent\AgentTokenService;
use OCA\ProjectCheck\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;

/**
 * Session+CSRF surface for the "AI access" settings section.
 *
 * Agent credentials are REFUSED on every endpoint here (enforced by
 * AgentAuthMiddleware before dispatch) — a leaked token must never mint,
 * enumerate, or rotate tokens. These actions require the normal web
 * session + CSRF token; POSTs carry no NoCSRFRequired on purpose.
 *
 * Mint rights (v1): app admins only — OR any app user when the admin has
 * enabled self-service mint (NH-06 flag `agent_self_mint`). Tokens are
 * personal: a token always belongs to the minting session user; admins
 * additionally see/revoke/rotate every token of this instance.
 */
class AgentTokenController extends Controller
{
	public const SELF_MINT_CONFIG_KEY = 'agent_self_mint';

	public function __construct(
		IRequest $request,
		private readonly AgentTokenService $tokenService,
		private readonly AgentTokenMapper $tokenMapper,
		private readonly AgentAuditMapper $auditMapper,
		private readonly AgentManifest $manifest,
		private readonly AgentAccessGate $accessGate,
		private readonly IUserSession $userSession,
		private readonly IConfig $config,
		private readonly IURLGenerator $urlGenerator,
		private readonly AgentContext $agentContext,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	public function index(): JSONResponse
	{
		$uid = $this->requireSessionUid();
		$isAdmin = $this->accessGate->canAdminister($uid);
		if (!$isAdmin && !$this->selfMintEnabled() && !$this->accessGate->canUseApp($uid)) {
			throw new AgentException('forbidden', 'No app access.', 403);
		}
		$now = time();
		$rows = $isAdmin ? $this->tokenMapper->findAll() : $this->tokenMapper->findByUser($uid);
		return new JSONResponse([
			'ok' => true,
			'tokens' => array_map(fn (AgentToken $t) => $this->tokenService->serialize($t, $now), $rows),
			'policy' => $this->policyPayload($uid),
		]);
	}

	#[NoAdminRequired]
	#[UserRateLimit(limit: 10, period: 60)]
	public function mint(): JSONResponse
	{
		$uid = $this->requireSessionUid();
		$this->assertMayMint($uid);

		$body = $this->jsonBody();
		$label = (string)($body['label'] ?? '');
		$scopes = $this->resolveRequestedScopes($body);
		[$raw, $entity] = $this->tokenService->mint($uid, $label, $scopes, time());

		return new JSONResponse([
			'ok' => true,
			'token' => $this->tokenService->serialize($entity, time()),
			// Raw value — returned EXACTLY once, never stored, never logged.
			'secret' => $raw,
			'mcpConfig' => $this->mcpConfigSnippet($raw),
		], 201);
	}

	#[NoAdminRequired]
	public function revoke(int $id): JSONResponse
	{
		$uid = $this->requireSessionUid();
		$token = $this->requireOwnedOrAdminToken($id, $uid);
		$this->tokenService->revoke($token, time());
		return new JSONResponse(['ok' => true]);
	}

	#[NoAdminRequired]
	#[UserRateLimit(limit: 10, period: 60)]
	public function rotate(int $id): JSONResponse
	{
		$uid = $this->requireSessionUid();
		$this->assertMayMint($uid);
		$token = $this->requireOwnedOrAdminToken($id, $uid);
		if ($token->isRevoked()) {
			throw new AgentException('validation', 'Cannot rotate a revoked token.', 422);
		}
		[$raw, $entity] = $this->tokenService->rotate($token, time());
		return new JSONResponse([
			'ok' => true,
			'token' => $this->tokenService->serialize($entity, time()),
			'secret' => $raw,
			'mcpConfig' => $this->mcpConfigSnippet($raw),
		], 201);
	}

	#[NoAdminRequired]
	public function audit(mixed $token_id = null): JSONResponse
	{
		$uid = $this->requireSessionUid();
		$isAdmin = $this->accessGate->canAdminister($uid);
		$tokenId = is_numeric($token_id) ? (int)$token_id : null;

		if ($tokenId !== null) {
			// Uniform-404: a token that exists but is not yours looks absent.
			$this->requireOwnedOrAdminToken($tokenId, $uid);
			$rows = $this->auditMapper->findByToken($tokenId);
		} elseif ($isAdmin) {
			$rows = [];
			foreach ($this->tokenMapper->findAll() as $t) {
				foreach ($this->auditMapper->findByToken($t->getId(), 20) as $row) {
					$rows[] = $row;
				}
			}
		} else {
			$rows = $this->auditMapper->findByUser($uid);
		}

		return new JSONResponse([
			'ok' => true,
			'audit' => array_map(static fn ($r) => [
				'id' => $r->getId(),
				'tokenId' => $r->getTokenId(),
				'userId' => $r->getUserId(),
				'actorType' => $r->getActorType(),
				'tool' => $r->getTool(),
				'route' => $r->getRoute(),
				'clientRequestId' => $r->getClientRequestId(),
				'result' => $r->getResult(),
				'createdAt' => $r->getCreatedAt(),
			], $rows),
		]);
	}

	/**
	 * NH-06: admin toggles self-service mint for app users.
	 */
	public function updatePolicy(): JSONResponse
	{
		$uid = $this->requireSessionUid();
		if (!$this->accessGate->canAdminister($uid)) {
			throw new AgentException('forbidden', 'App admin required.', 403);
		}
		$body = $this->jsonBody();
		$selfMint = in_array($body['selfMint'] ?? null, [true, 1, '1', 'true'], true);
		$this->config->setAppValue(Application::APP_ID, self::SELF_MINT_CONFIG_KEY, $selfMint ? '1' : '0');
		return new JSONResponse(['ok' => true, 'policy' => $this->policyPayload($uid)]);
	}

	// ── internals ────────────────────────────────────────────────────

	private function requireSessionUid(): string
	{
		$user = $this->userSession->getUser();
		if ($user === null) {
			throw new AgentException('agent_token_invalid', 'Authentication required.', 401);
		}
		return $user->getUID();
	}

	private function selfMintEnabled(): bool
	{
		return $this->config->getAppValue(Application::APP_ID, self::SELF_MINT_CONFIG_KEY, '0') === '1';
	}

	private function assertMayMint(string $uid): void
	{
		if ($this->accessGate->canAdminister($uid)) {
			return;
		}
		if ($this->selfMintEnabled() && $this->accessGate->canUseApp($uid)) {
			return;
		}
		throw new AgentException('forbidden', 'App admin required to create agent tokens.', 403);
	}

	/**
	 * @param array<string, mixed> $body
	 * @return list<string>
	 */
	private function resolveRequestedScopes(array $body): array
	{
		$preset = (string)($body['preset'] ?? '');
		if ($preset === 'custom') {
			$scopes = $body['scopes'] ?? [];
			if (!is_array($scopes)) {
				throw new AgentException('validation', 'scopes must be a list.', 422, [
					'fields' => ['scopes' => 'invalid_type'],
				]);
			}
			return AgentScopeChecker::validateGrantRequest($scopes, $this->manifest->scopeSet());
		}
		if (in_array($preset, ['read', 'act'], true)) {
			return AgentScopeChecker::validateGrantRequest(
				$this->manifest->scopesForPreset($preset),
				$this->manifest->scopeSet(),
			);
		}
		throw new AgentException('validation', 'Unknown scope preset.', 422, [
			'fields' => ['preset' => 'invalid'],
		]);
	}

	/**
	 * Uniform-404 ownership: a token that exists but belongs to someone
	 * else is indistinguishable from a missing one (no existence oracle).
	 * Admins may act on any token.
	 */
	private function requireOwnedOrAdminToken(int $id, string $uid): AgentToken
	{
		$token = $this->tokenMapper->findById($id);
		if ($token === null) {
			throw new AgentException('not_found', 'Token not found.', 404);
		}
		if ($token->getUserId() === $uid || $this->accessGate->canAdminister($uid)) {
			return $token;
		}
		throw new AgentException('not_found', 'Token not found.', 404);
	}

	private function policyPayload(string $uid): array
	{
		return [
			'selfMintEnabled' => $this->selfMintEnabled(),
			'isAdmin' => $this->accessGate->canAdminister($uid),
			'scopes' => $this->manifest->scopeSet(),
			'scopeLabels' => $this->manifest->scopeLabels(),
			'agentHeader' => $this->manifest->agentHeader(),
		];
	}

	/**
	 * Ready-to-paste MCP client config — the "connect my AI" flow ends here,
	 * not at a bare secret (SPEC §7).
	 *
	 * @return array<string, mixed>
	 */
	private function mcpConfigSnippet(string $raw): array
	{
		$base = rtrim($this->urlGenerator->getBaseUrl(), '/');
		return [
			'mcpServers' => [
				'checksuite' => [
					'command' => 'python3',
					'args' => ['checksuite-mcp.py'],
					'env' => [
						'CK_NC_URL' => $base,
						'CK_TOKENS' => json_encode([Application::APP_ID => $raw]),
					],
				],
			],
		];
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
