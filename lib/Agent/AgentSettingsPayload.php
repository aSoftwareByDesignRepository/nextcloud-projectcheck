<?php
/** Synced from _shared/agent-auth — do not edit app-side. */

declare(strict_types=1);

namespace OCA\ProjectCheck\Agent;

use OCP\IConfig;
use OCP\IURLGenerator;
use OCA\ProjectCheck\AppInfo\Application;
use OCA\ProjectCheck\Controller\AgentTokenController;

/**
 * Builds the `$agentAccess` template payload for the "AI access" settings
 * section. One call from the app's settings controller:
 *
 *   $params['agentAccess'] = $this->agentSettingsPayload->payload($uid);
 *
 * The partial renders itself empty/hidden when 'visible' is false, so a
 * non-admin user never sees the section (nav-gate, not a disabled state)
 * unless self-mint is enabled and they may use the app.
 */
class AgentSettingsPayload
{
	public function __construct(
		private readonly AgentTokenMapper $tokenMapper,
		private readonly AgentTokenService $tokenService,
		private readonly AgentManifest $manifest,
		private readonly AgentAccessGate $accessGate,
		private readonly IConfig $config,
		private readonly IURLGenerator $urlGenerator,
	) {
	}

	/**
	 * @return array<string, mixed>
	 */
	public function payload(string $uid): array
	{
		$isAdmin = $this->accessGate->canAdminister($uid);
		$selfMint = $this->selfMintEnabled();
		$canUse = $this->accessGate->canUseApp($uid);
		$canMint = $isAdmin || ($selfMint && $canUse);
		// The section is hidden for users who can neither mint nor see their
		// own tokens — including when the app declares no agent surface.
		$visible = $canMint || $isAdmin;
		if (!$this->manifest->isDeclared()) {
			$visible = $isAdmin;
		}

		$now = time();
		$rows = $isAdmin ? $this->tokenMapper->findAll() : $this->tokenMapper->findByUser($uid);

		return [
			'visible' => $visible,
			'isAdmin' => $isAdmin,
			'canMint' => $canMint,
			'selfMintEnabled' => $selfMint,
			'appId' => Application::APP_ID,
			'agentHeader' => $this->manifest->agentHeader(),
			'ncUrl' => rtrim($this->urlGenerator->getBaseUrl(), '/'),
			'declared' => $this->manifest->isDeclared(),
			'tokens' => array_map(
				fn (AgentToken $t) => $this->tokenService->serialize($t, $now),
				$rows,
			),
			'presets' => [
				'read' => $this->manifest->scopesForPreset('read'),
				'act' => $this->manifest->scopesForPreset('act'),
			],
			'allScopes' => $this->manifest->scopeSet(),
			'scopeLabels' => $this->manifest->scopeLabels(),
		];
	}

	private function selfMintEnabled(): bool
	{
		return $this->config->getAppValue(
			Application::APP_ID,
			AgentTokenController::SELF_MINT_CONFIG_KEY,
			'0',
		) === '1';
	}
}
