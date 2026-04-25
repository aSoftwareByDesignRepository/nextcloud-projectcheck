<?php

declare(strict_types=1);

/**
 * Notification notifier for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Notification;

use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCA\ProjectCheck\Service\AccessControlService;

/**
 * Notification notifier for project events
 */
class Notifier implements INotifier
{
	/** @var IL10N */
	private $l10n;

	/** @var IURLGenerator */
	private $urlGenerator;

	/** @var IUserManager */
	private $userManager;

	/** @var AccessControlService */
	private $accessControl;

	/**
	 * Notifier constructor
	 *
	 * @param IL10N $l10n
	 * @param IURLGenerator $urlGenerator
	 * @param IUserManager $userManager
	 * @param AccessControlService $accessControl
	 */
	public function __construct(
		IL10N $l10n,
		IURLGenerator $urlGenerator,
		IUserManager $userManager,
		AccessControlService $accessControl
	) {
		$this->l10n = $l10n;
		$this->urlGenerator = $urlGenerator;
		$this->userManager = $userManager;
		$this->accessControl = $accessControl;
	}

	/**
	 * @return string
	 */
	public function getID()
	{
		return 'projectcheck';
	}

	/**
	 * @return string
	 */
	public function getName()
	{
		return $this->l10n->t('Project Control');
	}

	/**
	 * @param INotification $notification
	 * @param string $languageCode The code of the language that should be used to prepare the notification
	 * @return INotification
	 * @throws \InvalidArgumentException When the notification was not prepared by a notifier
	 * @since 17.0.0
	 */
	public function prepare(INotification $notification, $languageCode)
	{
		if ($notification->getApp() !== 'projectcheck') {
			throw new \InvalidArgumentException();
		}

		// Set language for translations
		// Note: setLanguageFromRequest() is not available in all Nextcloud versions

		$subject = $this->getSubject($notification);
		$message = $this->getMessage($notification);
		$richSubject = $this->getRichSubject($notification);
		$richMessage = $this->getRichMessage($notification);
		$link = $this->getLink($notification);

		$uid = $notification->getUser();
		if (!$this->accessControl->canUseApp($uid)) {
			$link = $this->urlGenerator->linkToDefaultPageUrl();
			$richSubject = [
				'subject' => $subject,
				'parameters' => $this->richParametersToHighlightsOnly($richSubject['parameters'] ?? []),
			];
		}

		$notification->setParsedSubject($subject)
			->setRichSubject($richSubject['subject'], $richSubject['parameters'])
			->setParsedMessage($message)
			->setRichMessage($richMessage['message'], $richMessage['parameters'])
			->setIcon($this->getIcon($notification))
			->setLink($link);

		return $notification;
	}

	/**
	 * Replace navigable types with non-link highlights when the user has no app access.
	 *
	 * @param array<string, array<string, mixed>> $params
	 * @return array<string, array<string, mixed>>
	 */
	private function richParametersToHighlightsOnly(array $params): array
	{
		$out = [];
		foreach ($params as $key => $param) {
			if (!is_array($param) || !isset($param['name'])) {
				continue;
			}
			$out[$key] = [
				'type' => 'highlight',
				'id' => (string) ($param['id'] ?? $param['name']),
				'name' => (string) $param['name'],
			];
		}
		return $out;
	}

	/**
	 * Get the subject for the notification
	 *
	 * @param INotification $notification
	 * @return string
	 */
	private function getSubject(INotification $notification)
	{
		$subject = '';
		$parameters = $notification->getSubjectParameters();

		switch ($notification->getSubject()) {
			case 'project_created':
				$subject = $this->l10n->t('New project created: {project}');
				break;
			case 'project_updated':
				$subject = $this->l10n->t('Project updated: {project}');
				break;
			case 'project_deleted':
				$subject = $this->l10n->t('Project deleted: {project}');
				break;
			case 'project_status_changed':
				$subject = $this->l10n->t('Project status changed: {project} is now {status}');
				break;
			case 'time_entry_created':
				$subject = $this->l10n->t('Time entry logged: {hours} hours on {project}');
				break;
			case 'time_entry_updated':
				$subject = $this->l10n->t('Time entry updated on project {project}');
				break;
			case 'time_entry_deleted':
				$subject = $this->l10n->t('Time entry deleted from project {project}');
				break;
			case 'customer_created':
				$subject = $this->l10n->t('New customer created: {customer}');
				break;
			case 'customer_updated':
				$subject = $this->l10n->t('Customer updated: {customer}');
				break;
			case 'customer_deleted':
				$subject = $this->l10n->t('Customer deleted: {customer}');
				break;
			case 'budget_warning':
				$subject = $this->l10n->t('Budget warning: Project {project} has reached {percentage}% of its budget');
				break;
			case 'budget_exceeded':
				$subject = $this->l10n->t('Budget exceeded: Project {project} has exceeded its budget');
				break;
			case 'deadline_approaching':
				$subject = $this->l10n->t('Deadline approaching: Project {project} is due in {days} days');
				break;
			case 'deadline_overdue':
				$subject = $this->l10n->t('Deadline overdue: Project {project} is overdue by {days} days');
				break;
			default:
				$subject = $notification->getSubject();
		}

		return $subject;
	}

