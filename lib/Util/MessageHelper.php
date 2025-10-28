<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Util;

use OCP\IL10N;
use OCP\IUserSession;

/**
 * Message helper for ProjectControl app
 * Provides message templates, localization, and content management
 */
class MessageHelper {
	private IL10N $l10n;
	private IUserSession $userSession;

	public function __construct(
		IL10N $l10n,
		IUserSession $userSession
	) {
		$this->l10n = $l10n;
		$this->userSession = $userSession;
	}

	/**
	 * Get message template for common scenarios
	 */
	public function getMessageTemplate(string $template, array $data = []): array {
		$templates = [
			// Customer messages
			'customer_created' => [
				'type' => 'success',
				'title' => $this->l10n->t('Customer Created'),
				'message' => $this->l10n->t('Customer "%s" has been created successfully.', [$data['name'] ?? '']),
				'actions' => [
					[
						'name' => 'view',
						'label' => $this->l10n->t('View Customer'),
						'primary' => true
					],
					[
						'name' => 'create_project',
						'label' => $this->l10n->t('Create Project')
					]
				]
			],
			'customer_updated' => [
				'type' => 'success',
				'title' => $this->l10n->t('Customer Updated'),
				'message' => $this->l10n->t('Customer "%s" has been updated successfully.', [$data['name'] ?? '']),
				'actions' => [
					[
						'name' => 'view',
						'label' => $this->l10n->t('View Customer'),
						'primary' => true
					]
				]
			],
			'customer_deleted' => [
				'type' => 'info',
				'title' => $this->l10n->t('Customer Deleted'),
				'message' => $this->l10n->t('Customer "%s" has been deleted.', [$data['name'] ?? '']),
				'actions' => [
					[
						'name' => 'undo',
						'label' => $this->l10n->t('Undo'),
						'primary' => true
					]
				]
			],

			// Project messages
			'project_created' => [
				'type' => 'success',
				'title' => $this->l10n->t('Project Created'),
				'message' => $this->l10n->t('Project "%s" has been created successfully.', [$data['name'] ?? '']),
				'actions' => [
					[
						'name' => 'view',
						'label' => $this->l10n->t('View Project'),
						'primary' => true
					],
					[
						'name' => 'add_time_entry',
						'label' => $this->l10n->t('Add Time Entry')
					]
				]
			],
			'project_updated' => [
				'type' => 'success',
				'title' => $this->l10n->t('Project Updated'),
				'message' => $this->l10n->t('Project "%s" has been updated successfully.', [$data['name'] ?? '']),
				'actions' => [
					[
						'name' => 'view',
						'label' => $this->l10n->t('View Project'),
						'primary' => true
					]
				]
			],
			'project_deleted' => [
				'type' => 'info',
				'title' => $this->l10n->t('Project Deleted'),
				'message' => $this->l10n->t('Project "%s" has been deleted.', [$data['name'] ?? '']),
				'actions' => [
					[
						'name' => 'undo',
						'label' => $this->l10n->t('Undo'),
						'primary' => true
					]
				]
			],
			'project_status_changed' => [
				'type' => 'info',
				'title' => $this->l10n->t('Project Status Changed'),
				'message' => $this->l10n->t('Project "%s" status changed to "%s".', [
					$data['name'] ?? '',
					$data['status'] ?? ''
				]),
				'actions' => [
					[
						'name' => 'view',
						'label' => $this->l10n->t('View Project'),
						'primary' => true
					]
				]
			],

			// Time entry messages
			'time_entry_created' => [
				'type' => 'success',
				'title' => $this->l10n->t('Time Entry Created'),
				'message' => $this->l10n->t('Time entry for %s hours has been created successfully.', [$data['hours'] ?? '']),
				'actions' => [
					[
						'name' => 'view',
						'label' => $this->l10n->t('View Entry'),
						'primary' => true
					],
					[
						'name' => 'add_another',
						'label' => $this->l10n->t('Add Another')
					]
				]
			],
			'time_entry_updated' => [
				'type' => 'success',
				'title' => $this->l10n->t('Time Entry Updated'),
				'message' => $this->l10n->t('Time entry has been updated successfully.'),
				'actions' => [
					[
						'name' => 'view',
						'label' => $this->l10n->t('View Entry'),
						'primary' => true
					]
				]
			],
			'time_entry_deleted' => [
				'type' => 'info',
				'title' => $this->l10n->t('Time Entry Deleted'),
				'message' => $this->l10n->t('Time entry has been deleted.'),
				'actions' => [
					[
						'name' => 'undo',
						'label' => $this->l10n->t('Undo'),
						'primary' => true
					]
				]
			],
			'time_entry_overlap' => [
				'type' => 'warning',
				'title' => $this->l10n->t('Time Entry Overlap'),
				'message' => $this->l10n->t('This time entry overlaps with an existing entry. Please adjust the time range.'),
				'actions' => [
					[
						'name' => 'adjust',
						'label' => $this->l10n->t('Adjust Time'),
						'primary' => true
					],
					[
						'name' => 'view_conflicts',
						'label' => $this->l10n->t('View Conflicts')
					]
				]
			],

			// Settings messages
			'settings_updated' => [
				'type' => 'success',
				'title' => $this->l10n->t('Settings Updated'),
				'message' => $this->l10n->t('Settings have been updated successfully.'),
				'actions' => []
			],
			'permissions_changed' => [
				'type' => 'info',
				'title' => $this->l10n->t('Permissions Updated'),
				'message' => $this->l10n->t('User permissions have been updated.'),
				'actions' => [
					[
						'name' => 'view_users',
						'label' => $this->l10n->t('View Users'),
						'primary' => true
					]
				]
			],

			// Bulk operation messages
			'bulk_operation_success' => [
				'type' => 'success',
				'title' => $this->l10n->t('Bulk Operation Completed'),
				'message' => $this->l10n->t('%d items have been processed successfully.', [$data['count'] ?? 0]),
				'actions' => [
					[
						'name' => 'view_results',
						'label' => $this->l10n->t('View Results'),
						'primary' => true
					]
				]
			],
			'bulk_operation_partial' => [
				'type' => 'warning',
				'title' => $this->l10n->t('Bulk Operation Partially Completed'),
				'message' => $this->l10n->t('%d items processed successfully, %d failed.', [
					$data['success_count'] ?? 0,
					$data['error_count'] ?? 0
				]),
				'actions' => [
					[
						'name' => 'view_errors',
						'label' => $this->l10n->t('View Errors'),
						'primary' => true
					],
					[
						'name' => 'retry_failed',
						'label' => $this->l10n->t('Retry Failed')
					]
				]
			],

			// Error messages
			'validation_error' => [
				'type' => 'error',
				'title' => $this->l10n->t('Validation Error'),
				'message' => $this->l10n->t('Please correct the following errors:'),
				'actions' => [
					[
						'name' => 'fix_errors',
						'label' => $this->l10n->t('Fix Errors'),
						'primary' => true
					]
				]
			],
			'permission_denied' => [
				'type' => 'error',
				'title' => $this->l10n->t('Permission Denied'),
				'message' => $this->l10n->t('You do not have permission to perform this action.'),
				'actions' => [
					[
						'name' => 'contact_admin',
						'label' => $this->l10n->t('Contact Administrator'),
						'primary' => true
					]
				]
			],
			'network_error' => [
				'type' => 'error',
				'title' => $this->l10n->t('Network Error'),
				'message' => $this->l10n->t('A network error occurred. Please check your connection and try again.'),
				'actions' => [
					[
						'name' => 'retry',
						'label' => $this->l10n->t('Retry'),
						'primary' => true
					]
				]
			]
		];

		return $templates[$template] ?? [
			'type' => 'info',
			'title' => $this->l10n->t('Message'),
			'message' => $this->l10n->t('An action has been completed.'),
			'actions' => []
		];
	}

