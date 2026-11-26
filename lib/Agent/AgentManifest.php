<?php
/** Synced from _shared/agent-auth — do not edit app-side. */

declare(strict_types=1);

namespace OCA\ProjectCheck\Agent;

/**
 * Runtime view over the shipped agent contract (`appinfo/agent-manifest.json`,
 * byte-identical to tests/contracts/agent-tools.json — parity is enforced by
 * scripts/check-agent-contracts.py; tests/ is NOT shipped in release
 * tarballs, so the served copy lives in appinfo/).
 *
 * The manifest is the single source of truth for:
 *   - which routes are agent-exposed (toolFor(method, path))
 *   - which scope each tool requires
 *   - risk class / confirm requirement / idempotency mechanism
 *   - the scope universe a mint may grant (scopeSet)
 *
 * A request carrying an agent credential to a NON-declared path is denied
 * (fail-closed): the manifest defines the whole reachable surface.
 */
class AgentManifest
{
	public const SCHEMA_VERSION = 1;

	public const RISK_READ = 0;
	public const RISK_WRITE = 1;
	public const RISK_STATE_TRANSITION = 2;
	public const RISK_MONEY_LEGAL = 3;

	private const MUTATING_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

	/** @var array<string, mixed>|null */
	private ?array $data = null;
	private bool $loaded = false;

	public function __construct(
		private readonly string $appId,
		private readonly string $manifestPath,
	) {
	}

	/**
	 * Conventional agent auth header for this app: X-<Studly>-Agent.
	 * HTTP headers are case-insensitive; the exact casing is pinned by the
	 * CI gate so docs/configs never drift.
	 */
	public function agentHeader(): string
	{
		$stem = $this->appId;
		if (str_ends_with($stem, 'check') && strlen($stem) > 5) {
			return 'X-' . ucfirst(substr($stem, 0, -5)) . 'Check-Agent';
		}
		return 'X-' . ucfirst($stem) . '-Agent';
	}

	public function isAvailable(): bool
	{
		return $this->load() !== null;
	}

	public function isDeclared(): bool
	{
		$data = $this->load();
		return $data !== null && ($data['agentExposure'] ?? 'none') === 'declared';
	}

