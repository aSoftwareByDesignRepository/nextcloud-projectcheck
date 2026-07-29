<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Notification;

use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;

/**
 * Rich text for ProjectCheck notifications (push + bell).
 */
class Notifier implements INotifier
{
	public function __construct(
		private readonly IFactory $l10nFactory,
		private readonly IURLGenerator $urlGenerator,
	) {
	}

	public function getID(): string
	{
		return 'projectcheck';
	}

	public function getName(): string
	{
		return 'ProjectCheck';
	}

	public function prepare(INotification $notification, string $languageCode): INotification
	{
		if ($notification->getApp() !== 'projectcheck') {
			throw new UnknownNotificationException();
		}

		$l = $this->l10nFactory->get('projectcheck', $languageCode);
		$params = array_merge($notification->getSubjectParameters(), $notification->getMessageParameters());
		$projectName = (string)($params['project_name'] ?? $params['projectName'] ?? '');
		$from = (string)($params['from'] ?? '');
		$to = (string)($params['to'] ?? '');
		$date = (string)($params['date'] ?? '');

		switch ($notification->getSubject()) {
			case 'billing_status_changed':
				$notification->setParsedSubject($l->t('Settlement updated'))
					->setParsedMessage(
						$l->t(
							'Your time entry on %1$s (%2$s) changed from %3$s to %4$s.',
							[
								$projectName !== '' ? $projectName : $l->t('a project'),
								$date !== '' ? $date : '—',
								$this->statusLabel($l, $from),
								$this->statusLabel($l, $to),
							]
						)
					)
					->setLink($this->urlGenerator->linkToRouteAbsolute('projectcheck.timeentry.index'));
				break;

			default:
				throw new UnknownNotificationException();
		}

		return $notification;
	}

	/**
	 * @param \OCP\IL10N $l
	 */
	private function statusLabel(\OCP\IL10N $l, string $status): string
	{
		return match ($status) {
			'open' => $l->t('Open'),
			'invoiced' => $l->t('Invoiced'),
			'paid' => $l->t('Paid'),
			'excluded' => $l->t('Not billable'),
			default => $status,
		};
	}
}
