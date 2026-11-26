<?php
/** Synced from _shared/agent-auth — do not edit app-side. */

declare(strict_types=1);

namespace OCA\ProjectCheck\Listener;

use OCA\ProjectCheck\Agent\AgentTokenMapper;
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
	) {
	}

	public function handle(Event $event): void
	{
		if (!$event instanceof UserDeletedEvent) {
			return;
		}
		$this->tokens->deleteByUserId($event->getUser()->getUID());
	}
}
