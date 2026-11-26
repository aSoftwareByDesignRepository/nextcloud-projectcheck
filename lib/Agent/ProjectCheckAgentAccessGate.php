<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Agent;

use OCA\ProjectCheck\Service\AccessControlService;
use OCP\IGroupManager;

/**
 * App-door adapter: the agent surface honours the exact same access policy
 * as the web UI. canUseApp = the app access door; canAdminister = org
 * admins (canManageOrganization) or server admins — matching who may reach
 * the org settings page that hosts the "AI access (MCP)" section.
 */
final class ProjectCheckAgentAccessGate implements AgentAccessGate
{
	public function __construct(
		private readonly AccessControlService $accessControl,
		private readonly IGroupManager $groupManager,
	) {
	}

	public function canUseApp(string $uid): bool
	{
		return $this->accessControl->canUseApp($uid);
	}

	public function canAdminister(string $uid): bool
	{
		return $this->accessControl->canManageOrganization($uid)
			|| $this->groupManager->isAdmin($uid);
	}
}
