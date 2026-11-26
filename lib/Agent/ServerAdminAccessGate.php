<?php
/** Synced from _shared/agent-auth — do not edit app-side. */

declare(strict_types=1);

namespace OCA\ProjectCheck\Agent;

use OCP\IGroupManager;
use OCP\IUserSession;

/**
 * Fallback AgentAccessGate for apps without a dedicated app-admin role:
 * NC server admins administer; any logged-in user may use.
 *
 * Apps with an access door MUST bind their own adapter instead — this
 * fallback would widen the agent surface past the app's door policy.
 */
final class ServerAdminAccessGate implements AgentAccessGate
{
	public function __construct(
		private readonly IGroupManager $groupManager,
		private readonly IUserSession $userSession,
	) {
	}

	public function canUseApp(string $uid): bool
	{
		$user = $this->userSession->getUser();
		if ($user !== null) {
			return $user->getUID() === $uid;
		}
		return false;
	}

	public function canAdminister(string $uid): bool
	{
		return $this->groupManager->isAdmin($uid);
	}
}
