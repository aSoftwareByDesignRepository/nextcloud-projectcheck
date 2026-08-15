<?php

declare(strict_types=1);

/**
 * Activity provider for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Activity;

use OCA\ProjectCheck\AppInfo\Application;
use OCP\Activity\Exceptions\UnknownActivityException;
use OCP\Activity\IEvent;
use OCP\Activity\IProvider;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserManager;

/**
 * Activity provider for project events
 */
class Provider implements IProvider
{
	public function __construct(
		private IL10N $l10n,
		private IURLGenerator $urlGenerator,
		private IUserManager $userManager,
	) {
	}

	/**
	 * @param string $language The language which should be used for translating, e.g. "en"
	 * @throws UnknownActivityException when the event is not owned/known by this provider
	 */
	public function parse($language, IEvent $event, ?IEvent $previousEvent = null): IEvent
	{
		if ($event->getApp() !== Application::APP_ID) {
			throw new UnknownActivityException();
		}

		$subject = $this->getSubject($event);
		$message = $this->getMessage($event);
		$richSubject = $this->getRichSubject($event);

		$event->setParsedSubject($subject)
			->setRichSubject($richSubject['subject'], $richSubject['parameters'])
			->setParsedMessage($message)
			->setIcon($this->getIcon($event));

		if ($previousEvent instanceof IEvent) {
			$event->setChildEvent($previousEvent);
		}

		return $event;
	}

	/**
	 * Get the subject for the activity
	 *
	 * @param IEvent $event
	 * @return string
	 */
	private function getSubject(IEvent $event)
	{
		$subject = '';
		$parameters = $event->getSubjectParameters();

		switch ($event->getSubject()) {
			case 'project_created':
				$subject = $this->l10n->t('{actor} created project {project}');
				break;
			case 'project_updated':
				$subject = $this->l10n->t('{actor} updated project {project}');
				break;
			case 'project_deleted':
				$subject = $this->l10n->t('{actor} deleted project {project}');
				break;
			case 'project_status_changed':
				$subject = $this->l10n->t('{actor} changed status of project {project} to {status}');
				break;
			case 'time_entry_created':
				$subject = $this->l10n->t('{actor} logged {hours} hours on project {project}');
				break;
			case 'time_entry_updated':
				$subject = $this->l10n->t('{actor} updated time entry on project {project}');
				break;
			case 'time_entry_deleted':
				$subject = $this->l10n->t('{actor} deleted time entry from project {project}');
				break;
			case 'customer_created':
				$subject = $this->l10n->t('{actor} created customer {customer}');
				break;
			case 'customer_updated':
				$subject = $this->l10n->t('{actor} updated customer {customer}');
				break;
			case 'customer_deleted':
				$subject = $this->l10n->t('{actor} deleted customer {customer}');
				break;
			case 'member_removed':
				$subject = $this->l10n->t('{actor} removed {member} from project {project}');
				break;
			case 'billing_status_changed':
				$subject = $this->l10n->t('{actor} changed the settlement status of a time entry to {status}');
				break;
			case 'billing_bulk_changed':
				$subject = $this->l10n->t('{actor} changed the settlement status of multiple time entries to {status}');
				break;
			case 'member_role_changed':
				$subject = $this->l10n->t('{actor} changed the project role of {member} to {status}');
				break;
			case 'budget_warning':
				$subject = $this->l10n->t('Project {project} has reached {percentage}% of its budget');
				break;
			default:
				throw new UnknownActivityException();
		}

		return $subject;
	}