	public function schemaVersion(): int
	{
		$data = $this->load();
		return $data !== null ? (int)($data['schemaVersion'] ?? 0) : 0;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function tools(): array
	{
		$data = $this->load();
		$tools = $data['tools'] ?? [];
		return is_array($tools) ? array_values($tools) : [];
	}

	/**
	 * Match a request method + normalized app-relative path to a declared
	 * tool. Path templates use `{param}` segments which match `[^/]+`.
	 *
	 * @return array<string, mixed>|null
	 */
	public function toolFor(string $method, string $path): ?array
	{
		$method = strtoupper($method);
		foreach ($this->tools() as $tool) {
			if (strtoupper((string)($tool['method'] ?? '')) !== $method) {
				continue;
			}
			if ($this->pathMatches((string)($tool['path'] ?? ''), $path)) {
				return $tool;
			}
		}
		return null;
	}

	/**
	 * Does the path fall inside ANY declared tool's path template (any
	 * method)? Used to reject agent credentials on undeclared surfaces even
	 * when the verb differs — an existence oracle by method is not allowed.
	 */
	public function isAgentPath(string $path): bool
	{
		foreach ($this->tools() as $tool) {
			if ($this->pathMatches((string)($tool['path'] ?? ''), $path)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * All scopes the manifest declares — the mint-time grant ceiling.
	 *
	 * @return list<string>
	 */
	public function scopeSet(): array
	{
		$set = [];
		foreach ($this->tools() as $tool) {
			$scope = (string)($tool['scope'] ?? '');
			if ($scope !== '') {
				$set[$scope] = true;
			}
		}
		return array_keys($set);
	}

	/**
	 * Scopes for a settings preset.
	 *   read → every risk-0 scope
	 *   act  → risk-0 + risk-1/2 write scopes (NOT R3 — money/legal writes
	 *          are never in the lazy preset; custom selection only)
	 *
	 * @return list<string>
	 */
	public function scopesForPreset(string $preset): array
	{
		$scopes = [];
		foreach ($this->tools() as $tool) {
			$scope = (string)($tool['scope'] ?? '');
			$risk = (int)($tool['riskClass'] ?? 0);
			if ($scope === '') {
				continue;
			}
			if ($preset === 'read' && $risk === self::RISK_READ) {
				$scopes[$scope] = true;
			}
			if ($preset === 'act' && $risk <= self::RISK_STATE_TRANSITION) {
				$scopes[$scope] = true;
			}
		}
		return array_keys($scopes);
	}

	public function requiresConfirm(array $tool): bool
	{
		return ($tool['confirm'] ?? null) === 'required'
			|| (int)($tool['riskClass'] ?? 0) >= self::RISK_STATE_TRANSITION;
	}

	public function isMutating(array $tool): bool
	{
		return in_array(strtoupper((string)($tool['method'] ?? 'GET')), self::MUTATING_METHODS, true);
	}

	public function isBatchForbidden(array $tool): bool
	{
		return ($tool['batch'] ?? null) === 'forbidden';
	}

	/**
	 * The endpoint-path prefix under which agent credentials may act.
	 * Derived from declared tool paths — anything outside is hard-denied.
	 *
	 * @return list<string>
	 */
	public function agentPathPrefixes(): array
	{
		$prefixes = ['/agent/v1/manifest'];
		foreach ($this->tools() as $tool) {
			$path = (string)($tool['path'] ?? '');
			if ($path === '') {
				continue;
			}
			// Template prefix up to the first {param} gives a safe prefix.
			$cut = strpos($path, '{');
			$prefix = $cut === false ? $path : substr($path, 0, $cut);
			$prefix = rtrim($prefix, '/');
			if ($prefix !== '') {
				$prefixes[] = $prefix;
			}
		}
		return array_values(array_unique($prefixes));
	}

	/**
	 * Served payload: manifest verbatim + envelope metadata. The MCP
	 * adapter treats this as authoritative (fail-closed on schemaVersion
	 * outside its supported range).
	 *
	 * @return array<string, mixed>
	 */
	public function servedEnvelope(string $appVersion): array
	{
		$manifest = $this->load() ?? [
			'schemaVersion' => self::SCHEMA_VERSION,
			'appId' => $this->appId,
			'agentExposure' => 'none',
			'tools' => [],
		];
		return [
			'schemaVersion' => (int)($manifest['schemaVersion'] ?? self::SCHEMA_VERSION),
			'appId' => $this->appId,
			'appVersion' => $appVersion,
			'agentHeader' => $this->agentHeader(),
			'manifest' => $manifest,
		];
	}

	/**
	 * Scope → human label map for the settings surface (chips show intent,
	 * never grammar). Label = the tool's short `label` if present, else its
	 * first sentence of `description`.
	 *
	 * @return array<string, string>
	 */
	public function scopeLabels(): array
	{
		$labels = [];
		foreach ($this->tools() as $tool) {
			$scope = (string)($tool['scope'] ?? '');
			if ($scope === '' || isset($labels[$scope])) {
				continue;
			}
			$label = (string)($tool['label'] ?? '');
			if ($label === '') {
				$desc = trim((string)($tool['description'] ?? ''));
				$label = $desc !== '' ? (explode('.', $desc)[0] ?? $desc) : $scope;
			}
			$labels[$scope] = $label;
		}
		return $labels;
	}

	public function pathMatches(string $template, string $path): bool
	{
		if ($template === '') {
			return false;
		}
		$pattern = preg_quote($template, '#');
		// preg_quote escapes {} to \{ \} — turn each escaped {param} into a segment wildcard.
		$pattern = preg_replace('#\\\\\{[^/]+\\\\\}#', '[^/]+', $pattern);
		return preg_match('#^' . $pattern . '$#', $path) === 1;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function load(): ?array
	{
		if ($this->loaded) {
			return $this->data;
		}
		$this->loaded = true;
		if (!is_file($this->manifestPath)) {
			return null;
		}
		try {
			$decoded = json_decode((string)file_get_contents($this->manifestPath), true, 32, JSON_THROW_ON_ERROR);
		} catch (\JsonException) {
			return null;
		}
		$this->data = is_array($decoded) ? $decoded : null;
		return $this->data;
	}
}
