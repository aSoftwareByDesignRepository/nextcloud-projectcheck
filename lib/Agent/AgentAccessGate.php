<?php
/** Synced from _shared/agent-auth — do not edit app-side. */

declare(strict_types=1);

namespace OCA\ProjectCheck\Agent;

/**
 * Per-app seam: who may use the app and who may administer it.
 *
 * The pack cannot know each app's access-door service, so each app binds an
 * implementation in Application::register() (a thin adapter over its
 * AccessControlService / CompanionGateService / role system, or the
 * pack-provided ServerAdminAccessGate fallback for apps without a
 * dedicated app-admin role).
 */
interface AgentAccessGate
{
	/**
	 * May this user reach the app at all (the app's access door)?
	 */
	public function canUseApp(string $uid): bool;

	/**
	 * May this user administer the app (dedicated App Admin role per the
	 * access-door rule, or NC server admin)? Token mint/revoke/rotate for
	 * OTHER users' tokens and policy flags require this.
	 */
	public function canAdminister(string $uid): bool;
}