	/**
	 * Get the rich subject for the activity
	 *
	 * @param IEvent $event
	 * @return array
	 */
	private function getRichSubject(IEvent $event)
	{
		$parameters = $event->getSubjectParameters();
		$richParameters = [];

		// Add actor
		if (isset($parameters['actor'])) {
			$user = $this->userManager->get($parameters['actor']);
			$richParameters['actor'] = [
				'type' => 'user',
				'id' => $parameters['actor'],
				'name' => $user ? $user->getDisplayName() : $parameters['actor']
			];
		}

		// Add project (legacy publishers used project_name)
		$projectLabel = $parameters['project'] ?? $parameters['project_name'] ?? null;
		if ($projectLabel !== null) {
			$richParameters['project'] = [
				'type' => 'project',
				'id' => $parameters['project_id'] ?? 0,
				'name' => $projectLabel,
				'link' => $this->urlGenerator->linkToRoute('projectcheck.project.show', ['id' => $parameters['project_id'] ?? 0])
			];
		}

		// Add customer
		if (isset($parameters['customer'])) {
			$richParameters['customer'] = [
				'type' => 'customer',
				'id' => $parameters['customer_id'] ?? 0,
				'name' => $parameters['customer'],
				'link' => $this->urlGenerator->linkToRoute('projectcheck.customer.show', ['id' => $parameters['customer_id'] ?? 0])
			];
		}

		// Add status
		if (isset($parameters['status'])) {
			$richParameters['status'] = [
				'type' => 'highlight',
				'id' => $parameters['status'],
				'name' => $parameters['status']
			];
		}

		// Add hours
		if (isset($parameters['hours'])) {
			$richParameters['hours'] = [
				'type' => 'highlight',
				'id' => $parameters['hours'],
				'name' => $parameters['hours'] . ' hours'
			];
		}

		// Add percentage
		if (isset($parameters['percentage'])) {
			$richParameters['percentage'] = [
				'type' => 'highlight',
				'id' => $parameters['percentage'],
				'name' => $parameters['percentage'] . '%'
			];
		}

		// Add member
		if (isset($parameters['member'])) {
			$user = $this->userManager->get($parameters['member']);
			$richParameters['member'] = [
				'type' => 'user',
				'id' => $parameters['member'],
				'name' => $user ? $user->getDisplayName() : $parameters['member']
			];
		}

		return [
			'subject' => $this->getSubject($event),
			'parameters' => $richParameters
		];
	}

	/**
	 * Get the message for the activity
	 *
	 * @param IEvent $event
	 * @return string
	 */
	private function getMessage(IEvent $event)
	{
		$message = '';
		$parameters = $event->getMessageParameters();

		switch ($event->getSubject()) {
			case 'project_created':
				if (isset($parameters['description'])) {
					$message = $this->l10n->t('Description: {description}');
				}
				break;
			case 'project_updated':
				if (isset($parameters['changes'])) {
					$message = $this->l10n->t('Changes: {changes}');
				}
				break;
			case 'project_status_changed':
				if (isset($parameters['note']) && trim((string)$parameters['note']) !== '') {
					$message = $this->l10n->t('Note: {note}', ['note' => trim((string)$parameters['note'])]);
				}
				break;
			case 'time_entry_created':
				if (isset($parameters['description'])) {
					$message = $this->l10n->t('Description: {description}');
				}
				break;
			case 'budget_warning':
				$message = $this->l10n->t('Please review the project budget and consider taking action.');
				break;
		}

		return $message;
	}

	/**
	 * Get the icon for the activity
	 *
	 * @param IEvent $event
	 * @return string
	 */
	private function getIcon(IEvent $event)
	{
		switch ($event->getSubject()) {
			case 'project_created':
				return 'icon-add';
			case 'project_updated':
				return 'icon-rename';
			case 'project_deleted':
				return 'icon-delete';
			case 'project_status_changed':
				return 'icon-change';
			case 'time_entry_created':
				return 'icon-time';
			case 'time_entry_updated':
				return 'icon-rename';
			case 'time_entry_deleted':
				return 'icon-delete';
			case 'customer_created':
				return 'icon-add';
			case 'customer_updated':
				return 'icon-rename';
			case 'customer_deleted':
				return 'icon-delete';
			case 'member_removed':
				return 'icon-delete';
			case 'budget_warning':
				return 'icon-warning';
			default:
				return 'icon-projectcontrol';
		}
	}
}
