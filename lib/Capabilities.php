<?php

/**
 * Capabilities for the projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck;

use OCP\Capabilities\ICapability;

/**
 * Capabilities class for projectcheck app
 */
class Capabilities implements ICapability
{
	/**
	 * Function an app uses to return the capabilities
	 *
	 * @return array Array containing the apps capabilities
	 */
	public function getCapabilities()
	{
		return [
			'projectcheck' => [
				'version' => '1.0.0',
				'features' => [
					'project_management' => [
						'description' => 'Project management functionality',
						'capabilities' => [
							'create_projects',
							'edit_projects',
							'delete_projects',
							'view_projects',
							'manage_team_members',
							'change_project_status'
						]
					],
					'customer_management' => [
						'description' => 'Customer management functionality',
						'capabilities' => [
							'create_customers',
							'edit_customers',
							'delete_customers',
							'view_customers',
							'search_customers'
						]
					],
					'time_tracking' => [
						'description' => 'Time tracking functionality',
						'capabilities' => [
							'create_time_entries',
							'edit_time_entries',
							'delete_time_entries',
							'view_time_entries',
							'search_time_entries',
							'track_time_by_project'
						]
					],
					'budget_management' => [
						'description' => 'Budget and cost management',
						'capabilities' => [
							'calculate_budget_consumption',
							'track_project_costs',
							'generate_cost_reports',
							'monitor_budget_limits'
						]
					],
					'reporting' => [
						'description' => 'Reporting and analytics',
						'capabilities' => [
							'project_reports',
							'time_reports',
							'cost_reports',
							'dashboard_statistics'
						]
					],
					'permissions' => [
						'description' => 'Permission system',
						'capabilities' => [
							'user_permissions',
							'project_permissions',
							'team_member_management',
							'role_based_access'
						]
					]
				],
				'api' => [
					'endpoints' => [
						'projects' => [
							'GET /api/projects' => 'List projects',
							'POST /api/projects' => 'Create project',
							'GET /api/projects/{id}' => 'Get project details',
							'PUT /api/projects/{id}' => 'Update project',
							'DELETE /api/projects/{id}' => 'Delete project'
						],
						'customers' => [
							'GET /api/customers' => 'List customers',
							'POST /api/customers' => 'Create customer',
							'GET /api/customers/{id}' => 'Get customer details',
							'PUT /api/customers/{id}' => 'Update customer',
							'DELETE /api/customers/{id}' => 'Delete customer'
						],
						'time_entries' => [
							'GET /api/time-entries' => 'List time entries',
							'POST /api/time-entries' => 'Create time entry',
							'GET /api/time-entries/{id}' => 'Get time entry details',
							'PUT /api/time-entries/{id}' => 'Update time entry',
							'DELETE /api/time-entries/{id}' => 'Delete time entry'
						],
						'dashboard' => [
							'GET /api/dashboard/stats' => 'Get dashboard statistics'
						]
					],
					'authentication' => [
						'type' => 'session_based',
						'csrf_protection' => true
					]
				],
				'data_models' => [
					'project' => [
						'id' => 'bigint',
						'name' => 'string',
						'short_description' => 'text',
						'detailed_description' => 'text',
						'customer_id' => 'bigint',
						'hourly_rate' => 'decimal',
						'total_budget' => 'decimal',
						'available_hours' => 'decimal',
						'category' => 'string',
						'priority' => 'string',
						'status' => 'string',
						'start_date' => 'date',
						'end_date' => 'date',
						'tags' => 'text',
						'created_by' => 'string',
						'created_at' => 'datetime',
						'updated_at' => 'datetime'
					],
					'customer' => [
						'id' => 'bigint',
						'name' => 'string',
						'email' => 'string',
						'phone' => 'string',
						'address' => 'text',
						'contact_person' => 'string',
						'created_by' => 'string',
						'created_at' => 'datetime',
						'updated_at' => 'datetime'
					],
					'time_entry' => [
						'id' => 'bigint',
						'project_id' => 'bigint',
						'user_id' => 'string',
						'date' => 'date',
						'hours' => 'decimal',
						'description' => 'text',
						'hourly_rate' => 'decimal',
						'created_at' => 'datetime',
						'updated_at' => 'datetime'
					]
				]
			]
		];
	}
}
