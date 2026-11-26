<?php
/** Synced from _shared/agent-auth — do not edit app-side. */

declare(strict_types=1);

namespace OCA\ProjectCheck\Listener;

use OCA\ProjectCheck\Agent\AgentTokenMapper;
use Psr\Log\LoggerInterface;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\UserDeletedEvent;

/**
 * GDPR / least-privilege: when the NC account goes away, its agent tokens
 * must not live on as orphaned credentials. Audit rows are kept — they
 * are the forensic trail (user_id becomes a dangling reference, which is
 * correct for an audit log).
 *
 * Registered per app in Application::register():
 *   $context->registerEventListener(UserDeletedEvent::class, AgentUserDeletedListener::class);
 *
 * @template-implements IEventListener<UserDeletedEvent>
 */
final class AgentUserDeletedListener implements IEventListener
{
	public function __construct(
		private readonly AgentTokenMapper $tokens,
		private readonly LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void
	{
		if (!$event instanceof UserDeletedEvent) {
			return;
		}
		// This listener fires on EVERY user deletion system-wide — a
		// missing table (mid-upgrade, app freshly installed but repair
		// step not yet run, partial uninstall) must not turn a user
		// deletion into a 500 for an unrelated app.
		try {
			$this->tokens->deleteByUserId($event->getUser()->getUID());
		} catch (\Throwable $e) {
			$this->logger->warning('agent token purge failed', [
				'exception' => $e,
			]);
		}
	}
}