	/**
	 * Get the rich subject for the notification
	 *
	 * @param INotification $notification
	 * @return array
	 */
	private function getRichSubject(INotification $notification)
	{
		$parameters = $notification->getSubjectParameters();
		$richParameters = [];

		// Add project
		if (isset($parameters['project'])) {
			$richParameters['project'] = [
				'type' => 'project',
				'id' => $parameters['project_id'] ?? 0,
				'name' => $parameters['project'],
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

		// Add days
		if (isset($parameters['days'])) {
			$richParameters['days'] = [
				'type' => 'highlight',
				'id' => $parameters['days'],
				'name' => $parameters['days'] . ' days'
			];
		}

		return [
			'subject' => $this->getSubject($notification),
			'parameters' => $richParameters
		];
	}

	/**
	 * Get the message for the notification
	 *
	 * @param INotification $notification
	 * @return string
	 */
	private function getMessage(INotification $notification)
	{
		$message = '';
		$parameters = $notification->getMessageParameters();

		switch ($notification->getSubject()) {
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
			case 'time_entry_created':
				if (isset($parameters['description'])) {
					$message = $this->l10n->t('Description: {description}');
				}
				break;
			case 'budget_warning':
				$message = $this->l10n->t('Please review the project budget and consider taking action.');
				break;
			case 'budget_exceeded':
				$message = $this->l10n->t('The project has exceeded its allocated budget. Immediate action is required.');
				break;
			case 'deadline_approaching':
				$message = $this->l10n->t('Please ensure all tasks are completed before the deadline.');
				break;
			case 'deadline_overdue':
				$message = $this->l10n->t('The project is overdue. Please update the project status or extend the deadline.');
				break;
		}

		return $message;
	}

	/**
	 * Get the rich message for the notification
	 *
	 * @param INotification $notification
	 * @return array
	 */
	private function getRichMessage(INotification $notification)
	{
		$parameters = $notification->getMessageParameters();
		$richParameters = [];

		// Add description
		if (isset($parameters['description'])) {
			$richParameters['description'] = [
				'type' => 'highlight',
				'id' => $parameters['description'],
				'name' => $parameters['description']
			];
		}

		// Add changes
		if (isset($parameters['changes'])) {
			$richParameters['changes'] = [
				'type' => 'highlight',
				'id' => $parameters['changes'],
				'name' => $parameters['changes']
			];
		}

		return [
			'message' => $this->getMessage($notification),
			'parameters' => $richParameters
		];
	}

	/**
	 * Get the icon for the notification
	 *
	 * @param INotification $notification
	 * @return string
	 */
	private function getIcon(INotification $notification)
	{
		switch ($notification->getSubject()) {
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
			case 'budget_warning':
				return 'icon-warning';
			case 'budget_exceeded':
				return 'icon-error';
			case 'deadline_approaching':
				return 'icon-calendar';
			case 'deadline_overdue':
				return 'icon-error';
			default:
				return 'icon-projectcontrol';
		}
	}

	/**
	 * Get the link for the notification
	 *
	 * @param INotification $notification
	 * @return string
	 */
	private function getLink(INotification $notification)
	{
		$parameters = $notification->getSubjectParameters();

		switch ($notification->getSubject()) {
			case 'project_created':
			case 'project_updated':
			case 'project_deleted':
			case 'project_status_changed':
			case 'time_entry_created':
			case 'time_entry_updated':
			case 'time_entry_deleted':
			case 'budget_warning':
			case 'budget_exceeded':
			case 'deadline_approaching':
			case 'deadline_overdue':
				if (isset($parameters['project_id'])) {
					return $this->urlGenerator->linkToRoute('projectcheck.project.show', ['id' => $parameters['project_id']]);
				}
				return $this->urlGenerator->linkToRoute('projectcheck.project.index');
			case 'customer_created':
			case 'customer_updated':
			case 'customer_deleted':
				if (isset($parameters['customer_id'])) {
					return $this->urlGenerator->linkToRoute('projectcheck.customer.show', ['id' => $parameters['customer_id']]);
				}
				return $this->urlGenerator->linkToRoute('projectcheck.customer.index');
			default:
				return $this->urlGenerator->linkToRoute('projectcheck.page.index');
		}
	}
}