	/**
	 * Get dynamic message content based on form data and context
	 */
	public function getDynamicMessage(string $action, array $formData, array $context = []): array {
		$user = $this->userSession->getUser();
		$userId = $user ? $user->getUID() : 'unknown';

		$dynamicData = array_merge($formData, $context, [
			'user_id' => $userId,
			'timestamp' => date('Y-m-d H:i:s'),
			'user_display_name' => $user ? $user->getDisplayName() : 'Unknown User'
		]);

		switch ($action) {
			case 'time_entry_submitted':
				$hours = $formData['hours'] ?? 0;
				$projectName = $formData['project_name'] ?? 'Unknown Project';
				$date = $formData['date'] ?? date('Y-m-d');
				
				return [
					'type' => 'success',
					'title' => $this->l10n->t('Time Entry Submitted'),
					'message' => $this->l10n->t('You have logged %s hours for project "%s" on %s.', [
						$hours,
						$projectName,
						$date
					]),
					'actions' => [
						[
							'name' => 'view_timesheet',
							'label' => $this->l10n->t('View Timesheet'),
							'primary' => true
						],
						[
							'name' => 'add_another',
							'label' => $this->l10n->t('Add Another Entry')
						]
					]
				];

			case 'project_assigned':
				$projectName = $formData['project_name'] ?? 'Unknown Project';
				$role = $formData['role'] ?? 'Team Member';
				$startDate = $formData['start_date'] ?? 'TBD';
				
				return [
					'type' => 'info',
					'title' => $this->l10n->t('Project Assignment'),
					'message' => $this->l10n->t('You have been assigned to project "%s" as %s starting %s.', [
						$projectName,
						$role,
						$startDate
					]),
					'actions' => [
						[
							'name' => 'view_project',
							'label' => $this->l10n->t('View Project'),
							'primary' => true
						],
						[
							'name' => 'view_tasks',
							'label' => $this->l10n->t('View Tasks')
						]
					]
				];

			case 'budget_warning':
				$projectName = $formData['project_name'] ?? 'Unknown Project';
				$remainingBudget = $formData['remaining_budget'] ?? 0;
				$percentage = $formData['percentage_used'] ?? 0;
				
				return [
					'type' => 'warning',
					'title' => $this->l10n->t('Budget Warning'),
					'message' => $this->l10n->t('Project "%s" has used %d%% of its budget. Remaining: $%s', [
						$projectName,
						$percentage,
						number_format($remainingBudget, 2)
					]),
					'actions' => [
						[
							'name' => 'view_budget',
							'label' => $this->l10n->t('View Budget'),
							'primary' => true
						],
						[
							'name' => 'request_increase',
							'label' => $this->l10n->t('Request Increase')
						]
					]
				];

			case 'deadline_approaching':
				$projectName = $formData['project_name'] ?? 'Unknown Project';
				$daysLeft = $formData['days_left'] ?? 0;
				
				return [
					'type' => 'warning',
					'title' => $this->l10n->t('Deadline Approaching'),
					'message' => $this->l10n->t('Project "%s" deadline is in %d days.', [
						$projectName,
						$daysLeft
					]),
					'actions' => [
						[
							'name' => 'view_project',
							'label' => $this->l10n->t('View Project'),
							'primary' => true
						],
						[
							'name' => 'extend_deadline',
							'label' => $this->l10n->t('Request Extension')
						]
					]
				];

			default:
				return $this->getMessageTemplate('validation_error', $dynamicData);
		}
	}

	/**
	 * Get message with localization support
	 */
	public function getLocalizedMessage(string $key, array $parameters = []): string {
		return $this->l10n->t($key, $parameters);
	}

	/**
	 * Get message severity level and appropriate styling
	 */
	public function getMessageSeverity(string $type): array {
		$severities = [
			'success' => [
				'level' => 'low',
				'icon' => 'check-circle',
				'color' => 'green',
				'autoDismiss' => 5000
			],
			'info' => [
				'level' => 'low',
				'icon' => 'information-circle',
				'color' => 'blue',
				'autoDismiss' => 4000
			],
			'warning' => [
				'level' => 'medium',
				'icon' => 'exclamation-triangle',
				'color' => 'yellow',
				'autoDismiss' => 6000
			],
			'error' => [
				'level' => 'high',
				'icon' => 'x-circle',
				'color' => 'red',
				'autoDismiss' => 8000
			],
			'critical' => [
				'level' => 'critical',
				'icon' => 'stop-circle',
				'color' => 'red',
				'autoDismiss' => 0 // Don't auto-dismiss critical messages
			]
		];

		return $severities[$type] ?? $severities['info'];
	}

	/**
	 * Get message action buttons
	 */
	public function getMessageActions(string $type, array $context = []): array {
		$baseActions = [
			'dismiss' => [
				'name' => 'dismiss',
				'label' => $this->l10n->t('Dismiss'),
				'primary' => false
			]
		];

		$typeActions = [
			'success' => [
				'view' => [
					'name' => 'view',
					'label' => $this->l10n->t('View Details'),
					'primary' => true
				]
			],
			'error' => [
				'retry' => [
					'name' => 'retry',
					'label' => $this->l10n->t('Retry'),
					'primary' => true
				],
				'report' => [
					'name' => 'report',
					'label' => $this->l10n->t('Report Issue'),
					'primary' => false
				]
			],
			'warning' => [
				'acknowledge' => [
					'name' => 'acknowledge',
					'label' => $this->l10n->t('Acknowledge'),
					'primary' => true
				],
				'learn_more' => [
					'name' => 'learn_more',
					'label' => $this->l10n->t('Learn More'),
					'primary' => false
				]
			]
		];

		$actions = $baseActions;
		if (isset($typeActions[$type])) {
			$actions = array_merge($actions, $typeActions[$type]);
		}

		// Add context-specific actions
		if (!empty($context['actions'])) {
			$actions = array_merge($actions, $context['actions']);
		}

		return array_values($actions);
	}

	/**
	 * Format message for display
	 */
	public function formatMessage(array $messageData): array {
		$severity = $this->getMessageSeverity($messageData['type']);
		$actions = $this->getMessageActions($messageData['type'], $messageData);

		return array_merge($messageData, [
			'severity' => $severity,
			'actions' => $actions,
			'formatted' => true,
			'timestamp' => date('Y-m-d H:i:s')
		]);
	}

	/**
	 * Get message history for user
	 */
	public function getMessageHistory(string $userId, int $limit = 50): array {
		// This would typically query a database table
		// For now, return empty array
		return [];
	}

	/**
	 * Save message to history
	 */
	public function saveMessageToHistory(array $messageData): bool {
		// This would typically save to a database table
		// For now, return true
		return true;
	}

	/**
	 * Get persistent notifications for user
	 */
	public function getPersistentNotifications(string $userId): array {
		// This would typically query a database table
		// For now, return empty array
		return [];
	}

	/**
	 * Mark notification as acknowledged
	 */
	public function acknowledgeNotification(string $notificationId, string $userId): bool {
		// This would typically update a database table
		// For now, return true
		return true;
	}
}
